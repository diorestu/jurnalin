<?php

use App\Actions\Accounting\PeriodLocks;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\TeamRole;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('protects the locked cutoff and earlier balances while keeping later and other company entries open', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $teamId = $user->currentTeam->id;
    DB::table('accounting_period_locks')->insert([
        'team_id' => $teamId, 'month' => '2026-11', 'is_locked' => true,
        'reason' => 'Pembukuan selesai diperiksa', 'changed_by' => $user->id, 'changed_at' => now(),
    ]);
    $guards = app(PeriodLocks::class);
    expect(fn () => $guards->assertOpen($teamId, '2026-10-31'))->toThrow(ValidationException::class);
    $guards->assertOpen($teamId, '2026-12-01');
    $guards->assertOpen($other->currentTeam->id, '2026-11-01');
    expect(fn () => $guards->assertOpen($teamId, '2026-11-01'))->toThrow(ValidationException::class);
    expect(fn () => $guards->assertOpen($teamId, '2026-11-30'))->toThrow(ValidationException::class);

    $account = DB::table('journal_accounts')->where('team_id', $teamId)->where('code', '1101')->first();
    $entry = JournalEntry::create([
        'team_id' => $teamId, 'posted_by' => $user->id, 'request_id' => (string) Str::uuid(),
        'template_name' => 'Jurnal periode', 'date' => '2026-11-30',
    ]);
    $entry->lines()->create([
        'journal_account_id' => $account->id, 'account_code' => $account->code,
        'account_name' => $account->name, 'side' => 'debit', 'amount' => 1000,
    ]);
    expect(fn () => $guards->assertAccountTypeChangeAllowed($teamId, $account->id))->toThrow(ValidationException::class);
    $guards->assertAccountTypeChangeAllowed($other->currentTeam->id, $account->id);
    $entry->update(['date' => '2026-12-01']);
    $guards->assertAccountTypeChangeAllowed($teamId, $account->id);
});

it('stores independent audit snapshots that retain their recorded values', function () {
    $user = User::factory()->create();
    $after = ['month' => '2026-11', 'is_locked' => true, 'lines' => [['amount' => 150000]]];
    app(RecordAccountingAudit::class)->record(
        $user->currentTeam->id, $user->id, 'period.locked', 'accounting_period', null,
        null, $after, 'Laporan sudah diperiksa',
    );
    $after['lines'][0]['amount'] = 900000;
    $event = DB::table('accounting_audit_events')->first();

    expect($event->before)->toBeNull()
        ->and(json_decode($event->after, true))->toMatchArray([
            'month' => '2026-11', 'is_locked' => true, 'lines' => [['amount' => 150000]],
        ]);
});

it('versions period changes, accepts identical retries and restricts management to admins', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $url = route('accounting-periods.change', $team);
    $payload = ['month' => '2026-11', 'locked' => true, 'reason' => 'Laporan selesai diperiksa', 'version' => 0];

    $this->actingAs($member)->post($url, $payload)->assertForbidden();
    $this->actingAs($owner)->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('accounting_audit_events', 1);
    $this->post($url, [...$payload, 'locked' => false, 'reason' => 'Perbaikan hasil pemeriksaan'])
        ->assertSessionHasErrors('version');
    $this->assertDatabaseHas('accounting_period_locks', ['team_id' => $team->id, 'is_locked' => true, 'version' => 1]);
    $this->post($url, [...$payload, 'locked' => false, 'reason' => 'Perbaikan hasil pemeriksaan', 'version' => 1])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('accounting_period_locks', ['team_id' => $team->id, 'is_locked' => false, 'version' => 2]);
    $this->assertDatabaseCount('accounting_audit_events', 2);
    $this->actingAs($member)->get(route('accounting-audit.index', $team))->assertForbidden();
});

it('refuses to lock a month with pending company drafts but ignores another company drafts', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $team = $owner->currentTeam;
    $draft = [
        'team_id' => $team->id, 'mode' => 'manual', 'status' => 'pending', 'date' => '2026-11-30',
        'payload' => json_encode(['lines' => []]), 'request_id' => (string) Str::uuid(),
        'request_hash' => str_repeat('a', 64), 'created_by' => $owner->id,
        'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('journal_drafts')->insert($draft);
    $payload = ['month' => '2026-11', 'locked' => true, 'reason' => 'Periksa draf terakhir', 'version' => 0];
    $url = route('accounting-periods.change', $team);
    $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('month');
    $this->assertDatabaseCount('accounting_period_locks', 0);
    $this->assertDatabaseCount('accounting_audit_events', 0);

    DB::table('journal_drafts')->where('team_id', $team->id)->update(['status' => 'draft']);
    DB::table('journal_drafts')->insert([
        ...$draft, 'team_id' => $other->currentTeam->id, 'request_id' => (string) Str::uuid(), 'created_by' => $other->id,
    ]);
    $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('accounting_period_locks', 1);
});

it('requires reopening the latest locked period first', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $url = route('accounting-periods.change', $team);
    foreach (['2026-11', '2026-12'] as $month) {
        $this->actingAs($owner)->post($url, ['month' => $month, 'locked' => true, 'version' => 0, 'reason' => 'Laporan selesai diperiksa'])
            ->assertSessionHasNoErrors();
    }
    $this->post($url, ['month' => '2026-11', 'locked' => false, 'version' => 1, 'reason' => 'Perbaikan November'])
        ->assertSessionHasErrors('locked');
    $this->post($url, ['month' => '2026-12', 'locked' => false, 'version' => 1, 'reason' => 'Perbaikan Desember'])
        ->assertSessionHasNoErrors();
    $this->post($url, ['month' => '2026-11', 'locked' => false, 'version' => 1, 'reason' => 'Perbaikan November'])
        ->assertSessionHasNoErrors();
    expect(app(PeriodLocks::class)->lockedThrough($team->id))->toBeNull();
});
