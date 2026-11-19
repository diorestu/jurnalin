<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JournalDraftController extends Controller
{
    public function index(Team $current_team): Response
    {
        $drafts = DB::table('journal_drafts as drafts')->leftJoin('users', 'users.id', '=', 'drafts.created_by')
            ->where('drafts.team_id', $current_team->id)->orderByDesc('drafts.updated_at')->orderByDesc('drafts.id')
            ->select('drafts.*', 'users.name as creator_name')->paginate(20);
        $drafts->through(fn ($draft) => [
            'id' => $draft->id, 'status' => $draft->status, 'date' => $draft->date, 'description' => $draft->description,
            'createdBy' => $draft->creator_name, 'createdAt' => Carbon::parse($draft->created_at)->toIso8601String(),
            'showUrl' => route('journals.draft-show', [$current_team, $draft->id]),
            'journalUrl' => $draft->journal_entry_id === null ? null : route('journals.show', [$current_team, $draft->journal_entry_id]),
        ]);

        return Inertia::render('journal/Drafts', [
            'title' => 'Draf & Persetujuan', 'drafts' => $drafts, 'indexUrl' => route('journals.index', $current_team),
        ]);
    }

    public function show(Request $request, Team $current_team, int $draft, JournalDrafts $workflow): Response
    {
        $record = $workflow->find($current_team, $draft);
        $payload = $workflow->payload($record);
        $supervisor = $workflow->supervisor($request->user(), $current_team);
        $editable = $record->status === 'draft' && ($supervisor || $record->created_by === $request->user()->id);
        $trade = null;
        $editTrade = null;
        if ($record->mode === 'trade') {
            $data = $payload['data'];
            $direction = TradeDirection::from($payload['direction']);
            $kind = TradeKind::from($data['kind']);
            $source = isset($data['source_id']) ? DB::table('trade_documents')->where('team_id', $current_team->id)->where('id', $data['source_id'])->first() : null;
            $lines = $data['lines'] ?? [];
            if ($source !== null && ! $kind->isCash()) {
                $sourceLines = DB::table('trade_document_lines')->where('document_id', $source->id)->get()->keyBy('id');
                $lines = array_map(function ($line) use ($sourceLines) {
                    $original = $sourceLines->get($line['source_line_id'] ?? 0);

                    return [...$line, 'name' => $original->name ?? $line['name'] ?? '', 'unit_price' => $original->unit_price ?? $line['unit_price'] ?? 0];
                }, $lines);
            }
            $amount = $kind->isCash() ? (int) $data['amount'] : array_reduce($lines, fn ($sum, $line) => $sum + (int) $line['quantity'] * (int) $line['unit_price'], 0);
            $account = isset($data['account_id']) ? DB::table('journal_accounts')->where('team_id', $current_team->id)->where('id', $data['account_id'])->first() : null;
            $lines=$payload['pricing_preview']['lines']??$lines;
            $amount=$payload['pricing_preview']['amount']??$amount;
            $trade = [
                'label' => $kind->label($direction), 'directionLabel' => $direction->label(),
                'partner' => $source->partner ?? $data['partner'] ?? '', 'amount' => $amount, 'lines' => $lines,
                'sourceUrl' => $source === null ? null : route('trade.show', [$current_team, $direction, $source->id]),
                'sourceNumber' => $source === null ? null : TradeKind::from($source->kind)->number($direction, $source->id),
                'dueDate' => $data['due_date'] ?? null, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'accountLabel' => $account === null ? null : $account->code.' · '.$account->name,
            ];
            $editTrade = $editable ? route('trade.create', [$current_team, $direction, 'draft' => $draft]) : null;
        }

        return Inertia::render('journal/DraftShow', [
            'draft' => [
                'id' => $record->id, 'mode' => $record->mode, 'status' => $record->status, 'date' => $record->date,
                'description' => $record->description, 'version' => $record->version,
                'createdBy' => $record->created_by === null ? null : DB::table('users')->where('id', $record->created_by)->value('name'),
                'createdAt' => Carbon::parse($record->created_at)->toIso8601String(),
                'submittedAt' => $record->submitted_at === null ? null : Carbon::parse($record->submitted_at)->toIso8601String(),
                'reviewReason' => $record->review_reason, 'lines' => $record->mode === 'trade' ? [] : $payload['lines'], 'trade' => $trade,
                'journalUrl' => $record->journal_entry_id === null ? null : route('journals.show', [$current_team, $record->journal_entry_id]),
            ],
            'accounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)->orderBy('code')->get(['id', 'code', 'name', 'active']),
            'permissions' => [
                'edit' => $editable, 'submit' => $editable,
                'approve' => $supervisor && $record->status === 'pending', 'return' => $supervisor && $record->status === 'pending',
            ],
            'urls' => [
                'index' => route('journals.drafts', $current_team), 'update' => route('journals.draft-update', [$current_team, $draft]),
                'submit' => route('journals.draft-submit', [$current_team, $draft]), 'approve' => route('journals.draft-approve', [$current_team, $draft]),
                'return' => route('journals.draft-return', [$current_team, $draft]), 'editTrade' => $editTrade,
            ],
        ]);
    }

    public function update(Request $request, Team $current_team, int $draft, JournalDrafts $workflow): RedirectResponse
    {
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        $workflow->update($current_team, $request->user(), $draft, (int) $version, $request->all());

        return to_route('journals.draft-show', [$current_team, $draft]);
    }

    public function submit(Request $request, Team $current_team, int $draft, JournalDrafts $workflow): RedirectResponse
    {
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        $workflow->submit($current_team, $request->user(), $draft, (int) $version);

        return to_route('journals.draft-show', [$current_team, $draft]);
    }

    public function approve(Request $request, Team $current_team, int $draft, JournalDrafts $workflow): RedirectResponse
    {
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        $workflow->approve($current_team, $request->user(), $draft, (int) $version);

        return to_route('journals.draft-show', [$current_team, $draft]);
    }

    public function returnForRevision(Request $request, Team $current_team, int $draft, JournalDrafts $workflow): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $workflow->returnForRevision($current_team, $request->user(), $draft, (int) $data['version'], $data['reason']);

        return to_route('journals.draft-show', [$current_team, $draft]);
    }
}
