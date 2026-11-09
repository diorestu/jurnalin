<script module lang="ts">
    export const layout = (props: { title: string; indexUrl: string; reportUrl: string }) => ({
        breadcrumbs: [{ title: 'Report', href: props.indexUrl }, { title: props.title, href: props.reportUrl }],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ReportFilters from '@/components/reports/ReportFilters.svelte';
    import { displayDate, rupiah } from '@/components/reports/format';

    type Columns = {
        openingDebit: string;
        openingCredit: string;
        debit: string;
        credit: string;
        closingDebit: string;
        closingCredit: string;
    };
    type Row = Columns & {
        id: number;
        code: string;
        name: string;
        type: string | null;
        active: boolean;
        ledgerUrl: string;
    };

    let { title, start, end, indexUrl, reportUrl, report }: {
        title: string;
        start: string;
        end: string;
        indexUrl: string;
        reportUrl: string;
        report: { rows: Row[]; totals: Columns; balanced: boolean };
    } = $props();

    const columnKeys: (keyof Columns)[] = ['openingDebit', 'openingCredit', 'debit', 'credit', 'closingDebit', 'closingCredit'];
    const amount = (value: string) => value === '0' ? '—' : rupiah(value);
</script>

<AppHead {title} />

<main class="mx-auto flex w-full min-w-0 max-w-7xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="text-base underline underline-offset-4">Kembali ke Report</Link>
        <h1 class="mt-2 text-3xl font-bold">{title}</h1>
        <p class="mt-1 text-muted-foreground">Saldo akun dan mutasi periode {displayDate(start)} – {displayDate(end)}.</p>
    </header>

    <ReportFilters url={reportUrl} {start} {end} />

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-label="Rincian neraca saldo">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-base">
                <thead class="border-b bg-muted/60 text-muted-foreground">
                    <tr>
                        <th scope="col" rowspan="2" class="min-w-56 px-4 py-3 text-left font-medium">Akun</th>
                        <th scope="colgroup" colspan="2" class="border-l px-4 py-2 text-center font-medium">Saldo awal</th>
                        <th scope="colgroup" colspan="2" class="border-l px-4 py-2 text-center font-medium">Mutasi periode</th>
                        <th scope="colgroup" colspan="2" class="border-l px-4 py-2 text-center font-medium">Saldo akhir</th>
                    </tr>
                    <tr>
                        <th scope="col" class="border-l px-4 pb-2 text-right font-medium">Debit</th>
                        <th scope="col" class="px-4 pb-2 text-right font-medium">Kredit</th>
                        <th scope="col" class="border-l px-4 pb-2 text-right font-medium">Debit</th>
                        <th scope="col" class="px-4 pb-2 text-right font-medium">Kredit</th>
                        <th scope="col" class="border-l px-4 pb-2 text-right font-medium">Debit</th>
                        <th scope="col" class="px-4 pb-2 text-right font-medium">Kredit</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#each report.rows as row (row.id)}
                        <tr>
                            <th scope="row" class="max-w-80 break-words px-4 py-3 text-left font-normal">
                                <Link href={row.ledgerUrl} class="underline-offset-4 hover:underline focus-visible:underline">
                                    <span class="block text-sm text-muted-foreground">{row.code}</span>
                                    {row.name}
                                </Link>
                                {#if !row.active}
                                    <span class="mt-0.5 block text-sm text-muted-foreground">Nonaktif</span>
                                {/if}
                            </th>
                            {#each columnKeys as key (key)}
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                    {#if row[key] !== '0'}<Link href={row.ledgerUrl} class="underline decoration-muted-foreground/40 underline-offset-4">{amount(row[key])}</Link>{:else}—{/if}
                                </td>
                            {/each}
                        </tr>
                    {:else}
                        <tr><td colspan="7" class="px-4 py-6 text-center text-muted-foreground">Belum ada saldo atau mutasi akun sampai akhir periode ini.</td></tr>
                    {/each}
                </tbody>
                <tfoot class="border-t bg-muted/60 font-semibold">
                    <tr>
                        <th scope="row" class="px-4 py-3 text-left">Total</th>
                        {#each columnKeys as key (key)}
                            <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{rupiah(report.totals[key])}</td>
                        {/each}
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {#if report.balanced}
        <p role="status">Total debit dan kredit seimbang.</p>
    {:else}
        <p class="text-destructive" role="alert">Total debit dan kredit memiliki selisih. Periksa saldo dan jurnal sumber melalui akun terkait.</p>
    {/if}
    <p class="text-sm text-muted-foreground">Klik nama akun atau nominal untuk membuka buku besar pada periode yang sama. Akun nonaktif dan akun bersaldo nol yang memiliki mutasi tetap ditampilkan.</p>
</main>
