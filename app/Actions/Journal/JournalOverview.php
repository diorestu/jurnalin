<?php

namespace App\Actions\Journal;

use App\Enums\AccountType;
use App\Models\JournalEntry;
use Brick\Math\BigInteger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class JournalOverview
{
    private const AMOUNT_BASE = 1_000_000_000;

    /**
     * @return array{
     *     totals: array{revenue: string, expense: string, net: string, entries: int, unclassifiedLines: int},
     *     activity: list<array{day: int, entries: int}>,
     *     recentJournals: list<array{id: int, date: string, title: string, description: string|null, amount: string}>
     * }
     */
    public function forMonth(int $teamId, CarbonInterface $start): array
    {
        $dates = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];

        $amount = 'CAST(lines.amount AS DECIMAL(30, 0))';
        $postedLines = DB::table('journal_entries as entries')
            ->join('journal_entry_lines as lines', 'lines.journal_entry_id', '=', 'entries.id')
            ->leftJoin('journal_accounts as accounts', function ($join) {
                $join->on('accounts.id', '=', 'lines.journal_account_id')
                    ->on('accounts.team_id', '=', 'entries.team_id');
            })
            ->where('entries.team_id', $teamId)
            ->whereBetween('entries.date', $dates)
            ->select('entries.date', 'entries.id', 'accounts.type')
            ->selectRaw("CASE WHEN accounts.type = ? THEN CASE WHEN lines.side = 'credit' THEN $amount ELSE -$amount END ELSE 0 END AS revenue", [AccountType::Revenue->value])
            ->selectRaw("CASE WHEN accounts.type = ? THEN CASE WHEN lines.side = 'debit' THEN $amount ELSE -$amount END ELSE 0 END AS expense", [AccountType::Expense->value]);

        // SQLite sums signed 64-bit integers. Sum high and low pieces separately to keep rupiah totals exact.
        $base = self::AMOUNT_BASE;
        $daily = DB::query()->fromSub($postedLines, 'posted')
            ->groupBy('date')
            ->select('date')
            ->selectRaw('COUNT(DISTINCT id) AS entry_count')
            ->selectRaw('SUM(CASE WHEN type IS NULL THEN 1 ELSE 0 END) AS unclassified_lines')
            ->selectRaw("SUM((revenue - revenue % $base) / $base) AS revenue_high, SUM(revenue % $base) AS revenue_low")
            ->selectRaw("SUM((expense - expense % $base) / $base) AS expense_high, SUM(expense % $base) AS expense_low")
            ->get()
            ->keyBy('date');

        $revenue = BigInteger::zero();
        $expense = BigInteger::zero();
        $entries = 0;
        $unclassifiedLines = 0;
        $activity = [];

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->copy()->day($day)->toDateString();
            $summary = $daily->get($date);
            $count = (int) ($summary->entry_count ?? 0);
            $activity[] = ['day' => $day, 'entries' => $count];
            $entries += $count;
            $unclassifiedLines += (int) ($summary->unclassified_lines ?? 0);
            if ($summary !== null) {
                $revenue = $revenue->plus($this->combineAmount((string) $summary->revenue_high, (string) $summary->revenue_low));
                $expense = $expense->plus($this->combineAmount((string) $summary->expense_high, (string) $summary->expense_low));
            }
        }

        $recent = JournalEntry::query()
            ->where('team_id', $teamId)
            ->whereBetween('date', $dates)
            ->select(['id', 'date', 'description', 'template_name'])
            ->withSum(['lines as debit_amount' => fn ($query) => $query->where('side', 'debit')], 'amount')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->date,
                'title' => $entry->template_name,
                'description' => $entry->description,
                'amount' => (string) ($entry->getAttribute('debit_amount') ?? 0),
            ]);

        return [
            'totals' => [
                'revenue' => (string) $revenue,
                'expense' => (string) $expense,
                'net' => (string) $revenue->minus($expense),
                'entries' => $entries,
                'unclassifiedLines' => $unclassifiedLines,
            ],
            'activity' => $activity,
            'recentJournals' => array_values($recent->all()),
        ];
    }

    private function combineAmount(string $high, string $low): BigInteger
    {
        return BigInteger::of($high)->multipliedBy(self::AMOUNT_BASE)->plus($low);
    }
}
