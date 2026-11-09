<?php

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('summarizes only the selected company and month with debit and credit signs', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $post = function (User $author, string $date, string $debit, string $credit, int $amount): void {
        $teamId = $author->currentTeam->id;
        $entry = JournalEntry::create([
            'team_id' => $teamId,
            'journal_template_id' => DB::table('journal_templates')->where('team_id', $teamId)->value('id'),
            'posted_by' => $author->id,
            'request_id' => (string) Str::uuid(),
            'template_name' => 'Jurnal pengujian',
            'date' => $date,
        ]);

        foreach (['debit' => $debit, 'credit' => $credit] as $side => $code) {
            $account = DB::table('journal_accounts')->where('team_id', $teamId)->where('code', $code)->first();
            $entry->lines()->create([
                'journal_account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'side' => $side,
                'amount' => $amount,
            ]);
        }
    };

    $post($user, '2026-11-01', '1101', '4101', 1000000);
    $post($user, '2026-11-02', '4101', '1101', 100000);
    $post($user, '2026-11-30', '6102', '1101', 250000);
    $post($user, '2026-11-30', '1101', '6102', 50000);
    $post($user, '2026-11-30', '1102', '1101', 200000);
    $post($user, '2026-12-01', '1101', '4101', 9000000);
    $post($other, '2026-11-30', '1101', '4101', 8000000);

    $this->actingAs($user)->get(route('dashboard', [$user->currentTeam, 'month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
            ->where('month', '2026-11')
            ->where('totals.revenue', '900000')
            ->where('totals.expense', '200000')
            ->where('totals.net', '700000')
            ->where('totals.entries', 5)
            ->where('activity.29.entries', 3)
            ->has('recentJournals', 5)
            ->where('recentJournals.0.date', '2026-11-30')
            ->where('recentJournals.0.amount', '200000')
            ->where('journalUrl', route('journals.index', $user->currentTeam)));
});

it('shows an empty dashboard for months with no journals and defaults invalid month input', function () {
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 6)->startOfDay());
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard', [$user->currentTeam, 'month' => '2026-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-11')
            ->where('totals.entries', 0)
            ->where('totals.net', '0')
            ->has('recentJournals', 0));
});

it('keeps rupiah totals exact beyond the SQLite signed integer limit', function () {
    $user = User::factory()->create();
    $teamId = $user->currentTeam->id;
    $templateId = DB::table('journal_templates')->where('team_id', $teamId)->value('id');
    $accounts = DB::table('journal_accounts')->where('team_id', $teamId)->pluck('id', 'code');

    for ($start = 1; $start <= 9224; $start += 100) {
        $entries = [];
        $lines = [];

        for ($id = $start; $id <= min($start + 99, 9224); $id++) {
            $entries[] = [
                'id' => $id,
                'team_id' => $teamId,
                'journal_template_id' => $templateId,
                'request_id' => (string) Str::uuid(),
                'template_name' => 'Pendapatan tunai',
                'date' => '2026-11-06',
                'created_at' => '2026-11-06 00:00:00',
            ];

            foreach (['debit' => '1101', 'credit' => '4101'] as $side => $code) {
                $lines[] = [
                    'journal_entry_id' => $id,
                    'journal_account_id' => $accounts[$code],
                    'account_code' => $code,
                    'account_name' => $code === '1101' ? 'Kas' : 'Pendapatan Usaha',
                    'side' => $side,
                    'amount' => 999999999999999,
                ];
            }
        }

        DB::table('journal_entries')->insert($entries);
        DB::table('journal_entry_lines')->insert($lines);
    }

    $this->actingAs($user)->get(route('dashboard', [$user->currentTeam, 'month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.revenue', '9223999999999990776')
            ->where('totals.net', '9223999999999990776')
            ->where('totals.entries', 9224));
});
