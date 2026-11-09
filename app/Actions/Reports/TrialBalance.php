<?php

namespace App\Actions\Reports;

use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

class TrialBalance
{
    /**
     * @return array{
     *     rows: list<array{id: int, code: string, name: string, type: string|null, active: bool, openingDebit: string, openingCredit: string, debit: string, credit: string, closingDebit: string, closingCredit: string}>,
     *     totals: array<string, string>, balanced: bool
     * }
     */
    public function forPeriod(int $teamId, string $start, string $end): array
    {
        $accounts = DB::table('journal_accounts')->where('team_id', $teamId)
            ->orderBy('code')->get(['id', 'code', 'name', 'type', 'active']);
        $balances = [];

        foreach (DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('journal_accounts as accounts', function ($join) {
                $join->on('accounts.id', '=', 'lines.journal_account_id')
                    ->on('accounts.team_id', '=', 'entries.team_id');
            })
            ->where('entries.team_id', $teamId)->where('entries.date', '<=', $end)
            ->select('lines.journal_account_id', 'lines.side', 'lines.amount', 'entries.date')
            ->cursor() as $line) {
            $accountId = (int) $line->journal_account_id;
            $balances[$accountId] ??= [
                'opening' => BigInteger::zero(),
                'debit' => BigInteger::zero(),
                'credit' => BigInteger::zero(),
            ];
            $amount = BigInteger::of((string) $line->amount);
            if ($line->date < $start) {
                $balances[$accountId]['opening'] = $line->side === 'debit'
                    ? $balances[$accountId]['opening']->plus($amount)
                    : $balances[$accountId]['opening']->minus($amount);
            } else {
                $balances[$accountId][$line->side] = $balances[$accountId][$line->side]->plus($amount);
            }
        }

        $totals = array_fill_keys(['openingDebit', 'openingCredit', 'debit', 'credit', 'closingDebit', 'closingCredit'], BigInteger::zero());
        $rows = [];
        foreach ($accounts as $account) {
            $balance = $balances[$account->id] ?? null;
            if ($balance === null || ($balance['opening']->isZero() && $balance['debit']->isZero() && $balance['credit']->isZero())) {
                continue;
            }

            $opening = $balance['opening'];
            $closing = $opening->plus($balance['debit'])->minus($balance['credit']);
            $columns = [
                'openingDebit' => $opening->isPositive() ? $opening : BigInteger::zero(),
                'openingCredit' => $opening->isNegative() ? $opening->negated() : BigInteger::zero(),
                'debit' => $balance['debit'],
                'credit' => $balance['credit'],
                'closingDebit' => $closing->isPositive() ? $closing : BigInteger::zero(),
                'closingCredit' => $closing->isNegative() ? $closing->negated() : BigInteger::zero(),
            ];
            foreach ($columns as $name => $amount) {
                $totals[$name] = $totals[$name]->plus($amount);
            }

            $rows[] = [
                'id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'type' => $account->type,
                'active' => (bool) $account->active,
                ...array_map(fn (BigInteger $amount) => (string) $amount, $columns),
            ];
        }

        return [
            'rows' => $rows,
            'totals' => array_map(fn (BigInteger $amount) => (string) $amount, $totals),
            'balanced' => $totals['openingDebit']->isEqualTo($totals['openingCredit'])
                && $totals['debit']->isEqualTo($totals['credit'])
                && $totals['closingDebit']->isEqualTo($totals['closingCredit']),
        ];
    }
}
