<script module lang="ts">
    export const layout = (props: { indexUrl: string; journalUrl: string; journal: { id: number } }) => ({
        breadcrumbs: [
            { title: 'Buku Jurnal', href: props.indexUrl },
            { title: 'JRN-' + String(props.journal.id).padStart(4, '0'), href: props.journalUrl },
        ],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppHead from '@/components/AppHead.svelte';
    import JournalLines from '@/components/journal/JournalLines.svelte';
    import type { JournalLine } from '@/components/journal/types';
    import { displayDate } from '@/components/reports/format';

    let { indexUrl, journal, sources, reversal }: {
        indexUrl: string;
        journalUrl: string;
        journal: {
            id: number;
            date: string;
            templateName: string;
            description: string | null;
            postedBy: string | null;
            approvedBy: string | null; approvedAt: string | null;
            createdAt: string;
            lines: JournalLine[];
            debitTotal: string;
            creditTotal: string;
            balanced: boolean;
        };
        sources: { label: string; url: string }[];
        reversal: { allowed: boolean; url: string; date: string; requestId: string; originalUrl: string | null; reversedUrl: string | null; reason: string | null };
    } = $props();

    let reverseDate = $state(untrack(() => reversal.date));
    let reason = $state('');
    let processing = $state(false);
    let error = $state('');
    function reverse(event: SubmitEvent) {
        event.preventDefault();
        if (processing) return;
        router.post(reversal.url, { date: reverseDate, reason, request_id: reversal.requestId }, {
            preserveScroll: true,
            onStart: () => { processing = true; error = ''; },
            onError: errors => { error = String(Object.values(errors)[0]); },
            onFinish: () => { processing = false; },
        });
    }

    const number = $derived('JRN-' + String(journal.id).padStart(4, '0'));
    const postedFormat = new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta',
    });
</script>

<AppHead title={number} />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="text-base underline underline-offset-4">Kembali ke Buku Jurnal</Link>
        <h1 class="mt-2 text-3xl font-bold">{number}</h1>
        <p class="mt-1 text-muted-foreground">Rincian jurnal terposting.</p>
    </header>

    <section class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5" aria-labelledby="journal-metadata-heading">
        <h2 id="journal-metadata-heading" class="text-lg font-semibold">Informasi jurnal</h2>
        <dl class="mt-4 grid grid-cols-2 gap-4 text-base">
            <div>
                <dt class="text-muted-foreground">Tanggal jurnal</dt>
                <dd class="mt-1 font-medium">{displayDate(journal.date)}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-muted-foreground">Jenis jurnal</dt>
                <dd class="mt-1 break-words font-medium">{journal.templateName}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-muted-foreground">Dicatat oleh</dt>
                <dd class="mt-1 break-words font-medium">{journal.postedBy ?? 'Pengguna tidak tersedia'}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Waktu pencatatan (WIB)</dt>
                <dd class="mt-1 font-medium">{postedFormat.format(new Date(journal.createdAt))}</dd>
            </div>
            {#if journal.approvedBy}
                <div class="min-w-0"><dt class="text-muted-foreground">Disetujui oleh</dt><dd class="mt-1 font-medium">{journal.approvedBy}{#if journal.approvedAt}<span class="block text-sm text-muted-foreground">{postedFormat.format(new Date(journal.approvedAt))} WIB</span>{/if}</dd></div>
            {/if}
            {#if journal.description}
                <div class="col-span-full min-w-0">
                    <dt class="text-muted-foreground">Keterangan</dt>
                    <dd class="mt-1 break-words whitespace-pre-wrap">{journal.description}</dd>
                </div>
            {/if}
        </dl>
    </section>

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="journal-lines-heading">
        <h2 id="journal-lines-heading" class="px-4 py-4 text-lg font-semibold">Rincian debit dan kredit</h2>
        {#if journal.lines.length}
            <JournalLines lines={journal.lines} label={'Rincian ' + number} />
        {:else}
            <p class="px-4 pb-4 text-destructive" role="alert">Jurnal ini belum memiliki rincian.</p>
        {/if}
    </section>

    {#if journal.lines.length}
        {#if journal.balanced}
            <p role="status">Jurnal seimbang.</p>
        {:else}
            <p class="text-destructive" role="alert">Total debit dan kredit jurnal memiliki selisih.</p>
        {/if}
    {/if}

    {#if sources.length}
        <section class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5" aria-labelledby="journal-sources-heading">
            <h2 id="journal-sources-heading" class="text-lg font-semibold">Dokumen sumber</h2>
            <ul class="mt-3 flex flex-col gap-2">
                {#each sources as source (source.url)}
                    <li><Link href={source.url} class="break-words text-base underline underline-offset-4">{source.label}</Link></li>
                {/each}
            </ul>
        </section>
    {/if}

    {#if reversal.originalUrl}
        <p>Jurnal pembalik dari <Link href={reversal.originalUrl} class="underline underline-offset-4">jurnal asal</Link>. {reversal.reason}</p>
    {/if}
    {#if reversal.reversedUrl}
        <p>Jurnal ini memiliki <Link href={reversal.reversedUrl} class="underline underline-offset-4">jurnal pembalik</Link>.</p>
    {/if}
    {#if reversal.allowed}
        <section class="rounded-xl border bg-card p-4 sm:p-5" aria-labelledby="reversal-title">
            <h2 id="reversal-title" class="text-lg font-semibold">Buat jurnal pembalik</h2>
            <form class="mt-3 flex flex-col gap-3" onsubmit={reverse}>
                <fieldset disabled={processing} class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="reversal-date">Tanggal pembalikan</Label>
                        <DateRangePicker id="reversal-date" bind:value={reverseDate} min={journal.date} max="9999-12-31" required disabled={processing} />
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="reversal-reason">Alasan pembalikan</Label>
                        <Input id="reversal-reason" bind:value={reason} minlength={3} maxlength={400} required />
                    </div>
                </fieldset>
                {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
                <Button class="self-start" type="submit" disabled={processing || reason.trim().length < 3}>{processing ? 'Memproses…' : 'Posting jurnal pembalik'}</Button>
            </form>
        </section>
    {/if}

    <p class="text-sm text-muted-foreground">Kode dan nama akun mengikuti catatan saat jurnal diposting.</p>
</main>
