<script module lang="ts">
    import { index } from '@/routes/journals';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{
            title: 'Buku Jurnal',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        }],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { page } from '@inertiajs/svelte';
    import { show as showJournal } from '@/routes/journals';
    import JournalDraft from '@/components/journal/JournalDraft.svelte';
    import JournalManual from '@/components/journal/JournalManual.svelte';
    import { Button } from '@/components/ui/button';
    import type { JournalTemplate, PostedJournal } from '@/components/journal/types';

    let {
        canPost,
        draftsUrl,
        journalTemplates,
        journalAccounts,
        manualStoreUrl,
        journalTemplatesUrl,
        journals,
        journalStoreUrl,
        draftKey,
        today,
    }: {
        canPost: boolean;
        draftsUrl: string;
        journalTemplates: JournalTemplate[];
        journalAccounts: { id: number; code: string; name: string }[];
        manualStoreUrl: string;
        journalTemplatesUrl: string | null;
        journals: { data: PostedJournal[]; prev_page_url: string | null; next_page_url: string | null };
        journalStoreUrl: string;
        draftKey: string;
        today: string;
    } = $props();

    const currentTeam = $derived(page.props.currentTeam as Team);
    let method = $state<'template' | 'manual'>('template');

    const money = new Intl.NumberFormat('id-ID');
    const totals = $derived(journals.data.reduce((total, journal) => {
        for (const line of journal.lines) {
            total[line.side] += BigInt(line.amount ?? 0);
        }
        return total;
    }, { debit: 0n, credit: 0n }));

    const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' });
</script>

<AppHead title="Buku Jurnal Umum" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Buku Jurnal Umum</h1>
            <p class="mt-1 text-sm text-muted-foreground">Catat transaksi dengan template atau isi akun debit dan kredit secara manual.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Link href={draftsUrl} class="inline-flex min-h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent">Draf & Persetujuan</Link>
        {#if journalTemplatesUrl}
            <Link href={journalTemplatesUrl} class="inline-flex min-h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent">
                Kelola template
            </Link>
        {/if}
        </div>
    </header>

    {#key journalStoreUrl}
        <div class="flex flex-wrap gap-2" role="group" aria-label="Metode input jurnal">
            <Button type="button" variant={method === 'template' ? 'default' : 'outline'}
                aria-pressed={method === 'template'} onclick={() => { method = 'template'; }}>Dengan template</Button>
            <Button type="button" variant={method === 'manual' ? 'default' : 'outline'}
                aria-pressed={method === 'manual'} onclick={() => { method = 'manual'; }}>Manual</Button>
        </div>
        <div hidden={method !== 'template'}>
            <JournalDraft templates={journalTemplates} storeUrl={journalStoreUrl} {canPost} {draftKey} {today} />
        </div>
        <div hidden={method !== 'manual'}>
            <JournalManual accounts={journalAccounts} storeUrl={manualStoreUrl} {canPost} {draftKey} {today} />
        </div>
    {/key}

    <section class="flex min-w-0 flex-col gap-3" aria-labelledby="posted-journals-title">
        <h2 id="posted-journals-title" class="font-semibold">Jurnal terposting</h2>

        <div class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground">
            <div class="overflow-x-auto">
                <table class="w-full text-sm sm:min-w-[640px]" aria-labelledby="posted-journals-title">
                    <colgroup>
                        <col class="hidden w-36 sm:table-column" />
                        <col />
                        <col class="w-[30%] sm:w-1/5" />
                        <col class="w-[30%] sm:w-1/5" />
                    </colgroup>
                    <thead class="border-b bg-muted/60 text-muted-foreground">
                        <tr>
                            <th scope="col" class="hidden w-36 px-4 py-2.5 text-left font-medium sm:table-cell">Tanggal / jurnal</th>
                            <th scope="col" class="px-3 py-2.5 text-left font-medium sm:px-4">Keterangan / akun</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-medium sm:px-4">Debit (Rp)</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-medium sm:px-4">Kredit (Rp)</th>
                        </tr>
                    </thead>

                    {#each journals.data as entry (entry.id)}
                        {@const number = 'JRN-' + String(entry.id).padStart(4, '0')}
                        {@const date = dateFormat.format(new Date(entry.date))}
                        <tbody class="border-b last:border-b-0" aria-label={number}>
                            <tr>
                                <th scope="rowgroup" rowspan={entry.lines.length + 1} class="hidden px-4 py-3 text-left align-top font-normal whitespace-nowrap sm:table-cell">
                                    <time datetime={entry.date}>{date}</time>
                                    <Link href={showJournal([currentTeam.slug, entry.id])} class="mt-0.5 block text-xs underline underline-offset-4 tabular-nums">{number}</Link>
                                </th>
                                <td colspan="3" class="px-3 pt-3 pb-1 sm:px-4">
                                    <span class="mb-0.5 block text-xs text-muted-foreground sm:hidden">{date} · <Link href={showJournal([currentTeam.slug, entry.id])} class="underline underline-offset-4">{number}</Link></span>
                                    {#if entry.description}
                                        <span class="block break-words text-muted-foreground">{entry.description}</span>
                                    {/if}
                                </td>
                            </tr>
                            {#each entry.lines as line, index (index)}
                                <tr>
                                    <td class="px-3 py-2 sm:px-4">
                                        <span class={line.side === 'credit' ? 'block pl-3' : 'block'}>
                                            <span class="mr-1.5 inline-block text-xs tabular-nums text-muted-foreground">{line.account_code}</span>
                                            {line.account_name}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-4">
                                        {line.side === 'debit' ? money.format(line.amount ?? 0) : '—'}
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-4">
                                        {line.side === 'credit' ? money.format(line.amount ?? 0) : '—'}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    {:else}
                        <tbody>
                            <tr>
                                <td class="hidden sm:table-cell"></td>
                                <td colspan="3" class="px-4 py-5 text-center text-muted-foreground">Belum ada jurnal terposting.</td>
                            </tr>
                        </tbody>
                    {/each}

                    {#if journals.data.length}
                        <tfoot class="border-t bg-muted/60 font-medium">
                            <tr>
                                <td class="hidden sm:table-cell"></td>
                                <th scope="row" class="px-3 py-3 text-left font-medium sm:px-4">Total halaman</th>
                                <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums sm:px-4">{money.format(totals.debit)}</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums sm:px-4">{money.format(totals.credit)}</td>
                            </tr>
                        </tfoot>
                    {/if}
                </table>
            </div>
        </div>

        {#if journals.prev_page_url || journals.next_page_url}
            <nav class="flex flex-wrap gap-4 text-sm" aria-label="Halaman buku jurnal">
                {#if journals.prev_page_url}
                    <Link href={journals.prev_page_url} class="underline">Sebelumnya</Link>
                {/if}
                {#if journals.next_page_url}
                    <Link href={journals.next_page_url} class="underline">Berikutnya</Link>
                {/if}
            </nav>
        {/if}
    </section>
</main>
