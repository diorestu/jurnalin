<script module lang="ts">
    export const layout = (props: { indexUrl: string; title: string }) => ({
        breadcrumbs: [{ title: 'Buku Jurnal', href: props.indexUrl }],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { displayDate } from '@/components/reports/format';
    import { Badge } from '@/components/ui/badge';

    type Status = 'draft' | 'pending' | 'posted';
    let { drafts, indexUrl, title }: {
        drafts: {
            data: {
                id: number; status: Status; date: string; description: string | null;
                createdBy: string | null; createdAt: string; showUrl: string; journalUrl: string | null; modeLabel?: string;
            }[];
            next_page_url: string | null;
            prev_page_url: string | null;
        };
        indexUrl: string;
        title: string;
    } = $props();

    const labels: Record<Status, string> = { draft: 'Draf', pending: 'Menunggu persetujuan', posted: 'Terposting' };
</script>

<AppHead {title} />
<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="underline underline-offset-4">Kembali ke Buku Jurnal</Link>
        <h1 class="mt-2 text-3xl font-bold">{title}</h1>
        <p class="mt-1 text-muted-foreground">Periksa draf, ajukan persetujuan, dan buka jurnal yang sudah diposting.</p>
    </header>

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card" aria-label="Daftar draf jurnal">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/60 text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-medium">Draf jurnal</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium">Tanggal</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium">Status</th>
                        <th scope="col" class="hidden px-4 py-3 text-left font-medium md:table-cell">Pembuat</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#each drafts.data as draft (draft.id)}
                        <tr>
                            <td class="break-words px-4 py-3">
                                <Link href={draft.showUrl} class="font-medium underline underline-offset-4">DRAF-{String(draft.id).padStart(4, '0')}</Link>
                                {#if draft.modeLabel}<p class="mt-0.5 text-xs text-muted-foreground">{draft.modeLabel}</p>{/if}
                                {#if draft.description}<p class="mt-0.5 text-muted-foreground">{draft.description}</p>{/if}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{displayDate(draft.date)}</td>
                            <td class="px-4 py-3">
                                <Badge variant={draft.status === 'pending' ? 'secondary' : 'outline'}>{labels[draft.status]}</Badge>
                                {#if draft.journalUrl}
                                    <Link href={draft.journalUrl} class="mt-1 block underline underline-offset-4">Buka jurnal</Link>
                                {/if}
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{draft.createdBy ?? '—'}</td>
                        </tr>
                    {:else}
                        <tr><td colspan="4" class="px-4 py-5 text-muted-foreground">Belum ada draf jurnal. Buat draf melalui halaman Buku Jurnal.</td></tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </section>

    {#if drafts.prev_page_url || drafts.next_page_url}
        <nav class="flex flex-wrap gap-4" aria-label="Halaman draf jurnal">
            {#if drafts.prev_page_url}<Link href={drafts.prev_page_url} class="underline underline-offset-4">Sebelumnya</Link>{/if}
            {#if drafts.next_page_url}<Link href={drafts.next_page_url} class="underline underline-offset-4">Berikutnya</Link>{/if}
        </nav>
    {/if}
</main>
