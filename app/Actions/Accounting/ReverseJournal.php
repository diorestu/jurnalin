<?php

namespace App\Actions\Accounting;

use App\Actions\Opening\AccountingStartDate;
use App\Models\JournalEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseJournal
{
    public function __construct(private JournalDrafts $workflow, private RecordAccountingAudit $audit, private AccountingStartDate $dates) {}

    /** @param array{request_id:string,date:string,reason:string} $data */
    public function handle(Team $team, User $user, int $journal, array $data): int
    {
        abort_unless($this->workflow->supervisor($user, $team), 403);

        return DB::transaction(function () use ($team, $user, $journal, $data) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $entry = JournalEntry::where('team_id', $team->id)->whereKey($journal)->first();
            abort_if($entry === null, 404);
            $existing = JournalEntry::where('reversal_of_id', $journal)->first();
            if ($existing !== null) {
                if ($existing->request_id === $data['request_id'] && $existing->date === $data['date'] && $existing->reversal_reason === $data['reason']) {
                    return $existing->id;
                }
                throw ValidationException::withMessages(['journal' => 'Jurnal ini sudah memiliki jurnal pembalik.']);
            }
            if ($entry->reversal_of_id !== null || DB::table('trade_documents')->where('journal_entry_id', $journal)->exists()
                || DB::table('asset_depreciations')->where('journal_entry_id', $journal)->exists()
                || DB::table('opening_balances')->where('journal_entry_id', $journal)->exists()) {
                throw ValidationException::withMessages(['journal' => 'Koreksi jurnal ini melalui dokumen sumber atau jurnal penyesuaian.']);
            }
            if ($data['date'] < $entry->date) {
                throw ValidationException::withMessages(['date' => 'Tanggal pembalikan tidak boleh sebelum jurnal asal.']);
            }
            $this->dates->assertAfter($team->id, $data['date']);
            $sourceLines = $entry->lines()->get();
            if ($sourceLines->isEmpty()) {
                throw ValidationException::withMessages(['journal' => 'Jurnal asal tidak memiliki rincian.']);
            }
            $reversal = JournalEntry::create([
                'team_id' => $team->id, 'posted_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
                'request_id' => $data['request_id'], 'reversal_of_id' => $journal, 'reversal_reason' => $data['reason'],
                'template_name' => 'Jurnal pembalik', 'date' => $data['date'], 'description' => 'Pembalikan JRN-'.str_pad((string) $journal, 4, '0', STR_PAD_LEFT).' · '.$data['reason'],
            ]);
            $lines = $sourceLines->map(fn ($line) => [
                'journal_account_id' => $line->journal_account_id, 'account_code' => $line->account_code, 'account_name' => $line->account_name,
                'side' => $line->side === 'debit' ? 'credit' : 'debit', 'amount' => $line->amount,
            ])->all();
            $reversal->lines()->createMany($lines);
            $this->audit->record($team->id, $user->id, 'journal.reversed', 'journal', $journal,
                ['journal_id' => $journal], ['reversal_id' => $reversal->id, 'date' => $data['date'], 'lines' => $lines], $data['reason']);

            return $reversal->id;
        }, attempts: 5);
    }
}
