<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\PeriodLocks;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class PeriodController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $this->authorizeAdmin($request, $current_team);
        $data = $request->validate(['month' => $this->monthRules()]);
        $month = $data['month'] ?? now('Asia/Jakarta')->format('Y-m');
        $current = $this->query($current_team->id)->where('locks.month', $month)->first();
        $periods = $this->query($current_team->id)->orderByDesc('locks.month')
            ->paginate(24)->withQueryString()->through(fn (stdClass $row) => $this->present($row));

        return Inertia::render('accounting/Periods', [
            'lockedThrough' => app(PeriodLocks::class)->lockedThrough($current_team->id),
            'month' => $month,
            'current' => $current === null ? [
                'month' => $month, 'locked' => false, 'reason' => null,
                'actor' => null, 'changedAt' => null, 'version' => 0,
            ] : $this->present($current),
            'periods' => $periods,
            'indexUrl' => route('accounting-periods.index', $current_team),
            'changeUrl' => route('accounting-periods.change', $current_team),
            'auditUrl' => route('accounting-audit.index', $current_team),
        ]);
    }

    public function change(Request $request, Team $current_team, RecordAccountingAudit $audit): RedirectResponse
    {
        $this->authorizeAdmin($request, $current_team);
        $data = $request->validate([
            'month' => ['required', ...$this->monthRules()],
            'locked' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'version' => ['nullable', 'integer', 'min:0'],
        ], ['reason.min' => 'Jelaskan alasan perubahan minimal 3 karakter.']);

        DB::transaction(function () use ($request, $current_team, $data, $audit) {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $current = DB::table('accounting_period_locks')->where('team_id', $current_team->id)
                ->where('month', $data['month'])->first();
            $locked = (bool) $data['locked'];
            if ($current !== null && (bool) $current->is_locked === $locked && $current->reason === $data['reason']) {
                return;
            }

            if (($current === null && (int) ($data['version'] ?? 0) !== 0)
                || ($current !== null && (! isset($data['version']) || (int) $data['version'] !== (int) $current->version))) {
                throw ValidationException::withMessages([
                    'version' => 'Periode telah diubah oleh pengguna lain. Muat ulang status periode sebelum mencoba lagi.',
                ]);
            }

            if (! $locked && DB::table('accounting_period_locks')->where('team_id', $current_team->id)
                ->where('is_locked', true)->where('month', '>', $data['month'])->exists()) {
                throw ValidationException::withMessages(['locked' => 'Buka periode terkunci yang paling baru terlebih dahulu.']);
            }

            $start = Carbon::createFromFormat('!Y-m', $data['month'], 'Asia/Jakarta');
            if ($locked && DB::table('journal_drafts')->where('team_id', $current_team->id)
                ->where('status', 'pending')
                ->where('date', '<=', $start->copy()->endOfMonth()->toDateString())->exists()) {
                throw ValidationException::withMessages([
                    'month' => 'Masih ada draf jurnal menunggu persetujuan sampai periode ini. Selesaikan draf sebelum mengunci.',
                ]);
            }

            $before = $current === null ? null : [
                'month' => $current->month, 'is_locked' => (bool) $current->is_locked,
                'reason' => $current->reason, 'version' => (int) $current->version,
            ];
            $after = [
                'month' => $data['month'], 'is_locked' => $locked,
                'reason' => $data['reason'], 'version' => (int) ($current->version ?? 0) + 1,
            ];
            $values = [
                'is_locked' => $locked,
                'reason' => $data['reason'],
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
                'version' => $after['version'],
            ];
            if ($current === null) {
                $id = DB::table('accounting_period_locks')->insertGetId([
                    'team_id' => $current_team->id, 'month' => $data['month'], ...$values,
                ]);
            } else {
                $id = (int) $current->id;
                DB::table('accounting_period_locks')->where('team_id', $current_team->id)->where('id', $id)->update($values);
            }

            $audit->record($current_team->id, $request->user()->id,
                $locked ? 'period.locked' : 'period.unlocked', 'accounting_period', $id,
                $before, $after, $data['reason']);
        }, attempts: 5);

        return to_route('accounting-periods.index', [$current_team, 'month' => $data['month']]);
    }

    /** @return list<string> */
    private function monthRules(): array
    {
        return ['nullable', 'date_format:Y-m', 'regex:/\A(?:19\d{2}|[2-9]\d{3})-(?:0[1-9]|1[0-2])\z/'];
    }

    private function authorizeAdmin(Request $request, Team $team): void
    {
        abort_unless($request->user()?->teamRole($team)?->isAtLeast(TeamRole::Admin), 403);
    }

    private function query(int $teamId): Builder
    {
        return DB::table('accounting_period_locks as locks')->leftJoin('users', 'users.id', '=', 'locks.changed_by')
            ->where('locks.team_id', $teamId)->select('locks.*', 'users.name as actor_name');
    }

    /** @return array{month: string, locked: bool, reason: string|null, actor: string|null, changedAt: string|null, version: int} */
    private function present(stdClass $row): array
    {
        return [
            'month' => (string) $row->month,
            'locked' => (bool) $row->is_locked,
            'reason' => $row->reason,
            'actor' => $row->actor_name,
            'changedAt' => Carbon::parse($row->changed_at, config('app.timezone'))->toIso8601String(),
            'version' => (int) $row->version,
        ];
    }
}
