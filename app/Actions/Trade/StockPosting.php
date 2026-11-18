<?php

namespace App\Actions\Trade;

use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class StockPosting
{
    public function __construct(private PostTradeJournal $journal) {}

    /** @return list<array{account:int,side:string,amount:int}> */
    public function record(int $team, int $document, TradeDirection $direction, TradeKind $kind, string $date): array
    {
        $sales = $direction === TradeDirection::Sales;
        $adjustments = [];
        $doc = DB::table('trade_documents')->where('id', $document)->firstOrFail();
        foreach (DB::table('trade_document_lines')->where('document_id', $document)->whereNotNull('item_id')->orderBy('id')->get() as $line) {
            $item = DB::table('catalog_items')->where('team_id', $team)->where('id', $line->item_id)->firstOrFail();
            $source = $line->source_line_id ? DB::table('trade_document_lines')->where('id', $line->source_line_id)->firstOrFail() : null;
            if (! in_array($kind, [TradeKind::Receipt, TradeKind::Delivery, TradeKind::Invoice, TradeKind::Return], true)) {
                continue;
            }
            $account = null;
            $expense = null;
            $cost = 0;
            if ($kind === TradeKind::Invoice || $kind === TradeKind::Return) {
                $account = $kind === TradeKind::Return ? $source->posting_account : ($sales ? $item->sales_account : ($item->stock ? $this->journal->inventoryAccountId($team) : $item->expense_account));
                if (! $sales && ! $item->stock && ! $account) {
                    throw ValidationException::withMessages(['lines' => "Atur akun beban jasa $item->sku terlebih dahulu."]);
                }
                $base = $sales ? $this->journal->controlAccount($team, 'sales')->id : ($kind === TradeKind::Return ? DB::table('trade_documents')->where('id', $doc->source_id)->value('account_id') : $doc->account_id);
                $account = $account ?? $base;
                if ((int) $base !== (int) $account) {
                    $debit = ($sales === ($kind === TradeKind::Invoice)) ? $base : $account;
                    $credit = $debit === $base ? $account : $base;
                    $this->pair($adjustments, $debit, $credit, $line->total);
                }
            }
            if ($item->stock) {
                $physical = $kind !== TradeKind::Invoice || ! $source;
                $inbound = $kind === TradeKind::Return ? $sales : ! $sales;
                if ($physical) {
                    $moves = DB::table('stock_movements')->where('item_id', $item->id);
                    if ($date < $moves->max('date')) {
                        throw ValidationException::withMessages(['date' => "Tanggal mendahului mutasi terakhir $item->sku."]);
                    }
                    $qty = (int) $moves->sum('quantity');
                    $value = (int) $moves->sum('value');
                    if (! $inbound && $line->quantity > $qty) {
                        throw ValidationException::withMessages(['lines' => "Stok $item->sku tersedia $qty $item->unit."]);
                    }
                    $cost = $inbound ? ($kind === TradeKind::Return ? $this->allocatedCost($source, $line, $kind) : $line->total) : $this->prorate($value, $line->quantity, $qty);
                    DB::table('stock_movements')->insert(['item_id' => $item->id, 'line_id' => $line->id, 'reason' => $kind->label($direction), 'date' => $date, 'quantity' => $inbound ? $line->quantity : -$line->quantity, 'value' => $inbound ? $cost : -$cost]);
                } else {
                    $cost = $this->allocatedCost($source, $line, $kind);
                }
                if (($sales && in_array($kind, [TradeKind::Invoice, TradeKind::Return], true)) || (! $sales && $kind === TradeKind::Return && $line->total !== $cost)) {
                    $expense = $kind === TradeKind::Return ? ($source->cost_account ?? $item->expense_account) : $item->expense_account;
                    $expense ??= DB::table('journal_accounts')->where('team_id', $team)->where('code', '5101')->where('type', 'expense')->value('id');
                    if (! $expense) {
                        throw ValidationException::withMessages(['lines' => "Atur akun HPP untuk $item->sku."]);
                    }
                    $inventory = $this->journal->inventoryAccountId($team);
                    $delta = $sales ? ($kind === TradeKind::Invoice ? $cost : -$cost) : ($cost - $line->total);
                    $this->pair($adjustments, $delta > 0 ? $expense : $inventory, $delta > 0 ? $inventory : $expense, BigInteger::of($delta)->abs()->toInt());
                }
            }
            DB::table('trade_document_lines')->where('id', $line->id)->update(['stock_cost' => $cost, 'posting_account' => $account, 'cost_account' => $expense]);
        }

        return $adjustments;
    }

    private function allocatedCost(stdClass $source, stdClass $line, TradeKind $kind): int
    {
        $prior = DB::table('trade_document_lines as l')->join('trade_documents as d', 'd.id', '=', 'l.document_id')->where('l.source_line_id', $source->id)->where('d.kind', $kind->value)->where('l.id', '<', $line->id)->sum('l.quantity');

        return $this->prorate($source->stock_cost, (int) $prior + $line->quantity, $source->quantity) - $this->prorate($source->stock_cost, (int) $prior, $source->quantity);
    }

    private function prorate(int $value, int $part, int $quantity): int
    {
        return BigInteger::of($value)->multipliedBy($part)->dividedBy($quantity, RoundingMode::HalfUp)->toInt();
    }

    /** @param list<array{account:int,side:string,amount:int}> $lines */
    private function pair(array &$lines, int $debit, int $credit, int $amount): void
    {
        if ($amount === 0) {
            return;
        }
        $lines[] = ['account' => $debit, 'side' => 'debit', 'amount' => $amount];
        $lines[] = ['account' => $credit, 'side' => 'credit', 'amount' => $amount];
    }
}
