<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

it('limits staff imports to journals of their own company', function () {
    $team = User::factory()->create()->currentTeam;
    $staff = User::factory()->create();
    $url = fn ($kind) => route('imports.upload', [$team, $kind, 'preview']);
    $this->actingAs($staff)->postJson($url('jurnal'))->assertForbidden();
    $team->members()->attach($staff, ['role' => 'member']);
    $this->postJson($url('coa'))->assertForbidden();
    $this->postJson($url('saldo-awal'))->assertForbidden();
    $this->postJson($url('jurnal'))->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('previews the downloaded template and confirms it atomically', function (string $kind, string $table, array $rows) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $response = $this->actingAs($user)->get(route('imports.template', [$team, $kind]))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'data-import');
    try {
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $book->getSheet(0)->fromArray($rows, null, 'A2', true);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $file = new UploadedFile($path, 'data.xlsx', null, null, true);
        $before = DB::table($table)->count();
        $url = fn ($step) => route('imports.upload', [$team, $kind, $step]);
        $preview = $this->postJson($url('preview'), ['file' => $file])->assertOk()->assertJsonPath('total', count($rows));
        $this->assertDatabaseCount($table, $before);
        $this->postJson($url('confirm'), ['file' => $file, 'token' => 'invalid'])->assertUnprocessable();
        foreach ([1, 2] as $attempt) {
            $this->postJson($url('confirm'), ['file' => $file, 'token' => $preview->json('token')])->assertOk();
            $this->assertDatabaseCount($table, $before + 1);
        }
    } finally {
        unlink($path);
    }
})->with([
    ['kontak', 'trade_partners', [['pelanggan', 'ABC', 'PT ABC', 30, 'info@example.test', 'Bali', '']]],
    ['coa', 'journal_accounts', [['QA-003', 'Akun contoh', 'asset', '1', '0']]],
    ['jurnal', 'journal_drafts', [['REF-A', '2026-01-01', 'Transfer', '1101', 100, 0], ['REF-A', '2026-01-01', 'Transfer', '1102', 0, 100]]],
    ['saldo-awal', 'opening_balances', [['2026-01-01', '1101', 100, 0], ['2026-01-01', '1102', 0, 100]]],
]);
