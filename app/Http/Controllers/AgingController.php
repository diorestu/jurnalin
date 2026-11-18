<?php

namespace App\Http\Controllers;

use App\Actions\Trade\TradeBalances;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Brick\Math\BigInteger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AgingController extends Controller
{
    public function __invoke(Request $request, Team $current_team, TradeBalances $balances): Response
    {
        $filters = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'], 'direction' => ['nullable', Rule::enum(TradeDirection::class)], 'contact' => ['nullable', 'integer', 'min:1']]);
        $date = $filters['as_of'] ?? now('Asia/Jakarta')->toDateString();
        $direction = TradeDirection::from($filters['direction'] ?? 'penjualan');
        $contacts = DB::table('trade_partners')->where('team_id', $current_team->id)->where('direction', $direction->value)->orderBy('name')->get(['id', 'name', 'code'])->keyBy('id');
        $contact = $filters['contact'] ?? null;
        abort_if($contact && ! $contacts->has($contact), 404);
        $invoices = DB::table('trade_documents as d')->leftJoin('opening_balances as o', 'o.id', '=', 'd.opening_balance_id')->where('d.team_id', $current_team->id)->where('d.direction', $direction->value)->where('d.kind', 'invoice')->where('d.date', '<=', $date)->where(fn ($q) => $q->whereNull('d.opening_balance_id')->orWhere('o.date', '<=', $date))->when($contact, fn ($q) => $q->where('partner_id', $contact))->orderBy('due_date')->orderBy('d.id')->get(['d.*']);
        $open = $balances->invoices($invoices, $date);
        $ids = $invoices->pluck('id')->filter(fn ($id) => $open[$id] !== 0);
        $movements = DB::table('trade_documents')->where('team_id', $current_team->id)->whereIntegerInRaw('source_id', $ids)->whereIn('kind', ['payment', 'return', 'refund'])->where('date', '<=', $date)->orderBy('date')->orderBy('id')->get()->groupBy('source_id');
        $advances = DB::table('trade_advance_allocations as a')->join('trade_documents as d', 'd.id', '=', 'a.advance_id')->where('d.team_id', $current_team->id)->whereIntegerInRaw('a.invoice_id', $ids)->get(['a.invoice_id', 'a.amount', 'd.id', 'd.date'])->groupBy('invoice_id');
        $totals = array_fill_keys(['Belum jatuh tempo', '1–30 hari', '31–60 hari', '61–90 hari', '>90 hari', 'Tanpa jatuh tempo', 'Kredit retur'], '0');
        $rows = [];
        $net = BigInteger::zero();
        foreach ($invoices as $doc) {
            $amount = $open[$doc->id];
            if ($amount === 0) {
                continue;
            }
            $days = $doc->due_date ? Carbon::parse($doc->due_date, 'UTC')->diffInDays(Carbon::parse($date, 'UTC'), false) : null;
            $bucket = match (true) {
                $amount < 0 => 'Kredit retur', $days === null => 'Tanpa jatuh tempo', $days <= 0 => 'Belum jatuh tempo', $days <= 30 => '1–30 hari', $days <= 60 => '31–60 hari', $days <= 90 => '61–90 hari', default => '>90 hari',
            };
            $totals[$bucket] = (string) BigInteger::of($totals[$bucket])->plus($amount);
            $net = $net->plus($amount);
            $entries = [];
            foreach ($advances->get($doc->id, collect()) as $advance) {
                $entries[] = ['label' => 'Alokasi DP', 'date' => $doc->date, 'amount' => (string) -$advance->amount, 'url' => route('trade.show', [$current_team, $direction, $advance->id])];
            }
            foreach ($movements->get($doc->id, collect()) as $item) {
                $entries[] = ['label' => TradeKind::from($item->kind)->label($direction), 'date' => $item->date, 'amount' => (string) ($item->kind === 'refund' ? $item->total : -$item->total), 'url' => route('trade.show', [$current_team, $direction, $item->id])];
            }
            $rows[] = ['entries' => $entries, 'original' => (string) $doc->total, 'partner' => $contacts->get($doc->partner_id)->name ?? $doc->partner, 'reference' => $doc->reference ?: TradeKind::Invoice->number($direction, $doc->id), 'date' => $doc->date, 'due' => $doc->due_date, 'amount' => (string) $amount, 'bucket' => $bucket, 'url' => route('trade.show', [$current_team, $direction, $doc->id])];
        }
        $gl = BigInteger::zero();
        if (! $contact) {
            $sales = $direction === TradeDirection::Sales;
            $lines = DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('journal_accounts as a', 'a.id', '=', 'l.journal_account_id')->where('a.team_id', $current_team->id)->where('e.team_id', $current_team->id)->where('a.trade_role', $sales ? 'receivable' : 'payable')->where('e.date', '<=', $date)->select('l.side', 'l.amount')->cursor();
            foreach ($lines as $line) {
                $gl = $line->side === ($sales ? 'debit' : 'credit') ? $gl->plus($line->amount) : $gl->minus($line->amount);
            }
        }

        return Inertia::render('Aging', ['base' => route('reports.aging', $current_team), 'date' => $date, 'direction' => $direction->value, 'contact' => $contact, 'contacts' => $contacts->values(), 'rows' => $rows, 'totals' => $totals, 'net' => (string) $net, 'ledger' => $contact ? null : (string) $gl, 'difference' => $contact ? null : (string) $gl->minus($net)]);
    }
}
