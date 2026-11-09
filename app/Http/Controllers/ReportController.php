<?php

namespace App\Http\Controllers;

use App\Actions\Reports\FinancialReports;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function financial(Request $request, Team $current_team, FinancialReports $reports): Response
    {
        $date = $request->validate([
            'period' => ['nullable', 'in:monthly,yearly'],
            'year' => ['nullable', 'integer', 'between:1900,9999'],
            'month' => ['nullable', 'date_format:Y-m', 'regex:/\A(?:19\d{2}|[2-9]\d{3})-(?:0[1-9]|1[0-2])\z/'],
            'as_of' => $this->dateRules(),
        ]);
        $period = $date['period'] ?? 'monthly';
        $month = $date['month'] ?? (isset($date['as_of']) ? substr($date['as_of'], 0, 7) : now('Asia/Jakarta')->format('Y-m'));
        $year = (string) ($date['year'] ?? substr($month, 0, 4));
        $asOf = $period === 'yearly'
            ? $year.'-12-31'
            : Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();

        $start = $period === 'yearly' ? $year.'-01-01' : $month.'-01';
        $report = $reports->financial($current_team->id, $asOf);
        foreach (['assets', 'liabilities', 'equity'] as $group) {
            $report[$group] = $this->linkBalances($current_team, $report[$group], $start, $asOf);
        }
        $report['earningsUrl'] = route('reports.profit-loss', [$current_team, 'start' => '1900-01-01', 'end' => $asOf]);

        return Inertia::render('reports/Financial', [
            'period' => $period,
            'year' => $year,
            'month' => $month,
            'asOf' => $asOf,
            'report' => $report,
            'indexUrl' => route('reports.index', $current_team),
            'reportUrl' => route('reports.financial', $current_team),
        ]);
    }

    public function profitLoss(Request $request, Team $current_team, FinancialReports $reports): Response
    {
        [$start, $end] = $this->period($request);

        $report = $reports->profitLoss($current_team->id, $start, $end);
        foreach (['revenue', 'expense'] as $group) {
            $report[$group] = $this->linkBalances($current_team, $report[$group], $start, $end);
        }

        return Inertia::render('reports/ProfitLoss', [
            'start' => $start, 'end' => $end,
            'report' => $report,
            'indexUrl' => route('reports.index', $current_team),
            'reportUrl' => route('reports.profit-loss', $current_team),
        ]);
    }

    public function ledger(Request $request, Team $current_team, FinancialReports $reports): Response
    {
        [$start, $end] = $this->period($request);
        $data = $request->validate([
            'account' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'between:1,1000000'],
        ]);
        $accounts = DB::table('journal_accounts')->where('team_id', $current_team->id)->orderBy('code')
            ->get(['id', 'code', 'name', 'active']);
        $accountId = $data['account'] ?? $accounts->first()->id ?? null;
        abort_unless($accountId === null || $accounts->contains('id', (int) $accountId), 404);
        $url = route('reports.ledger', $current_team);

        $report = $accountId === null ? null : $reports->ledger($current_team->id, (int) $accountId, $start, $end, (int) ($data['page'] ?? 1), $url);
        if ($report !== null) {
            $report['openingUrl'] = $start > '1900-01-01' ? route('reports.ledger', [
                $current_team, 'account' => $accountId, 'start' => '1900-01-01',
                'end' => Carbon::parse($start)->subDay()->toDateString(),
            ]) : null;
            $report['entries']->through(fn (array $entry) => [
                ...$entry,
                'journalUrl' => route('journals.show', [$current_team, $entry['journalId']]),
            ]);
        }

        return Inertia::render('reports/Ledger', [
            'start' => $start, 'end' => $end,
            'accounts' => $accounts,
            'accountId' => $accountId === null ? null : (int) $accountId,
            'report' => $report,
            'indexUrl' => route('reports.index', $current_team),
            'reportUrl' => $url,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function linkBalances(Team $team, array $rows, string $start, string $end): array
    {
        return array_map(fn (array $row) => [
            ...$row,
            'ledgerUrl' => route('reports.ledger', [$team, 'account' => $row['id'], 'start' => $start, 'end' => $end]),
        ], $rows);
    }

    /** @return array{string, string} */
    private function period(Request $request): array
    {
        $start = $request->query('start') ?? now('Asia/Jakarta')->startOfMonth()->toDateString();
        $end = $request->query('end') ?? now('Asia/Jakarta')->toDateString();
        $request->merge(['start' => $start, 'end' => $end]);
        $data = $request->validate([
            'start' => ['required', ...$this->dateRules()],
            'end' => ['required', ...$this->dateRules(), 'after_or_equal:start'],
        ]);

        return [$data['start'], $data['end']];
    }

    /** @return list<string> */
    private function dateRules(): array
    {
        return ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'];
    }
}
