<script module lang="ts">
    export const layout = (props: { title: string; indexUrl: string; formUrl: string }) => ({
        breadcrumbs: [
            { title: props.title, href: props.indexUrl },
            { title: 'Buat dokumen', href: props.formUrl },
        ],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import {
        formatDate,
        partnerLabel,
        rupiah,
        type Account,
        type Direction,
        type Kind,
        type Source,
    } from '@/components/trade/documents';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import QuillEditor from '@/components/ui/rich-text/QuillEditor.svelte';
    import { AccountSelect, NativeSelect } from '@/components/ui/select';

    type DraftLine = {
        key: number;
        sourceLineId: number | null;
        name: string;
        quantity: number | undefined;
        unitPrice: number | undefined;
        remaining: number | null;
    };

    let {
        direction,
        title,
        canPost,
        editingDraft,
        kinds,
        selectedKind,
        source,
        cashAccounts,
        purchaseAccounts,
        date,
        requestId,
        indexUrl,
        storeUrl,
        backUrl,
    }: {
        direction: Direction;
        title: string;
        canPost: boolean;
        editingDraft: { id: number; version: number; data: {
            kind: string; partner?: string; date: string; due_date?: string | null; reference?: string | null; notes_delta?: string | null;
            amount?: number | null; account_id?: number | null;
            lines?: { name?: string; quantity: number; unit_price?: number; source_line_id?: number | null }[];
        } } | null;
        kinds: Kind[];
        selectedKind: string;
        source: Source | null;
        cashAccounts: Account[];
        purchaseAccounts: Account[];
        date: string;
        requestId: string;
        indexUrl: string;
        storeUrl: string;
        backUrl: string;
    } = $props();

    const moneyKinds = new Set(['advance', 'payment', 'refund', 'advance_refund']);
    const maxUnitPrice = 999_999_999_999;
    let nextKey = 0;

    function blankLine(): DraftLine {
        return { key: nextKey++, sourceLineId: null, name: '', quantity: 1, unitPrice: undefined, remaining: null };
    }

    const initial = untrack(() => editingDraft?.data);
    const editingVersion = untrack(() => editingDraft?.version);
    let kind = $state(untrack(() => initial?.kind || selectedKind || kinds[0]?.value || ''));
    let partner = $state(untrack(() => source?.partner ?? initial?.partner ?? ''));
    let transactionDate = $state(untrack(() => initial?.date ?? (source && source.date > date ? source.date : date)));
    let dueDate = $state(initial?.due_date ?? '');
    let reference = $state(initial?.reference ?? '');
    let notesDelta = $state(initial?.notes_delta ?? '');
    let amount = $state<number | undefined>(initial?.amount ?? undefined);
    let selectedAccount = $state<number | null>(initial?.account_id ?? null);
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});
    let submittedKeys = $state<number[]>([]);
    let lines = $state<DraftLine[]>(untrack(() => initial
        ? (initial.lines ?? []).map(line => {
            const original = source?.lines.find(item => item.id === line.source_line_id);
            return { key: nextKey++, sourceLineId: line.source_line_id ?? null,
                name: original?.name ?? line.name ?? '', quantity: line.quantity,
                unitPrice: Number(original?.unit_price ?? line.unit_price ?? 0),
                remaining: original ? Number(original.remaining) : null };
        })
        : source
        ? source.lines.map((line) => ({
            key: nextKey++,
            sourceLineId: line.id,
            name: line.name,
            quantity: Number(line.remaining),
            unitPrice: Number(line.unit_price),
            remaining: Number(line.remaining),
        }))
        : [blankLine()],
    ));

    const moneyOnly = $derived(moneyKinds.has(kind));
    const financial = $derived(moneyOnly || kind === 'invoice' || kind === 'return');
    const submitAction = $derived(financial && (!canPost || editingDraft) ? 'submit' : 'post');
    const purchaseInvoice = $derived(direction === 'pembelian' && kind === 'invoice');
    const needsAccount = $derived(moneyOnly || purchaseInvoice);
    const accounts = $derived(moneyOnly ? cashAccounts : purchaseAccounts);
    const accountId = $derived(accounts.some((account) => account.id === selectedAccount)
        ? selectedAccount
        : purchaseInvoice ? accounts[0]?.id ?? null : null,
    );
    const accountLabel = $derived(moneyOnly ? 'Akun kas atau bank' : 'Akun aset atau beban');
    const kindLabel = $derived(kinds.find((option) => option.value === kind)?.label ?? kind);
    const availableAmount = $derived(source?.availableAmount == null ? null : Math.abs(Number(source.availableAmount)));
    const itemTotal = $derived(lines.reduce((total, line) => total + lineTotal(line), 0n));

    function lineTotal(line: DraftLine): bigint {
        if (!Number.isSafeInteger(line.quantity) || !Number.isSafeInteger(line.unitPrice)) return 0n;

        return BigInt(line.quantity ?? 0) * BigInt(line.unitPrice ?? 0);
    }

    function lineErrors(line: DraftLine): string {
        const index = submittedKeys.indexOf(line.key);
        if (index < 0) return '';

        return Object.entries(errors)
            .filter(([field]) => field === `lines.${index}` || field.startsWith(`lines.${index}.`))
            .map(([, message]) => message)
            .join(' ');
    }

    function save(event?: SubmitEvent, action: 'post' | 'draft' | 'submit' = submitAction) {
        event?.preventDefault();
        if (processing) return;

        const activeLines = moneyOnly ? [] : lines.filter((line) => !source || (line.quantity ?? 0) > 0);
        submittedKeys = activeLines.map((line) => line.key);

        if (!moneyOnly && !activeLines.length) {
            errors = { lines: 'Masukkan jumlah lebih dari nol pada minimal satu baris.' };
            return;
        }

        router.post(storeUrl, {
            kind,
            action,
            draft_id: editingDraft?.id ?? null,
            version: editingVersion ?? null,
            source_id: source?.id ?? null,
            partner,
            date: transactionDate,
            due_date: kind === 'invoice' ? dueDate || null : null,
            reference: reference || null,
            notes_delta: notesDelta || null,
            request_id: requestId,
            lines: activeLines.map((line) => ({
                name: line.name,
                quantity: line.quantity ?? 0,
                unit_price: line.unitPrice ?? 0,
                source_line_id: line.sourceLineId,
            })),
            amount: moneyOnly ? amount ?? null : null,
            account_id: needsAccount ? accountId : null,
        }, {
            preserveScroll: true,
            onStart: () => { processing = true; errors = {}; },
            onError: (validationErrors) => { errors = validationErrors; },
            onFinish: () => { processing = false; },
        });
    }

    function changeKind(event: Event & { currentTarget: HTMLSelectElement }) {
        kind = event.currentTarget.value;
        selectedAccount = null;
        errors = {};
    }
</script>

<AppHead title={`Buat dokumen · ${title}`} />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header>
        <Link href={backUrl || indexUrl} class="inline-flex items-center gap-1.5 text-sm underline underline-offset-4">
            <ArrowLeft class="size-4" aria-hidden="true" /> Kembali
        </Link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">{editingDraft ? 'Ubah dokumen draf' : source ? kindLabel : 'Buat dokumen'}</h1>
        {#if source}
            <p class="mt-1 text-sm text-muted-foreground">
                Dari dokumen {source.number} · {formatDate(source.date)}
            </p>
        {:else}
            <p class="mt-1 text-sm text-muted-foreground">{title}</p>
        {/if}
    </header>

    <form class="flex flex-col gap-5" onsubmit={save}>
        {#if Object.keys(errors).length}
            <div class="rounded-md border border-destructive/50 px-4 py-3 text-sm text-destructive" role="alert">
                {errors.request_id ?? errors.source_id ?? 'Periksa kolom yang ditandai, lalu simpan kembali.'}
            </div>
        {/if}

        <fieldset class="grid gap-4 rounded-xl border bg-card p-4 text-card-foreground sm:grid-cols-2 sm:p-5" disabled={processing}>
            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="trade-create-kind">Jenis dokumen</Label>
                {#if source || editingDraft}
                    <Input id="trade-create-kind" value={kindLabel} readonly aria-invalid={!!errors.kind} />
                {:else}
                    <NativeSelect id="trade-create-kind" value={kind} onchange={changeKind} required aria-invalid={!!errors.kind}>
                        {#each kinds as option (option.value)}
                            <option value={option.value}>{option.label}</option>
                        {/each}
                    </NativeSelect>
                {/if}
                <InputError message={errors.kind} />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="trade-create-partner">{partnerLabel(direction)}</Label>
                <Input id="trade-create-partner" bind:value={partner} readonly={source !== null} required maxlength={160}
                    aria-invalid={!!errors.partner} />
                <InputError message={errors.partner} />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="trade-create-date">Tanggal</Label>
                <DateRangePicker id="trade-create-date" bind:value={transactionDate} min={source?.date ?? '1900-01-01'}
                    max="9999-12-31" disabled={processing} required invalid={!!errors.date} />
                <InputError message={errors.date} />
            </div>
            {#if kind === 'invoice'}
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="trade-create-due">Jatuh tempo</Label>
                    <DateRangePicker id="trade-create-due" bind:value={dueDate} min={transactionDate || '1900-01-01'}
                        max="9999-12-31" disabled={processing} invalid={!!errors.due_date} clearable />
                    <InputError message={errors.due_date} />
                </div>
            {/if}
            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="trade-create-reference">Referensi <span class="font-normal text-muted-foreground">(opsional)</span></Label>
                <Input id="trade-create-reference" bind:value={reference} maxlength={100} aria-invalid={!!errors.reference} />
                <InputError message={errors.reference} />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5 sm:col-span-2">
                <Label id="trade-create-notes-label">Catatan <span class="font-normal text-muted-foreground">(opsional)</span></Label>
                <QuillEditor id="trade-create-notes" labelledBy="trade-create-notes-label" bind:value={notesDelta}
                    maxLength={500} disabled={processing} invalid={!!(errors.notes_delta || errors.notes)} />
                <InputError message={errors.notes_delta || errors.notes} />
            </div>
        </fieldset>

        {#if needsAccount}
            <fieldset class="grid gap-4 rounded-xl border bg-card p-4 text-card-foreground sm:grid-cols-2 sm:p-5" disabled={processing}>
                {#if moneyOnly}
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="trade-create-amount">Nominal (Rp)</Label>
                        <NumberInput id="trade-create-amount" bind:value={amount} currency min={1} max={availableAmount ?? 999999999999999}
                            required aria-invalid={!!errors.amount} />
                        {#if availableAmount !== null}
                            <p class="text-xs text-muted-foreground">Maksimal {rupiah(availableAmount)}</p>
                        {/if}
                        <InputError message={errors.amount} />
                    </div>
                {/if}
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="trade-create-account">{accountLabel}</Label>
                    <AccountSelect id="trade-create-account" {accounts}
                        bind:value={() => accountId, next => { selectedAccount = next; }}
                        required disabled={processing} invalid={!!errors.account_id} />
                    {#if !accounts.length}
                        <p class="text-xs text-muted-foreground">Belum ada akun aktif yang sesuai di COA.</p>
                    {/if}
                    <InputError message={errors.account_id} />
                </div>
            </fieldset>
        {/if}

        {#if !moneyOnly}
            <fieldset class="min-w-0 rounded-xl border bg-card text-card-foreground" disabled={processing}>
                <div class="flex flex-wrap items-center justify-between gap-2 p-4 sm:px-5">
                    <div>
                        <h2 class="font-semibold">Barang atau jasa</h2>
                        {#if source}
                            <p class="mt-1 text-sm text-muted-foreground">Isi jumlah 0 untuk melewati baris.</p>
                        {/if}
                    </div>
                    {#if !source}
                        <Button type="button" variant="outline" size="sm" onclick={() => { lines.push(blankLine()); }}>
                            <Plus class="size-4" aria-hidden="true" /> Tambah baris
                        </Button>
                    {/if}
                </div>
                {#if errors.lines}
                    <InputError message={errors.lines} class="px-4 pb-3 sm:px-5" />
                {/if}
                <div class="divide-y border-t">
                    {#each lines as line, index (line.key)}
                        {@const label = `Baris ${index + 1}`}
                        <div class="px-4 py-3 sm:px-5">
                            <div class="grid grid-cols-2 items-start gap-3 md:grid-cols-[minmax(0,2fr)_minmax(6rem,0.8fr)_minmax(7rem,1fr)_minmax(0,1fr)_auto]">
                                <div class="col-span-2 flex min-w-0 flex-col gap-1.5 md:col-span-1">
                                    <Label for={`trade-line-name-${line.key}`}>Barang atau jasa <span class="sr-only">{label}</span></Label>
                                    <Input id={`trade-line-name-${line.key}`} bind:value={line.name} readonly={source !== null} required maxlength={160} />
                                </div>
                                <div class="flex min-w-0 flex-col gap-1.5">
                                    <Label for={`trade-line-qty-${line.key}`}>Jumlah <span class="sr-only">{label}</span></Label>
                                    <NumberInput id={`trade-line-qty-${line.key}`} bind:value={line.quantity} min={source ? 0 : 1}
                                        max={Math.min(line.remaining ?? 1000000, 1000000)} required />
                                    {#if line.remaining !== null}
                                        <span class="text-xs text-muted-foreground">Tersedia {line.remaining}</span>
                                    {/if}
                                </div>
                                <div class="flex min-w-0 flex-col gap-1.5">
                                    <Label for={`trade-line-price-${line.key}`}>Harga satuan (Rp) <span class="sr-only">{label}</span></Label>
                                    <NumberInput id={`trade-line-price-${line.key}`} bind:value={line.unitPrice} currency min={1}
                                        max={maxUnitPrice} readonly={source !== null} required />
                                </div>
                                <div class="flex min-w-0 flex-col gap-1.5">
                                    <p class="text-sm font-medium">Subtotal</p>
                                    <p class="break-words text-sm tabular-nums md:py-2">{rupiah(lineTotal(line))}</p>
                                </div>
                                {#if !source}
                                    <Button type="button" variant="ghost" size="icon" class="justify-self-end md:mt-5"
                                        aria-label={`Hapus baris ${index + 1}`} disabled={lines.length === 1}
                                        onclick={() => { lines = lines.filter((item) => item.key !== line.key); }}>
                                        <Trash2 class="size-4" aria-hidden="true" />
                                    </Button>
                                {/if}
                            </div>
                            <InputError message={lineErrors(line)} class="mt-2" />
                        </div>
                    {:else}
                        <p class="p-4 text-sm text-muted-foreground sm:px-5">Tidak ada baris sumber yang tersedia.</p>
                    {/each}
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/40 px-4 py-4 text-sm font-semibold sm:px-5">
                    <span>Total dokumen</span>
                    <span class="tabular-nums">{rupiah(itemTotal)}</span>
                </div>
            </fieldset>
        {/if}

        <div class="flex flex-wrap justify-end gap-2">
            <Button asChild variant="outline">
                {#snippet children(props)}
                    <Link {...props} href={backUrl || indexUrl}>Batal</Link>
                {/snippet}
            </Button>
            <div class="flex flex-wrap gap-2">
                {#if financial}<Button type="button" variant="outline" disabled={processing || (needsAccount && !accounts.length)} onclick={() => save(undefined, 'draft')}>Simpan draf</Button>{/if}
                <Button type="submit" disabled={processing || (needsAccount && !accounts.length) || (!moneyOnly && !lines.length)}>
                    {processing ? 'Menyimpan…' : !financial ? 'Simpan dokumen' : submitAction === 'post' ? 'Posting dokumen' : 'Ajukan persetujuan'}
                </Button>
            </div>
        </div>
    </form>
</main>
