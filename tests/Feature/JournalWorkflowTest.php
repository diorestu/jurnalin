<?php

use App\Actions\Trade\TradeBalances;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function workflowPayload(User $owner): array
{
    $accounts = DB::table('journal_accounts')->where('team_id', $owner->currentTeam->id)->pluck('id', 'code');

    return [
        'request_id' => (string) Str::uuid(), 'date' => '2026-11-08',
        'description' => 'Jurnal staf untuk diperiksa',
        'lines' => [
            ['account_id' => $accounts['1101'], 'debit' => 200000, 'credit' => 0],
            ['account_id' => $accounts['4120'], 'debit' => 0, 'credit' => 200000],
        ],
    ];
}

it('keeps staff drafts out of reports until an authorized supervisor approves them once', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($staff, ['role' => TeamRole::Member->value]);
    $payload = [...workflowPayload($owner), 'action' => 'draft'];
    $this->actingAs($staff)->post(route('journals.manual', $team), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('journals.manual', $team), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('journal_drafts', 1);
    $this->assertDatabaseCount('journal_entries', 0);
    $draft = DB::table('journal_drafts')->first();
    $this->post(route('journals.draft-submit', [$team, $draft->id]), ['version' => 1])->assertSessionHasNoErrors();
    $this->post(route('journals.draft-approve', [$team, $draft->id]), ['version' => 2])->assertForbidden();
    $this->actingAs($owner)->post(route('journals.draft-approve', [$team, $draft->id]), ['version' => 2])->assertSessionHasNoErrors();
    $this->post(route('journals.draft-approve', [$team, $draft->id]), ['version' => 2])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseHas('journal_entries', ['approved_by' => $owner->id, 'posted_by' => $staff->id]);
    $this->assertDatabaseHas('journal_drafts', ['status' => 'posted']);
});

it('saves incomplete drafts but rejects unbalanced submission and stale draft edits', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $payload = [...workflowPayload($owner), 'action' => 'draft'];
    $payload['lines'][1]['credit'] = 100000;
    $this->actingAs($owner)->post(route('journals.manual', $team), $payload)->assertSessionHasNoErrors();
    $draft = DB::table('journal_drafts')->first();
    $this->post(route('journals.draft-submit', [$team, $draft->id]), ['version' => 1])->assertSessionHasErrors('lines');
    $update = [...$payload, 'version' => 1];
    unset($update['request_id'], $update['action']);
    $update['lines'][1]['credit'] = 200000;
    $this->post(route('journals.draft-update', [$team, $draft->id]), $update)->assertSessionHasNoErrors();
    $this->post(route('journals.draft-update', [$team, $draft->id]), $update)->assertSessionHasErrors('version');
    $this->assertDatabaseCount('journal_entries', 0);
});

it('reverses a manual journal in an open period without changing the original and prevents double reversal', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $this->actingAs($owner)->post(route('journals.manual', $team), workflowPayload($owner))->assertSessionHasNoErrors();
    $journal = DB::table('journal_entries')->first();
    $data = ['request_id' => (string) Str::uuid(), 'date' => '2026-11-09', 'reason' => 'Koreksi akun transaksi'];
    $url = route('journals.reverse', [$team, $journal->id]);
    $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
    $this->post($url, $data)->assertSessionHasNoErrors();
    $this->post($url, [...$data, 'request_id' => (string) Str::uuid()])->assertSessionHasErrors('journal');
    $this->assertDatabaseCount('journal_entries', 2);
    $reversal = DB::table('journal_entries')->where('reversal_of_id', $journal->id)->first();
    $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $reversal->id, 'account_code' => '1101', 'side' => 'credit', 'amount' => 200000]);
    $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $journal->id, 'account_code' => '1101', 'side' => 'debit', 'amount' => 200000]);
    $this->assertDatabaseHas('accounting_audit_events', ['action' => 'journal.reversed', 'subject_id' => $journal->id]);
});

it('requires staff invoices and payments to be approved before activating documents and changing balances', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($staff, ['role' => TeamRole::Member->value]);
    $url = route('trade.store', [$team, 'penjualan']);
    $invoicePayload = [
        'request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'date' => '2026-11-08', 'partner' => 'Pelanggan persetujuan',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 200000]],
    ];
    $this->actingAs($staff)->post($url, [...$invoicePayload, 'action' => 'post'])->assertForbidden();
    $this->post($url, $invoicePayload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('trade_documents', 0);
    $this->assertDatabaseCount('journal_entries', 0);
    $draft = DB::table('journal_drafts')->where('mode', 'trade')->first();
    $this->actingAs($owner)->post(route('journals.draft-approve', [$team, $draft->id]), ['version' => 1])->assertSessionHasNoErrors();
    $invoice = DB::table('trade_documents')->first();
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');
    $paymentPayload = [
        'request_id' => (string) Str::uuid(), 'kind' => 'payment', 'date' => '2026-11-09',
        'source_id' => $invoice->id, 'amount' => 100000, 'account_id' => $cash,
    ];
    $this->actingAs($staff)->post($url, $paymentPayload)->assertSessionHasNoErrors();
    expect(app(TradeBalances::class)->invoice($invoice))->toBe(200000);
    $paymentDraft = DB::table('journal_drafts')->where('id', '!=', $draft->id)->first();
    $this->actingAs($owner)->post(route('journals.draft-approve', [$team, $paymentDraft->id]), ['version' => 1])->assertSessionHasNoErrors();
    expect(app(TradeBalances::class)->invoice($invoice))->toBe(100000);
    $this->actingAs($staff)->post($url, $paymentPayload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('trade_documents', 2);
    $this->assertDatabaseCount('journal_entries', 2);
});

it('returns trade drafts for revision and revalidates the edited command before approval', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($staff, ['role' => TeamRole::Member->value]);
    $url = route('trade.store', [$team, 'pembelian']);
    $payload = [
        'request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'date' => '2026-11-08', 'partner' => 'Pemasok persetujuan',
        'lines' => [['name' => 'Barang', 'quantity' => 1, 'unit_price' => 300000]],
    ];
    $this->actingAs($staff)->post($url, $payload)->assertSessionHasNoErrors();
    $draft = DB::table('journal_drafts')->first();
    $this->actingAs($owner)->post(route('journals.draft-return', [$team, $draft->id]), ['version' => 1, 'reason' => 'Perbaiki nilai tagihan'])->assertSessionHasNoErrors();
    $payload['lines'][0]['unit_price'] = 250000;
    $this->actingAs($staff)->post($url, [...$payload, 'draft_id' => $draft->id, 'version' => 2, 'action' => 'submit'])->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('journals.draft-approve', [$team, $draft->id]), ['version' => 3])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('trade_documents', ['total' => 250000]);
    $this->assertDatabaseHas('accounting_audit_events', ['action' => 'draft.returned', 'reason' => 'Perbaiki nilai tagihan']);
});

it('blocks new financial postings and COA reclassification in locked months and records reopening reasons', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $this->actingAs($owner)->post(route('journals.manual', $team), workflowPayload($owner))->assertSessionHasNoErrors();
    $journal = DB::table('journal_entries')->first();
    $lockUrl = route('accounting-periods.change', $team);
    $this->post($lockUrl, ['month' => '2026-11', 'locked' => true, 'version' => 0, 'reason' => 'Tutup buku November'])->assertSessionHasNoErrors();

    $this->post(route('journals.manual', $team), [...workflowPayload($owner), 'request_id' => (string) Str::uuid()])
        ->assertSessionHasErrors('date');
    $this->post(route('trade.store', [$team, 'penjualan']), [
        'request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'date' => '2026-11-10', 'partner' => 'Pelanggan',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 100000]],
    ])->assertSessionHasErrors('date');
    $account = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '4120')->first();
    $this->put(route('chart-of-accounts.update', [$team, $account->id]), [
        'code' => $account->code, 'name' => $account->name, 'type' => 'expense', 'active' => true,
    ])->assertSessionHasErrors('type');

    $this->post(route('journals.reverse', [$team, $journal->id]), [
        'request_id' => (string) Str::uuid(), 'date' => '2026-11-30', 'reason' => 'Koreksi jurnal bulan tertutup',
    ])->assertSessionHasErrors('date');
    $this->post(route('journals.reverse', [$team, $journal->id]), [
        'request_id' => (string) Str::uuid(), 'date' => '2026-12-01', 'reason' => 'Penyesuaian pada periode terbuka',
    ])->assertSessionHasNoErrors();
    $this->post($lockUrl, ['month' => '2026-11', 'locked' => false, 'version' => 1, 'reason' => 'Koreksi resmi setelah review'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('accounting_audit_events', ['action' => 'period.unlocked', 'reason' => 'Koreksi resmi setelah review']);
    $this->assertDatabaseHas('accounting_audit_events', ['action' => 'journal.posted', 'actor_id' => $owner->id]);
});

it('revalidates competing pending payments and keeps rejected approvals from partially posting', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($staff, ['role' => TeamRole::Member->value]);
    $url = route('trade.store', [$team, 'penjualan']);
    $this->actingAs($owner)->post($url, [
        'request_id' => (string) Str::uuid(), 'kind' => 'invoice', 'date' => '2026-11-08', 'partner' => 'Pelanggan',
        'lines' => [['name' => 'Jasa', 'quantity' => 1, 'unit_price' => 200000]],
    ])->assertSessionHasNoErrors();
    $invoice = DB::table('trade_documents')->first();
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');
    foreach ([100000, 200000] as $amount) {
        $this->actingAs($staff)->post($url, [
            'request_id' => (string) Str::uuid(), 'kind' => 'payment', 'date' => '2026-11-09',
            'source_id' => $invoice->id, 'account_id' => $cash, 'amount' => $amount,
        ])->assertSessionHasNoErrors();
    }
    $drafts = DB::table('journal_drafts')->orderBy('id')->get();
    $this->actingAs($owner)->post(route('journals.draft-approve', [$team, $drafts[1]->id]), ['version' => 1])->assertSessionHasNoErrors();
    $this->post(route('journals.draft-approve', [$team, $drafts[0]->id]), ['version' => 1])->assertSessionHasErrors('amount');
    $this->assertDatabaseCount('trade_documents', 2);
    $this->assertDatabaseCount('journal_entries', 2);
    $this->assertDatabaseHas('journal_drafts', ['id' => $drafts[0]->id, 'status' => 'pending']);
});
