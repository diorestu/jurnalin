<?php

namespace App\Actions\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodLocks
{
    public function assertOpen(int $teamId, string $date): void
    {
        $lockedThrough = $this->lockedThrough($teamId);
        if ($lockedThrough !== null && substr($date, 0, 7) <= $lockedThrough) {
            throw ValidationException::withMessages([
                'date' => 'Tanggal ini termasuk pembukuan yang dikunci sampai '.$lockedThrough.'. Buka periode terbaru sebelum mencatat transaksi.',
            ]);
        }
    }

    public function lockedThrough(int $teamId): ?string
    {
        return DB::table('accounting_period_locks')->where('team_id', $teamId)
            ->where('is_locked', true)->max('month');
    }

    public function assertAccountTypeChangeAllowed(int $teamId, int $accountId): void
    {
        $month = $this->lockedThrough($teamId);
        if ($month === null) {
            return;
        }
        $end = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();
        $hasLockedEntries = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->where('entries.team_id', $teamId)->where('lines.journal_account_id', $accountId)
            ->where('entries.date', '<=', $end)->exists();

        if ($hasLockedEntries) {
            throw ValidationException::withMessages([
                'type' => 'Jenis akun tidak dapat diubah karena sudah dipakai dalam periode terkunci.',
            ]);
        }
    }
}
