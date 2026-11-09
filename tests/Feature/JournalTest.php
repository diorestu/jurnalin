<?php

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('loads only the active journal templates for the current company', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->get(route('journals.index', $user->currentTeam))
        ->assertInertia(fn (Assert $page) => $page->component('journal/Index')
            ->has('journalTemplates', 4)
            ->where('journalTemplates.1.keyword', 'pendapatan_bank')
            ->where('journalTemplates.1.lines.0.account_name', 'Bank BCA')
            ->where('journalTemplates.1.lines.1.side', 'credit')
            ->has('journals.data', 0)
        );

    $this->get(route('journals.index', $other->currentTeam))->assertForbidden();
});

it('posts separate balanced debit and credit lines and safely retries the same request', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $page = $this->actingAs($user)
        ->get(route('journals.index', $team))
        ->viewData('page');

    $template = $page['props']['journalTemplates'][1];
    $payload = [
        'request_id' => (string) Str::uuid(),
        'template_id' => $template['id'],
        'signature' => $template['signature'],
        'date' => '2026-11-06',
        'amount' => 1500000,
        'description' => 'Jasa konsultasi',
        'lines' => [['account_name' => 'Akun yang dikirim klien']],
    ];

    $url = route('journals.store', $team);
    $this->post($url, $payload)->assertRedirect(route('journals.index', $team));
    $this->post($url, $payload)->assertRedirect(route('journals.index', $team));
    $this->post($url, [...$payload, 'amount' => 2000000])
        ->assertSessionHasErrors('request_id');

    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseCount('journal_entry_lines', 2);
    $this->assertDatabaseHas('journal_entry_lines', [
        'account_name' => 'Bank BCA', 'side' => 'debit', 'amount' => 1500000,
    ]);
    $this->assertDatabaseHas('journal_entry_lines', [
        'account_name' => 'Pendapatan Usaha', 'side' => 'credit', 'amount' => 1500000,
    ]);
    $this->get(route('journals.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('journals.data.0.description', 'Jasa konsultasi')
            ->has('journals.data.0.lines', 2)
        );
});

it('rejects templates from another company and templates changed after the preview', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $foreign = DB::table('journal_templates')->where('team_id', $other->currentTeam->id)->first();
    $own = DB::table('journal_templates')->where('team_id', $user->currentTeam->id)->first();

    $url = route('journals.store', $user->currentTeam);
    $payload = [
        'request_id' => (string) Str::uuid(),
        'signature' => str_repeat('a', 64),
        'date' => '2026-11-06',
        'amount' => 1500000,
    ];

    $this->actingAs($user)->post($url, [...$payload, 'template_id' => $foreign->id])
        ->assertSessionHasErrors('template_id');
    $this->post($url, [...$payload, 'template_id' => $own->id])
        ->assertSessionHasErrors('template_id');

    $this->assertDatabaseCount('journal_entries', 0);
});

it('lets company admins configure journal templates and blocks regular members from editing them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $accounts = DB::table('journal_accounts')->where('team_id', $team->id)->pluck('id', 'code');
    $foreign = DB::table('journal_accounts')->where('team_id', $member->currentTeam->id)->value('id');
    $payload = [
        'keyword' => 'biaya_pln',
        'name' => 'Tagihan PLN',
        'debit_account_id' => $accounts['6102'],
        'credit_account_id' => $accounts['1101'],
        'active' => true,
    ];

    $url = route('journal-templates.store', $team);
    $this->actingAs($member)->post($url, $payload)->assertForbidden();
    $this->actingAs($owner)->post($url, [...$payload, 'debit_account_id' => $foreign])
        ->assertSessionHasErrors('debit_account_id');
    $this->postJson($url, [...$payload, 'credit_account_id' => (string) $accounts['6102']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credit_account_id');
    $this->post($url, $payload)->assertRedirect(route('journal-templates.index', $team));

    $templateId = DB::table('journal_templates')->where('team_id', $team->id)
        ->where('keyword', 'biaya_pln')->value('id');
    $this->get(route('journals.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->has('journalTemplates', 5)
            ->where('journalTemplates.4.name', 'Tagihan PLN')
        );
    $this->put(route('journal-templates.update', [$team, $templateId]), [...$payload, 'active' => false])
        ->assertRedirect(route('journal-templates.index', $team));
    $this->get(route('journals.index', $team))
        ->assertInertia(fn (Assert $page) => $page->has('journalTemplates', 4));
});

it('posts a balanced manual journal with multiple lines and preserves numeric account snapshots', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $accounts = DB::table('journal_accounts')->where('team_id', $team->id)->pluck('id', 'code');
    $payload = [
        'request_id' => (string) Str::uuid(), 'date' => '2026-11-08', 'description' => 'Jurnal penyesuaian manual',
        'lines' => [
            ['account_id' => $accounts['1101'], 'debit' => 200000, 'credit' => 0],
            ['account_id' => $accounts['1102'], 'debit' => 100000, 'credit' => 0],
            ['account_id' => $accounts['4120'], 'debit' => 0, 'credit' => 300000],
        ],
    ];
    $url = route('journals.manual', $team);
    $this->actingAs($user)->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect(route('journals.index', $team));
    $this->post($url, $payload)->assertRedirect(route('journals.index', $team));
    $this->post($url, [...$payload, 'description' => 'Isi berbeda'])->assertSessionHasErrors('request_id');
    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseCount('journal_entry_lines', 3);
    $this->assertDatabaseHas('journal_entries', ['journal_template_id' => null, 'description' => $payload['description']]);
    $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '4120', 'side' => 'credit', 'amount' => 300000]);
});

it('rejects unbalanced or double-sided manual lines and inactive or foreign accounts without partial postings', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $accounts = DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)->pluck('id', 'code');
    $foreign = DB::table('journal_accounts')->where('team_id', $other->currentTeam->id)->value('id');
    $payload = [
        'date' => '2026-11-08',
        'lines' => [
            ['account_id' => $accounts['1101'], 'debit' => 100000, 'credit' => 0],
            ['account_id' => $accounts['4120'], 'debit' => 0, 'credit' => 90000],
        ],
    ];
    $post = fn (array $data) => $this->actingAs($user)->post(route('journals.manual', $user->currentTeam), [
        'request_id' => (string) Str::uuid(), ...$data,
    ]);
    $post($payload)->assertSessionHasErrors('lines');
    $payload['lines'][1]['credit'] = 100000;
    $bad = $payload;
    $bad['lines'][0]['credit'] = 1000;
    $post($bad)->assertSessionHasErrors('lines.0.debit');
    $bad = $payload;
    $bad['lines'][0]['account_id'] = $foreign;
    $post($bad)->assertSessionHasErrors('lines.0.account_id');
    DB::table('journal_accounts')->where('id', $accounts['1101'])->update(['active' => false]);
    $post($payload)->assertSessionHasErrors('lines.0.account_id');
    $this->assertDatabaseCount('journal_entries', 0);
});
