<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\DepreciateAsset;
use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Opening\AccountingStartDate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request, Team $current_team, DepreciateAsset $schedule): Response
    {
        $totals = DB::table('asset_depreciations')->select('asset_id')->selectRaw('COUNT(*) AS periods, SUM(amount) AS accumulated')->groupBy('asset_id');
        $assets = DB::table('fixed_assets as a')->where('team_id', $current_team->id)->leftJoinSub($totals, 'd', 'd.asset_id', '=', 'a.id')->orderBy('code')->get(['a.*', 'd.accumulated', 'd.periods']);
        foreach ($assets as $asset) {
            $asset->book = (int) $asset->cost - (int) $asset->accumulated;
            $asset->next = $schedule->next($asset, (int) $asset->periods);
        }
        $selected = $assets->firstWhere('id', $request->integer('asset'));
        abort_if($request->filled('asset') && ! $selected, 404);

        return Inertia::render('Assets', ['team' => $current_team->slug, 'assets' => $assets, 'selected' => $selected, 'canPost' => app(JournalDrafts::class)->supervisor($request->user(), $current_team), 'accounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)->where('active', true)->where('is_cash', false)->whereNull('trade_role')->whereIn('type', ['expense', 'asset'])->orderBy('code')->get(['id', 'code', 'name', 'type']), 'history' => $selected ? DB::table('asset_depreciations')->where('asset_id', $selected->id)->orderByDesc('month')->get() : []]);
    }

    public function store(Request $request, Team $current_team): RedirectResponse
    {
        $id = DB::transaction(function () use ($request, $current_team) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $account = fn ($type) => Rule::exists('journal_accounts', 'id')->where('team_id', $current_team->id)->where('active', true)->where('is_cash', false)->whereNull('trade_role')->where('type', $type);
            $request->merge(['code' => strtoupper($request->string('code')->trim()->toString())]);
            $data = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('fixed_assets')->where('team_id', $current_team->id)], 'name' => 'required|string|max:160', 'location' => 'nullable|string|max:160', 'category' => 'nullable|string|max:100', 'acquired' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:9999-12-31', 'start_month' => 'required|date_format:Y-m', 'months' => 'required|integer|between:1,600', 'cost' => 'required|integer|between:1,999999999999999', 'residual' => 'required|integer|min:0|lt:cost', 'asset_account' => ['required', 'integer', $account('asset')], 'accumulation_account' => ['required', 'integer', 'different:asset_account', $account('asset')], 'expense_account' => ['required', 'integer', $account('expense')]]);
            if ($data['start_month'] < substr($data['acquired'], 0, 7)) {
                throw ValidationException::withMessages(['start_month' => 'Penyusutan dimulai pada atau setelah bulan perolehan.']);
            }
            $first = Carbon::parse($data['start_month'].'-01');
            if ($first->copy()->addMonths($data['months'] - 1)->year > 9999) {
                throw ValidationException::withMessages(['months' => 'Jadwal penyusutan melewati batas tahun laporan.']);
            }
            app(AccountingStartDate::class)->assertAfter($current_team->id, $first->endOfMonth()->toDateString());
            $id = DB::table('fixed_assets')->insertGetId(['team_id' => $current_team->id, ...$data]);
            app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'asset.registered', 'asset', $id, null, $data);

            return $id;
        });

        return to_route('assets.index', [$current_team, 'asset' => $id]);
    }

    public function post(Request $request, Team $current_team, int $asset, DepreciateAsset $schedule): RedirectResponse
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $schedule->post($current_team, $asset, $data['month'], $request->user()->id);

        return to_route('assets.index', [$current_team, 'asset' => $asset]);
    }
}
