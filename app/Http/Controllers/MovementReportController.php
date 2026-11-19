<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\PeriodLocks;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Reports\MovementReports;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MovementReportController extends Controller
{
    public function index(Request $request, Team $current_team, string $type, MovementReports $reports): Response
    {
        $data = $request->validate(['period' => 'nullable|in:monthly,yearly', 'month' => 'nullable|date_format:Y-m', 'year' => 'nullable|integer|between:1900,9999']);
        $annual = ($data['period'] ?? 'monthly') === 'yearly';
        $now = now('Asia/Jakarta');
        $month = $data['month'] ?? $now->format('Y-m');
        $year = $data['year'] ?? substr($month, 0, 4);
        $start = Carbon::parse($annual ? $year.'-01-01' : $month.'-01');
        $end = $annual ? $start->copy()->endOfYear() : $start->copy()->endOfMonth();
        $prior = $annual ? $start->copy()->subYear() : $start->copy()->subMonth();
        $method = $type === 'arus-kas' ? 'cash' : 'equity';
        $current = $reports->$method($current_team->id, $start->toDateString(), $end->toDateString());
        $previous = $reports->$method($current_team->id, $prior->toDateString(), $start->copy()->subDay()->toDateString());

        return Inertia::render('reports/Movements', ['type' => $type, 'team' => $current_team->slug, 'url' => route('reports.movements', [$current_team, $type]), 'period' => $annual ? 'yearly' : 'monthly', 'month' => $month, 'year' => (string) $year, 'currentLabel' => $start->format($annual ? 'Y' : 'Y-m'), 'previousLabel' => $prior->format($annual ? 'Y' : 'Y-m'), 'report' => $current, 'previous' => $previous['rows'], 'canEdit' => app(JournalDrafts::class)->supervisor($request->user(), $current_team)]);
    }

    public function classify(Request $request, Team $current_team, int $entry): RedirectResponse
    {
        $category = $request->validate(['category' => 'required|in:operating,investing,financing,unclassified'])['category'];
        DB::transaction(function () use ($category, $request, $current_team, $entry) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = DB::table('journal_entries')->where('team_id', $current_team->id)->where('id', $entry)->firstOrFail();
            abort_if(DB::table('opening_balances')->where('journal_entry_id', $entry)->exists(), 422);
            abort_unless(DB::table('journal_entry_lines as l')->join('journal_accounts as a', 'a.id', '=', 'l.journal_account_id')->where('l.journal_entry_id', $entry)->where('a.is_cash', true)->exists(), 422);
            app(PeriodLocks::class)->assertOpen($current_team->id, $record->date);
            DB::table('journal_entries')->where('id', $entry)->update(['cash_flow_category' => $category]);
            app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'cashflow.classified', 'journal', $entry, ['category' => $record->cash_flow_category], compact('category'));
        });

        return back();
    }
}
