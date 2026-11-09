<?php

namespace App\Actions\Trade;

use App\Enums\TradeKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class TradeBalances
{
    public function invoice(stdClass $invoice): int
    {
        return $this->invoices(collect([$invoice]))[$invoice->id] ?? 0;
    }

    /**
     * @param  Collection<int, stdClass>  $documents
     * @return array<int, int>
     */
    public function invoices(Collection $documents): array
    {
        $invoices = $documents->where('kind', TradeKind::Invoice->value);
        if ($invoices->isEmpty()) {
            return [];
        }

        $ids = $invoices->pluck('id');
        $settlements = DB::table('trade_documents')->whereIn('source_id', $ids)
            ->whereIn('kind', [TradeKind::Payment->value, TradeKind::Return->value, TradeKind::Refund->value])
            ->select('source_id', 'kind')->selectRaw('SUM(total) AS amount')
            ->groupBy('source_id', 'kind')->get()->groupBy('source_id')
            ->map(fn ($rows) => $rows->pluck('amount', 'kind'));
        $applied = DB::table('trade_advance_allocations')->whereIn('invoice_id', $ids)
            ->select('invoice_id')->selectRaw('SUM(amount) AS amount')->groupBy('invoice_id')->pluck('amount', 'invoice_id');

        return $invoices->mapWithKeys(function (stdClass $invoice) use ($settlements, $applied) {
            $amounts = $settlements->get($invoice->id, collect());

            return [$invoice->id => (int) $invoice->total
                - (int) $applied->get($invoice->id, 0)
                - (int) $amounts->get(TradeKind::Payment->value, 0)
                - (int) $amounts->get(TradeKind::Return->value, 0)
                + (int) $amounts->get(TradeKind::Refund->value, 0)];
        })->all();
    }

    public function applied(int $invoiceId): int
    {
        return (int) DB::table('trade_advance_allocations')->where('invoice_id', $invoiceId)->sum('amount');
    }

    /** @return Collection<int, stdClass> */
    public function advances(int $orderId): Collection
    {
        return DB::table('trade_documents as advances')
            ->where('advances.source_id', $orderId)->where('advances.kind', TradeKind::Advance->value)
            ->select('advances.*')
            ->selectSub(DB::table('trade_advance_allocations')->whereColumn('advance_id', 'advances.id')
                ->selectRaw('SUM(amount)'), 'applied')
            ->selectSub(DB::table('trade_documents as refunds')->whereColumn('refunds.source_id', 'advances.id')
                ->where('refunds.kind', TradeKind::AdvanceRefund->value)->selectRaw('SUM(refunds.total)'), 'refunded')
            ->orderBy('advances.date')->orderBy('advances.id')->get();
    }

    public function availableAdvance(stdClass $advance): int
    {
        $current = $this->advances((int) $advance->source_id)->firstWhere('id', $advance->id);

        return $current ? $this->unused($current) : 0;
    }

    public function unused(stdClass $advance): int
    {
        return (int) $advance->total - (int) $advance->applied - (int) $advance->refunded;
    }

    public function limit(stdClass $source, TradeKind $kind): int
    {
        return max(0, match ($kind) {
            TradeKind::Advance => (int) $source->total
                - (int) DB::table('trade_documents')->where('order_id', $source->id)->where('kind', TradeKind::Invoice->value)->sum('total')
                - $this->advances((int) $source->id)->sum($this->unused(...)),
            TradeKind::Payment => $this->invoice($source),
            TradeKind::Refund => -$this->invoice($source),
            TradeKind::AdvanceRefund => $this->availableAdvance($source),
            default => 0,
        });
    }

    /** @return Collection<int, stdClass> */
    public function sourceLines(int $documentId, TradeKind $next): Collection
    {
        $used = DB::table('trade_document_lines as lines')
            ->join('trade_documents as documents', 'documents.id', '=', 'lines.document_id')
            ->where('documents.source_id', $documentId)->where('documents.kind', $next->value)
            ->whereNotNull('lines.source_line_id')->select('lines.source_line_id')
            ->selectRaw('SUM(lines.quantity) AS quantity')->groupBy('lines.source_line_id')->pluck('quantity', 'lines.source_line_id');

        return DB::table('trade_document_lines')->where('document_id', $documentId)->orderBy('id')->get()
            ->map(function (stdClass $line) use ($used) {
                $line->remaining = (int) $line->quantity - (int) $used->get($line->id, 0);

                return $line;
            });
    }
}
