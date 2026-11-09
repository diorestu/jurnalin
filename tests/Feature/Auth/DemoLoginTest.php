<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a usable demo account and its journal workspace only once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseCount('journal_templates', 4);

    $user = User::where('email', 'demo@konsulin.test')->firstOrFail();
    $dashboard = route('dashboard', $user->currentTeam);
    $this->post(route('login.store'), [
        'email' => 'demo@konsulin.test',
        'password' => 'KonsulinDemo!2026',
    ])->assertRedirect($dashboard);
    $this->assertAuthenticatedAs($user);
    $this->get($dashboard)->assertOk();
});

it('does not create the demo account in production', function () {
    $this->app->instance('env', 'production');

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseMissing('users', ['email' => 'demo@konsulin.test']);
});
