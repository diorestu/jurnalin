<?php

namespace Database\Seeders;

use App\Actions\Journal\SeedChartOfAccounts;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(SeedChartOfAccounts $accounts): void
    {
        Team::query()->select('id')->lazyById()->each(
            fn (Team $team) => $accounts->handle($team->id),
        );
    }
}
