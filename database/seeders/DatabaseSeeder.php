<?php

namespace Database\Seeders;

use App\Actions\Journal\SeedChartOfAccounts;
use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(CreateTeam $createTeam, SeedChartOfAccounts $accounts): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function () use ($createTeam, $accounts) {
            $user = User::query()->firstOrCreate(
                ['email' => 'demo@konsulin.test'],
                ['name' => 'Konsulin Demo', 'password' => 'KonsulinDemo!2026'],
            );

            if ($user->wasRecentlyCreated) {
                $createTeam->handle($user, 'Konsulin Demo', isPersonal: true);
            } elseif ($team = $user->personalTeam()) {
                $accounts->handle($team->id);
            }
        });
    }
}
