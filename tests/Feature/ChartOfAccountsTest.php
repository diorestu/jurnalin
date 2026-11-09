<?php

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lets admins manage company accounts and exposes active accounts to templates', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $index = route('chart-of-accounts.index', $team);
    $store = route('chart-of-accounts.store', $team);
    $accountCount = DB::table('journal_accounts')->where('team_id', $team->id)->count();
    $templateAccountCount = count($this->actingAs($owner)->get(route('journal-templates.index', $team))
        ->viewData('page')['props']['accounts']);
    $payload = ['code' => 'UT-TEST', 'name' => 'Utang Usaha', 'type' => 'liability', 'active' => true];

    $this->actingAs($member)->get($index)->assertForbidden();
    $this->post($store, $payload)->assertForbidden();

    $this->actingAs($owner)->get($index)
        ->assertInertia(fn (Assert $page) => $page->component('journal/Accounts')
            ->has('accounts', $accountCount)
            ->where('accounts.0.type', 'asset'));

    $this->post($store, $payload)->assertRedirect($index);
    $this->post($store, $payload)->assertSessionHasErrors('code');
    $this->post($store, [...$payload, 'type' => 'invalid'])->assertSessionHasErrors('type');

    $account = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', 'UT-TEST')->first();
    $this->get(route('journal-templates.index', $team))
        ->assertInertia(fn (Assert $page) => $page->has('accounts', $templateAccountCount + 1)
            ->where('accounts.'.$templateAccountCount.'.id', $account->id));

    $foreign = DB::table('journal_accounts')->where('team_id', $member->personalTeam()->id)->value('id');
    $this->put(route('chart-of-accounts.update', [$team, $foreign]), $payload)->assertNotFound();

    $this->put(route('chart-of-accounts.update', [$team, $account->id]), [
        ...$payload, 'name' => 'Utang Dagang', 'active' => false,
    ])->assertRedirect($index);

    $this->assertDatabaseHas('journal_accounts', ['id' => $account->id, 'name' => 'Utang Dagang', 'active' => false]);
    $this->get(route('journal-templates.index', $team))
        ->assertInertia(fn (Assert $page) => $page->has('accounts', $templateAccountCount));
});

it('keeps posted journal snapshots when an account is renamed and protects active templates', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $journal = route('journals.index', $team);
    $template = $this->actingAs($owner)->get($journal)->viewData('page')['props']['journalTemplates'][0];

    $this->post(route('journals.store', $team), [
        'request_id' => (string) Str::uuid(),
        'template_id' => $template['id'],
        'signature' => $template['signature'],
        'date' => '2026-11-06',
        'amount' => 50000,
    ])->assertRedirect($journal);

    $update = route('chart-of-accounts.update', [$team, $template['lines'][0]['journal_account_id']]);
    $payload = ['code' => '1111', 'name' => 'Kas Operasional', 'type' => 'asset', 'active' => true];
    $this->put($update, [...$payload, 'active' => false])->assertSessionHasErrors('active');
    $this->put($update, $payload)->assertRedirect(route('chart-of-accounts.index', $team));

    $this->get($journal)->assertInertia(fn (Assert $page) => $page
        ->where('journalTemplates.0.lines.0.account_name', 'Kas Operasional')
        ->where('journalTemplates.0.lines.0.account_code', '1111')
        ->where('journals.data.0.lines.0.account_name', 'Kas')
        ->where('journals.data.0.lines.0.account_code', '1101'));
});
