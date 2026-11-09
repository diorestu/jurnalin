<script module lang="ts">
    export const layout = (props: { indexUrl: string; reportUrl: string }) => ({
        breadcrumbs: [{ title: 'Report', href: props.indexUrl }, { title: 'Laporan Laba Rugi', href: props.reportUrl }],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AccountBalances from '@/components/reports/AccountBalances.svelte';
    import ReportFilters from '@/components/reports/ReportFilters.svelte';
    import { displayDate, rupiah, type AccountBalance } from '@/components/reports/format';

    let { start, end, report, indexUrl, reportUrl }: {
        start: string; end: string; indexUrl: string; reportUrl: string;
        report: { revenue: AccountBalance[]; expense: AccountBalance[]; revenueTotal: string; expenseTotal: string; net: string; unclassified: number };
    } = $props();
</script>

<AppHead title="Laporan Laba Rugi" />
<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="text-base underline underline-offset-4">Kembali ke Report</Link>
        <h1 class="mt-2 text-3xl font-bold">Laporan Laba Rugi</h1>
        <p class="mt-1 text-muted-foreground">{displayDate(start)} – {displayDate(end)}.</p>
    </header>
    <ReportFilters url={reportUrl} {start} {end} />
    {#if report.unclassified > 0}
        <p class="text-destructive" role="alert">{report.unclassified} baris jurnal memakai akun tanpa jenis COA. Lengkapi jenis akun agar seluruh transaksi masuk ke laporan.</p>
    {/if}
    <div class="grid min-w-0 items-start gap-5 lg:grid-cols-2">
        <AccountBalances title="Pendapatan" rows={report.revenue} total={report.revenueTotal} />
        <AccountBalances title="Beban" rows={report.expense} total={report.expenseTotal} />
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/40 p-4">
        <h2 class="text-lg font-semibold">{BigInt(report.net) < 0n ? 'Rugi bersih' : 'Laba bersih'}</h2>
        <p class="text-2xl font-bold tabular-nums">{rupiah(report.net)}</p>
    </div>
    <p class="text-sm text-muted-foreground">Menggunakan jurnal terposting dan jenis COA saat ini. HPP dan pajak tercakup sesuai jurnal yang sudah dicatat.</p>
</main>
