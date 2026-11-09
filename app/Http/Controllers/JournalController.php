<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Journal\JournalTemplates;
use App\Actions\Opening\AccountingStartDate;
use App\Enums\TeamRole;
use App\Models\JournalEntry;
use App\Models\Team;
use Brick\Math\BigInteger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request, Team $current_team, JournalTemplates $templates): Response
    {
        return Inertia::render('journal/Index', [
            'canPost' => app(JournalDrafts::class)->supervisor($request->user(), $current_team),
            'draftsUrl' => route('journals.drafts', $current_team),
            'journalAccounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)
                ->where('active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'manualStoreUrl' => route('journals.manual', $current_team),
            'journalTemplates' => $templates->forTeam($current_team->id),
            'journals' => JournalEntry::query()
                ->where('team_id', $current_team->id)
                ->with('lines:id,journal_entry_id,account_code,account_name,side,amount')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20, ['id', 'date', 'description', 'template_name']),
            'journalTemplatesUrl' => $request->user()->teamRole($current_team)?->isAtLeast(TeamRole::Admin)
                ? route('journal-templates.index', $current_team)
                : null,
            'journalStoreUrl' => route('journals.store', $current_team),
            'draftKey' => (string) Str::uuid(),
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function store(Request $request, Team $current_team, JournalTemplates $templates): RedirectResponse
    {
        if ($response = $this->draftSubmission($request, $current_team, 'template')) {
            return $response;
        }
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'template_id' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'size:64'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'amount' => ['required', 'integer', 'between:1,999999999999999'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $requestHash = hash('sha256', json_encode([
            (int) $data['template_id'],
            $data['signature'],
            $data['date'],
            (int) $data['amount'],
            $data['description'] ?? null,
        ], JSON_THROW_ON_ERROR));
        $existingEntry = JournalEntry::query()
            ->where('team_id', $current_team->id)
            ->where('request_id', $data['request_id'])
            ->useWritePdo();

        if ($entry = $existingEntry->first()) {
            $this->verifyReplay($entry, $requestHash);

            return to_route('journals.index', $current_team);
        }

        try {
            DB::transaction(function () use ($current_team, $data, $request, $requestHash, $templates) {
                Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
                $this->assertNoDraft($current_team, $data['request_id']);
                app(AccountingStartDate::class)->assertAfter($current_team->id, $data['date']);
                $template = $templates->forTeam($current_team->id, (int) $data['template_id'])->first();
                if ($template === null || ! hash_equals($template['signature'], $data['signature'])) {
                    throw ValidationException::withMessages([
                        'template_id' => 'Template berubah atau tidak tersedia. Pilih template kembali.',
                    ]);
                }

                $entry = JournalEntry::query()->create([
                    'team_id' => $current_team->id,
                    'request_id' => $data['request_id'],
                    'request_hash' => $requestHash,
                    'journal_template_id' => $template['id'],
                    'posted_by' => $request->user()->id,
                    'approved_by' => $request->user()->id, 'approved_at' => now(),
                    'template_name' => $template['name'],
                    'date' => $data['date'],
                    'description' => $data['description'] ?? null,
                ]);

                $entry->lines()->createMany(array_map(
                    fn (array $line) => [...$line, 'amount' => (int) $data['amount']],
                    $template['lines'],
                ));
                app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'journal.posted', 'journal', $entry->id,
                    null, ['date' => $data['date'], 'description' => $data['description'] ?? null, 'lines' => $entry->lines()->get()->toArray()]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $entry = $existingEntry->first();
            if ($entry === null) {
                throw $exception;
            }

            $this->verifyReplay($entry, $requestHash);
        }

        return to_route('journals.index', $current_team);
    }

    public function manual(Request $request, Team $current_team): RedirectResponse
    {
        if ($response = $this->draftSubmission($request, $current_team, 'manual')) {
            return $response;
        }
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'list', 'min:2', 'max:50'],
            'lines.*' => ['array:account_id,debit,credit'],
            'lines.*.account_id' => ['required', 'integer', 'min:1'],
            'lines.*.debit' => ['nullable', 'integer', 'between:0,999999999999999'],
            'lines.*.credit' => ['nullable', 'integer', 'between:0,999999999999999'],
        ]);

        $debit = BigInteger::zero();
        $credit = BigInteger::zero();
        foreach ($data['lines'] as $index => &$line) {
            $line = [
                'account_id' => (int) $line['account_id'],
                'debit' => (int) ($line['debit'] ?? 0),
                'credit' => (int) ($line['credit'] ?? 0),
            ];

            if (($line['debit'] > 0) === ($line['credit'] > 0)) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.debit' => 'Isi tepat satu nominal debit atau kredit pada setiap baris.',
                ]);
            }

            $debit = $debit->plus($line['debit']);
            $credit = $credit->plus($line['credit']);
        }
        unset($line);

        if (! $debit->isEqualTo($credit) || $debit->isGreaterThan('999999999999999')) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit dan kredit harus sama dan maksimal Rp999.999.999.999.999.',
            ]);
        }

        $hash = hash('sha256', json_encode([
            'manual', $data['date'], $data['description'] ?? null, $data['lines'],
        ], JSON_THROW_ON_ERROR));
        $existing = JournalEntry::query()->where('team_id', $current_team->id)
            ->where('request_id', $data['request_id'])->useWritePdo();

        if ($entry = $existing->first()) {
            $this->verifyReplay($entry, $hash);

            return to_route('journals.index', $current_team);
        }

        try {
            DB::transaction(function () use ($current_team, $request, $data, $hash) {
                Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
                $this->assertNoDraft($current_team, $data['request_id']);
                app(AccountingStartDate::class)->assertAfter($current_team->id, $data['date']);
                $accounts = DB::table('journal_accounts')->where('team_id', $current_team->id)
                    ->where('active', true)->whereIn('id', array_column($data['lines'], 'account_id'))
                    ->get(['id', 'code', 'name'])->keyBy('id');
                $lines = [];
                foreach ($data['lines'] as $index => $line) {
                    $account = $accounts->get($line['account_id']);
                    if ($account === null) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.account_id' => 'Pilih akun COA aktif milik perusahaan ini.',
                        ]);
                    }

                    $lines[] = [
                        'journal_account_id' => $account->id,
                        'account_code' => $account->code,
                        'account_name' => $account->name,
                        'side' => $line['debit'] > 0 ? 'debit' : 'credit',
                        'amount' => max($line['debit'], $line['credit']),
                    ];
                }

                $entry = JournalEntry::create([
                    'team_id' => $current_team->id,
                    'request_id' => $data['request_id'],
                    'request_hash' => $hash,
                    'posted_by' => $request->user()->id,
                    'approved_by' => $request->user()->id, 'approved_at' => now(),
                    'template_name' => 'Jurnal manual',
                    'date' => $data['date'],
                    'description' => $data['description'] ?? null,
                ]);
                $entry->lines()->createMany($lines);
                app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'journal.posted', 'journal', $entry->id,
                    null, ['date' => $data['date'], 'description' => $data['description'] ?? null, 'lines' => $lines]);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $entry = $existing->first();
            if ($entry === null) {
                throw $exception;
            }
            $this->verifyReplay($entry, $hash);
        }

        return to_route('journals.index', $current_team);
    }

    private function draftSubmission(Request $request, Team $team, string $mode): ?RedirectResponse
    {
        $workflow = app(JournalDrafts::class);
        $canPost = $workflow->supervisor($request->user(), $team);
        $action = $request->input('action', $canPost ? 'post' : 'submit');
        $request->merge(['action' => $action]);
        $request->validate(['action' => ['required', 'in:post,draft,submit']]);
        if ($action === 'post') {
            abort_unless($canPost, 403);
            if (DB::table('journal_drafts')->where('team_id', $team->id)->where('request_id', $request->input('request_id'))->exists()) {
                throw ValidationException::withMessages(['request_id' => 'Lanjutkan posting melalui draf yang sudah tersimpan.']);
            }

            return null;
        }
        $id = $workflow->save($team, $request->user(), $mode, $request->all());

        return to_route('journals.draft-show', [$team, $id]);
    }

    private function assertNoDraft(Team $team, string $requestId): void
    {
        if (DB::table('journal_drafts')->where('team_id', $team->id)->where('request_id', $requestId)->exists()) {
            throw ValidationException::withMessages(['request_id' => 'Lanjutkan posting melalui draf yang tersimpan.']);
        }
    }

    private function verifyReplay(JournalEntry $entry, string $requestHash): void
    {
        if ($entry->request_hash !== $requestHash) {
            throw ValidationException::withMessages([
                'request_id' => 'Permintaan sebelumnya sudah diposting dengan data berbeda. Periksa riwayat sebelum memposting kembali.',
            ]);
        }
    }
}
