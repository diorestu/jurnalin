<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $base = route('partners.index', $current_team);
        $partners = DB::table('trade_partners')->where('team_id', $current_team->id)->orderBy('name')->get();
        $selected = $partners->firstWhere('id', $request->integer('contact'));
        abort_if($request->filled('contact') && ! $selected, 404);
        $history = $selected ? DB::table('trade_documents')->where('team_id', $current_team->id)->where('partner_id', $selected->id)->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString()->through(function ($doc) use ($current_team) {
            $direction = TradeDirection::from($doc->direction);

            return ['number' => TradeKind::from($doc->kind)->number($direction, $doc->id), 'date' => $doc->date, 'total' => (string) $doc->total, 'url' => route('trade.show', [$current_team, $direction, $doc->id])];
        }) : null;

        return Inertia::render('Partners', ['base' => $base, 'partners' => $partners, 'selected' => $selected, 'history' => $history, 'agingUrl' => $selected ? route('reports.aging', [$current_team, 'direction' => $selected->direction, 'contact' => $selected->id]) : null, 'canEdit' => app(JournalDrafts::class)->supervisor($request->user(), $current_team)]);
    }

    public function save(Request $request, Team $current_team, ?int $contact = null): RedirectResponse
    {
        $id = DB::transaction(function () use ($request, $current_team, $contact) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('trade_partners')->where('team_id', $current_team->id);
            $old = $contact ? (clone $query)->where('id', $contact)->first() : null;
            abort_if($contact && ! $old, 404);
            $direction = $old->direction ?? $request->input('direction');
            $request->merge(['direction' => $direction, 'code' => strtoupper($request->string('code')->trim()->toString())]);
            $data = $request->validate(['direction' => ['required', Rule::enum(TradeDirection::class)], 'code' => ['required', 'string', 'max:40', Rule::unique('trade_partners')->where('team_id', $current_team->id)->where('direction', $direction)->ignore($contact)], 'name' => ['required', 'string', 'max:160'], 'terms' => ['nullable', 'integer', 'between:0,3650'], 'contact' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'npwp' => ['nullable', 'string', 'max:32']]);
            if ($old) {
                $query->where('id', $contact)->update($data);
                $id = $old->id;
            } else {
                $id = DB::table('trade_partners')->insertGetId(['team_id' => $current_team->id, ...$data]);
            }
            app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, $old ? 'partner.updated' : 'partner.created', 'partner', $id, $old ? (array) $old : null, $data);

            return $id;
        });

        return to_route('partners.index', [$current_team, 'contact' => $id]);
    }
}
