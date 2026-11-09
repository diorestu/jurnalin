<?php

use App\Actions\Reports\TrialBalance;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function trialPosting(User $user, string $date, string $debit, string $credit, int|string $amount): void
{
    $entry = JournalEntry::create([
        'team_id' => $user->currentTeam->id,
        'posted_by' => $user->id,
        'request_id' => (string) Str::uuid(),
        'template_name' => 'Jurnal uji neraca saldo',
        'date' => $date,
    ]);

    foreach (['debit' => $debit, 'credit' => $credit] as $side => $code) {
        $account = DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)
            ->where('code', $code)->first();
        $entry->lines()->create([
            'journal_account_id' => $account->id, 'account_code' => $account->code,
            'account_name' => $account->name, 'side' => $side, 'amount' => $amount,
        ]);
    }
}

it('includes opening balances, inactive and zero closing accounts and excludes other companies and future entries', function () {
    $user = User::factory()->create();
    trialPosting($user, '2026-10-31', '1101', '1102', 100000);
    trialPosting($user, '2026-11-01', '1102', '1101', 100000);
    trialPosting($user, '2026-11-30', '6102', '1101', 50000);
    trialPosting($user, '2026-12-01', '1101', '1102', 900000);
    $other = User::factory()->create();
    trialPosting($other, '2026-11-15', '1101', '1102', 800000);
    DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)
        ->where('code', '1102')->update(['active' => false]);

    $report = app(TrialBalance::class)->forPeriod($user->currentTeam->id, '2026-11-01', '2026-11-30');
    $rows = collect($report['rows'])->keyBy('code');

    expect($report['rows'])->toHaveCount(3)
        ->and($rows['1101'])->toMatchArray([
            'openingDebit' => '100000', 'openingCredit' => '0',
            'debit' => '0', 'credit' => '150000', 'closingDebit' => '0', 'closingCredit' => '50000',
        ])->and($rows['1102'])->toMatchArray([
            'active' => false, 'openingDebit' => '0', 'openingCredit' => '100000',
            'debit' => '100000', 'credit' => '0', 'closingDebit' => '0', 'closingCredit' => '0',
        ])->and($report['totals'])->toMatchArray([
            'openingDebit' => '100000', 'openingCredit' => '100000',
            'debit' => '150000', 'credit' => '150000', 'closingDebit' => '50000', 'closingCredit' => '50000',
        ])->and($report['balanced'])->toBeTrue();
});

it('keeps totals exact above SQLite and PHP native integer sum limits', function () {
    $user = User::factory()->create();
    trialPosting($user, '2026-11-01', '1101', '1102', '9000000000000000000');
    trialPosting($user, '2026-11-02', '1101', '1102', '9000000000000000000');

    $report = app(TrialBalance::class)->forPeriod($user->currentTeam->id, '2026-11-01', '2026-11-30');

    expect($report['totals']['debit'])->toBe('18000000000000000000')
        ->and($report['totals']['credit'])->toBe('18000000000000000000')
        ->and($report['balanced'])->toBeTrue();
});
