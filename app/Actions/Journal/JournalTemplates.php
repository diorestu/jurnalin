<?php

namespace App\Actions\Journal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type TemplateLine array{side: string, journal_account_id: int, account_code: string, account_name: string}
 * @phpstan-type Template array{id: int, keyword: string, name: string, lines: list<TemplateLine>, signature: string}
 */
class JournalTemplates
{
    /** @return Collection<int, Template> */
    public function forTeam(int $teamId, ?int $templateId = null): Collection
    {
        return DB::table('journal_templates as templates')
            ->join('journal_template_lines as lines', 'lines.journal_template_id', '=', 'templates.id')
            ->join('journal_accounts as accounts', 'accounts.id', '=', 'lines.journal_account_id')
            ->where('templates.team_id', $teamId)
            ->where('accounts.team_id', $teamId)
            ->where('templates.active', true)
            ->where('accounts.active', true)
            ->when($templateId !== null, fn ($query) => $query->where('templates.id', $templateId))
            ->orderBy('templates.id')
            ->orderByRaw("case when lines.side = 'debit' then 0 else 1 end")
            ->get([
                'templates.id',
                'templates.keyword',
                'templates.name',
                'lines.side',
                'accounts.id as journal_account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
            ])
            ->groupBy('id')
            ->filter(fn (Collection $rows) => $rows->count() === 2
                && $rows->pluck('journal_account_id')->unique()->count() === 2)
            ->map(function (Collection $rows) {
                $template = $rows->first();
                $lines = array_values($rows->map(fn ($row) => [
                    'side' => (string) $row->side,
                    'journal_account_id' => (int) $row->journal_account_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                ])->all());

                return [
                    'id' => (int) $template->id,
                    'keyword' => (string) $template->keyword,
                    'name' => (string) $template->name,
                    'lines' => $lines,
                    'signature' => hash('sha256', json_encode([$template->name, $lines], JSON_THROW_ON_ERROR)),
                ];
            })
            ->values();
    }
}
