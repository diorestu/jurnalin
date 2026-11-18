<?php

namespace App\Actions\Trade;

use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\AccountType;
use App\Enums\TradeAccountRole;
use App\Enums\TradeDirection;
use App\Enums\TradeKind;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class PostTradeJournal
{
    /** @param list<array{account:int,side:string,amount:int}> $adjustments */
    public function record(stdClass $document, ?stdClass $source, int $userId, int $applied, ?int $approvedBy = null, array $adjustments = []): void
    {
        $direction = TradeDirection::from($document->direction);
        $kind = TradeKind::from($document->kind);
        $sales = $direction === TradeDirection::Sales;
        $teamId = (int) $document->team_id;
        $total = (int) $document->total;
        $account = fn (string $role) => $this->controlAccount($teamId, $role);
        $counterpartyAccount = fn () => $account($sales ? 'receivable' : 'payable');
        $selected = fn (?int $id) => $this->getAccount($teamId, $id);

        [$debit, $credit] = match ($kind) {
            TradeKind::Invoice => $sales
                ? [[$counterpartyAccount(), $total - $applied], [$account('sales'), $total]]
                : [[$selected($document->account_id), $total], [$counterpartyAccount(), $total]],
            TradeKind::Advance => [[$selected($document->account_id), $total], [$account('advance'), $total]],
            TradeKind::AdvanceRefund => [[$account('advance'), $total], [$selected($document->account_id), $total]],
            TradeKind::Payment => $sales
                ? [[$selected($document->account_id), $total], [$counterpartyAccount(), $total]]
                : [[$counterpartyAccount(), $total], [$selected($document->account_id), $total]],
            TradeKind::Return => $sales
                ? [[$account('sales'), $total], [$counterpartyAccount(), $total]]
                : [[$counterpartyAccount(), $total], [$this->getAccount($teamId, $source?->account_id, requireActive: false), $total]],
            TradeKind::Refund => $sales
                ? [[$counterpartyAccount(), $total], [$selected($document->account_id), $total]]
                : [[$selected($document->account_id), $total], [$counterpartyAccount(), $total]],
            default => [null, null],
        };

        if ($debit === null || $credit === null) {
            return;
        }

        $lines = [
            $this->line($debit[0], 'debit', $debit[1]),
            $this->line($credit[0], 'credit', $credit[1]),
        ];
        if ($applied > 0) {
            array_splice($lines, 1, 0, [$this->line($account('advance'), 'debit', $applied)]);
        }

        if ($adjustments) {
            foreach ($adjustments as $a) {
                $lines[] = $this->line($this->getAccount($teamId, $a['account']), $a['side'], $a['amount']);
            }
            $net = [];
            foreach ($lines as $line) {
                $id = $line['journal_account_id'];
                $net[$id] = ($net[$id] ?? 0) + ($line['side'] === 'debit' ? $line['amount'] : -$line['amount']);
            }
            $lines = [];
            foreach ($net as $id => $value) {
                if ($value !== 0) {
                    $lines[] = $this->line($this->getAccount($teamId, $id, false), $value > 0 ? 'debit' : 'credit', abs($value));
                }
            }
        }
        $entry = JournalEntry::create([
            'team_id' => $teamId,
            'posted_by' => $userId,
            'approved_by' => $approvedBy ?? $userId, 'approved_at' => now(),
            'request_id' => (string) Str::uuid(),
            'template_name' => $kind->label($direction),
            'date' => $document->date,
            'description' => $kind->number($direction, (int) $document->id).' · '.$document->partner,
        ]);
        $entry->lines()->createMany(array_values(array_filter($lines, fn (array $line) => $line['amount'] > 0)));

        DB::table('trade_documents')->where('id', $document->id)->update(['journal_entry_id' => $entry->id]);
        app(RecordAccountingAudit::class)->record($teamId, $approvedBy ?? $userId, 'journal.posted', 'journal', $entry->id, null,
            ['date' => $document->date, 'document_id' => $document->id, 'lines' => $lines]);
    }

    /** @return Collection<int, stdClass> */
    public function cashAccounts(int $teamId): Collection
    {
        return DB::table('journal_accounts')->where('team_id', $teamId)
            ->where('active', true)->where('is_cash', true)->where('type', AccountType::Asset->value)
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** @return Collection<int, stdClass> */
    public function purchaseAccounts(int $teamId): Collection
    {
        return DB::table('journal_accounts')->where('team_id', $teamId)
            ->where('active', true)->where('is_cash', false)->whereNull('trade_role')
            ->whereIn('type', [AccountType::Asset->value, AccountType::Expense->value])
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function inventoryAccountId(int $teamId): int
    {
        return (int) $this->controlAccount($teamId, 'inventory')->id;
    }

    public function controlAccount(int $teamId, string $role): stdClass
    {
        $existing = DB::table('journal_accounts')->where('team_id', $teamId)->where('trade_role', $role)->first();
        if ($existing !== null) {
            return $this->getAccount($teamId, (int) $existing->id);
        }

        [$baseCode, $name, $type] = TradeAccountRole::from($role)->definition();

        $code = $baseCode;
        for ($suffix = 1; DB::table('journal_accounts')->where('team_id', $teamId)->where('code', $code)->exists(); $suffix++) {
            $code = $baseCode.'-'.$suffix;
        }

        $id = DB::table('journal_accounts')->insertGetId([
            'team_id' => $teamId,
            'code' => $code,
            'name' => $name,
            'type' => $type->value,
            'trade_role' => $role,
        ]);

        return $this->getAccount($teamId, $id);
    }

    private function getAccount(int $teamId, ?int $id, bool $requireActive = true): stdClass
    {
        return DB::table('journal_accounts')->where('team_id', $teamId)->when($requireActive, fn ($query) => $query->where('active', true))->where('id', $id)->first()
            ?? throw ValidationException::withMessages(['account_id' => 'Akun COA untuk jurnal ini tidak aktif. Aktifkan kembali sebelum melanjutkan.']);
    }

    /** @return array{journal_account_id: int, account_code: string, account_name: string, side: string, amount: int} */
    private function line(stdClass $account, string $side, int $amount): array
    {
        return [
            'journal_account_id' => (int) $account->id,
            'account_code' => (string) $account->code,
            'account_name' => (string) $account->name,
            'side' => $side,
            'amount' => $amount,
        ];
    }
}
