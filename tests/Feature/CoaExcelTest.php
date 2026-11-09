<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function coaWorkbook(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('COA')->fromArray([['kode', 'nama', 'jenis', 'aktif', 'kas_bank'], ...$rows], null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'coa-test');
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return new UploadedFile($path, 'coa.xlsx', null, null, true);
}

it('imports new accounts atomically and skips existing codes on repeat', function () {
    $user = User::factory()->create();
    $url = route('chart-of-accounts.import', $user->currentTeam);
    $rows = [['QA-001', 'Akun Excel', 'expense', '1', '0'], ['1101', 'Nama pengganti', 'asset', '0', '0']];
    $file = coaWorkbook($rows);
    try {
        $this->actingAs($user)->post($url, ['file' => $file])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, ['file' => $file])->assertSessionHasNoErrors();
        expect(DB::table('journal_accounts')->where('team_id', $user->currentTeam->id)->where('code', 'QA-001')->count())->toBe(1);
        $this->assertDatabaseHas('journal_accounts', ['team_id' => $user->currentTeam->id, 'code' => '1101', 'name' => 'Kas', 'active' => true]);
        $this->assertDatabaseCount('accounting_audit_events', 1);
    } finally {
        unlink($file->getPathname());
    }
    $bad = coaWorkbook([['QA-002', 'Valid', 'expense', '1', '0'], ['QA-003', 'Invalid', 'expense', '1', '1']]);
    try {
        $this->post($url, ['file' => $bad])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('journal_accounts', ['code' => 'QA-002']);
    } finally {
        unlink($bad->getPathname());
    }
});

it('exports a real scoped Excel workbook and requires company access', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('chart-of-accounts.export', $user->currentTeam))->assertForbidden();
    $response = $this->actingAs($user)->get(route('chart-of-accounts.export', $user->currentTeam))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'coa-export');
    file_put_contents($path, $response->streamedContent());
    try {
        $book = IOFactory::load($path);
        expect($book->getSheetByName('COA')->getCell('A1')->getValue())->toBe('kode');
        expect($book->getSheetByName('COA')->getHighestDataRow())->toBe(171);
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});
