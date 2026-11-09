<?php

namespace App\Http\Controllers;

use App\Actions\Accounting\RecordAccountingAudit;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JournalTemplateController extends Controller
{
    public function index(Team $current_team): Response
    {
        $teamTemplates = DB::table('journal_templates')
            ->where('team_id', $current_team->id)
            ->orderBy('name')
            ->get(['id', 'keyword', 'name', 'active']);

        $lines = DB::table('journal_template_lines')
            ->whereIn('journal_template_id', $teamTemplates->pluck('id'))
            ->get(['journal_template_id', 'side', 'journal_account_id'])
            ->groupBy('journal_template_id');

        return Inertia::render('journal/Templates', [
            'accounts' => DB::table('journal_accounts')
                ->where('team_id', $current_team->id)
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'templates' => $teamTemplates->map(function ($template) use ($lines, $current_team) {
                $accounts = $lines->get($template->id, collect())->pluck('journal_account_id', 'side');

                return [
                    'id' => $template->id,
                    'keyword' => $template->keyword,
                    'name' => $template->name,
                    'active' => (bool) $template->active,
                    'debit_account_id' => $accounts->get('debit'),
                    'credit_account_id' => $accounts->get('credit'),
                    'update_url' => route('journal-templates.update', [$current_team, $template->id]),
                ];
            }),
            'storeUrl' => route('journal-templates.store', $current_team),
            'coaUrl' => route('chart-of-accounts.index', $current_team),
            'journalUrl' => route('journals.index', $current_team),
        ]);
    }

    public function store(Request $request, Team $current_team): RedirectResponse
    {
        return $this->save($request, $current_team);
    }

    public function update(Request $request, Team $current_team, int $template): RedirectResponse
    {
        abort_unless(DB::table('journal_templates')
            ->where('team_id', $current_team->id)->where('id', $template)->exists(), 404);

        return $this->save($request, $current_team, $template);
    }

    private function save(Request $request, Team $team, ?int $templateId = null): RedirectResponse
    {
        $keyword = Rule::unique('journal_templates', 'keyword')->where('team_id', $team->id);
        if ($templateId !== null) {
            $keyword->ignore($templateId);
        }

        try {
            DB::transaction(function () use ($request, $keyword, $team, $templateId) {
                Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

                $account = Rule::exists('journal_accounts', 'id')
                    ->where('team_id', $team->id)->where('active', true);
                $data = $request->validate([
                    'keyword' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $keyword],
                    'name' => ['required', 'string', 'max:120'],
                    'debit_account_id' => ['required', 'integer', $account],
                    'credit_account_id' => ['required', 'integer', $account],
                    'active' => ['required', 'boolean'],
                ], [
                    'keyword.regex' => 'Gunakan huruf kecil, angka, dan garis bawah untuk kata kunci.',
                    'keyword.unique' => 'Kata kunci sudah digunakan di perusahaan ini.',
                ]);

                if ((int) $data['debit_account_id'] === (int) $data['credit_account_id']) {
                    throw ValidationException::withMessages([
                        'credit_account_id' => 'Pilih akun debit dan kredit yang berbeda.',
                    ]);
                }

                $before = $templateId === null ? null : [
                    'template' => (array) DB::table('journal_templates')->where('id', $templateId)->first(),
                    'lines' => DB::table('journal_template_lines')->where('journal_template_id', $templateId)->get()->map(fn ($line) => (array) $line)->all(),
                ];
                $values = [
                    'keyword' => $data['keyword'],
                    'name' => $data['name'],
                    'active' => $data['active'],
                ];

                if ($templateId === null) {
                    $templateId = DB::table('journal_templates')->insertGetId(['team_id' => $team->id, ...$values]);
                } else {
                    DB::table('journal_templates')->where('team_id', $team->id)
                        ->where('id', $templateId)->update($values);
                }

                DB::table('journal_template_lines')->upsert([
                    ['journal_template_id' => $templateId, 'journal_account_id' => $data['debit_account_id'], 'side' => 'debit'],
                    ['journal_template_id' => $templateId, 'journal_account_id' => $data['credit_account_id'], 'side' => 'credit'],
                ], ['journal_template_id', 'side'], ['journal_account_id']);
                app(RecordAccountingAudit::class)->record($team->id, $request->user()->id,
                    $before === null ? 'template.created' : 'template.updated', 'journal_template', $templateId, $before, $data);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['keyword' => 'Kata kunci sudah digunakan di perusahaan ini.']);
        }

        return to_route('journal-templates.index', $team);
    }
}
