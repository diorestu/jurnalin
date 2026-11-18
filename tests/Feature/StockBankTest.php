<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('imports a statement twice and confirms one ledger match without posting twice', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $cash = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1101')->value('id');
    $other = DB::table('journal_accounts')->where('team_id', $team->id)->where('code', '1102')->value('id');
    $this->actingAs($user)->post(route('journals.manual', $team), ['request_id' => (string) Str::uuid(), 'date' => '2026-10-10', 'description' => 'TRF-1', 'lines' => [['account_id' => $cash, 'debit' => 100], ['account_id' => $other, 'credit' => 100]]])->assertSessionHasNoErrors();
    foreach ([1, 2] as $attempt) {
        $file = UploadedFile::fake()->createWithContent('mutasi.csv', "tanggal,referensi,keterangan,masuk,keluar\n2026-10-11,TRF-1,Transfer,100,0\n");
        $this->post(route('bank.import', $team), ['file' => $file, 'account' => $cash])->assertRedirect()->assertSessionHasNoErrors();
    }
    $this->assertDatabaseCount('bank_statement_lines', 1);
    $line = DB::table('journal_entry_lines')->where('journal_account_id', $cash)->value('id');
    $this->get(route('bank.index', [$team, 'month' => '2026-10', 'account' => $cash]))->assertInertia(fn (AssertableInertia $p) => $p->component('Bank')->where('statements.data.0.candidates.0.id', $line));
    $id = DB::table('bank_statement_lines')->value('id');
    $this->post(route('bank.match', [$team, $id]), ['line' => $line])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('bank_statement_lines', ['id' => $id, 'matched_line_id' => $line]);
    $this->assertDatabaseCount('journal_entries', 1);
});
