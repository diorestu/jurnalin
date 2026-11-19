<script module lang="ts">
    export const layout = (props: { urls: { index: string } }) => ({
        breadcrumbs: [{ title: 'Draf & Persetujuan', href: props.urls.index }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { displayDate } from '@/components/reports/format';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { AccountSelect } from '@/components/ui/select';

    type DraftLine = { account_id: number | null; debit: number; credit: number };
    type Draft = {
        id: number; status: 'draft' | 'pending' | 'posted'; date: string; description: string | null;
        version: number; createdBy: string | null; createdAt: string; submittedAt: string | null;
        reviewReason: string | null; journalUrl: string | null; lines: DraftLine[];
        mode: 'manual' | 'template' | 'trade';
        trade?: {
            label: string; directionLabel: string; partner: string; amount: number | string;
            sourceUrl: string | null; sourceNumber: string | null;
            lines: { name: string; quantity: number; unit_price: number;tax_code?:string;tax_amount?:number;unit_discount?:number }[];
            accountLabel: string | null; dueDate: string | null; reference: string | null; notes: string | null;
        };
    };
    type EditableLine = { key: number; account_id: number | null; debit?: number; credit?: number };

    let { draft, accounts, permissions, urls }: {
        draft: Draft;
        accounts: { id: number; code: string; name: string; active?: boolean }[];
        permissions: { edit: boolean; submit: boolean; approve: boolean; return: boolean };
        urls: { index: string; update: string; submit: string; approve: string; return: string; editTrade: string | null };
    } = $props();

    let nextKey = 0;
    function editable(source: DraftLine[]): EditableLine[] {
        return source.map(line => ({ key: nextKey++, account_id: line.account_id, debit: line.debit || undefined, credit: line.credit || undefined }));
    }

    let date = $state(untrack(() => draft.date));
    let description = $state(untrack(() => draft.description ?? ''));
    let lines = $state<EditableLine[]>(untrack(() => editable(draft.lines)));
    let editingVersion = $state(untrack(() => draft.version));
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});
    let reason = $state('');
    let notice = $state('');
    const maxAmount = 999_999_999_999_999n;
    const money = new Intl.NumberFormat('id-ID');
    const timestamps = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });
    const labels = { draft: 'Draf', pending: 'Menunggu persetujuan', posted: 'Terposting' };
    const isTrade = $derived(draft.mode === 'trade');
    const normalizedLines = $derived(lines.map(line => ({
        account_id: line.account_id, debit: line.debit ?? 0, credit: line.credit ?? 0,
    })));
    const dirty = $derived(!isTrade && (date !== draft.date || description !== (draft.description ?? '')
        || JSON.stringify(normalizedLines) !== JSON.stringify(draft.lines.map(line => ({
            account_id: line.account_id, debit: line.debit, credit: line.credit,
        })))));
    const totals = $derived(normalizedLines.reduce((sum, line) => ({
        debit: sum.debit + BigInt(line.debit), credit: sum.credit + BigInt(line.credit),
    }), { debit: 0n, credit: 0n }));
    const balanced = $derived(totals.debit > 0n && totals.debit === totals.credit && totals.debit <= maxAmount);
    const ready = $derived(isTrade || (balanced && lines.length >= 2 && lines.every(line =>
        line.account_id !== null && accounts.some(account => account.id === line.account_id && account.active !== false)
        && ((line.debit ?? 0) > 0) !== ((line.credit ?? 0) > 0),
    )));

    function preventImplicitPost(event: KeyboardEvent) {
        if (event.key === 'Enter') event.preventDefault();
    }

    function request(url: string, data: Record<string, unknown>, message: string) {
        if (processing) return;
        router.post(url, { version: editingVersion, ...data }, {
            preserveScroll: true,
            onStart: () => { processing = true; errors = {}; notice = ''; },
            onError: validationErrors => { errors = validationErrors; },
            onSuccess: page => {
                const saved = page.props.draft as Draft;
                if (saved) {
                    editingVersion = saved.version;
                    date = saved.date;
                    description = saved.description ?? '';
                    lines = editable(saved.lines);
                }
                reason = '';
                notice = message;
            },
            onFinish: () => { processing = false; },
        });
    }

    function save(event: SubmitEvent) {
        event.preventDefault();
        if (!permissions.edit || isTrade) return;
        request(urls.update, { date, description: description || null, lines: normalizedLines }, 'Draf berhasil disimpan.');
    }

    function returnDraft(event: SubmitEvent) {
        event.preventDefault();
        if (!permissions.return || reason.trim().length < 3) return;
        request(urls.return, { reason: reason.trim() }, 'Draf dikembalikan untuk diperbaiki.');
    }
</script>

<AppHead title={'DRAF-' + String(draft.id).padStart(4, '0')} />
<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <Link href={urls.index} class="underline underline-offset-4">Kembali ke Draf & Persetujuan</Link>
            <h1 class="mt-2 text-3xl font-bold">DRAF-{String(draft.id).padStart(4, '0')}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{draft.createdBy ?? '—'} · Dibuat {timestamps.format(new Date(draft.createdAt))} WIB</p>
        </div>
        <Badge variant={draft.status === 'pending' ? 'secondary' : 'outline'}>{labels[draft.status]}</Badge>
    </header>

    {#if Object.keys(errors).length}
        <div class="rounded-md border border-destructive/50 p-4 text-sm text-destructive" role="alert">
            {#each [...new Set(Object.values(errors))] as message (message)}<p>{message}</p>{/each}
            {#if errors.version}
                <Button type="button" variant="outline" class="mt-2" onclick={() => window.location.reload()}>Muat ulang draf</Button>
            {/if}
        </div>
    {/if}
    {#if notice}<p role="status">{notice}</p>{/if}
    {#if draft.reviewReason}
        <section class="rounded-xl border bg-muted/40 p-4">
            <h2 class="font-semibold">Catatan pemeriksaan</h2>
            <p class="mt-1 break-words text-sm">{draft.reviewReason}</p>
        </section>
    {/if}
    {#if draft.status === 'pending'}
        <p class="text-sm text-muted-foreground">Draf sedang menunggu pemeriksaan. Pemeriksa dapat menyetujui atau mengembalikannya untuk diperbaiki.</p>
    {/if}

    {#if isTrade}
        <section class="min-w-0 overflow-hidden rounded-xl border bg-card" aria-labelledby="trade-draft-title">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b p-4 sm:px-5">
                <div>
                    <h2 id="trade-draft-title" class="font-semibold">{draft.trade?.label ?? 'Dokumen transaksi'}</h2>
                    {#if draft.trade}<p class="mt-0.5 text-sm text-muted-foreground">{draft.trade.directionLabel}</p>{/if}
                </div>
                {#if permissions.edit && urls.editTrade}
                    <Link href={urls.editTrade} class="text-sm font-medium underline underline-offset-4">Ubah dokumen draf</Link>
                {/if}
            </header>
            <dl class="grid gap-4 p-4 text-sm sm:grid-cols-2 sm:px-5">
                <div><dt class="text-muted-foreground">Tanggal transaksi</dt><dd class="mt-1 font-medium">{displayDate(draft.date)}</dd></div>
                {#if draft.trade}
                    <div><dt class="text-muted-foreground">Pelanggan / pemasok</dt><dd class="mt-1 break-words font-medium">{draft.trade.partner}</dd></div>
                    <div><dt class="text-muted-foreground">Nominal</dt><dd class="mt-1 font-semibold tabular-nums">IDR {money.format(BigInt(draft.trade.amount))}</dd></div>
                    {#if draft.trade.reference}<div><dt class="text-muted-foreground">Referensi</dt><dd class="mt-1 break-words">{draft.trade.reference}</dd></div>{/if}
                    {#if draft.trade.dueDate}<div><dt class="text-muted-foreground">Jatuh tempo</dt><dd class="mt-1">{displayDate(draft.trade.dueDate)}</dd></div>{/if}
                    {#if draft.trade.notes}<div class="col-span-full"><dt class="text-muted-foreground">Catatan</dt><dd class="mt-1 break-words whitespace-pre-wrap">{draft.trade.notes}</dd></div>{/if}
                    {#if draft.trade.accountLabel}<div><dt class="text-muted-foreground">Akun transaksi</dt><dd class="mt-1 break-words">{draft.trade.accountLabel}</dd></div>{/if}
                    {#if draft.trade.sourceUrl}
                        <div><dt class="text-muted-foreground">Dokumen sumber</dt><dd class="mt-1"><Link href={draft.trade.sourceUrl} class="underline underline-offset-4">{draft.trade.sourceNumber ?? 'Buka dokumen sumber'}</Link></dd></div>
                    {/if}
                {/if}
                {#if draft.description}<div class="min-w-0 sm:col-span-2"><dt class="text-muted-foreground">Keterangan</dt><dd class="mt-1 break-words">{draft.description}</dd></div>{/if}
            </dl>
            {#if draft.trade?.lines.length}
                <div class="overflow-x-auto border-t">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/60 text-muted-foreground"><tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium">Barang atau jasa</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Jumlah</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Harga satuan</th>
                        </tr></thead>
                        <tbody class="divide-y">
                            {#each draft.trade.lines as line, index (index)}
                                <tr><td class="break-words px-4 py-3">{line.name}{#if line.tax_code || line.unit_discount}<small class="block">Diskon/unit IDR {money.format(line.unit_discount??0)} · {line.tax_code??'Tanpa pajak'} IDR {money.format(line.tax_amount??0)}</small>{/if}</td><td class="px-4 py-3 text-right tabular-nums">{line.quantity}</td><td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">IDR {money.format(BigInt(line.unit_price))}</td></tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}
        </section>
    {:else}
    <form class="overflow-hidden rounded-xl border bg-card" onsubmit={save}>
        <fieldset disabled={processing || !permissions.edit} class="flex flex-col">
            <div class="grid gap-4 border-b p-4 sm:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)] sm:p-5">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="draft-date">Tanggal jurnal draf</Label>
                    <DateRangePicker id="draft-date" bind:value={date} min="1900-01-01" max="9999-12-31" required disabled={processing || !permissions.edit} />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="draft-description">Keterangan jurnal draf</Label>
                    <Input id="draft-description" bind:value={description} maxlength={500} onkeydown={preventImplicitPost} />
                </div>
            </div>
            <div class="divide-y">
                {#each lines as line, index (line.key)}
                    <div class="grid min-w-0 grid-cols-2 items-end gap-3 px-4 py-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:px-5">
                        <div class="col-span-2 flex min-w-0 flex-col gap-1.5 md:col-span-1">
                            <Label for={'draft-account-' + line.key}>Akun COA draf baris {index + 1}</Label>
                            <AccountSelect id={'draft-account-' + line.key} {accounts} bind:value={line.account_id}
                                disabled={processing || !permissions.edit} invalid={!!errors['lines.' + index + '.account_id']} />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'draft-debit-' + line.key}>Debit draf baris {index + 1}</Label>
                            <NumberInput id={'draft-debit-' + line.key} bind:value={line.debit} currency min={0} max={Number(maxAmount)}
                                disabled={processing || !permissions.edit || ((line.credit ?? 0) > 0 && (line.debit ?? 0) <= 0)} placeholder="0" onkeydown={preventImplicitPost} />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'draft-credit-' + line.key}>Kredit draf baris {index + 1}</Label>
                            <NumberInput id={'draft-credit-' + line.key} bind:value={line.credit} currency min={0} max={Number(maxAmount)}
                                disabled={processing || !permissions.edit || ((line.debit ?? 0) > 0 && (line.credit ?? 0) <= 0)} placeholder="0" onkeydown={preventImplicitPost} />
                        </div>
                        {#if permissions.edit}
                            <Button type="button" variant="outline" disabled={lines.length <= 2} class="col-span-2 justify-self-end md:col-span-1"
                                onclick={() => { lines = lines.filter(item => item.key !== line.key); errors = {}; }}>Hapus baris {index + 1}</Button>
                        {/if}
                    </div>
                {/each}
            </div>
            {#if permissions.edit}
                <div class="border-t px-4 py-3 sm:px-5">
                    <Button type="button" variant="outline" disabled={lines.length >= 50}
                        onclick={() => { lines.push({ key: nextKey++, account_id: null }); }}>Tambah baris</Button>
                </div>
            {/if}
        </fieldset>

        <footer class="flex flex-wrap items-end justify-between gap-4 border-t p-4 sm:px-5">
            <div>
                <dl class="flex flex-wrap gap-x-6 gap-y-2">
                    <div><dt class="text-sm text-muted-foreground">Total debit</dt><dd class="font-semibold tabular-nums">IDR {money.format(totals.debit)}</dd></div>
                    <div><dt class="text-sm text-muted-foreground">Total kredit</dt><dd class="font-semibold tabular-nums">IDR {money.format(totals.credit)}</dd></div>
                </dl>
                <p class="mt-1 text-sm text-muted-foreground" role="status">{balanced ? 'Total debit dan kredit seimbang.' : 'Draf belum seimbang.'}</p>
            </div>
            {#if permissions.edit}
                <Button type="submit" disabled={processing || !dirty}>{processing ? 'Menyimpan…' : 'Simpan draf'}</Button>
            {/if}
        </footer>
    </form>
    {/if}

    {#if draft.journalUrl}
        <Link href={draft.journalUrl} class="self-start font-medium underline underline-offset-4">Buka jurnal terposting</Link>
    {/if}
    {#if permissions.submit}
        <div class="flex flex-wrap items-center gap-3">
            <Button type="button" disabled={processing || dirty || !ready}
                onclick={() => request(urls.submit, {}, 'Draf diajukan untuk persetujuan.')}>{processing ? 'Memproses…' : 'Ajukan persetujuan'}</Button>
            {#if dirty}<p class="text-sm text-muted-foreground">Simpan perubahan sebelum mengajukan persetujuan.</p>
            {:else if !ready}<p class="text-sm text-muted-foreground">Lengkapi akun aktif dan seimbangkan debit serta kredit sebelum diajukan.</p>{/if}
        </div>
    {/if}
    {#if permissions.approve}
        <div class="flex flex-wrap items-center gap-3">
            <Button type="button" disabled={processing || !ready}
                onclick={() => request(urls.approve, {}, 'Draf disetujui dan diposting.')}>{processing ? 'Memproses…' : 'Setujui & Posting'}</Button>
            {#if !ready}<p class="text-sm text-muted-foreground">Akun atau nominal memerlukan perbaikan sebelum draf dapat disetujui.</p>{/if}
        </div>
    {/if}
    {#if permissions.return}
        <section class="rounded-xl border bg-card p-4 sm:p-5" aria-labelledby="draft-return-title">
            <h2 id="draft-return-title" class="font-semibold">Kembalikan untuk diperbaiki</h2>
            <form class="mt-3 flex flex-col gap-3" onsubmit={returnDraft}>
                <Label for="draft-return-reason">Alasan pengembalian</Label>
                <Input id="draft-return-reason" bind:value={reason} required minlength={3} maxlength={500} disabled={processing} />
                <Button type="submit" variant="outline" class="self-start" disabled={processing || reason.trim().length < 3}>{processing ? 'Memproses…' : 'Kembalikan draf'}</Button>
            </form>
        </section>
    {/if}
</main>
