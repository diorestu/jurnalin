<script module lang="ts">
    export const layout = (props: { indexUrl: string; reportUrl: string }) => ({
        breadcrumbs: [{ title: 'Report', href: props.indexUrl }, { title: 'Jurnal Besar', href: props.reportUrl }],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ReportFilters from '@/components/reports/ReportFilters.svelte';
    import { displayDate, ledgerBalance, rupiah } from '@/components/reports/format';

    let { start, end, accountId, accounts, report, indexUrl, reportUrl }: {
        start: string; end: string; accountId: number | null; indexUrl: string; reportUrl: string;
        accounts: { id: number; code: string; name: string; active: boolean }[];
        report: null | {
            opening: string; openingUrl: string | null; debitTotal: string; creditTotal: string; closing: string;
            entries: {
                data: { id: number; journalId: number; journalUrl: string; date: string; title: string; description: string | null; accountCode: string; accountName: string; debit: string; credit: string; balance: string }[];
                prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number;
            };
        };
    } = $props();

    const account = $derived(accounts.find((item) => item.id === accountId));
</script>

<AppHead title="Jurnal Besar" />
<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="text-base underline underline-offset-4">Kembali ke Report</Link>
        <h1 class="mt-2 text-3xl font-bold">Jurnal Besar</h1>
        <p class="mt-1 text-muted-foreground">Buku besar per akun · {displayDate(start)} – {displayDate(end)}.</p>
    </header>
    <ReportFilters url={reportUrl} {start} {end} {accounts} {accountId} />
    {#if report && account}
        <section class="min-w-0 overflow-hidden rounded-xl border bg-card">
            <header class="flex flex-wrap justify-between gap-2 border-b p-4">
                <h2 class="text-lg font-semibold">{account.code} · {account.name}</h2>
                <p>Saldo awal {#if report.openingUrl && report.opening !== '0'}<Link href={report.openingUrl} class="font-semibold underline underline-offset-4 tabular-nums">{ledgerBalance(report.opening)}</Link>{:else}<strong class="tabular-nums">{ledgerBalance(report.opening)}</strong>{/if}</p>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[740px] text-base">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium">Tanggal / jurnal</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium">Keterangan</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Debit</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Kredit</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each report.entries.data as entry (entry.id)}
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{displayDate(entry.date)}<Link href={entry.journalUrl} class="block text-sm underline underline-offset-4">JRN-{String(entry.journalId).padStart(4, '0')}</Link></td>
                                <td class="max-w-80 break-words px-4 py-3">{entry.description || entry.title}<span class="block text-sm text-muted-foreground">{entry.accountCode} · {entry.accountName}</span></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{entry.debit === '0' ? '—' : rupiah(entry.debit)}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{entry.credit === '0' ? '—' : rupiah(entry.credit)}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{ledgerBalance(entry.balance)}</td>
                            </tr>
                        {:else}
                            <tr><td colspan="5" class="px-4 py-5 text-muted-foreground">Belum ada mutasi akun pada periode ini.</td></tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
        <dl class="grid gap-3 rounded-xl border bg-muted/40 p-4 sm:grid-cols-3">
            <div><dt class="text-muted-foreground">Total debit periode</dt><dd class="text-lg font-semibold tabular-nums">{rupiah(report.debitTotal)}</dd></div>
            <div><dt class="text-muted-foreground">Total kredit periode</dt><dd class="text-lg font-semibold tabular-nums">{rupiah(report.creditTotal)}</dd></div>
            <div><dt class="text-muted-foreground">Saldo akhir</dt><dd class="text-lg font-semibold tabular-nums">{ledgerBalance(report.closing)}</dd></div>
        </dl>
        {#if report.entries.prev_page_url || report.entries.next_page_url}
            <nav aria-label="Halaman buku besar" class="flex flex-wrap items-center gap-4">
                {#if report.entries.prev_page_url}<Link href={report.entries.prev_page_url} class="underline">Sebelumnya</Link>{/if}
                <span class="text-muted-foreground">Halaman {report.entries.current_page} dari {report.entries.last_page}</span>
                {#if report.entries.next_page_url}<Link href={report.entries.next_page_url} class="underline">Berikutnya</Link>{/if}
            </nav>
        {/if}
        <p class="text-sm text-muted-foreground">D = saldo debit, K = saldo kredit. Nama dan kode pada rincian mengikuti pencatatan saat jurnal diposting.</p>
    {:else}
        <p>Tambahkan akun melalui Kelola COA untuk melihat buku besar.</p>
    {/if}
</main>
