<?php

namespace App\Actions\Accounting;

use App\Actions\Opening\AccountingStartDate;
use App\Models\JournalEntry;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class DepreciateAsset
{
    /** @return array{month:string,amount:int}|null */
    public function next(stdClass $asset, int $posted): ?array
    {
        if ($posted >= $asset->months) {
            return null;
        }
        $base = (int) $asset->cost - (int) $asset->residual;
        $months = (int) $asset->months;
        // Round cumulative depreciation so the final book value equals the residual.
        $cumulative = fn (int $n) => intdiv($base * $n + intdiv($months, 2), $months);

        return ['month' => Carbon::parse($asset->start_month.'-01')->addMonths($posted)->format('Y-m'), 'amount' => $cumulative($posted + 1) - $cumulative($posted)];
    }

    public function post(Team $team, int $id, string $month, int $actor): void
    {
        DB::transaction(function () use ($team, $id, $month, $actor) {
            Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $asset = DB::table('fixed_assets')->where('team_id', $team->id)->where('id', $id)->firstOrFail();
            $history = DB::table('asset_depreciations')->where('asset_id', $id);
            if ((clone $history)->where('month', $month)->exists()) {
                return;
            }
            $next = $this->next($asset, $history->count());
            if ($next === null || $month !== $next['month']) {
                throw ValidationException::withMessages(['month' => 'Posting harus mengikuti urutan bulan penyusutan aset.']);
            }
            $date = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
            app(AccountingStartDate::class)->assertAfter($team->id, $date);
            $entry = null;
            if ($next['amount'] > 0) {
                $entry = JournalEntry::create(['team_id' => $team->id, 'posted_by' => $actor, 'approved_by' => $actor, 'approved_at' => now(), 'request_id' => (string) Str::uuid(), 'template_name' => 'Penyusutan aset tetap', 'date' => $date, 'description' => $asset->code.' · '.$asset->name.' · '.$month]);
                foreach (['debit' => [$asset->expense_account, 'expense'], 'credit' => [$asset->accumulation_account, 'asset']] as $side => [$accountId,$type]) {
                    $account = DB::table('journal_accounts')->where('team_id', $team->id)->where('id', $accountId)->where('active', true)->where('type', $type)->first();
                    if (! $account) {
                        throw ValidationException::withMessages(['month' => 'Periksa jenis dan status akun penyusutan.']);
                    }
                    $entry->lines()->create(['journal_account_id' => $accountId, 'account_code' => $account->code, 'account_name' => $account->name, 'side' => $side, 'amount' => $next['amount']]);
                }
            }
            DB::table('asset_depreciations')->insert(['asset_id' => $id, 'month' => $month, 'amount' => $next['amount'], 'journal_entry_id' => $entry?->id]);
            app(RecordAccountingAudit::class)->record($team->id, $actor, 'asset.depreciated', 'asset', $id, null, [...$next, 'journal_id' => $entry?->id]);
        }, attempts: 3);
    }
}
