<?php

namespace App\Actions\Reports;

use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

class MovementReports
{
    /** @return array<string,mixed> */
    public function cash(int $team, string $start, string $end): array
    {
        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_accounts as a', 'a.id', '=', 'l.journal_account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->leftJoin('trade_documents as t', 't.journal_entry_id', '=', 'e.id')
            ->leftJoin('opening_balances as o', 'o.journal_entry_id', '=', 'e.id')
            ->where('e.team_id', $team)->where('a.team_id', $team)->where('a.is_cash', true)
            ->where('e.date', '<=', $end)->orderBy('e.id')->select('e.id', 'e.date', 'e.description', 'e.cash_flow_category', 't.kind', 'o.id as opening_id', 'l.side', 'l.amount')->cursor();
        $opening = BigInteger::zero();
        $documents = [];
        foreach ($lines as $line) {
            $amount = BigInteger::of($line->amount);
            if ($line->side === 'credit') {
                $amount = $amount->negated();
            }
            if ($line->date < $start) {
                $opening = $opening->plus($amount);

                continue;
            }
            $documents[$line->id] ??= ['id' => $line->id, 'date' => $line->date, 'description' => $line->description, 'category' => $line->opening_id ? 'opening' : ($line->cash_flow_category ?? ($line->kind ? 'operating' : 'unclassified')), 'amount' => BigInteger::zero()];
            $documents[$line->id]['amount'] = $documents[$line->id]['amount']->plus($amount);
        }
        $rows = array_fill_keys(['opening', 'operating', 'investing', 'financing', 'unclassified'], '0');
        $details = [];
        $closing = $opening;
        foreach ($documents as $doc) {
            if ($doc['amount']->isZero()) {
                continue;
            }
            $key = $doc['category'];
            $rows[$key] = (string) BigInteger::of($rows[$key])->plus($doc['amount']);
            $closing = $closing->plus($doc['amount']);
            $details[] = [...$doc, 'amount' => (string) $doc['amount']];
        }

        return ['rows' => ['Saldo kas/bank awal' => (string) $opening, 'Saldo awal yang dimasukkan dalam periode' => $rows['opening'], 'Aktivitas operasi' => $rows['operating'], 'Aktivitas investasi' => $rows['investing'], 'Aktivitas pendanaan' => $rows['financing'], 'Belum diklasifikasikan' => $rows['unclassified'], 'Saldo kas/bank akhir' => (string) $closing], 'details' => $details];
    }

    /** @return array<string,mixed> */
    public function equity(int $team, string $start, string $end): array
    {
        $opening = BigInteger::zero();
        $capital = BigInteger::zero();
        $earnings = BigInteger::zero();
        $details = [];
        $lines = DB::table('journal_entry_lines as l')->join('journal_accounts as a', 'a.id', '=', 'l.journal_account_id')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('a.team_id', $team)->where('e.team_id', $team)->whereIn('a.type', ['equity', 'expense', 'revenue'])->where('e.date', '<=', $end)
            ->orderBy('e.date')->orderBy('e.id')->select('e.id', 'e.date', 'e.description', 'a.type', 'l.account_name', 'l.side', 'l.amount')->cursor();
        foreach ($lines as $line) {
            $amount = BigInteger::of($line->amount);
            if ($line->side === 'debit') {
                $amount = $amount->negated();
            }
            if ($line->date < $start) {
                $opening = $opening->plus($amount);

                continue;
            }
            if ($line->type === 'equity') {
                $capital = $capital->plus($amount);
            } else {
                $earnings = $earnings->plus($amount);
            }
            $details[] = ['id' => $line->id, 'date' => $line->date, 'description' => $line->account_name.' · '.$line->description, 'amount' => (string) $amount, 'category' => ''];
        }

        return ['rows' => ['Ekuitas awal termasuk hasil usaha' => (string) $opening, 'Mutasi akun ekuitas' => (string) $capital, 'Laba / rugi periode' => (string) $earnings, 'Ekuitas akhir' => (string) $opening->plus($capital)->plus($earnings)], 'details' => $details];
    }
}
