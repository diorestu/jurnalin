<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\AccountType;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class CoaExcelController extends Controller
{
    private const HEADERS = ['kode', 'nama', 'jenis', 'aktif', 'kas_bank'];

    public function export(Request $request, Team $current_team): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('COA');
        $sheet->fromArray(self::HEADERS);
        if (! $request->boolean('template')) {
            $row = 2;
            foreach (DB::table('journal_accounts')->where('team_id', $current_team->id)->orderBy('code')->get() as $account) {
                foreach ([$account->code, $account->name, $account->type ?? '', (string) (int) $account->active, (string) (int) $account->is_cash] as $col => $value) {
                    $sheet->setCellValueExplicit([$col + 1, $row], $value, DataType::TYPE_STRING);
                }
                $row++;
            }
        }
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        foreach (['A' => 20, 'B' => 48, 'C' => 20, 'D' => 12, 'E' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $guide = $book->createSheet()->setTitle('Petunjuk');
        $guide->fromArray([
            ['Kolom', 'Isi'],
            ['kode', 'Kode unik. Kode yang sudah ada dilewati.'],
            ['nama', 'Nama akun, maksimal 255 karakter.'],
            ['jenis', 'asset / liability / equity / revenue / expense'],
            ['aktif', '1 untuk aktif, 0 untuk nonaktif.'],
            ['kas_bank', '1 hanya untuk akun asset yang digunakan sebagai kas/bank; selain itu 0.'],
            ['Batas', 'Maksimal 5.000 akun dan ukuran file 5 MB. Semua baris divalidasi sebelum disimpan.'],
        ]);
        $guide->getColumnDimension('A')->setWidth(18);
        $guide->getColumnDimension('B')->setWidth(100);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, ($request->boolean('template') ? 'template-coa' : 'coa-'.$current_team->slug).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request, Team $current_team): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        $path = $request->file('file')->getRealPath();
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak valid.']);
        }
        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $bytes += ($zip->statIndex($i)['size'] ?? 0);
        }
        $zip->close();
        if ($bytes > 30 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Isi file Excel terlalu besar.']);
        }

        $book = null;
        try {
            $reader = IOFactory::createReader('Xlsx');
            $info = collect($reader->listWorksheetInfo($path))->firstWhere('worksheetName', 'COA');
            if ($info === null || $info['totalRows'] > 5001 || $info['totalColumns'] > 5) {
                throw ValidationException::withMessages(['file' => 'Gunakan sheet COA dengan lima kolom dan maksimal 5.000 akun.']);
            }
            $reader->setLoadSheetsOnly('COA');
            $book = $reader->load($path);
            $sheet = $book->getSheetByName('COA');
            $rows = [];
            foreach (range(1, max(1, $info['totalRows'])) as $row) {
                $values = [];
                for ($col = 1; $col <= 5; $col++) {
                    $cell = $sheet->getCell([$col, $row]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        throw ValidationException::withMessages(['file' => 'Baris '.$row.': gunakan nilai biasa, bukan formula Excel.']);
                    }
                    $values[] = trim((string) $cell->getValue());
                }
                if ($row === 1 && $values !== self::HEADERS) {
                    throw ValidationException::withMessages(['file' => 'Header harus kode, nama, jenis, aktif, kas_bank. Unduh template untuk memulai.']);
                }
                if ($row > 1 && array_filter($values, fn ($value) => $value !== '') !== []) {
                    $rows[$row] = $values;
                }
            }
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca. Gunakan template Excel yang tersedia.']);
        } finally {
            $book?->disconnectWorksheets();
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File belum berisi akun.']);
        }

        [$added, $skipped] = DB::transaction(function () use ($current_team, $request, $rows) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('journal_accounts')->where('team_id', $current_team->id)->pluck('code')->map(fn ($code) => strtoupper($code))->flip();
            $pending = [];
            $seen = [];
            $skipped = 0;
            foreach ($rows as $row => [$code, $name, $type, $active, $cash]) {
                $code = strtoupper($code);
                if (isset($seen[$code])) {
                    throw ValidationException::withMessages(['file' => 'Baris '.$row.': kode akun berulang di dalam file.']);
                }
                $seen[$code] = true;
                if ($existing->has($code)) {
                    $skipped++;

                    continue;
                }
                $data = ['code' => $code, 'name' => $name, 'type' => $type, 'active' => $active, 'is_cash' => $cash];
                $validator = Validator::make($data, [
                    'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/D'],
                    'name' => ['required', 'string', 'max:255'],
                    'type' => ['required', Rule::enum(AccountType::class)],
                    'active' => ['required', Rule::in(['0', '1'])],
                    'is_cash' => ['required', Rule::in(['0', '1'])],
                ]);
                if ($validator->fails() || ($cash === '1' && $type !== 'asset')) {
                    throw ValidationException::withMessages(['file' => 'Baris '.$row.': '.($validator->errors()->first() ?: 'Kas/bank harus bertipe asset.')]);
                }
                $pending[] = [...$data, 'active' => $active === '1', 'is_cash' => $cash === '1'];
            }
            foreach ($pending as $data) {
                $id = DB::table('journal_accounts')->insertGetId(['team_id' => $current_team->id, ...$data]);
                app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'account.imported', 'account', $id, null, $data);
            }

            return [count($pending), $skipped];
        }, attempts: 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => $added.' akun ditambahkan, '.$skipped.' kode yang sudah ada dilewati.']);

        return to_route('chart-of-accounts.index', $current_team);
    }
}
