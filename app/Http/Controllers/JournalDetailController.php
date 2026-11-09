<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Brick\Math\BigInteger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JournalDetailController extends Controller
{
    public function __invoke(Request $request, Team $current_team, int $journal): Response
    {
        $entry = DB::table('journal_entries as entries')
            ->leftJoin('users', 'users.id', '=', 'entries.posted_by')
            ->where('entries.team_id', $current_team->id)->where('entries.id', $journal)
            ->select('entries.id', 'entries.date', 'entries.template_name', 'entries.description',
                'entries.created_at', 'entries.approved_by', 'entries.approved_at', 'entries.reversal_of_id', 'entries.reversal_reason', 'users.name as posted_by_name')->first();
        abort_if($entry === null, 404);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $journal)
            ->orderBy('id')->get(['id', 'account_code', 'account_name', 'side', 'amount']);
        $totals = ['debit' => BigInteger::zero(), 'credit' => BigInteger::zero()];
        foreach ($lines as $line) {
            $totals[$line->side] = $totals[$line->side]->plus((string) $line->amount);
        }

        $sources = [];
        $opening = DB::table('opening_balances')->where('team_id', $current_team->id)
            ->where('journal_entry_id', $journal)->first(['id']);
        if ($opening !== null) {
            $sources[] = [
                'label' => 'Saldo awal #'.$opening->id,
                'url' => route('opening-balances.show', [$current_team, $opening->id]),
            ];
        }

        $documents = DB::table('trade_documents')->where('team_id', $current_team->id)
            ->where('journal_entry_id', $journal)->orderBy('id')->get(['id', 'direction', 'kind']);
        foreach ($documents as $document) {
            $direction = TradeDirection::from($document->direction);
            $kind = TradeKind::from($document->kind);
            $sources[] = [
                'label' => $kind->label($direction).' · '.$kind->number($direction, (int) $document->id),
                'url' => route('trade.show', [$current_team, $direction, $document->id]),
            ];
        }

        $reversal = DB::table('journal_entries')->where('team_id', $current_team->id)->where('reversal_of_id', $journal)->first(['id']);
        $original = $entry->reversal_of_id === null ? null : DB::table('journal_entries')
            ->where('team_id', $current_team->id)->where('id', $entry->reversal_of_id)->first(['id']);

        return Inertia::render('journal/Show', [
            'reversal' => [
                'allowed' => app(JournalDrafts::class)->supervisor($request->user(), $current_team) && $sources === [] && $reversal === null && $entry->reversal_of_id === null,
                'url' => route('journals.reverse', [$current_team, $journal]),
                'date' => max(now('Asia/Jakarta')->toDateString(), $entry->date),
                'requestId' => (string) Str::uuid(),
                'originalUrl' => $original === null ? null : route('journals.show', [$current_team, $original->id]),
                'reversedUrl' => $reversal === null ? null : route('journals.show', [$current_team, $reversal->id]),
                'reason' => $entry->reversal_reason,
            ],
            'indexUrl' => route('journals.index', $current_team),
            'journalUrl' => route('journals.show', [$current_team, $journal]),
            'journal' => [
                'id' => (int) $entry->id,
                'date' => $entry->date,
                'templateName' => $entry->template_name,
                'description' => $entry->description,
                'postedBy' => $entry->posted_by_name,
                'approvedBy' => $entry->approved_by === null ? null : DB::table('users')->where('id', $entry->approved_by)->value('name'),
                'approvedAt' => $entry->approved_at === null ? null : Carbon::parse($entry->approved_at, config('app.timezone'))->toIso8601String(),
                'createdAt' => Carbon::parse($entry->created_at, config('app.timezone'))->toIso8601String(),
                'lines' => $lines->map(fn ($line) => [
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'side' => $line->side,
                    'amount' => (int) $line->amount,
                ]),
                'debitTotal' => (string) $totals['debit'],
                'creditTotal' => (string) $totals['credit'],
                'balanced' => $totals['debit']->isEqualTo($totals['credit']),
            ],
            'sources' => $sources,
        ]);
    }
}
