<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $items = DB::table('catalog_items as i')->where('i.team_id', $current_team->id)->select('i.*')
            ->selectSub(DB::table('stock_movements')->selectRaw('COALESCE(SUM(quantity),0)')->whereColumn('item_id', 'i.id'), 'quantity')
            ->selectSub(DB::table('stock_movements')->selectRaw('COALESCE(SUM(value),0)')->whereColumn('item_id', 'i.id'), 'value')->orderBy('name')->get();
        $selected = $items->firstWhere('id', $request->integer('item'));
        abort_if($request->filled('item') && ! $selected, 404);
        $moves = $selected ? DB::table('stock_movements as m')->leftJoin('trade_document_lines as l', 'l.id', '=', 'm.line_id')->leftJoin('trade_documents as d', 'd.id', '=', 'l.document_id')->where('m.item_id', $selected->id)->orderByDesc('m.id')->paginate(50, ['m.*', 'd.direction', 'd.id as document'])->withQueryString() : null;

        return Inertia::render('Catalog', ['items' => $items, 'selected' => $selected, 'moves' => $moves, 'team' => $current_team->slug, 'canEdit' => app(JournalDrafts::class)->supervisor($request->user(), $current_team), 'accounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)->where('active', true)->whereIn('type', ['expense', 'revenue'])->get(['id', 'name', 'code', 'type'])]);
    }

    public function save(Request $request, Team $current_team, ?int $item = null): RedirectResponse
    {
        $id = DB::transaction(function () use ($request, $current_team, $item) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $old = $item ? DB::table('catalog_items')->where('team_id', $current_team->id)->where('id', $item)->firstOrFail() : null;
            $request->merge(['sku' => strtoupper($request->string('sku')->trim()->toString())]);
            $account = fn ($type) => Rule::exists('journal_accounts', 'id')->where('team_id', $current_team->id)->where('active', true)->where('type', $type);
            $data = $request->validate(['sku' => ['required', 'string', 'max:80', Rule::unique('catalog_items')->where('team_id', $current_team->id)->ignore($item)], 'name' => 'required|string|max:160', 'stock' => 'required|boolean', 'unit' => 'required|string|max:30', 'buy_price' => 'required|integer|between:0,999999999999', 'sell_price' => 'required|integer|between:0,999999999999', 'sales_account' => ['nullable', 'integer', $account('revenue')], 'expense_account' => ['nullable', 'integer', $account('expense')]]);
            if ($old) {
                $data['stock'] = $old->stock;
                $data['unit'] = $old->unit;
                DB::table('catalog_items')->where('id', $item)->update($data);
                $id = $old->id;
            } else {
                $id = DB::table('catalog_items')->insertGetId([...$data, 'team_id' => $current_team->id]);
            }
            app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'catalog.saved', 'item', $id, $old ? (array) $old : null, $data);

            return $id;
        });

        return to_route('catalog.index', [$current_team, 'item' => $id]);
    }
}
