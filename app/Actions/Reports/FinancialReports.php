<?php

namespace App\Actions\Reports;

use App\Enums\AccountType;
use Brick\Math\BigInteger;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FinancialReports
{
    /**
     * @return array{rows: list<array{id: int, code: string, name: string, type: string|null, balance: string}>, unclassified: int}
     */
    private function accountBalances(int $teamId, ?string $start, string $end): array
    {
        $balances = [];
        $unclassified = 0;
        foreach ($this->lines($teamId)->where('entries.date', '<=', $end)
            ->when($start !== null, fn (Builder $query) => $query->where('entries.date', '>=', $start))
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'lines.side', 'lines.amount')->cursor() as $line) {
            if (! isset($balances[$line->id])) {
                $balances[$line->id] = [
                    'id' => (int) $line->id,
                    'code' => (string) $line->code,
                    'name' => (string) $line->name,
                    'type' => $line->type,
                    'balance' => BigInteger::zero(),
                ];
            }

            if ($line->type === null) {
                $unclassified++;
            }

            $amount = BigInteger::of((string) $line->amount);
            $balances[$line->id]['balance'] = $line->side === 'debit'
                ? $balances[$line->id]['balance']->plus($amount)
                : $balances[$line->id]['balance']->minus($amount);
        }

        $rows = [];
        foreach ($balances as $row) {
            if ($row['balance']->isZero()) {
                continue;
            }

            if (in_array($row['type'], [AccountType::Liability->value, AccountType::Equity->value, AccountType::Revenue->value], true)) {
                $row['balance'] = $row['balance']->negated();
            }

            $row['balance'] = (string) $row['balance'];
            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['code'], $b['code']));

        return ['rows' => $rows, 'unclassified' => $unclassified];
    }

    /** @return array<string, mixed> */
    public function financial(int $teamId, string $asOf): array
    {
        $accounts = $this->accountBalances($teamId, null, $asOf);
        $report = ['unclassified' => $accounts['unclassified']];
        foreach ([
            'assets' => AccountType::Asset,
            'liabilities' => AccountType::Liability,
            'equity' => AccountType::Equity,
        ] as $group => $type) {
            $report[$group] = $this->ofType($accounts['rows'], $type);
            $report[$group.'Total'] = (string) $this->sum($report[$group]);
        }

        $earnings = $this->sum($this->ofType($accounts['rows'], AccountType::Revenue))
            ->minus($this->sum($this->ofType($accounts['rows'], AccountType::Expense)));
        $report['earnings'] = (string) $earnings;
        $report['equityWithEarnings'] = (string) BigInteger::of($report['equityTotal'])->plus($earnings);
        $report['liabilitiesAndEquity'] = (string) BigInteger::of($report['liabilitiesTotal'])->plus($report['equityWithEarnings']);
        $report['difference'] = (string) BigInteger::of($report['assetsTotal'])->minus($report['liabilitiesAndEquity']);

        return $report;
    }

    /** @return array<string, mixed> */
    public function profitLoss(int $teamId, string $start, string $end): array
    {
        $accounts = $this->accountBalances($teamId, $start, $end);
        $revenue = $this->ofType($accounts['rows'], AccountType::Revenue);
        $expense = $this->ofType($accounts['rows'], AccountType::Expense);
        $revenueTotal = $this->sum($revenue);
        $expenseTotal = $this->sum($expense);

        return [
            'revenue' => $revenue, 'expense' => $expense,
            'revenueTotal' => (string) $revenueTotal, 'expenseTotal' => (string) $expenseTotal,
            'net' => (string) $revenueTotal->minus($expenseTotal),
            'unclassified' => $accounts['unclassified'],
        ];
    }

    /** @return array<string, mixed> */
    public function ledger(int $teamId, int $accountId, string $start, string $end, int $page, string $url): array
    {
        $opening = BigInteger::zero();
        $balance = BigInteger::zero();
        $debit = BigInteger::zero();
        $credit = BigInteger::zero();
        $rows = [];
        $count = 0;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        foreach ($this->lines($teamId)->where('accounts.id', $accountId)->where('entries.date', '<=', $end)
            ->orderBy('entries.date')->orderBy('entries.id')->orderBy('lines.id')
            ->select('entries.id as journal_id', 'entries.date', 'entries.template_name', 'entries.description',
                'lines.id', 'lines.side', 'lines.amount', 'lines.account_code', 'lines.account_name')
            ->cursor() as $line) {
            $amount = BigInteger::of((string) $line->amount);
            $balance = $line->side === 'debit' ? $balance->plus($amount) : $balance->minus($amount);
            if ($line->date < $start) {
                $opening = $balance;

                continue;
            }

            if ($line->side === 'debit') {
                $debit = $debit->plus($amount);
            } else {
                $credit = $credit->plus($amount);
            }

            if ($count >= $offset && $count < $offset + $perPage) {
                $rows[] = [
                    'id' => (int) $line->id,
                    'journalId' => (int) $line->journal_id,
                    'date' => $line->date,
                    'title' => $line->template_name,
                    'description' => $line->description,
                    'accountCode' => $line->account_code,
                    'accountName' => $line->account_name,
                    'debit' => $line->side === 'debit' ? (string) $amount : '0',
                    'credit' => $line->side === 'credit' ? (string) $amount : '0',
                    'balance' => (string) $balance,
                ];
            }

            $count++;
        }

        return [
            'opening' => (string) $opening,
            'debitTotal' => (string) $debit,
            'creditTotal' => (string) $credit,
            'closing' => (string) $balance,
            'entries' => new LengthAwarePaginator($rows, $count, $perPage, $page, [
                'path' => $url,
                'query' => ['start' => $start, 'end' => $end, 'account' => $accountId],
            ]),
        ];
    }

    private function lines(int $teamId): Builder
    {
        return DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('journal_accounts as accounts', function ($join) {
                $join->on('accounts.id', '=', 'lines.journal_account_id')->on('accounts.team_id', '=', 'entries.team_id');
            })->where('entries.team_id', $teamId);
    }

    /**
     * @param  list<array{id: int, code: string, name: string, type: string|null, balance: string}>  $rows
     * @return list<array{id: int, code: string, name: string, type: string|null, balance: string}>
     */
    private function ofType(array $rows, AccountType $type): array
    {
        return array_values(array_filter($rows, fn (array $row) => $row['type'] === $type->value));
    }

    /** @param list<array{id: int, code: string, name: string, type: string|null, balance: string}> $rows */
    private function sum(array $rows): BigInteger
    {
        $total = BigInteger::zero();
        foreach ($rows as $row) {
            $total = $total->plus($row['balance']);
        }

        return $total;
    }
}
