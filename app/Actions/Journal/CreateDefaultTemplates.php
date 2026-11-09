<?php

namespace App\Actions\Journal;

use Illuminate\Support\Facades\DB;

class CreateDefaultTemplates
{
    public function __construct(private SeedChartOfAccounts $seedAccounts) {}

    public function handle(int $teamId): void
    {
        $this->seedAccounts->handle($teamId);
        $accounts = DB::table('journal_accounts')->where('team_id', $teamId)
            ->whereIn('code', ['1101', '1102', '4101', '6102'])->pluck('id', 'code');

        foreach ([
            ['pendapatan', 'Pendapatan tunai', '1101', '4101'],
            ['pendapatan_bank', 'Pendapatan lewat bank', '1102', '4101'],
            ['biaya_listrik', 'Biaya listrik', '6102', '1101'],
            ['setor_kas', 'Setor kas', '1102', '1101'],
        ] as [$keyword, $name, $debit, $credit]) {
            $templateId = DB::table('journal_templates')->insertGetId([
                'team_id' => $teamId,
                'keyword' => $keyword,
                'name' => $name,
            ]);

            DB::table('journal_template_lines')->insert([
                ['journal_template_id' => $templateId, 'journal_account_id' => $accounts[$debit], 'side' => 'debit'],
                ['journal_template_id' => $templateId, 'journal_account_id' => $accounts[$credit], 'side' => 'credit'],
            ]);
        }
    }
}
