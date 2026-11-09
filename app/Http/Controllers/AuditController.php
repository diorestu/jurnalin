<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class AuditController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        abort_unless($request->user()?->teamRole($current_team)?->isAtLeast(TeamRole::Admin), 403);
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'start' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31', ...($request->filled('start') ? ['after_or_equal:start'] : [])],
        ]);
        $query = DB::table('accounting_audit_events as events')
            ->leftJoin('users', 'users.id', '=', 'events.actor_id')->where('events.team_id', $current_team->id);
        if (! empty($data['action'])) {
            $query->where('events.action', $data['action']);
        }
        if (! empty($data['start'])) {
            $query->where('events.created_at', '>=', $this->boundary($data['start'], false));
        }
        if (! empty($data['end'])) {
            $query->where('events.created_at', '<=', $this->boundary($data['end'], true));
        }

        $events = $query->select('events.*', 'users.name as actor_name')
            ->orderByDesc('events.created_at')->orderByDesc('events.id')->paginate(50)->withQueryString()
            ->through(fn (stdClass $event) => [
                'id' => (int) $event->id,
                'actor' => $event->actor_name,
                'action' => (string) $event->action,
                'subjectType' => (string) $event->subject_type,
                'subjectId' => $event->subject_id === null ? null : (int) $event->subject_id,
                'reason' => $event->reason,
                'before' => $event->before === null ? null : json_decode($event->before, true, 512, JSON_THROW_ON_ERROR),
                'after' => $event->after === null ? null : json_decode($event->after, true, 512, JSON_THROW_ON_ERROR),
                'createdAt' => Carbon::parse($event->created_at, config('app.timezone'))->toIso8601String(),
            ]);

        return Inertia::render('accounting/Audit', [
            'events' => $events,
            'actions' => DB::table('accounting_audit_events')->where('team_id', $current_team->id)
                ->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'action' => $data['action'] ?? '', 'start' => $data['start'] ?? '', 'end' => $data['end'] ?? '',
            ],
            'indexUrl' => route('accounting-audit.index', $current_team),
            'periodsUrl' => route('accounting-periods.index', $current_team),
        ]);
    }

    private function boundary(string $date, bool $end): string
    {
        $local = Carbon::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta');

        return ($end ? $local->endOfDay() : $local)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
