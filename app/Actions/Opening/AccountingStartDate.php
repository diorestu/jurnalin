<?php

namespace App\Actions\Opening;

use App\Actions\Accounting\PeriodLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingStartDate
{
    public function assertAfter(int $teamId, string $date): void
    {
        app(PeriodLocks::class)->assertOpen($teamId, $date);
        $opening = DB::table('opening_balances')->where('team_id', $teamId)->value('date');
        if ($opening !== null && $date <= $opening) {
            throw ValidationException::withMessages([
                'date' => 'Tanggal transaksi harus setelah tanggal saldo awal ('.$opening.').',
            ]);
        }
    }
}
