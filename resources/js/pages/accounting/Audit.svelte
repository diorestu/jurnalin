<script module lang="ts">
    export const layout = (props: { indexUrl: string }) => ({
        breadcrumbs: [{ title: 'Audit Akuntansi', href: props.indexUrl }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Label } from '@/components/ui/label';
    import { NativeSelect } from '@/components/ui/select';

    type AuditEvent = {
        id: number;
        actor: string | null;
        action: string;
        subjectType: string;
        subjectId: number | null;
        reason: string | null;
        before: Record<string, unknown> | unknown[] | null;
        after: Record<string, unknown> | unknown[] | null;
        createdAt: string;
    };
    let { events, actions, filters, indexUrl, periodsUrl }: {
        events: { data: AuditEvent[]; prev_page_url: string | null; next_page_url: string | null };
        actions: string[];
        filters: { action: string; start: string; end: string };
        indexUrl: string;
        periodsUrl: string;
    } = $props();

    const timestamps = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });
    const numbers = new Intl.NumberFormat('id-ID');
    const actionLabels: Record<string, string> = {
        'draft.created': 'Draf dibuat',
        'draft.updated': 'Draf diperbarui',
        'draft.submitted': 'Draf diajukan',
        'draft.approved': 'Draf disetujui',
        'draft.returned': 'Draf dikembalikan untuk revisi',
        'journal.reversed': 'Jurnal dibalik',
        'template.created': 'Template jurnal dibuat',
        'template.updated': 'Template jurnal diubah',
        'period.locked': 'Periode dikunci',
        'period.unlocked': 'Periode dibuka',
        'journal.posted': 'Jurnal diposting',
        'journal.created': 'Jurnal dibuat',
        'journal.manual_posted': 'Jurnal manual diposting',
        'journal.draft_created': 'Draf jurnal dibuat',
        'journal.draft_approved': 'Draf jurnal disetujui',
        'journal.draft_rejected': 'Draf jurnal ditolak',
        'account.created': 'Akun COA dibuat',
        'account.updated': 'Akun COA diubah',
        'opening.posted': 'Saldo awal diposting',
        'trade.created': 'Dokumen transaksi dibuat',
    };
    const subjectLabels: Record<string, string> = {
        accounting_period: 'Periode akuntansi', journal: 'Jurnal', journal_entry: 'Jurnal',
        journal_draft: 'Draf jurnal', journal_template: 'Template jurnal', account: 'Akun COA', journal_account: 'Akun COA',
        opening_balance: 'Saldo awal', trade_document: 'Dokumen transaksi',
    };
    const fieldLabels: Record<string, string> = {
        payload: 'Data draf', direction: 'Jenis transaksi', kind: 'Dokumen', draft_id: 'Nomor draf', journal_id: 'Nomor jurnal', source_id: 'Dokumen sumber', document_id: 'Nomor dokumen', reversal_id: 'Jurnal pembalik',
        month: 'Bulan', is_locked: 'Status periode', reason: 'Alasan', version: 'Versi',
        date: 'Tanggal', description: 'Keterangan', amount: 'Nominal', total: 'Total',
        debit: 'Debit', credit: 'Kredit', lines: 'Rincian', name: 'Nama', code: 'Kode',
        type: 'Jenis', active: 'Aktif', is_cash: 'Akun kas atau bank', account_id: 'ID akun',
        account_code: 'Kode akun', account_name: 'Nama akun', side: 'Sisi jurnal',
        status: 'Status', partner: 'Pihak terkait', reference: 'Referensi', notes: 'Catatan',
        template_name: 'Jenis jurnal', template_id: 'ID template', request_id: 'Nomor permintaan',
        quantity: 'Jumlah', unit_price: 'Harga satuan', trade_role: 'Peran akun',
    };
    const actionLabel = (action: string) => actionLabels[action] ?? action.replaceAll('.', ' · ').replaceAll('_', ' ');
    const fieldLabel = (field: string) => fieldLabels[field] ?? field.replaceAll('_', ' ');

    let action = $state(untrack(() => filters.action));
    let start = $state(untrack(() => filters.start));
    let end = $state(untrack(() => filters.end));
    let processing = $state(false);
    let error = $state('');

    function display(value: unknown, key: string): string {
        if (value === null || value === undefined || value === '') return '—';
        if (key === 'is_locked') return value ? 'Dikunci' : 'Terbuka';
        if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
        if (['amount', 'total', 'debit', 'credit', 'unit_price'].includes(key) && /^-?\d+$/.test(String(value))) {
            return numbers.format(BigInt(String(value)));
        }
        if (key === 'status') return ({ draft: 'Draf', pending: 'Menunggu persetujuan', posted: 'Terposting' } as Record<string, string>)[String(value)] ?? String(value);
        if (key === 'direction') return value === 'penjualan' ? 'Penjualan' : value === 'pembelian' ? 'Pembelian' : String(value);
        if (key === 'side' && (value === 'debit' || value === 'credit')) return value === 'debit' ? 'Debit' : 'Kredit';
        return String(value);
    }

    function filter(event: SubmitEvent) {
        event.preventDefault();
        if (processing) return;

        router.get(indexUrl, {
            ...(action && { action }),
            ...(start && { start }),
            ...(end && { end }),
        }, {
            preserveScroll: true,
            onStart: () => { processing = true; error = ''; },
            onError: (errors) => { error = String(Object.values(errors)[0] ?? 'Filter tidak valid.'); },
            onFinish: () => { processing = false; },
        });
    }
</script>

{#snippet snapshot(value: unknown, key: string)}
    {#if Array.isArray(value)}
        <ol class="flex flex-col gap-2 border-l pl-3">
            {#each value as item, index (index)}
                <li class="min-w-0"><span class="text-xs text-muted-foreground">{index + 1}</span>{@render snapshot(item, key)}</li>
            {/each}
        </ol>
    {:else if typeof value === 'object' && value !== null}
        <dl class="flex min-w-0 flex-col gap-1.5">
            {#each Object.entries(value) as [field, entry] (field)}
                <div class="min-w-0">
                    <dt class="text-xs text-muted-foreground">{fieldLabel(field)}</dt>
                    <dd class="break-words whitespace-pre-wrap text-sm">{@render snapshot(entry, field)}</dd>
                </div>
            {/each}
        </dl>
    {:else}
        {display(value, key)}
    {/if}
{/snippet}

<AppHead title="Audit Akuntansi" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold">Audit Akuntansi</h1>
            <p class="mt-1 text-muted-foreground">Riwayat perubahan pembukuan beserta pelaku dan alasannya.</p>
        </div>
        <Link href={periodsUrl} class="underline underline-offset-4">Kelola periode</Link>
    </header>

    <form class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" onsubmit={filter}>
        <div class="flex w-full min-w-0 flex-col gap-1.5 sm:w-64">
            <Label for="audit-action">Jenis aktivitas</Label>
            <NativeSelect id="audit-action" bind:value={action} disabled={processing}>
                <option value="">Semua aktivitas</option>
                {#each actions as option (option)}<option value={option}>{actionLabel(option)}</option>{/each}
            </NativeSelect>
        </div>
        <div class="flex w-full min-w-0 flex-col gap-1.5 sm:w-auto">
            <Label for="audit-dates">Tanggal perubahan (WIB)</Label>
            <DateRangePicker id="audit-dates" mode="range" bind:value={start} bind:endValue={end}
                min="1900-01-01" max="9999-12-31" clearable disabled={processing} />
        </div>
        <Button type="submit" variant="outline" disabled={processing}>{processing ? 'Memuat…' : 'Tampilkan'}</Button>
        {#if filters.action || filters.start || filters.end}
            <Link href={indexUrl} class="py-2 underline underline-offset-4">Hapus filter</Link>
        {/if}
        {#if error}<p class="w-full text-sm text-destructive" role="alert">{error}</p>{/if}
    </form>

    <section class="flex min-w-0 flex-col gap-3" aria-label="Riwayat audit akuntansi">
        {#each events.data as event (event.id)}
            <article class="min-w-0 rounded-xl border bg-card p-4 text-card-foreground sm:p-5">
                <header class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="font-semibold">{actionLabel(event.action)}</h2>
                    <time datetime={event.createdAt} class="text-sm text-muted-foreground">{timestamps.format(new Date(event.createdAt))} WIB</time>
                </header>
                <p class="mt-1 break-words text-sm text-muted-foreground">
                    {event.actor ?? 'Pelaku tidak tersedia'} · {subjectLabels[event.subjectType] ?? event.subjectType.replaceAll('_', ' ')}{event.subjectId === null ? '' : ' #' + event.subjectId}
                </p>
                {#if event.reason}<p class="mt-3 break-words">{event.reason}</p>{/if}
                {#if event.before !== null || event.after !== null}
                    <details class="mt-3 border-t pt-3">
                        <summary class="min-h-9 cursor-pointer text-sm font-medium focus-visible:outline-2 focus-visible:outline-ring">Rincian perubahan</summary>
                        <div class="mt-3 grid min-w-0 gap-4 sm:grid-cols-2">
                            <section class="min-w-0" aria-label="Sebelum perubahan">
                                <h3 class="mb-2 text-sm font-semibold">Sebelum</h3>
                                {@render snapshot(event.before, '')}
                            </section>
                            <section class="min-w-0" aria-label="Setelah perubahan">
                                <h3 class="mb-2 text-sm font-semibold">Sesudah</h3>
                                {@render snapshot(event.after, '')}
                            </section>
                        </div>
                    </details>
                {/if}
            </article>
        {:else}
            <p class="rounded-xl border p-5 text-muted-foreground">Belum ada aktivitas yang sesuai.</p>
        {/each}
    </section>
    {#if events.prev_page_url || events.next_page_url}
        <nav class="flex flex-wrap gap-4" aria-label="Halaman audit akuntansi">
            {#if events.prev_page_url}<Link href={events.prev_page_url} class="underline">Sebelumnya</Link>{/if}
            {#if events.next_page_url}<Link href={events.next_page_url} class="underline">Berikutnya</Link>{/if}
        </nav>
    {/if}
</main>
