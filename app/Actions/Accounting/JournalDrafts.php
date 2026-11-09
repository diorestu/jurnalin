<?php

namespace App\Actions\Accounting;

use App\Actions\Journal\JournalTemplates;
use App\Actions\Opening\AccountingStartDate;
use App\Actions\Trade\PostTradeJournal;
use App\Actions\Trade\StoreTradeDocument;
use App\Actions\Trade\TradeBalances;
use App\Enums\TeamRole;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\JournalEntry;
use App\Models\Team;
use App\Models\User;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class JournalDrafts
{
    public function __construct(
        private RecordAccountingAudit $audit,
        private AccountingStartDate $dates,
    ) {}

    public function supervisor(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /** @param array<string, mixed> $input */
    public function save(Team $team, User $user, string $mode, array $input): int
    {
        $base = Validator::make($input, [
            'request_id' => ['required', 'uuid'],
            'action' => ['required', 'in:draft,submit'],
        ])->validate();

        return DB::transaction(function () use ($team, $user, $mode, $input, $base) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $intent = [];
            foreach (['action', 'date', 'description', 'template_id', 'signature', 'amount', 'lines'] as $field) {
                if (array_key_exists($field, $input)) {
                    $intent[$field] = $input[$field];
                }
            }
            $hash = hash('sha256', json_encode([$mode, $intent], JSON_THROW_ON_ERROR));
            if ($existing = DB::table('journal_drafts')->where('team_id', $team->id)->where('request_id', $base['request_id'])->first()) {
                if ($existing->created_by !== $user->id || ! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['request_id' => 'Permintaan draf ini sudah dipakai dengan data berbeda.']);
                }

                return (int) $existing->id;
            }
            $payload = $this->journalPayload($team, $mode, $input);
            if (JournalEntry::where('team_id', $team->id)->where('request_id', $base['request_id'])->exists()) {
                throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah menjadi jurnal terposting. Muat ulang halaman.']);
            }
            if ($base['action'] === 'submit') {
                $this->completeJournal($team, $payload);
                $this->dates->assertAfter($team->id, $payload['date']);
            }

            return $this->create($team, $user, $mode, $base['request_id'], $hash, $payload, $base['action'] === 'submit');
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function saveTrade(Team $team, User $user, TradeDirection $direction, array $data, string $action, ?int $draftId = null, ?int $version = null): int
    {
        return DB::transaction(function () use ($team, $user, $direction, $data, $action, $draftId, $version) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode([$direction->value, $action, $data], JSON_THROW_ON_ERROR));
            if ($draftId === null) {
                $existing = DB::table('journal_drafts')->where('team_id', $team->id)->where('request_id', $data['request_id'])->first();
                if ($existing !== null) {
                    if ($existing->created_by !== $user->id || ! hash_equals($existing->request_hash, $hash)) {
                        throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah disimpan dengan data berbeda.']);
                    }

                    return (int) $existing->id;
                }
            }
            $preview = $this->inspectTrade($team, $direction, $data);
            if ($action === 'submit') {
                $this->dates->assertAfter($team->id, $data['date']);
            }
            $payload = ['direction' => $direction->value, 'data' => $data, 'date' => $data['date'],
                'description' => $preview['label'].' · '.$preview['partner']];

            if ($draftId !== null) {
                $draft = $this->find($team, $draftId);
                $this->assertEdit($team, $user, $draft);
                abort_unless($draft->mode === 'trade', 404);
                $previousPayload = $this->payload($draft);
                abort_unless($previousPayload['direction'] === $direction->value, 404);
                if ($previousPayload['data']['kind'] !== $data['kind'] || $draft->request_id !== $data['request_id']) {
                    throw ValidationException::withMessages(['kind' => 'Jenis dan identitas dokumen draf tidak dapat diubah.']);
                }
                $this->version($draft, $version);
                DB::table('journal_drafts')->where('id', $draftId)->update([
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'date' => $payload['date'], 'description' => $payload['description'],
                    'status' => $action === 'submit' ? 'pending' : 'draft', 'version' => $draft->version + 1,
                    'submitted_at' => $action === 'submit' ? now() : null, 'updated_at' => now(),
                ]);
                $this->audit->record($team->id, $user->id, $action === 'submit' ? 'draft.submitted' : 'draft.updated', 'journal_draft', $draftId, $this->snapshot($draft), $payload);

                return $draftId;
            }
            if (DB::table('trade_documents')->where('team_id', $team->id)->where('request_id', $data['request_id'])->exists()) {
                throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah menjadi dokumen transaksi. Muat ulang halaman.']);
            }

            return $this->create($team, $user, 'trade', $data['request_id'], $hash, $payload, $action === 'submit');
        }, attempts: 5);
    }

    /** @param array<string, mixed> $input */
    public function update(Team $team, User $user, int $id, int $version, array $input): void
    {
        DB::transaction(function () use ($team, $user, $id, $version, $input) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $draft = $this->find($team, $id);
            $this->assertEdit($team, $user, $draft);
            abort_if($draft->mode === 'trade', 422, 'Ubah dokumen melalui formulir transaksi.');
            $this->version($draft, $version);
            $payload = $this->journalPayload($team, 'manual', $input);
            DB::table('journal_drafts')->where('id', $id)->update([
                'mode' => 'manual', 'date' => $payload['date'], 'description' => $payload['description'],
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'version' => $version + 1, 'updated_at' => now(),
            ]);
            $this->audit->record($team->id, $user->id, 'draft.updated', 'journal_draft', $id, $this->snapshot($draft), $payload);
        }, attempts: 5);
    }

    public function submit(Team $team, User $user, int $id, int $version): void
    {
        DB::transaction(function () use ($team, $user, $id, $version) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $draft = $this->find($team, $id);
            abort_unless($draft->created_by === $user->id || $this->supervisor($user, $team), 403);
            if ($draft->status === 'pending') {
                return;
            }
            $this->assertEdit($team, $user, $draft);
            $this->version($draft, $version);
            $payload = $this->payload($draft);
            if ($draft->mode === 'trade') {
                $this->inspectTrade($team, TradeDirection::from($payload['direction']), $payload['data']);
            } else {
                $this->completeJournal($team, $payload);
            }
            $this->dates->assertAfter($team->id, $draft->date);
            DB::table('journal_drafts')->where('id', $id)->update([
                'status' => 'pending', 'submitted_at' => now(), 'version' => $version + 1, 'updated_at' => now(),
            ]);
            $this->audit->record($team->id, $user->id, 'draft.submitted', 'journal_draft', $id, $this->snapshot($draft), ['status' => 'pending']);
        }, attempts: 5);
    }

    public function approve(Team $team, User $user, int $id, int $version): ?int
    {
        abort_unless($this->supervisor($user, $team), 403);

        return DB::transaction(function () use ($team, $user, $id, $version) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $draft = $this->find($team, $id);
            if ($draft->status === 'posted') {
                return $draft->journal_entry_id === null ? null : (int) $draft->journal_entry_id;
            }
            $this->version($draft, $version);
            if ($draft->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Ajukan draf sebelum menyetujuinya.']);
            }
            $this->dates->assertAfter($team->id, $draft->date);
            $payload = $this->payload($draft);
            if ($draft->mode === 'trade') {
                $direction = TradeDirection::from($payload['direction']);
                $documentId = app(StoreTradeDocument::class)->handle($team, $draft->created_by ?? $user->id, $direction, $payload['data'], $id, $user->id);
                $journalId = DB::table('trade_documents')->where('id', $documentId)->value('journal_entry_id');
                if ($journalId === null) {
                    throw ValidationException::withMessages(['status' => 'Dokumen ini tidak menghasilkan jurnal keuangan.']);
                }
                DB::table('journal_entries')->where('id', $journalId)->update(['approved_by' => $user->id, 'approved_at' => now()]);
            } else {
                $lines = $this->completeJournal($team, $payload);
                $entry = JournalEntry::create([
                    'team_id' => $team->id, 'posted_by' => $draft->created_by, 'approved_by' => $user->id, 'approved_at' => now(),
                    'request_id' => (string) Str::uuid(), 'journal_template_id' => $payload['template_id'],
                    'template_name' => $payload['template_name'], 'date' => $draft->date, 'description' => $draft->description,
                ]);
                $entry->lines()->createMany($lines);
                $journalId = $entry->id;
                $this->audit->record($team->id, $user->id, 'journal.posted', 'journal', $journalId, null, ['draft_id' => $id, 'date' => $draft->date, 'lines' => $lines]);
            }
            DB::table('journal_drafts')->where('id', $id)->update([
                'status' => 'posted', 'approved_by' => $user->id, 'approved_at' => now(),
                'journal_entry_id' => $journalId, 'version' => $version + 1, 'updated_at' => now(),
            ]);
            $this->audit->record($team->id, $user->id, 'draft.approved', 'journal_draft', $id, $this->snapshot($draft), ['status' => 'posted', 'journal_id' => $journalId]);

            return (int) $journalId;
        }, attempts: 5);
    }

    public function returnForRevision(Team $team, User $user, int $id, int $version, string $reason): void
    {
        abort_unless($this->supervisor($user, $team), 403);
        DB::transaction(function () use ($team, $user, $id, $version, $reason) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $draft = $this->find($team, $id);
            $this->version($draft, $version);
            if ($draft->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Hanya draf yang menunggu persetujuan dapat dikembalikan.']);
            }
            DB::table('journal_drafts')->where('id', $id)->update([
                'status' => 'draft', 'review_reason' => $reason, 'version' => $version + 1, 'updated_at' => now(),
            ]);
            $this->audit->record($team->id, $user->id, 'draft.returned', 'journal_draft', $id, $this->snapshot($draft), ['status' => 'draft'], $reason);
        }, attempts: 5);
    }

    public function find(Team $team, int $id): stdClass
    {
        $draft = DB::table('journal_drafts')->where('team_id', $team->id)->where('id', $id)->first();
        abort_if($draft === null, 404);

        return $draft;
    }

    public function assertEdit(Team $team, User $user, stdClass $draft): void
    {
        abort_unless($draft->created_by === $user->id || $this->supervisor($user, $team), 403);
        if ($draft->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Draf ini sedang ditinjau atau sudah diposting.']);
        }
    }

    /** @return array<string, mixed> */
    public function payload(stdClass $draft): array
    {
        return json_decode($draft->payload, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function journalPayload(Team $team, string $mode, array $input): array
    {
        $rules = [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-31'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
        if ($mode === 'template') {
            $data = Validator::make($input, [...$rules,
                'template_id' => ['required', 'integer', 'min:1'], 'signature' => ['required', 'string', 'size:64'],
                'amount' => ['required', 'integer', 'between:1,999999999999999'],
            ])->validate();
            $template = app(JournalTemplates::class)->forTeam($team->id, (int) $data['template_id'])->first();
            if ($template === null || ! hash_equals($template['signature'], $data['signature'])) {
                throw ValidationException::withMessages(['template_id' => 'Pilih ulang template jurnal yang aktif.']);
            }
            $lines = array_map(fn ($line) => [
                'account_id' => $line['journal_account_id'],
                'debit' => $line['side'] === 'debit' ? (int) $data['amount'] : 0,
                'credit' => $line['side'] === 'credit' ? (int) $data['amount'] : 0,
            ], $template['lines']);

            return ['date' => $data['date'], 'description' => $data['description'] ?? null,
                'template_id' => $template['id'], 'template_name' => $template['name'], 'lines' => $lines];
        }
        $data = Validator::make($input, [...$rules,
            'lines' => ['required', 'array', 'list', 'min:2', 'max:50'],
            'lines.*' => ['array:account_id,debit,credit'],
            'lines.*.account_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.debit' => ['nullable', 'integer', 'between:0,999999999999999'],
            'lines.*.credit' => ['nullable', 'integer', 'between:0,999999999999999'],
        ])->validate();
        $lines = array_map(fn (array $line) => [
            'account_id' => isset($line['account_id']) ? (int) $line['account_id'] : null,
            'debit' => (int) ($line['debit'] ?? 0), 'credit' => (int) ($line['credit'] ?? 0),
        ], $data['lines']);
        $ids = array_filter(array_column($lines, 'account_id'));
        $validIds = DB::table('journal_accounts')->where('team_id', $team->id)->whereIn('id', $ids)->pluck('id');
        foreach ($lines as $i => $line) {
            if ($line['account_id'] !== null && ! $validIds->contains($line['account_id'])) {
                throw ValidationException::withMessages(['lines.'.$i.'.account_id' => 'Pilih akun perusahaan ini.']);
            }
        }

        return ['date' => $data['date'], 'description' => $data['description'] ?? null, 'template_id' => null, 'template_name' => 'Jurnal manual', 'lines' => $lines];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function completeJournal(Team $team, array $payload): array
    {
        $accounts = DB::table('journal_accounts')->where('team_id', $team->id)->where('active', true)
            ->whereIn('id', array_filter(array_column($payload['lines'], 'account_id')))->get()->keyBy('id');
        $debit = BigInteger::zero();
        $credit = BigInteger::zero();
        $lines = [];
        foreach ($payload['lines'] as $i => $line) {
            $account = $accounts->get($line['account_id']);
            if ($account === null) {
                throw ValidationException::withMessages(['lines.'.$i.'.account_id' => 'Pilih akun COA aktif.']);
            }
            if (($line['debit'] > 0) === ($line['credit'] > 0)) {
                throw ValidationException::withMessages(['lines.'.$i.'.debit' => 'Isi tepat satu sisi debit atau kredit per baris.']);
            }
            $debit = $debit->plus($line['debit']);
            $credit = $credit->plus($line['credit']);
            $lines[] = ['journal_account_id' => $account->id, 'account_code' => $account->code, 'account_name' => $account->name,
                'side' => $line['debit'] > 0 ? 'debit' : 'credit', 'amount' => max($line['debit'], $line['credit'])];
        }
        if ($debit->isZero() || ! $debit->isEqualTo($credit) || $debit->isGreaterThan('999999999999999')) {
            throw ValidationException::withMessages(['lines' => 'Total debit dan kredit harus seimbang, positif, dan tidak melebihi IDR 999.999.999.999.999.']);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function inspectTrade(Team $team, TradeDirection $direction, array $data): array
    {
        $kind = TradeKind::from($data['kind']);
        if (! $kind->isCash() && ! in_array($kind, [TradeKind::Invoice, TradeKind::Return], true)) {
            throw ValidationException::withMessages(['kind' => 'Dokumen ini tidak memerlukan persetujuan keuangan.']);
        }
        $source = isset($data['source_id']) ? DB::table('trade_documents')->where('team_id', $team->id)
            ->where('direction', $direction->value)->where('id', $data['source_id'])->first() : null;
        if (isset($data['source_id']) && ($source === null || ! in_array($kind, TradeKind::from($source->kind)->next($direction), true))) {
            throw ValidationException::withMessages(['source_id' => 'Dokumen sumber tidak tersedia untuk transaksi ini.']);
        }
        if ($source === null && ! in_array($kind, TradeKind::standalone($direction), true)) {
            throw ValidationException::withMessages(['source_id' => 'Pilih dokumen sumber.']);
        }
        app(StoreTradeDocument::class)->validateDate($kind, $data['date'], $source, $source?->kind === TradeKind::Order->value ? $source->id : ($source->order_id ?? null));
        $posting = app(PostTradeJournal::class);
        if ($kind->isCash()) {
            $amount = (int) ($data['amount'] ?? 0);
            if ($source === null || $amount <= 0 || $amount > app(TradeBalances::class)->limit($source, $kind)) {
                throw ValidationException::withMessages(['amount' => 'Nominal melebihi saldo yang tersedia.']);
            }
            if (! $posting->cashAccounts($team->id)->contains('id', (int) ($data['account_id'] ?? 0))) {
                throw ValidationException::withMessages(['account_id' => 'Pilih akun kas atau bank aktif.']);
            }
            $lines = [];
        } else {
            [$lines, $amount] = app(StoreTradeDocument::class)->items($kind, $source, $data['lines'] ?? []);
            if ($direction === TradeDirection::Purchases && $kind === TradeKind::Invoice && (int) ($data['account_id'] ?? 0) !== 0
                && ! $posting->purchaseAccounts($team->id)->contains('id', (int) $data['account_id'])) {
                throw ValidationException::withMessages(['account_id' => 'Pilih akun pembelian aktif.']);
            }
        }
        $partner = $source->partner ?? $data['partner'] ?? null;
        if (! is_string($partner) || trim($partner) === '') {
            throw ValidationException::withMessages(['partner' => 'Isi pelanggan atau pemasok.']);
        }

        return ['label' => $kind->label($direction), 'partner' => $partner, 'amount' => $amount, 'lines' => $lines];
    }

    /** @param array<string, mixed> $payload */
    private function create(Team $team, User $user, string $mode, string $requestId, string $hash, array $payload, bool $submit): int
    {
        $id = DB::table('journal_drafts')->insertGetId([
            'team_id' => $team->id, 'mode' => $mode, 'status' => $submit ? 'pending' : 'draft',
            'date' => $payload['date'], 'description' => $payload['description'], 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_by' => $user->id, 'request_id' => $requestId, 'request_hash' => $hash,
            'submitted_at' => $submit ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record($team->id, $user->id, $submit ? 'draft.submitted' : 'draft.created', 'journal_draft', $id, null, ['status' => $submit ? 'pending' : 'draft', 'payload' => $payload]);

        return $id;
    }

    private function version(stdClass $draft, ?int $version): void
    {
        if ($version !== (int) $draft->version) {
            throw ValidationException::withMessages(['version' => 'Draf berubah. Muat ulang sebelum melanjutkan.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(stdClass $draft): array
    {
        return ['status' => $draft->status, 'version' => $draft->version, 'payload' => $this->payload($draft)];
    }
}
