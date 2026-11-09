<?php

use App\Actions\Trade\TradeBalances;
use App\Enums\TeamRole;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function openingPayload(User $user): array
{
    $accounts = DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)->pluck('id', 'code');

    return [
        'request_id' => (string) Str::uuid(), 'date' => '2026-10-31', 'description' => 'Migrasi saldo perusahaan',
        'lines' => [
            ['account_id' => $accounts['1101'], 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $accounts['3101'], 'debit' => 0, 'credit' => 1400000],
        ],
        'receivables' => [
            ['partner' => 'PT Pelanggan', 'reference' => 'INV-LAMA', 'invoice_date' => '2026-10-10', 'due_date' => '2026-11-15', 'amount' => 500000],
        ],
        'payables' => [
            ['partner' => 'PT Pemasok', 'reference' => 'TAG-LAMA', 'invoice_date' => '2026-10-15', 'amount' => 300000],
        ],
        'inventory' => [
            ['sku' => 'BRG-001', 'name' => 'Barang Awal', 'quantity' => 10, 'unit_cost' => 20000],
        ],
    ];
}

it('posts opening balances once and creates payable invoices without duplicating income or stock value', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $payload = openingPayload($user);
    $this->actingAs($user)->post(route('opening-balances.store', $team), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('opening-balances.store', $team), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('opening_balances', 1);
    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseCount('journal_entry_lines', 5);
    $this->assertDatabaseCount('trade_documents', 2);
    $this->assertDatabaseHas('opening_inventory', ['sku' => 'BRG-001', 'quantity' => 10, 'total' => 200000]);
    $this->assertDatabaseHas('journal_entry_lines', ['account_name' => 'Piutang Usaha', 'side' => 'debit', 'amount' => 500000]);

    $this->get(route('reports.financial', [$team, 'month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.assetsTotal', '1700000')->where('report.liabilitiesTotal', '300000')
            ->where('report.equityTotal', '1400000')->where('report.earnings', '0')->where('report.difference', '0'));

    $invoice = DB::table('trade_documents')->where('direction', 'penjualan')->first();
    $cashId = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');
    $this->post(route('trade.store', [$team, 'penjualan']), [
        'request_id' => (string) Str::uuid(), 'kind' => 'payment', 'date' => '2026-11-01',
        'source_id' => $invoice->id, 'account_id' => $cashId, 'amount' => 200000,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect(app(TradeBalances::class)->invoice($invoice))->toBe(300000);
    $this->assertDatabaseCount('journal_entries', 2);

    $payable = DB::table('trade_documents')->where('direction', 'pembelian')->whereNotNull('opening_balance_id')->first();
    $this->post(route('trade.store', [$team, 'pembelian']), [
        'request_id' => (string) Str::uuid(), 'kind' => 'payment', 'date' => '2026-11-01',
        'source_id' => $payable->id, 'account_id' => $cashId, 'amount' => 150000,
    ])->assertSessionHasNoErrors();
    expect(app(TradeBalances::class)->invoice($payable))->toBe(150000);

    $this->get(route('reports.ledger', [$team, 'account' => $cashId, 'start' => '2026-11-01', 'end' => '2026-11-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.opening', '1000000')->where('report.closing', '1050000'));
});

it('rejects unbalanced, foreign-account and unauthorized opening submissions atomically', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $payload = openingPayload($owner);
    $url = route('opening-balances.store', $team);
    $this->actingAs($member)->post($url, $payload)->assertForbidden();

    $bad = $payload;
    $bad['lines'][1]['credit'] = 1300000;
    $this->actingAs($owner)->post($url, $bad)->assertSessionHasErrors('lines');
    $bad = $payload;
    $bad['lines'][0]['account_id'] = DB::table('journal_accounts')->where('team_id', $member->personalTeam()->id)->value('id');
    $this->post($url, $bad)->assertSessionHasErrors('lines.0.account_id');
    $this->assertDatabaseCount('opening_balances', 0);
    $this->assertDatabaseCount('journal_entries', 0);
    $this->assertDatabaseCount('trade_documents', 0);
});

it('requires opening balances before existing journals and blocks later backdated manual and trade postings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $payload = openingPayload($user);
    $existing = JournalEntry::create([
        'team_id' => $team->id, 'posted_by' => $user->id, 'request_id' => (string) Str::uuid(),
        'template_name' => 'Jurnal lama', 'date' => '2026-10-01',
    ]);
    $this->actingAs($user)->post(route('opening-balances.store', $team), $payload)->assertSessionHasErrors('date');
    $existing->delete();
    $this->post(route('opening-balances.store', $team), $payload)->assertSessionHasNoErrors();
    $this->post(route('opening-balances.store', $team), [...$payload, 'request_id' => (string) Str::uuid()])
        ->assertSessionHasErrors('date');
    $this->post(route('journals.manual', $team), [
        'request_id' => (string) Str::uuid(), 'date' => '2026-10-31', 'lines' => [
            $payload['lines'][0],
            [...$payload['lines'][1], 'credit' => 1000000],
        ],
    ])->assertSessionHasErrors('date');
    $template = $this->get(route('journals.index', $team))->viewData('page')['props']['journalTemplates'][0];
    $this->post(route('journals.store', $team), [
        'request_id' => (string) Str::uuid(), 'date' => '2026-10-31',
        'template_id' => $template['id'], 'signature' => $template['signature'], 'amount' => 100000,
    ])->assertSessionHasErrors('date');
    $this->post(route('trade.store', [$team, 'penjualan']), [
        'request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'date' => '2026-10-31', 'partner' => 'Pelanggan',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 100000]],
    ])->assertSessionHasErrors('date');
    $this->assertDatabaseCount('journal_entries', 1);
});

it('links report amounts through ledger and journal to the opening package and limits access by company', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $this->actingAs($user)->post(route('opening-balances.store', $team), openingPayload($user))
        ->assertSessionHasNoErrors();
    $batch = DB::table('opening_balances')->where('team_id', $team->id)->first();
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');

    $this->get(route('reports.financial', [$team, 'month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.assets.0.ledgerUrl', route('reports.ledger', [$team, 'account' => $cash, 'start' => '2026-11-01', 'end' => '2026-11-30'])));
    $this->get(route('reports.ledger', [$team, 'account' => $cash, 'start' => '2026-10-01', 'end' => '2026-10-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.entries.data.0.journalUrl', route('journals.show', [$team, $batch->journal_entry_id])));
    $this->get(route('journals.show', [$team, $batch->journal_entry_id]))
        ->assertInertia(fn (Assert $page) => $page->component('journal/Show')
            ->where('sources.0.url', route('opening-balances.show', [$team, $batch->id])));
    $invoice = DB::table('trade_documents')->where('opening_balance_id', $batch->id)->where('direction', 'penjualan')->first();
    $this->get(route('trade.show', [$team, 'penjualan', $invoice->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.journalId', $batch->journal_entry_id)
            ->where('document.journalUrl', route('journals.show', [$team, $batch->journal_entry_id]))
            ->where('document.source.url', route('opening-balances.show', [$team, $batch->id])));

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get(route('journals.show', [$outsider->currentTeam, $batch->journal_entry_id]))->assertNotFound();
    $this->get(route('opening-balances.show', [$outsider->currentTeam, $batch->id]))->assertNotFound();
});
