<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\JournalDrafts;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Actions\Imports\WorkbookRows;
use App\Actions\Trade\PostTradeJournal;
use App\Models\Team;
use Brick\Math\BigInteger;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankController extends Controller
{
    private const HEADER = ['tanggal', 'referensi', 'keterangan', 'masuk', 'keluar'];

    private function ledger(int $team, int $account): Builder
    {
        return DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->leftJoin('bank_statement_lines as b', 'b.matched_line_id', '=', 'l.id')->where('e.team_id', $team)->where('l.journal_account_id', $account)
            ->select('l.id', 'l.side', 'l.amount', 'e.date', 'e.description', 'b.reference', 'e.id as entry');
    }

    public function index(Request $request, Team $current_team, PostTradeJournal $journal): Response
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'account' => ['nullable', 'integer']]);
        $accounts = $journal->cashAccounts($current_team->id);
        $account = (int) ($filters['account'] ?? $accounts->first()->id ?? 0);
        abort_if($account && ! $accounts->contains('id', $account), 404);
        $month = $filters['month'] ?? now('Asia/Jakarta')->format('Y-m');
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];
        $lines = $this->ledger($current_team->id, $account);
        $opening = BigInteger::zero();
        $inflow = BigInteger::zero();
        $outflow = BigInteger::zero();
        foreach ((clone $lines)->where('e.date', '<=', $range[1])->cursor() as $line) {
            if ($line->date < $range[0]) {
                $opening = $line->side === 'debit' ? $opening->plus($line->amount) : $opening->minus($line->amount);
            } elseif ($line->side === 'debit') {
                $inflow = $inflow->plus($line->amount);
            } else {
                $outflow = $outflow->plus($line->amount);
            }
        }
        $summary = ['Saldo awal' => (string) $opening, 'Masuk' => (string) $inflow, 'Keluar' => (string) $outflow, 'Saldo akhir' => (string) $opening->plus($inflow)->minus($outflow)];
        $entries = (clone $lines)->whereBetween('e.date', $range)->orderByDesc('e.date')->orderByDesc('l.id')->paginate(30, ['*'], 'ledger_page')->withQueryString();
        $available = (clone $lines)->whereNull('b.id')->whereBetween('e.date', [$start->copy()->subDays(7)->toDateString(), $end->addDays(7)->toDateString()])->get()->groupBy(fn ($l) => (string) ($l->side === 'debit' ? $l->amount : -$l->amount));
        $statements = DB::table('bank_statement_lines')->where('team_id', $current_team->id)->where('account_id', $account)->whereBetween('date', $range)->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        $statements->through(function ($row) use ($available) {
            $row->candidates = $row->matched_line_id ? [] : $available->get($row->amount, collect())->filter(fn ($l) => abs(Carbon::parse($l->date)->diffInDays(Carbon::parse($row->date))) <= 7)->sortBy(fn ($l) => [str_contains(strtolower((string) $l->description), strtolower($row->reference)) ? 0 : 1, abs(Carbon::parse($l->date)->diffInDays(Carbon::parse($row->date))), $l->id])->values();

            return $row;
        });

        return Inertia::render('Bank', ['summary' => $summary, 'team' => $current_team->slug, 'accounts' => $accounts, 'account' => $account, 'month' => $month, 'entries' => $entries, 'statements' => $statements, 'canMatch' => app(JournalDrafts::class)->supervisor($request->user(), $current_team), 'allAccounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)->where('active', true)->orderBy('code')->get(['id', 'name', 'code'])]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(fn () => print (implode(',', self::HEADER)."\n"), 'template-mutasi.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, Team $current_team, PostTradeJournal $journal): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'integer'], 'file' => ['required', 'file', 'extensions:xlsx,csv', 'max:5120']]);
        abort_unless($journal->cashAccounts($current_team->id)->contains('id', $data['account']), 422);
        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $rows = app(WorkbookRows::class)->read($file->getRealPath(), 'Mutasi', self::HEADER, 5000);
        } else {
            $reader = new \SplFileObject($file->getRealPath());
            $headers = $reader->fgetcsv(',', '"', '');
            if (! is_array($headers)) {
                throw ValidationException::withMessages(['file' => 'CSV kosong.']);
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($headers[0] ?? ''));
            if ($headers !== self::HEADER) {
                throw ValidationException::withMessages(['file' => 'Header CSV harus '.implode(', ', self::HEADER)]);
            }
            $rows = [];
            while (! $reader->eof()) {
                $row = $reader->fgetcsv(',', '"', '');
                if ($row === [null] || $row === false) {
                    continue;
                }
                if (count($rows) >= 5000 || count($row) !== 5) {
                    throw ValidationException::withMessages(['file' => 'Gunakan lima kolom dan maksimal 5.000 baris.']);
                }
                $rows[$reader->key() + 1] = array_map(fn ($value) => trim((string) $value), $row);
            }
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'Mutasi kosong.']);
        }
        $added = DB::transaction(function () use ($rows, $data, $current_team, $request) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $added = 0;
            foreach ($rows as $n => [$date,$ref,$description,$in,$out]) {
                $v = Validator::make(compact('date', 'ref', 'description', 'in', 'out'), ['date' => 'required|date_format:Y-m-d', 'ref' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'in' => ['nullable', 'regex:/^\d{1,15}$/D'], 'out' => ['nullable', 'regex:/^\d{1,15}$/D']]);
                if ($v->fails() || ((int) $in > 0) === ((int) $out > 0)) {
                    throw ValidationException::withMessages(['file' => "Baris $n: periksa tanggal, referensi dan satu nominal masuk atau keluar."]);
                }
                $amount = (int) $in - (int) $out;
                $key = ['team_id' => $current_team->id, 'account_id' => $data['account'], 'reference' => $ref];
                $existing = DB::table('bank_statement_lines')->where($key)->first();
                if ($existing) {
                    if ($existing->date !== $date || (int) $existing->amount !== $amount || $existing->description !== $description) {
                        throw ValidationException::withMessages(['file' => "Baris $n: referensi sudah ada dengan data berbeda."]);
                    }

                    continue;
                }
                DB::table('bank_statement_lines')->insert([...$key, ...compact('date', 'amount', 'description')]);
                $added++;
            }
            app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'bank.imported', 'account', $data['account'], null, ['added' => $added]);

            return $added;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => "$added mutasi baru."]);

        return to_route('bank.index', [$current_team, 'account' => $data['account'], 'month' => substr(reset($rows)[0], 0, 7)]);
    }

    public function matchLine(Request $request, Team $current_team, int $statement): RedirectResponse
    {
        $data = $request->validate(['line' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($current_team, $statement, $data, $request) {
            Team::whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $row = DB::table('bank_statement_lines')->where('team_id', $current_team->id)->where('id', $statement)->firstOrFail();
            if ((int) $row->matched_line_id === (int) $data['line']) {
                return;
            }
            $line = $this->ledger($current_team->id, $row->account_id)->where('l.id', $data['line'])->whereNull('b.id')->first();
            if (! $line || $row->matched_line_id || (int) $row->amount !== ($line->side === 'debit' ? (int) $line->amount : -(int) $line->amount)) {
                throw ValidationException::withMessages(['line' => 'Mutasi dan jurnal harus tersedia dengan akun, sisi, dan nominal yang sama.']);
            }
            DB::table('bank_statement_lines')->where('id', $statement)->update(['matched_line_id' => $line->id]);
            app(RecordAccountingAudit::class)->record($current_team->id, $request->user()->id, 'bank.matched', 'bank_statement', $statement, null, ['line' => $line->id]);
        });

        return back();
    }
}
