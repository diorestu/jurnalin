<?php

namespace App\Actions\Opening;

use App\Actions\Accounting\PeriodLocks;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Trade\PostTradeJournal;
use App\Actions\Trade\TradePartners;
use App\Enums\AccountType;
use App\Enums\TradeDirection;
use App\Models\JournalEntry;
use App\Models\Team;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class StoreOpeningBalances
{
    public const MAX_AMOUNT = 999999999999999;

    public function __construct(private PostTradeJournal $journal) {}

    /** @param array<string, mixed> $data */
    public function handle(Team $team, int $userId, array $data): int
    {
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($team, $userId, $data, $hash) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('opening_balances')->where('team_id', $team->id)->first();
            if ($existing !== null) {
                if ($existing->request_id === $data['request_id'] && hash_equals($existing->request_hash, $hash)) {
                    return (int) $existing->id;
                }
                throw ValidationException::withMessages(['date' => 'Saldo awal perusahaan sudah diposting. Buka paket saldo awal yang ada.']);
            }

            app(PeriodLocks::class)->assertOpen($team->id, $data['date']);
            $firstDate = DB::table('journal_entries')->where('team_id', $team->id)->min('date');
            if ($firstDate !== null && $data['date'] >= $firstDate) {
                throw ValidationException::withMessages(['date' => 'Tanggal saldo awal harus sebelum jurnal terposting pertama ('.$firstDate.').']);
            }

            $accounts = DB::table('journal_accounts')->where('team_id', $team->id)
                ->where('active', true)->whereIn('id', array_column($data['lines'] ?? [], 'account_id'))
                ->get()->keyBy('id');
            $lines = [];
            $debitTotal = BigInteger::zero();
            $creditTotal = BigInteger::zero();
            foreach ($data['lines'] ?? [] as $index => $line) {
                $account = $accounts->get($line['account_id']);
                if ($account === null || ! in_array($account->type, [AccountType::Asset->value, AccountType::Liability->value, AccountType::Equity->value], true)
                    || in_array($account->trade_role, ['receivable', 'payable', 'inventory'], true)) {
                    throw ValidationException::withMessages(['lines.'.$index.'.account_id' => 'Pilih akun neraca aktif. Piutang, utang, dan persediaan kontrol diisi melalui rincian saldo awal.']);
                }
                $debit = (int) ($line['debit'] ?? 0);
                $credit = (int) ($line['credit'] ?? 0);
                if (($debit > 0) === ($credit > 0)) {
                    throw ValidationException::withMessages(['lines.'.$index.'.debit' => 'Isi tepat satu sisi debit atau kredit pada setiap baris.']);
                }
                $debitTotal = $debitTotal->plus($debit);
                $creditTotal = $creditTotal->plus($credit);
                $lines[] = $this->line($account, $debit > 0 ? 'debit' : 'credit', max($debit, $credit));
            }

            foreach (['receivables' => ['receivable', 'debit'], 'payables' => ['payable', 'credit']] as $category => [$role, $side]) {
                $this->validateInvoices($data[$category] ?? [], $category, $data['date']);
                $sum = BigInteger::zero();
                foreach ($data[$category] ?? [] as $invoice) {
                    $sum = $sum->plus($invoice['amount']);
                }
                if ($sum->isGreaterThan(self::MAX_AMOUNT)) {
                    throw ValidationException::withMessages([$category => 'Total rincian melebihi batas nominal.']);
                }
                if (! $sum->isZero()) {
                    $lines[] = $this->line($this->journal->controlAccount($team->id, $role), $side, $sum->toInt());
                    if ($side === 'debit') {
                        $debitTotal = $debitTotal->plus($sum);
                    } else {
                        $creditTotal = $creditTotal->plus($sum);
                    }
                }
            }

            $stockTotal = BigInteger::zero();
            $stock = [];
            $skus = [];
            foreach ($data['inventory'] ?? [] as $index => $item) {
                $sku = mb_strtolower($item['sku']);
                if (isset($skus[$sku])) {
                    throw ValidationException::withMessages(['inventory.'.$index.'.sku' => 'Kode barang tidak boleh berulang dalam saldo awal.']);
                }
                $skus[$sku] = true;
                $total = BigInteger::of($item['quantity'])->multipliedBy($item['unit_cost']);
                if ($total->isGreaterThan(self::MAX_AMOUNT)) {
                    throw ValidationException::withMessages(['inventory.'.$index.'.unit_cost' => 'Nilai barang melebihi batas nominal.']);
                }
                $stockTotal = $stockTotal->plus($total);
                $stock[] = [...$item, 'total' => $total->toInt()];
            }
            if (! $stockTotal->isZero()) {
                if ($stockTotal->isGreaterThan(self::MAX_AMOUNT)) {
                    throw ValidationException::withMessages(['inventory' => 'Total persediaan melebihi batas nominal.']);
                }
                $lines[] = $this->line($this->journal->controlAccount($team->id, 'inventory'), 'debit', $stockTotal->toInt());
                $debitTotal = $debitTotal->plus($stockTotal);
            }

            if ($debitTotal->isZero() || ! $debitTotal->isEqualTo($creditTotal) || $debitTotal->isGreaterThan(self::MAX_AMOUNT)) {
                throw ValidationException::withMessages(['lines' => 'Total debit dan kredit saldo awal harus seimbang, positif, dan maksimal IDR 999.999.999.999.999.']);
            }

            $entry = JournalEntry::create([
                'team_id' => $team->id, 'posted_by' => $userId, 'approved_by' => $userId, 'approved_at' => now(), 'request_id' => (string) Str::uuid(),
                'template_name' => 'Saldo awal', 'date' => $data['date'],
                'description' => $data['description'] ?? 'Saldo awal perusahaan',
            ]);
            $entry->lines()->createMany($lines);
            $id = DB::table('opening_balances')->insertGetId([
                'team_id' => $team->id, 'date' => $data['date'], 'description' => $data['description'] ?? null,
                'request_id' => $data['request_id'], 'request_hash' => $hash,
                'journal_entry_id' => $entry->id, 'created_by' => $userId, 'created_at' => now(),
            ]);

            foreach (['receivables' => TradeDirection::Sales, 'payables' => TradeDirection::Purchases] as $category => $direction) {
                foreach ($data[$category] ?? [] as $invoice) {
                    DB::table('trade_documents')->insert([
                        'team_id' => $team->id, 'opening_balance_id' => $id, 'created_by' => $userId,
                        'direction' => $direction->value, 'kind' => 'invoice',
                        'request_id' => (string) Str::uuid(), 'request_hash' => $hash,
                        'partner' => $invoice['partner'], 'reference' => $invoice['reference'],
                        'partner_id' => TradePartners::resolve($team->id, $direction->value, $invoice['partner'])->id,
                        'date' => $invoice['invoice_date'], 'due_date' => $invoice['due_date'] ?? null,
                        'notes' => 'Saldo tagihan saat migrasi per '.$data['date'], 'total' => $invoice['amount'],
                        'created_at' => now(),
                    ]);
                }
            }
            foreach ($stock as $item) {
                DB::table('opening_inventory')->insert(['opening_balance_id' => $id, ...$item]);
                $sku = strtoupper($item['sku']);
                $product = DB::table('catalog_items')->where('team_id', $team->id)->where('sku', $sku)->first();
                if ($product && (! $product->stock || DB::table('stock_movements')->where('item_id', $product->id)->exists())) {
                    throw ValidationException::withMessages(['inventory' => "SKU $sku sudah memiliki mutasi atau merupakan jasa."]);
                }
                $productId = $product->id ?? DB::table('catalog_items')->insertGetId(['team_id' => $team->id, 'sku' => $sku, 'name' => $item['name'], 'unit' => 'unit', 'stock' => true, 'buy_price' => $item['unit_cost'], 'sell_price' => 0]);
                DB::table('stock_movements')->insert(['item_id' => $productId, 'date' => $data['date'], 'reason' => 'Saldo awal', 'quantity' => $item['quantity'], 'value' => $item['total']]);
            }

            app(RecordAccountingAudit::class)->record($team->id, $userId, 'opening.posted', 'opening_balance', $id, null,
                ['date' => $data['date'], 'journal_id' => $entry->id, 'lines' => $lines, 'receivables' => $data['receivables'] ?? [], 'payables' => $data['payables'] ?? [], 'inventory' => $stock]);

            return $id;
        }, attempts: 5);
    }

    /** @param list<array<string, mixed>> $invoices */
    private function validateInvoices(array $invoices, string $category, string $date): void
    {
        $references = [];
        foreach ($invoices as $index => $invoice) {
            $key = mb_strtolower($invoice['partner']).'|'.mb_strtolower($invoice['reference']);
            if (isset($references[$key])) {
                throw ValidationException::withMessages([$category.'.'.$index.'.reference' => 'Nomor tagihan untuk pihak yang sama tidak boleh berulang.']);
            }
            $references[$key] = true;
            if ($invoice['invoice_date'] > $date || (isset($invoice['due_date']) && $invoice['due_date'] < $invoice['invoice_date'])) {
                throw ValidationException::withMessages([$category.'.'.$index.'.invoice_date' => 'Tanggal faktur maksimal tanggal saldo awal, dan jatuh tempo harus setelah atau pada tanggal faktur.']);
            }
        }
    }

    /** @return array{journal_account_id: int, account_code: string, account_name: string, side: string, amount: int} */
    private function line(stdClass $account, string $side, int $amount): array
    {
        return [
            'journal_account_id' => (int) $account->id, 'account_code' => $account->code,
            'account_name' => $account->name, 'side' => $side, 'amount' => $amount,
        ];
    }
}
