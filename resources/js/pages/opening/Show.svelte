<script module lang="ts">
    export const layout = (props: { indexUrl: string }) => ({
        breadcrumbs: [{ title: 'Buku Jurnal', href: props.indexUrl }],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import JournalLines from '@/components/journal/JournalLines.svelte';
    import type { JournalLine } from '@/components/journal/types';
    import type { PartnerBalance } from '@/components/opening/types';
    import { displayDate } from '@/components/reports/format';

    let { batch, lines, receivables, payables, inventory, indexUrl }: {
        batch: {
            id: number; date: string; description: string | null; journalId: number;
            journalUrl: string; postedBy: string | null; createdAt: string;
        };
        lines: (JournalLine & { id: number })[];
        receivables: PartnerBalance[];
        payables: PartnerBalance[];
        inventory: { id: number; sku: string; name: string; quantity: number; unit_cost: number | string; total: number | string }[];
        indexUrl: string;
    } = $props();

    const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    const rupiah = (value: number | string) => currency.format(BigInt(value));
    const partnerGroups = $derived([
        { key: 'receivables', title: 'Piutang awal', rows: receivables },
        { key: 'payables', title: 'Utang awal', rows: payables },
    ]);
</script>

<AppHead title="Saldo Awal Terposting" />
<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="underline underline-offset-4">Kembali ke Buku Jurnal</Link>
        <h1 class="mt-2 text-3xl font-bold">Saldo Awal</h1>
        <p class="mt-1 text-muted-foreground">Terposting per {displayDate(batch.date)}.</p>
    </header>

    <section class="rounded-xl border bg-card p-4 sm:p-5" aria-labelledby="opening-info-title">
        <h2 id="opening-info-title" class="font-semibold">Informasi saldo awal</h2>
        <dl class="mt-3 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Tanggal saldo awal</dt>
                <dd class="mt-1 font-medium">{displayDate(batch.date)}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Jurnal pembukaan</dt>
                <dd class="mt-1"><Link href={batch.journalUrl} class="font-medium underline underline-offset-4">JRN-{String(batch.journalId).padStart(4, '0')}</Link></dd>
            </div>
            {#if batch.postedBy}
                <div><dt class="text-muted-foreground">Diposting oleh</dt><dd class="mt-1 font-medium">{batch.postedBy}</dd></div>
            {/if}
            {#if batch.description}
                <div class="min-w-0"><dt class="text-muted-foreground">Keterangan</dt><dd class="mt-1 break-words">{batch.description}</dd></div>
            {/if}
        </dl>
        <p class="mt-4 text-sm text-muted-foreground">Transaksi operasional berikutnya harus bertanggal setelah {displayDate(batch.date)}.</p>
    </section>

    <section class="overflow-hidden rounded-xl border bg-card" aria-labelledby="opening-lines-title">
        <h2 id="opening-lines-title" class="border-b px-4 py-4 font-semibold sm:px-5">Saldo per akun</h2>
        <JournalLines {lines} label="Jurnal saldo awal" />
    </section>

    {#each partnerGroups as group (group.key)}
        <section class="min-w-0 overflow-hidden rounded-xl border bg-card" aria-labelledby={'opening-' + group.key + '-title'}>
            <h2 id={'opening-' + group.key + '-title'} class="border-b px-4 py-4 font-semibold sm:px-5">{group.title}</h2>
            {#if group.rows.length}
                <ul class="divide-y">
                    {#each group.rows as row (row.id)}
                        <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm sm:px-5">
                            <div class="min-w-0">
                                <p class="break-words font-medium">{row.partner}</p>
                                <Link href={row.documentUrl} class="mt-0.5 inline-block break-all underline underline-offset-4">{row.reference}</Link>
                                <p class="mt-1 text-muted-foreground">Tanggal {displayDate(row.invoice_date)}{row.due_date ? ' · Jatuh tempo ' + displayDate(row.due_date) : ''}</p>
                            </div>
                            <p class="font-medium whitespace-nowrap tabular-nums">{rupiah(row.amount)}</p>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p class="px-4 py-4 text-sm text-muted-foreground sm:px-5">Tidak ada rincian {group.title.toLowerCase()}.</p>
            {/if}
        </section>
    {/each}

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card" aria-labelledby="opening-inventory-title">
        <h2 id="opening-inventory-title" class="border-b px-4 py-4 font-semibold sm:px-5">Persediaan awal</h2>
        {#if inventory.length}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium">Barang</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Kuantitas</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Harga perolehan</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each inventory as item (item.id)}
                            <tr>
                                <td class="break-words px-4 py-3">{item.name}<span class="block text-xs text-muted-foreground">{item.sku}</span></td>
                                <td class="px-4 py-3 text-right tabular-nums">{item.quantity}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{rupiah(item.unit_cost)}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{rupiah(item.total)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {:else}
            <p class="px-4 py-4 text-sm text-muted-foreground sm:px-5">Tidak ada rincian persediaan awal.</p>
        {/if}
    </section>
</main>
