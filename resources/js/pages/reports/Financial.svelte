<script module lang="ts">
    export const layout = (props: { indexUrl: string; reportUrl: string }) => ({
        breadcrumbs: [{ title: 'Report', href: props.indexUrl }, { title: 'Laporan Keuangan', href: props.reportUrl }],
    });
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ReportFilters from '@/components/reports/ReportFilters.svelte';
    import { displayDate, rupiah, type AccountBalance } from '@/components/reports/format';
    import type { Team } from '@/types';

    let { month, year, period: periodType, asOf, report, indexUrl, reportUrl }: {
        month: string; year: string; period: 'monthly' | 'yearly'; asOf: string; indexUrl: string; reportUrl: string;
        report: {
            assets: AccountBalance[]; liabilities: AccountBalance[]; equity: AccountBalance[];
            assetsTotal: string; liabilitiesTotal: string; equityTotal: string;
            earnings: string; equityWithEarnings: string; liabilitiesAndEquity: string;
            difference: string; unclassified: number; earningsUrl: string;
        };
    } = $props();

    const company = $derived((page.props.currentTeam as Team).name);
    const monthFormat = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    const period = $derived(periodType === 'yearly' ? 'tahun ' + year : monthFormat.format(new Date(month + '-01')));
    const groups = $derived([
        { title: 'Aset', rows: report.assets, total: report.assetsTotal },
        { title: 'Liabilitas', rows: report.liabilities, total: report.liabilitiesTotal },
        {
            title: 'Ekuitas',
            rows: [...report.equity, { id: -1, code: '—', name: 'Laba/rugi belum ditutup', type: 'equity', balance: report.earnings, ledgerUrl: report.earningsUrl }],
            total: report.equityWithEarnings,
        },
    ]);
</script>

<AppHead title="Laporan Keuangan" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="text-base underline underline-offset-4">Kembali ke Report</Link>
        <h1 class="mt-2 text-3xl font-bold">Laporan Keuangan</h1>
        <p class="mt-1 text-muted-foreground">Neraca akhir periode {period}.</p>
    </header>

    <ReportFilters url={reportUrl} {month} {year} period={periodType} />

    {#if report.unclassified > 0}
        <p class="text-destructive" role="alert">{report.unclassified} baris jurnal memakai akun tanpa jenis COA. Lengkapi jenis akunnya agar seluruh saldo masuk ke laporan.</p>
    {/if}

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="financial-statement-title">
        <header class="flex flex-col gap-1 border-b px-4 py-5 text-center sm:px-5">
            <p class="text-xl font-bold break-words">{company}</p>
            <h2 id="financial-statement-title" class="text-lg font-semibold">Laporan Posisi Keuangan</h2>
            <p class="text-base">Per {displayDate(asOf)}</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full text-base" aria-labelledby="financial-statement-title">
                <thead class="border-b bg-muted/60 text-muted-foreground">
                    <tr>
                        <th scope="col" class="w-1/5 px-3 py-2.5 text-left font-medium sm:px-5">Kode</th>
                        <th scope="col" class="px-3 py-2.5 text-left font-medium sm:px-4">Nama akun</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium sm:px-5">Saldo (IDR)</th>
                    </tr>
                </thead>
                {#each groups as group (group.title)}
                    <tbody aria-label={group.title}>
                        <tr class="border-b bg-muted/30">
                            <th scope="rowgroup" colspan="3" class="px-3 py-2.5 text-left font-semibold sm:px-5">{group.title}</th>
                        </tr>
                        {#each group.rows as row (row.id)}
                            <tr class="border-b">
                                <td class="break-all px-3 py-2 text-muted-foreground sm:px-5">{row.code}</td>
                                <td class="break-words px-3 py-2 sm:px-4">
                                    {#if row.ledgerUrl}<Link href={row.ledgerUrl} class="underline decoration-muted-foreground/40 underline-offset-4">{row.name}</Link>{:else}{row.name}{/if}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-5">
                                    {#if row.ledgerUrl}<Link href={row.ledgerUrl} class="underline decoration-muted-foreground/40 underline-offset-4">{rupiah(row.balance)}</Link>{:else}{rupiah(row.balance)}{/if}
                                </td>
                            </tr>
                        {:else}
                            <tr class="border-b"><td colspan="3" class="px-3 py-3 text-muted-foreground sm:px-5">Belum ada saldo pada akun {group.title.toLowerCase()}.</td></tr>
                        {/each}
                        <tr class="border-b font-semibold">
                            <th scope="row" colspan="2" class="px-3 py-3 text-left font-semibold sm:px-5">Total {group.title.toLowerCase()}</th>
                            <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums sm:px-5">{rupiah(group.total)}</td>
                        </tr>
                    </tbody>
                {/each}
                <tfoot class="bg-muted/60">
                    <tr>
                        <th scope="row" colspan="2" class="px-3 py-3 text-left font-bold sm:px-5">Total liabilitas dan ekuitas</th>
                        <td class="px-3 py-3 text-right font-bold whitespace-nowrap tabular-nums sm:px-5">{rupiah(report.liabilitiesAndEquity)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {#if report.difference !== '0'}
        <p class="text-destructive" role="alert">Neraca memiliki selisih {rupiah(report.difference)}. Periksa klasifikasi COA dan jurnal sumber.</p>
    {:else if report.unclassified === 0}
        <p>Neraca seimbang.</p>
    {/if}
    <p class="text-sm text-muted-foreground">Saldo mencakup jurnal sampai akhir periode pilihan dan mengikuti jenis COA saat ini. Laba/rugi belum ditutup berasal dari pendapatan dan beban yang belum dipindahkan ke ekuitas melalui jurnal penutupan.</p>
</main>
