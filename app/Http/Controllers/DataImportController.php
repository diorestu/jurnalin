<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Imports\WorkbookRows;
use App\Actions\Opening\StoreOpeningBalances;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    private const SHEETS = ['coa' => 'COA', 'kontak' => 'Kontak', 'jurnal' => 'Jurnal', 'saldo-awal' => 'Saldo Awal'];

    private const HEADERS = [
        'kontak' => ['jenis', 'kode', 'nama', 'termin_hari', 'kontak', 'alamat', 'npwp'],
        'coa' => ['kode', 'nama', 'jenis', 'aktif', 'kas_bank'],
        'jurnal' => ['referensi', 'tanggal', 'keterangan', 'kode_akun', 'debit', 'kredit'],
        'saldo-awal' => ['tanggal', 'kode_akun', 'debit', 'kredit'],
    ];

    private function authorizeKind(Request $request, Team $team, string $kind): void
    {
        abort_unless($kind === 'jurnal' || app(JournalDrafts::class)->supervisor($request->user(), $team), 403);
    }

    public function template(Request $request, Team $current_team, string $kind): StreamedResponse
    {
        $this->authorizeKind($request, $current_team, $kind);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(self::SHEETS[$kind]);
        $sheet->fromArray(self::HEADERS[$kind]);
        $sheet->freezePane('A2');
        foreach (self::HEADERS[$kind] as $i => $header) {
            $column = chr(65 + $i);
            $sheet->getColumnDimension($column)->setWidth(22);
            if (! in_array($header, ['debit', 'kredit'], true)) {
                $sheet->getStyle($column.':'.$column)->getNumberFormat()->setFormatCode('@');
            }
        }
        $book->createSheet()->setTitle('Petunjuk')->fromArray([[match ($kind) {
            'kontak' => 'Jenis pelanggan atau pemasok. Kode unik per jenis dan perusahaan. Kode yang sudah ada dilewati. Termin opsional dalam hari (0–3650).',
            'coa' => 'Jenis: asset, liability, equity, revenue, expense. Aktif dan kas_bank: 1 atau 0. Kode lama dilewati.',
            'jurnal' => 'Ulangi referensi, tanggal YYYY-MM-DD dan keterangan pada setiap baris satu jurnal. Isi satu sisi per baris dalam rupiah. Setiap referensi harus seimbang.',
            default => 'Isi akun neraca dan satu sisi nominal per baris. Gunakan satu tanggal YYYY-MM-DD. Untuk saldo awal dengan rincian piutang, utang atau persediaan gunakan menu Saldo Awal.',
        }]])->getColumnDimension('A')->setWidth(110);

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'template-'.$kind.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function upload(Request $request, Team $current_team, string $kind, string $step): JsonResponse
    {
        $this->authorizeKind($request, $current_team, $kind);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        $path = $request->file('file')->getRealPath();
        $headers = self::HEADERS[$kind];
        $rows = app(WorkbookRows::class)->read($path, self::SHEETS[$kind], $headers, match ($kind) {
            'coa', 'kontak' => 5000, 'jurnal' => 1000, default => 50,
        });
        $user = $request->user();
        $token = hash_hmac('sha256', implode('|', [$current_team->id, $user->id, $kind, hash_file('sha256', $path)]), (string) config('app.key'));
        $preview = $step === 'preview';
        if (! $preview && ! hash_equals($token, $request->string('token')->toString())) {
            throw ValidationException::withMessages(['file' => 'Pratinjau ulang file untuk perusahaan aktif.']);
        }

        DB::beginTransaction();
        try {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            if ($kind === 'kontak') {
                $added = 0;
                $seen = [];
                foreach ($rows as $n => [$type,$code,$name,$terms,$contact,$address,$npwp]) {
                    $code = strtoupper($code);
                    $validator = Validator::make(compact('type', 'code', 'name', 'terms', 'contact', 'address', 'npwp'), [
                        'type' => 'required|in:pelanggan,pemasok', 'code' => 'required|string|max:40', 'name' => 'required|string|max:160',
                        'terms' => 'nullable|integer|between:0,3650', 'contact' => 'nullable|string|max:255', 'address' => 'nullable|string|max:255', 'npwp' => 'nullable|string|max:32',
                    ]);
                    if ($validator->fails() || isset($seen[$type][$code])) {
                        throw ValidationException::withMessages(['file' => "Baris $n: ".($validator->errors()->first() ?: 'Kode kontak berulang.')]);
                    }
                    $seen[$type][$code] = true;
                    $key = ['team_id' => $current_team->id, 'direction' => $type === 'pelanggan' ? 'penjualan' : 'pembelian', 'code' => $code];
                    if (! DB::table('trade_partners')->where($key)->exists()) {
                        $added++;
                        if (! $preview) {
                            DB::table('trade_partners')->insert([...$key, ...compact('name', 'contact', 'address', 'npwp'), 'terms' => $terms === '' ? null : (int) $terms]);
                        }
                    }
                }
                $summary = "$added kontak baru, ".(count($rows) - $added).' kode lama dilewati.';
            } elseif ($kind === 'coa') {
                [$count, $skipped] = app(CoaExcelController::class)->applyRows($current_team, $user->id, $rows, $preview);
                $summary = "$count akun baru, $skipped kode lama dilewati.";
            } else {
                $accounts = DB::table('journal_accounts')->where('team_id', $current_team->id)->where('active', true)->pluck('id', 'code');
                $groups = [];
                foreach ($rows as $row => $values) {
                    $data = array_combine($headers, $values);
                    $ref = $data['referensi'] ?? 'saldo-awal';
                    $date = $data['tanggal'];
                    $description = $data['keterangan'] ?? null;
                    $account = $accounts->get(strtoupper($data['kode_akun']));
                    $validator = Validator::make($data, [
                        'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:9999-12-30'],
                        'referensi' => ['sometimes', 'required', 'max:100'],
                        'debit' => ['nullable', 'regex:/^\d{1,15}$/D'],
                        'kredit' => ['nullable', 'regex:/^\d{1,15}$/D'],
                    ]);
                    if ($validator->fails() || ! $account) {
                        throw ValidationException::withMessages(['file' => "Baris $row: ".($validator->errors()->first() ?: 'Kode akun tidak aktif atau tidak ditemukan.')]);
                    }
                    $groups[$ref] ??= ['date' => $date, 'description' => $description, 'lines' => []];
                    if ($date !== $groups[$ref]['date'] || $description !== $groups[$ref]['description']) {
                        throw ValidationException::withMessages(['file' => "Baris $row: tanggal dan keterangan harus sama dalam satu kelompok."]);
                    }
                    $groups[$ref]['lines'][] = ['account_id' => (int) $account, 'debit' => (int) $data['debit'], 'credit' => (int) $data['kredit']];
                }
                foreach ($groups as $ref => $group) {
                    $group['request_id'] = Uuid::uuid5(Uuid::NAMESPACE_URL, "import/$current_team->id/$kind/$ref")->toString();
                    try {
                        if ($kind === 'jurnal') {
                            app(JournalDrafts::class)->save($current_team, $user, 'manual', [...$group, 'action' => 'submit']);
                        } else {
                            if (count(array_unique(array_column($group['lines'], 'account_id'))) !== count($group['lines'])) {
                                throw ValidationException::withMessages(['file' => 'Setiap kode akun saldo awal harus unik.']);
                            }
                            app(StoreOpeningBalances::class)->handle($current_team, $user->id, $group);
                        }
                    } catch (ValidationException $error) {
                        throw ValidationException::withMessages(['file' => $ref.' — '.collect($error->errors())->flatten()->first()]);
                    }
                }
                $summary = $kind === 'jurnal' ? count($groups).' jurnal melalui alur persetujuan.' : count($rows).' baris saldo awal.';
            }
            if ($preview) {
                DB::rollBack();
            } else {
                app(RecordAccountingAudit::class)->record($current_team->id, $user->id, 'excel.imported', $kind, null, null, ['rows' => count($rows), 'summary' => $summary]);
                DB::commit();
            }
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        return response()->json(['summary' => $summary, 'token' => $token, 'headers' => $headers, 'rows' => array_slice($rows, 0, 20), 'total' => count($rows)]);
    }
}
