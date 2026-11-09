<?php

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('provisions a product and service chart alongside the original journal templates', function () {
    $team = User::factory()->create()->currentTeam;
    $accounts = DB::table('journal_accounts')->where('team_id', $team->id);

    expect($accounts->count())->toBeGreaterThan(100);
    foreach ([
        '1101' => ['Kas', 'asset'],
        '1102' => ['Bank BCA', 'asset'],
        '1120' => ['Piutang Usaha', 'asset'],
        '1300' => ['Persediaan Barang', 'asset'],
        '2100' => ['Utang Usaha', 'liability'],
        '2200' => ['Uang Muka Pelanggan', 'liability'],
        '3101' => ['Modal Disetor', 'equity'],
        '4111' => ['Penjualan Produk', 'revenue'],
        '4120' => ['Pendapatan Jasa', 'revenue'],
        '5101' => ['Harga Pokok Penjualan Barang', 'expense'],
        '5201' => ['Tenaga Kerja Langsung Jasa', 'expense'],
    ] as $code => [$name, $type]) {
        $this->assertDatabaseHas('journal_accounts', [
            'team_id' => $team->id, 'code' => (string) $code, 'name' => $name, 'type' => $type,
        ]);
    }

    expect((clone $accounts)->whereNotNull('trade_role')->count())->toBe(5);
    $this->assertDatabaseHas('journal_accounts', ['team_id' => $team->id, 'code' => '1103', 'is_cash' => true]);
    $this->assertDatabaseCount('journal_templates', 4);
});

it('adds missing codes to existing teams without altering accounts or duplicating trade roles', function () {
    $team = User::factory()->create()->currentTeam;
    $accounts = DB::table('journal_accounts')->where('team_id', $team->id);
    (clone $accounts)->where('code', '4111')->delete();
    (clone $accounts)->where('code', '1120')->update(['code' => 'AR-KHUSUS', 'name' => 'Piutang Dagang Lama']);
    (clone $accounts)->where('code', '4120')->update(['name' => 'Pendapatan Konsultasi Khusus', 'active' => false]);
    (clone $accounts)->where('code', '1102')->update(['name' => 'Bank Operasional Kami', 'is_cash' => false]);
    $templates = DB::table('journal_template_lines')->get()->all();

    $this->seed(ChartOfAccountsSeeder::class);
    $afterFirstRun = $accounts->count();
    $this->seed(ChartOfAccountsSeeder::class);

    expect($accounts->count())->toBe($afterFirstRun);
    expect(DB::table('journal_template_lines')->get()->all())->toEqual($templates);
    expect((clone $accounts)->where('trade_role', 'receivable')->count())->toBe(1);
    $this->assertDatabaseMissing('journal_accounts', ['team_id' => $team->id, 'code' => '1120']);
    $this->assertDatabaseHas('journal_accounts', ['team_id' => $team->id, 'code' => 'AR-KHUSUS', 'name' => 'Piutang Dagang Lama']);
    $this->assertDatabaseHas('journal_accounts', ['team_id' => $team->id, 'code' => '4111', 'name' => 'Penjualan Produk']);
    $this->assertDatabaseHas('journal_accounts', ['team_id' => $team->id, 'code' => '4120', 'name' => 'Pendapatan Konsultasi Khusus', 'active' => false]);
    $this->assertDatabaseHas('journal_accounts', ['team_id' => $team->id, 'code' => '1102', 'name' => 'Bank Operasional Kami', 'is_cash' => false]);
});
