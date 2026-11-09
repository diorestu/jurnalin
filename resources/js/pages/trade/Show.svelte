<script module lang="ts">
    import type { Detail } from '@/components/trade/documents';

    export const layout = (props: { title: string; indexUrl: string; document: Detail }) => ({
        breadcrumbs: [
            { title: props.title, href: props.indexUrl },
            { title: props.document.number, href: props.document.url },
        ],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import AppHead from '@/components/AppHead.svelte';
    import {
        formatDate,
        openAmountLabel,
        partnerLabel,
        rupiah,
        type Direction,
        type Summary,
    } from '@/components/trade/documents';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';

    let {
        direction,
        title,
        indexUrl,
        document,
        actions,
        related,
    }: {
        direction: Direction;
        title: string;
        indexUrl: string;
        document: Detail;
        actions: { label: string; url: string }[];
        related: Summary[];
    } = $props();
</script>

<AppHead title={`${document.number} · ${title}`} />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <Link href={indexUrl} class="inline-flex items-center gap-1.5 text-sm underline underline-offset-4">
                <ArrowLeft class="size-4" aria-hidden="true" /> Kembali ke daftar
            </Link>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">{document.number}</h1>
                <Badge variant="outline">{document.kindLabel}</Badge>
            </div>
        </div>
        {#if actions.length}
            <div class="flex flex-wrap gap-2">
                {#each actions as action, index (action.url)}
                    <Button asChild variant={index === 0 ? 'default' : 'outline'}>
                        {#snippet children(props)}
                            <Link {...props} href={action.url}>{action.label}</Link>
                        {/snippet}
                    </Button>
                {/each}
            </div>
        {/if}
    </header>

    <section class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5" aria-labelledby="trade-document-heading">
        <h2 id="trade-document-heading" class="font-semibold">Informasi dokumen</h2>
        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
            <div class="min-w-0">
                <dt class="text-muted-foreground">{partnerLabel(direction)}</dt>
                <dd class="mt-1 break-words font-medium">{document.partner}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Tanggal</dt>
                <dd class="mt-1 font-medium">{formatDate(document.date)}</dd>
            </div>
            {#if document.due_date}
                <div>
                    <dt class="text-muted-foreground">Jatuh tempo</dt>
                    <dd class="mt-1 font-medium">{formatDate(document.due_date)}</dd>
                </div>
            {/if}
            {#if document.source}
                <div>
                    <dt class="text-muted-foreground">Dokumen sumber</dt>
                    <dd class="mt-1"><Link href={document.source.url} class="font-medium underline underline-offset-4">{document.source.number}</Link></dd>
                </div>
            {/if}
            {#if document.reference}
                <div class="min-w-0">
                    <dt class="text-muted-foreground">Referensi</dt>
                    <dd class="mt-1 break-words font-medium">{document.reference}</dd>
                </div>
            {/if}
            {#if document.journalId !== null && document.journalUrl}
                <div>
                    <dt class="text-muted-foreground">Nomor jurnal</dt>
                    <dd class="mt-1 font-medium"><Link href={document.journalUrl} class="underline underline-offset-4">JRN-{String(document.journalId).padStart(4, '0')}</Link></dd>
                </div>
            {/if}
            {#if document.notes}
                <div class="col-span-full min-w-0">
                    <dt class="text-muted-foreground">Catatan</dt>
                    <dd class="mt-1 break-words whitespace-pre-wrap">{#if document.notesRuns.length}{#each document.notesRuns as run}<span class:font-semibold={run.attributes?.bold} class:italic={run.attributes?.italic} class:underline={run.attributes?.underline}>{run.insert}</span>{/each}{:else}{document.notes}{/if}</dd>
                </div>
            {/if}
        </dl>
    </section>

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="trade-amount-heading">
        <h2 id="trade-amount-heading" class="px-4 py-4 font-semibold sm:px-5">
            {document.lines.length ? 'Rincian barang atau jasa' : 'Rincian nominal'}
        </h2>
        {#if document.lines.length}
            <div class="overflow-x-auto">
                <table class="w-full border-t text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-left font-medium sm:px-5">Barang atau jasa</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Qty</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Harga satuan</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium sm:px-5">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each document.lines as line (line.id)}
                            <tr>
                                <td class="px-4 py-3 sm:px-5">{line.name}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{line.quantity}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{rupiah(line.unit_price)}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums sm:px-5">{rupiah(line.total)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
        <dl class="flex flex-wrap gap-x-8 gap-y-3 border-t bg-muted/40 px-4 py-4 text-sm sm:px-5">
            <div>
                <dt class="text-muted-foreground">Total dokumen</dt>
                <dd class="mt-1 font-semibold tabular-nums">{rupiah(document.total)}</dd>
            </div>
            {#if document.openAmount !== null}
                <div>
                    <dt class="text-muted-foreground">{openAmountLabel(direction, document.openAmount)}</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{rupiah(document.openAmount)}</dd>
                </div>
            {/if}
            {#if BigInt(document.appliedAdvance) > 0n}
                <div>
                    <dt class="text-muted-foreground">Uang muka yang digunakan</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{rupiah(document.appliedAdvance)}</dd>
                </div>
            {/if}
            {#if document.availableAmount !== null}
                <div>
                    <dt class="text-muted-foreground">Sisa tersedia</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{rupiah(document.availableAmount)}</dd>
                </div>
            {/if}
        </dl>
    </section>

    {#if related.length}
        <section class="rounded-xl border bg-card text-card-foreground" aria-labelledby="trade-related-heading">
            <h2 id="trade-related-heading" class="px-4 py-4 font-semibold sm:px-5">Dokumen terkait</h2>
            <ul class="divide-y border-t">
                {#each related as entry (entry.id)}
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm sm:px-5">
                        <div class="min-w-0">
                            <Link href={entry.url} class="font-medium underline-offset-4 hover:underline">{entry.number}</Link>
                            <p class="text-xs text-muted-foreground">{entry.kindLabel} · {formatDate(entry.date)}</p>
                        </div>
                        <span class="tabular-nums">{rupiah(entry.total)}</span>
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</main>
