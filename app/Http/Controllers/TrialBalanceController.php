<?php

namespace App\Http\Controllers;

use App\Actions\Reports\TrialBalance;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    public function __invoke(Request $request, Team $current_team, TrialBalance $balances): Response
    {
        $request->merge([
            'start' => $request->query('start') ?? now('Asia/Jakarta')->startOfMonth()->toDateString(),
            'end' => $request->query('end') ?? now('Asia/Jakarta')->toDateString(),
        ]);
        $dates = ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'];
        $data = $request->validate([
            'start' => $dates,
            'end' => [...$dates, 'after_or_equal:start'],
        ]);
        $report = $balances->forPeriod($current_team->id, $data['start'], $data['end']);
        $report['rows'] = array_map(fn (array $row) => [
            ...$row,
            'ledgerUrl' => route('reports.ledger', [
                $current_team, 'account' => $row['id'], 'start' => $data['start'], 'end' => $data['end'],
            ]),
        ], $report['rows']);

        return Inertia::render('reports/TrialBalance', [
            'title' => 'Neraca Saldo',
            'indexUrl' => route('reports.index', $current_team),
            'reportUrl' => route('reports.trial', $current_team),
            'start' => $data['start'],
            'end' => $data['end'],
            'report' => $report,
        ]);
    }
}
