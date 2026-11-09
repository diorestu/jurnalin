<?php

namespace App\Http\Controllers;

use App\Actions\Journal\JournalOverview;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Team $current_team, JournalOverview $overview): Response
    {
        $user = $request->user();
        $month = $request->query('month');
        $start = is_string($month) && preg_match('/\A(?:19\d{2}|[2-9]\d{3})-(?:0[1-9]|1[0-2])\z/', $month)
            ? Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')
            : null;
        $start ??= now('Asia/Jakarta')->startOfMonth();

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->when($user->email_verified_at === null, fn ($query) => $query
                ->where('code', $request->session()->get('team_invitation_code')))
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            ...$overview->forMonth($current_team->id, $start),
            'month' => $start->format('Y-m'),
            'dashboardUrl' => route('dashboard', $current_team),
            'journalUrl' => route('journals.index', $current_team),
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
