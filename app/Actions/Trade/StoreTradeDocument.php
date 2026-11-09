<?php

namespace App\Actions\Trade;

use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Opening\AccountingStartDate;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class StoreTradeDocument
{
    public const MAX_AMOUNT = 999999999999999;

    public function __construct(
        private TradeBalances $balances,
        private PostTradeJournal $journal,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Team $team, int $userId, TradeDirection $direction, array $data, ?int $approvedDraftId = null, ?int $approvedBy = null): int
    {
        $kind = TradeKind::from($data['kind']);
        $hash = hash('sha256', json_encode([$direction->value, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($team, $userId, $direction, $kind, $data, $hash, $approvedDraftId, $approvedBy) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            $existing = DB::table('trade_documents')->where('team_id', $team->id)
                ->where('request_id', $data['request_id'])->first();
            if ($existing !== null) {
                if ($existing->request_hash !== $hash) {
                    throw ValidationException::withMessages([
                        'request_id' => 'Permintaan ini sudah tersimpan dengan data berbeda. Periksa daftar dokumen.',
                    ]);
                }

                return (int) $existing->id;
            }

            $draft = DB::table('journal_drafts')->where('team_id', $team->id)->where('request_id', $data['request_id'])->first();
            if ($draft !== null && ($approvedDraftId !== (int) $draft->id || $draft->status !== 'pending')) {
                throw ValidationException::withMessages(['request_id' => 'Transaksi ini harus diposting melalui persetujuan draf.']);
            }

            app(AccountingStartDate::class)->assertAfter($team->id, $data['date']);

            $source = $this->source($team->id, $direction, $kind, $data['source_id'] ?? null);
            $orderId = $source?->kind === TradeKind::Order->value ? $source->id : $source?->order_id;
            $this->validateDate($kind, $data['date'], $source, $orderId);

            $partner = $source->partner ?? ($data['partner'] ?? null);
            if ($partner === null) {
                throw ValidationException::withMessages([
                    'partner' => $direction === TradeDirection::Sales ? 'Masukkan nama pelanggan.' : 'Masukkan nama pemasok.',
                ]);
            }

            $lines = [];
            $accountId = null;
            if ($kind->isCash()) {
                $total = (int) ($data['amount'] ?? 0);
                if ($source === null || $total <= 0 || $total > $this->balances->limit($source, $kind)) {
                    throw ValidationException::withMessages(['amount' => 'Nominal harus positif dan tidak melebihi saldo yang tersedia.']);
                }

                $accountId = (int) ($data['account_id'] ?? 0);
                if (! $this->journal->cashAccounts($team->id)->contains('id', $accountId)) {
                    throw ValidationException::withMessages(['account_id' => 'Pilih akun kas atau bank yang aktif.']);
                }
            } else {
                [$lines, $total] = $this->items($kind, $source, $data['lines'] ?? []);
                if ($kind === TradeKind::Invoice && $direction === TradeDirection::Purchases) {
                    $accountId = (int) ($data['account_id'] ?? 0);
                    if ($accountId === 0) {
                        $accountId = $this->journal->inventoryAccountId($team->id);
                    } elseif (! $this->journal->purchaseAccounts($team->id)->contains('id', $accountId)) {
                        throw ValidationException::withMessages(['account_id' => 'Pilih akun aset atau beban pembelian yang aktif.']);
                    }
                }
            }

            $document = [
                'team_id' => $team->id,
                'direction' => $direction->value,
                'kind' => $kind->value,
                'source_id' => $source?->id,
                'order_id' => $orderId,
                'account_id' => $accountId,
                'created_by' => $userId,
                'request_id' => $data['request_id'],
                'request_hash' => $hash,
                'partner' => $partner,
                'date' => $data['date'],
                'due_date' => $kind === TradeKind::Invoice ? ($data['due_date'] ?? null) : null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'notes_delta' => $data['notes_delta'] ?? null,
                'total' => $total,
                'created_at' => now(),
            ];
            $id = DB::table('trade_documents')->insertGetId($document);
            if ($lines !== []) {
                DB::table('trade_document_lines')->insert(array_map(
                    fn (array $line) => ['document_id' => $id, ...$line], $lines,
                ));
            }

            $applied = $direction === TradeDirection::Sales && $kind === TradeKind::Invoice && $orderId !== null
                ? $this->applyAdvances((int) $orderId, $id, $total)
                : 0;
            $this->journal->record((object) ['id' => $id, ...$document], $source, $userId, $applied, $approvedBy ?? $userId);

            app(RecordAccountingAudit::class)->record($team->id, $approvedBy ?? $userId, 'trade.created', 'trade_document', $id, null,
                ['direction' => $direction->value, 'kind' => $kind->value, 'date' => $data['date'], 'partner' => $partner, 'total' => $total, 'source_id' => $source->id ?? null, 'lines' => $lines]);

            return $id;
        }, attempts: 5);
    }

    private function source(int $teamId, TradeDirection $direction, TradeKind $kind, mixed $id): ?stdClass
    {
        if ($id === null && in_array($kind, TradeKind::standalone($direction), true)) {
            return null;
        }

        $source = $id === null ? null : DB::table('trade_documents')
            ->where('team_id', $teamId)->where('direction', $direction->value)->where('id', $id)->first();
        if ($source === null || ! in_array($kind, TradeKind::from($source->kind)->next($direction), true)) {
            throw ValidationException::withMessages(['source_id' => 'Dokumen sumber tidak tersedia untuk transaksi ini.']);
        }

        return $source;
    }

    public function validateDate(TradeKind $kind, string $date, ?stdClass $source, mixed $orderId): void
    {
        if ($source === null) {
            return;
        }

        $last = $source->date;
        if (in_array($kind, [TradeKind::Payment, TradeKind::Return, TradeKind::Refund], true)) {
            $last = max($last, DB::table('trade_documents')->where('source_id', $source->id)
                ->whereIn('kind', [TradeKind::Payment->value, TradeKind::Return->value, TradeKind::Refund->value])->max('date') ?? $last);
        } elseif ($orderId !== null && in_array($kind, [TradeKind::Advance, TradeKind::Invoice, TradeKind::AdvanceRefund], true)) {
            $last = max($last, DB::table('trade_documents')->where('order_id', $orderId)
                ->whereIn('kind', [TradeKind::Advance->value, TradeKind::AdvanceRefund->value, TradeKind::Invoice->value])->max('date') ?? $last);
        }

        if ($date < $last) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak boleh lebih awal dari dokumen terkait terakhir ('.$last.').']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $inputs
     * @return array{list<array{name: string, quantity: int, unit_price: int, total: int, source_line_id: int|null}>, int}
     */
    public function items(TradeKind $kind, ?stdClass $source, array $inputs): array
    {
        if ($inputs === []) {
            throw ValidationException::withMessages(['lines' => 'Tambahkan minimal satu rincian barang atau jasa.']);
        }

        $available = $source === null ? collect() : $this->balances->sourceLines((int) $source->id, $kind)->keyBy('id');
        $lines = [];
        $total = 0;
        $selected = [];
        foreach ($inputs as $input) {
            $sourceLineId = null;
            $quantity = (int) $input['quantity'];
            if ($source === null) {
                $name = (string) ($input['name'] ?? '');
                $price = (int) ($input['unit_price'] ?? 0);
                if ($name === '' || $price <= 0) {
                    throw ValidationException::withMessages(['lines' => 'Isi nama dan harga satuan untuk setiap baris.']);
                }
            } else {
                $sourceLineId = (int) ($input['source_line_id'] ?? 0);
                $line = $available->get($sourceLineId);
                if ($line === null || isset($selected[$sourceLineId]) || $quantity > $line->remaining) {
                    throw ValidationException::withMessages(['lines' => 'Jumlah atau rincian melebihi sisa dokumen sumber.']);
                }

                $selected[$sourceLineId] = true;
                $name = (string) $line->name;
                $price = (int) $line->unit_price;
            }

            $subtotal = $quantity * $price;
            $total += $subtotal;
            if ($total > self::MAX_AMOUNT) {
                throw ValidationException::withMessages(['lines' => 'Total dokumen melebihi batas nominal rupiah.']);
            }

            $lines[] = [
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => $subtotal,
                'source_line_id' => $sourceLineId,
            ];
        }

        return [$lines, $total];
    }

    private function applyAdvances(int $orderId, int $invoiceId, int $total): int
    {
        $remaining = $total;
        foreach ($this->balances->advances($orderId) as $advance) {
            $amount = min($remaining, $this->balances->unused($advance));
            if ($amount <= 0) {
                continue;
            }

            DB::table('trade_advance_allocations')->insert([
                'advance_id' => $advance->id,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
            ]);
            $remaining -= $amount;
            if ($remaining === 0) {
                break;
            }
        }

        return $total - $remaining;
    }
}
