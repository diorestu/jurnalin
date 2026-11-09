<script module lang="ts">
    import { index as reportIndex } from '@/routes/reports';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: props.currentTeam
            ? [{ title: 'Report', href: reportIndex(props.currentTeam.slug) }]
            : [],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import AppHead from '@/components/AppHead.svelte';
    import { financial, profitLoss, ledger, trial } from '@/routes/reports';

    let { currentTeam }: { currentTeam: Team } = $props();

    const reports = $derived([
        { label: 'Neraca Saldo', description: 'Saldo awal, mutasi, dan saldo akhir debit/kredit seluruh akun.', href: trial(currentTeam.slug) },
        { label: 'Laporan Keuangan', description: 'Neraca aset, liabilitas, dan ekuitas pada akhir bulan atau tahun pilihan.', href: financial(currentTeam.slug) },
        { label: 'Laporan Laba Rugi', description: 'Pendapatan, beban, dan hasil usaha selama periode pilihan.', href: profitLoss(currentTeam.slug) },
        { label: 'Jurnal Besar', description: 'Saldo awal, mutasi debit dan kredit, serta saldo akhir setiap akun.', href: ledger(currentTeam.slug) },
    ]);
</script>

<AppHead title="Report" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header>
        <h1 class="text-3xl leading-tight font-bold tracking-tight">Report</h1>
        <p class="mt-1 text-base text-muted-foreground">Pilih laporan pembukuan perusahaan.</p>
    </header>

    <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-label="Laporan keuangan perusahaan">
        <ul class="divide-y">
            {#each reports as report (report.label)}
                <li>
                    <Link href={report.href} class="flex items-center justify-between gap-4 px-4 py-4 hover:bg-accent focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-ring sm:px-5">
                        <span class="min-w-0">
                            <span class="block text-lg leading-tight font-semibold">{report.label}</span>
                            <span class="block text-base leading-snug text-muted-foreground">{report.description}</span>
                        </span>
                        <ArrowRight class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </Link>
                </li>
            {/each}
        </ul>
    </section>
</main>
