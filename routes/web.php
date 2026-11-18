<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\CoaExcelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\JournalDetailController;
use App\Http\Controllers\JournalDraftController;
use App\Http\Controllers\JournalReversalController;
use App\Http\Controllers\JournalTemplateController;
use App\Http\Controllers\OpeningBalancesController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TrialBalanceController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::prefix('impor-data')->group(function () {
            Route::inertia('/', 'Imports')->name('imports.index');
            Route::get('{kind}/template', [DataImportController::class, 'template'])->whereIn('kind', ['coa', 'jurnal', 'saldo-awal'])->name('imports.template');
            Route::post('{kind}/{step}', [DataImportController::class, 'upload'])->whereIn('kind', ['coa', 'jurnal', 'saldo-awal'])->whereIn('step', ['preview', 'confirm'])->name('imports.upload');
        });
        Route::inertia('report', 'Report')->name('reports.index');
        Route::get('report/keuangan', [ReportController::class, 'financial'])->name('reports.financial');
        Route::get('report/laba-rugi', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
        Route::get('report/buku-besar', [ReportController::class, 'ledger'])->name('reports.ledger');
        Route::get('report/neraca-saldo', TrialBalanceController::class)->name('reports.trial');
        Route::get('periode-akuntansi', [PeriodController::class, 'index'])->name('accounting-periods.index');
        Route::post('periode-akuntansi', [PeriodController::class, 'change'])->name('accounting-periods.change');
        Route::get('riwayat-akuntansi', [AuditController::class, 'index'])->name('accounting-audit.index');
        Route::get('draf-jurnal', [JournalDraftController::class, 'index'])->name('journals.drafts');
        Route::get('draf-jurnal/{draft}', [JournalDraftController::class, 'show'])->whereNumber('draft')->name('journals.draft-show');
        Route::post('draf-jurnal/{draft}', [JournalDraftController::class, 'update'])->whereNumber('draft')->name('journals.draft-update');
        Route::post('draf-jurnal/{draft}/ajukan', [JournalDraftController::class, 'submit'])->whereNumber('draft')->name('journals.draft-submit');
        Route::post('draf-jurnal/{draft}/setujui', [JournalDraftController::class, 'approve'])->whereNumber('draft')->name('journals.draft-approve');
        Route::post('draf-jurnal/{draft}/kembalikan', [JournalDraftController::class, 'returnForRevision'])->whereNumber('draft')->name('journals.draft-return');
        Route::post('jurnal/{journal}/balik', JournalReversalController::class)->whereNumber('journal')->name('journals.reverse');
        Route::get('jurnal/{journal}', JournalDetailController::class)->whereNumber('journal')->name('journals.show');
        Route::get('saldo-awal', [OpeningBalancesController::class, 'create'])->middleware(EnsureTeamMembership::class.':admin')->name('opening-balances.create');
        Route::post('saldo-awal', [OpeningBalancesController::class, 'store'])->middleware(EnsureTeamMembership::class.':admin')->name('opening-balances.store');
        Route::get('saldo-awal/{opening}', [OpeningBalancesController::class, 'show'])->whereNumber('opening')->name('opening-balances.show');
        Route::get('jurnal', [JournalController::class, 'index'])->name('journals.index');
        Route::post('jurnal', [JournalController::class, 'store'])->name('journals.store');
        Route::post('jurnal/manual', [JournalController::class, 'manual'])->name('journals.manual');

        Route::prefix('{direction}')->whereIn('direction', ['penjualan', 'pembelian'])->name('trade.')
            ->group(function () {
                Route::get('/', [TradeController::class, 'index'])->name('index');
                Route::get('baru', [TradeController::class, 'create'])->name('create');
                Route::get('{document}', [TradeController::class, 'show'])->whereNumber('document')->name('show');
                Route::post('/', [TradeController::class, 'store'])->name('store');
            });

        Route::prefix('coa')->name('chart-of-accounts.')
            ->middleware(EnsureTeamMembership::class.':admin')
            ->group(function () {
                Route::get('/', [ChartOfAccountsController::class, 'index'])->name('index');
                Route::post('/', [ChartOfAccountsController::class, 'store'])->name('store');
                Route::get('excel', [CoaExcelController::class, 'export'])->name('export');
                Route::post('excel', [CoaExcelController::class, 'import'])->name('import');
                Route::put('{account}', [ChartOfAccountsController::class, 'update'])->whereNumber('account')->name('update');
            });

        Route::prefix('template-jurnal')->name('journal-templates.')
            ->middleware(EnsureTeamMembership::class.':admin')
            ->group(function () {
                Route::get('/', [JournalTemplateController::class, 'index'])->name('index');
                Route::post('/', [JournalTemplateController::class, 'store'])->name('store');
                Route::put('{template}', [JournalTemplateController::class, 'update'])->whereNumber('template')->name('update');
            });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
