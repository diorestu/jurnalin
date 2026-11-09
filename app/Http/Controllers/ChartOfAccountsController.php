<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\PeriodLocks;
use App\Actions\Accounting\RecordAccountingAudit;
use App\Enums\AccountType;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountsController extends Controller
{
    public function index(Team $current_team): Response
    {
        return Inertia::render('journal/Accounts', [
            'accounts' => DB::table('journal_accounts')->where('team_id', $current_team->id)
                ->orderBy('code')->get(['id', 'code', 'name', 'type', 'active', 'is_cash', 'trade_role'])
                ->map(fn ($account) => [
                    'id' => (int) $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'active' => (bool) $account->active,
                    'is_cash' => (bool) $account->is_cash,
                    'trade_control' => $account->trade_role !== null,
                    'updateUrl' => route('chart-of-accounts.update', [$current_team, $account->id]),
                ]),
            'accountTypes' => array_map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ], AccountType::cases()),
            'exportUrl' => route('chart-of-accounts.export', $current_team),
            'importUrl' => route('chart-of-accounts.import', $current_team),
            'storeUrl' => route('chart-of-accounts.store', $current_team),
            'templatesUrl' => route('journal-templates.index', $current_team),
        ]);
    }

    public function store(Request $request, Team $current_team): RedirectResponse
    {
        return $this->save($request, $current_team);
    }

    public function update(Request $request, Team $current_team, int $account): RedirectResponse
    {
        abort_unless(DB::table('journal_accounts')->where('team_id', $current_team->id)
            ->where('id', $account)->exists(), 404);

        return $this->save($request, $current_team, $account);
    }

    private function save(Request $request, Team $team, ?int $accountId = null): RedirectResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper($request->input('code'))]);
        }

        $code = Rule::unique('journal_accounts', 'code')->where('team_id', $team->id);
        if ($accountId !== null) {
            $code->ignore($accountId);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/D', $code],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'active' => ['required', 'boolean'],
            'is_cash' => ['sometimes', 'boolean'],
        ], [
            'code.regex' => 'Gunakan huruf, angka, titik, garis bawah, atau tanda hubung. Awali dengan huruf atau angka.',
            'code.unique' => 'Kode akun sudah digunakan di perusahaan ini.',
            'type.enum' => 'Pilih jenis akun yang tersedia.',
        ]);

        $actorId = $request->user()->id;
        try {
            DB::transaction(function () use ($team, $accountId, $data, $actorId) {
                // Trade and template changes share this lock with account updates.
                Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

                if (($data['is_cash'] ?? false) && $data['type'] !== AccountType::Asset->value) {
                    throw ValidationException::withMessages(['is_cash' => 'Akun kas atau bank harus bertipe aset.']);
                }

                if ($data['type'] !== AccountType::Asset->value) {
                    $data['is_cash'] = false;
                }

                if ($accountId === null) {
                    $id = DB::table('journal_accounts')->insertGetId(['team_id' => $team->id, ...$data]);
                    app(RecordAccountingAudit::class)->record($team->id, $actorId, 'account.created', 'account', $id, null, $data);

                    return;
                }

                $account = DB::table('journal_accounts')->where('team_id', $team->id)->where('id', $accountId)->first();
                abort_if($account === null, 404);
                if ($data['type'] !== $account->type) {
                    app(PeriodLocks::class)->assertAccountTypeChangeAllowed($team->id, $accountId);
                }
                if ($account->trade_role !== null && (! $data['active'] || $data['type'] !== $account->type || ($data['is_cash'] ?? false))) {
                    throw ValidationException::withMessages([
                        'active' => 'Jenis dan status akun kontrol transaksi tidak dapat diubah.',
                    ]);
                }

                if (! $data['active'] && DB::table('journal_template_lines as lines')
                    ->join('journal_templates as templates', 'templates.id', '=', 'lines.journal_template_id')
                    ->where('templates.team_id', $team->id)
                    ->where('templates.active', true)
                    ->where('lines.journal_account_id', $accountId)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'active' => 'Akun masih dipakai template aktif. Ganti akun pada template atau nonaktifkan templatenya terlebih dahulu.',
                    ]);
                }

                DB::table('journal_accounts')->where('team_id', $team->id)
                    ->where('id', $accountId)->update($data);
                app(RecordAccountingAudit::class)->record($team->id, $actorId, 'account.updated', 'account', $accountId,
                    (array) $account, [...(array) $account, ...$data]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Kode akun sudah digunakan di perusahaan ini.']);
        }

        return to_route('chart-of-accounts.index', $team);
    }
}
