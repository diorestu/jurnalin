<script module lang="ts">
    export const layout = (props: { title: string; indexUrl: string }) => ({
        breadcrumbs: [{ title: props.title, href: props.indexUrl }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import {
        formatDate,
        openAmountLabel,
        partnerLabel,
        rupiah,
        type Direction,
        type Kind,
        type Summary,
    } from '@/components/trade/documents';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { NativeSelect } from '@/components/ui/select';

    let {
        direction,
        title,
        documents,
        kinds,
        filters,
        indexUrl,
        createUrl,
    }: {
        direction: Direction;
        title: string;
        documents: {
            data: Summary[];
            next_page_url: string | null;
            prev_page_url: string | null;
        };
        kinds: Kind[];
        filters: { kind: string; search: string };
        indexUrl: string;
        createUrl: string;
    } = $props();

    let search = $state(untrack(() => filters.search));
    let kind = $state(untrack(() => filters.kind));
    let loading = $state(false);

    function filter(event: SubmitEvent) {
        event.preventDefault();
        if (loading) return;

        router.get(indexUrl, {
            ...(search.trim() && { search: search.trim() }),
            ...(kind && { kind }),
        }, {
            preserveScroll: true,
            onStart: () => { loading = true; },
            onFinish: () => { loading = false; },
        });
    }
</script>

<AppHead {title} />

<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{title}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {direction === 'penjualan'
                    ? 'Pantau dokumen penjualan dan tagihan pelanggan.'
                    : 'Pantau dokumen pembelian dan tagihan pemasok.'}
            </p>
        </div>
        <Button asChild>
            {#snippet children(props)}
                <Link {...props} href={createUrl}>
                    <Plus class="size-4" aria-hidden="true" /> Buat dokumen
                </Link>
            {/snippet}
        </Button>
    </header>

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-label="Daftar dokumen">
        <form class="flex flex-wrap items-end gap-3 p-4 sm:px-5" onsubmit={filter}>
            <div class="flex w-full flex-col gap-1.5 sm:max-w-xs">
                <Label for="trade-search">Cari dokumen</Label>
                <Input id="trade-search" type="search" bind:value={search} placeholder={`Nomor atau nama ${partnerLabel(direction).toLowerCase()}`} />
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-1.5 sm:max-w-52">
                <Label for="trade-kind">Jenis dokumen</Label>
                <NativeSelect id="trade-kind" bind:value={kind}>
                    <option value="">Semua jenis</option>
                    {#each kinds as option (option.value)}
                        <option value={option.value}>{option.label}</option>
                    {/each}
                </NativeSelect>
            </div>
            <Button type="submit" variant="outline" disabled={loading}>
                <Search class="size-4" aria-hidden="true" /> {loading ? 'Mencari…' : 'Cari'}
            </Button>
            {#if filters.search || filters.kind}
                <Link href={indexUrl} class="py-2 text-sm underline underline-offset-4">Hapus filter</Link>
            {/if}
        </form>

        {#if documents.data.length}
            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-t text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-left font-medium sm:px-5">Dokumen</th>
                            <th scope="col" class="hidden w-[26%] px-4 py-2.5 text-left font-medium md:table-cell">{partnerLabel(direction)}</th>
                            <th scope="col" class="hidden w-[16%] px-4 py-2.5 text-left font-medium lg:table-cell">Tanggal</th>
                            <th scope="col" class="w-[44%] px-4 py-2.5 text-right font-medium md:w-[24%] sm:px-5">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each documents.data as document (document.id)}
                            <tr class="align-top">
                                <td class="break-words px-4 py-3 sm:px-5">
                                    <Link href={document.url} class="font-semibold underline-offset-4 hover:underline">{document.number}</Link>
                                    <div class="mt-1"><Badge variant="outline">{document.kindLabel}</Badge></div>
                                    <p class="mt-1 text-muted-foreground md:hidden">{document.partner}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground lg:hidden">{formatDate(document.date)}</p>
                                </td>
                                <td class="hidden break-words px-4 py-3 md:table-cell">{document.partner}</td>
                                <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">{formatDate(document.date)}</td>
                                <td class="break-words px-4 py-3 text-right tabular-nums sm:px-5">
                                    <p class="font-medium">{rupiah(document.total)}</p>
                                    {#if document.openAmount !== null}
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {openAmountLabel(direction, document.openAmount)} {rupiah(document.openAmount)}
                                        </p>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {:else}
            <p class="border-t px-4 py-7 text-center text-sm text-muted-foreground sm:px-5">
                {filters.search || filters.kind ? 'Dokumen yang cocok tidak ditemukan.' : 'Belum ada dokumen.'}
            </p>
        {/if}
    </section>

    {#if documents.prev_page_url || documents.next_page_url}
        <nav class="flex flex-wrap gap-4 text-sm" aria-label="Halaman daftar dokumen">
            {#if documents.prev_page_url}
                <Link href={documents.prev_page_url} class="underline underline-offset-4">Sebelumnya</Link>
            {/if}
            {#if documents.next_page_url}
                <Link href={documents.next_page_url} class="underline underline-offset-4">Berikutnya</Link>
            {/if}
        </nav>
    {/if}
</main>
