<?php

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function reportPosting(User $user, string $date, string $debit, string $credit, int $amount): void
{
    $entry = JournalEntry::create([
        'team_id' => $user->currentTeam->id,
        'posted_by' => $user->id,
        'request_id' => (string) Str::uuid(),
        'template_name' => 'Jurnal laporan',
        'date' => $date,
    ]);

    foreach (['debit' => $debit, 'credit' => $credit] as $side => $code) {
        $account = DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)->where('code', $code)->first();
        $entry->lines()->create([
            'journal_account_id' => $account->id, 'account_code' => $account->code,
            'account_name' => $account->name, 'side' => $side, 'amount' => $amount,
        ]);
    }
}

it('reports financial position as of a date and includes accumulated earnings exactly once', function () {
    $user = User::factory()->create();
    reportPosting($user, '2026-10-01', '1101', '3101', 1000000);
    reportPosting($user, '2026-11-01', '1101', '4111', 500000);
    reportPosting($user, '2026-11-02', '6102', '1101', 100000);
    reportPosting($user, '2026-12-01', '1101', '2100', 9000000);

    $this->actingAs($user)->get(route('reports.financial', [$user->currentTeam, 'as_of' => '2026-11-30']))
        ->assertInertia(fn (Assert $page) => $page->component('reports/Financial')
            ->where('report.assetsTotal', '1400000')
            ->where('report.liabilitiesTotal', '0')
            ->where('report.equityTotal', '1000000')
            ->where('report.earnings', '400000')
            ->where('report.difference', '0'));

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get(route('reports.financial', $user->currentTeam))->assertForbidden();
});

it('limits profit and loss to its period and company and handles reversed entries', function () {
    $user = User::factory()->create();
    reportPosting($user, '2026-10-31', '1101', '4111', 9000000);
    reportPosting($user, '2026-11-01', '1101', '4111', 500000);
    reportPosting($user, '2026-11-02', '4111', '1101', 50000);
    reportPosting($user, '2026-11-30', '5201', '1101', 100000);
    reportPosting($user, '2026-12-01', '1101', '4111', 7000000);
    $other = User::factory()->create();
    reportPosting($other, '2026-11-01', '1101', '4111', 8000000);

    $this->actingAs($user)->get(route('reports.profit-loss', [
        $user->currentTeam, 'start' => '2026-11-01', 'end' => '2026-11-30',
    ]))->assertInertia(fn (Assert $page) => $page->component('reports/ProfitLoss')
        ->where('report.revenueTotal', '450000')
        ->where('report.expenseTotal', '100000')
        ->where('report.net', '350000'));

    $this->getJson(route('reports.profit-loss', [
        $user->currentTeam, 'start' => '2026-11-30', 'end' => '2026-11-01',
    ]))->assertUnprocessable();
});

it('shows ledger opening balances and carries the balance across pages including inactive accounts', function () {
    $user = User::factory()->create();
    reportPosting($user, '2026-10-31', '1101', '3101', 100000);
    for ($i = 0; $i < 51; $i++) {
        reportPosting($user, '2026-11-01', '1101', '4111', 1000);
    }
    $account = DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)->where('code', '1101')->first();
    DB::table('journal_accounts')->where('id', $account->id)->update(['active' => false]);

    $this->actingAs($user)->get(route('reports.ledger', [
        $user->currentTeam, 'account' => $account->id, 'start' => '2026-11-01', 'end' => '2026-11-30', 'page' => 2,
    ]))->assertInertia(fn (Assert $page) => $page->component('reports/Ledger')
        ->where('report.opening', '100000')
        ->where('report.debitTotal', '51000')
        ->where('report.closing', '151000')
        ->has('report.entries.data', 1)
        ->where('report.entries.data.0.balance', '151000'));

    $outsider = User::factory()->create();
    $foreign = DB::table('journal_accounts')->where('team_id', $outsider->currentTeam->id)->value('id');
    $this->get(route('reports.ledger', [$user->currentTeam, 'account' => $foreign]))->assertNotFound();
});

it('filters financial position by calendar month including its last day', function () {
    $user = User::factory()->create();
    reportPosting($user, '2024-01-31', '1101', '3101', 1000000);
    reportPosting($user, '2024-02-29', '1101', '4111', 500000);
    reportPosting($user, '2024-03-01', '1101', '4111', 9000000);

    $this->actingAs($user)->get(route('reports.financial', [$user->currentTeam, 'month' => '2024-02']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2024-02')
            ->where('asOf', '2024-02-29')
            ->where('report.assetsTotal', '1500000')
            ->where('report.earnings', '500000')
            ->where('report.difference', '0'));

    $this->getJson(route('reports.financial', [$user->currentTeam, 'month' => '2024-13']))
        ->assertUnprocessable();
});

it('supports yearly financial position through December 31 and preserves monthly selection', function () {
    $user = User::factory()->create();
    reportPosting($user, '2024-12-31', '1101', '3101', 1000000);
    reportPosting($user, '2025-01-01', '1101', '4111', 200000);
    reportPosting($user, '2025-12-31', '6102', '1101', 50000);
    reportPosting($user, '2026-01-01', '1101', '4111', 9000000);

    $this->actingAs($user)->get(route('reports.financial', [
        $user->currentTeam, 'period' => 'yearly', 'year' => 2025,
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('period', 'yearly')
        ->where('year', '2025')
        ->where('asOf', '2025-12-31')
        ->where('report.assetsTotal', '1150000')
        ->where('report.earnings', '150000')
        ->where('report.difference', '0'));

    $this->get(route('reports.financial', [
        $user->currentTeam, 'period' => 'monthly', 'month' => '2025-01',
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('period', 'monthly')
        ->where('asOf', '2025-01-31')
        ->where('report.assetsTotal', '1200000'));

    $this->getJson(route('reports.financial', [
        $user->currentTeam, 'period' => 'yearly', 'year' => 1899,
    ]))->assertUnprocessable();
    $this->getJson(route('reports.financial', [
        $user->currentTeam, 'period' => 'weekly',
    ]))->assertUnprocessable();
});
