<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Imports\WorkbookRows;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $rows = app(WorkbookRows::class)->read($path, 'COA', self::HEADERS, 5000);
        [$added, $skipped] = $this->applyRows($current_team, $request->user()->id, $rows);
        Inertia::flash('toast', ['type' => 'success', 'message' => $added.' akun ditambahkan, '.$skipped.' kode yang sudah ada dilewati.']);

        return to_route('chart-of-accounts.index', $current_team);
    }

    /** @param array<int, list<string>> $rows
     * @return array{int,int}
     */
    public function applyRows(Team $current_team, int $userId, array $rows, bool $preview = false): array
    {
        return DB::transaction(function () use ($current_team, $userId, $rows, $preview) {
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
            if ($preview) {
                return [count($pending), $skipped];
            }
            foreach ($pending as $data) {
                $id = DB::table('journal_accounts')->insertGetId(['team_id' => $current_team->id, ...$data]);
                app(RecordAccountingAudit::class)->record($current_team->id, $userId, 'account.imported', 'account', $id, null, $data);
            }

            return [count($pending), $skipped];
        }, attempts: 5);
    }
}
