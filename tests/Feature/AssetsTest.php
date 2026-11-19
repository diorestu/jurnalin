<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('posts consecutive monthly journals once and stops exactly at residual value', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $ids = DB::table('journal_accounts')->where('team_id', $team->id)->pluck('id', 'code');
    $this->actingAs($owner)->post(route('assets.store', $team), [
        'code' => 'MOB-01', 'name' => 'Kendaraan', 'cost' => 10001, 'residual' => 1, 'months' => 3, 'acquired' => '2026-01-20', 'start_month' => '2026-02', 'asset_account' => $ids['1503'], 'expense_account' => $ids['6142'], 'accumulation_account' => $ids['1553'],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $asset = DB::table('fixed_assets')->value('id');
    $url = route('assets.depreciate', [$team, $asset]);
    $this->post($url, ['month' => '2026-03'])->assertSessionHasErrors('month');
    $lock = DB::table('accounting_period_locks')->insertGetId(['team_id' => $team->id, 'month' => '2026-02', 'is_locked' => true, 'reason' => 'Tutup buku', 'changed_at' => now()]);
    $this->post($url, ['month' => '2026-02'])->assertSessionHasErrors('date');
    $this->assertDatabaseCount('asset_depreciations', 0);
    DB::table('accounting_period_locks')->where('id', $lock)->update(['is_locked' => false]);
    foreach (['2026-02', '2026-02', '2026-03', '2026-04'] as $month) {
        $this->post($url, compact('month'))->assertRedirect()->assertSessionHasNoErrors();
    }
    expect(DB::table('asset_depreciations')->orderBy('month')->pluck('amount')->all())->toBe([3333, 3334, 3333]);
    $this->assertDatabaseCount('journal_entries', 3);
    $this->assertDatabaseHas('journal_entries', ['date' => '2026-02-28']);
    $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '1553', 'side' => 'credit', 'amount' => 3334]);
    $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '6142', 'side' => 'debit', 'amount' => 3334]);
    $this->get(route('assets.index', [$team, 'asset' => $asset]))->assertInertia(fn (AssertableInertia $p) => $p->component('Assets')->where('selected.book', 1)->where('selected.next', null)->has('history', 3));
    $this->post($url, ['month' => '2026-05'])->assertSessionHasErrors('month');
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->post($url, ['month' => '2026-02'])->assertForbidden();
    $this->post(route('assets.depreciate', [$outsider->currentTeam, $asset]), ['month' => '2026-02'])->assertNotFound();
    $team->members()->attach($outsider, ['role' => 'member']);
    $this->post($url, ['month' => '2026-02'])->assertForbidden();
});
