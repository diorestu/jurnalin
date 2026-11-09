<script module lang="ts">
    export const layout = (props: { indexUrl: string; storeUrl: string }) => ({
        breadcrumbs: [{ title: 'Kelola COA', href: props.indexUrl }, { title: 'Saldo Awal', href: props.storeUrl }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PartnerRows from '@/components/opening/PartnerRows.svelte';
    import type { PartnerDraft } from '@/components/opening/types';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { AccountSelect } from '@/components/ui/select';

    type Line = { key: number; account_id: number | null; debit?: number; credit?: number };
    type Inventory = { key: number; sku: string; name: string; quantity?: number; unit_cost?: number };
    let { accounts, date, maxDate, storeUrl, indexUrl, requestId }: {
        accounts: { id: number; code: string; name: string; type: string | null }[];
        date: string; maxDate: string; storeUrl: string; indexUrl: string; requestId: string;
    } = $props();

    let selectedDate = $state(untrack(() => date));
    let description = $state('');
    let nextKey = 0;
    const blank = (): Line => ({ key: nextKey++, account_id: null });
    let lines = $state<Line[]>([blank(), blank()]);
    let receivables = $state<PartnerDraft[]>([]);
    let payables = $state<PartnerDraft[]>([]);
    let inventory = $state<Inventory[]>([]);
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    const money = new Intl.NumberFormat('id-ID');
    const max = 999_999_999_999_999n;
    const activeLines = $derived(lines.filter(line => line.account_id !== null || (line.debit ?? 0) > 0 || (line.credit ?? 0) > 0));
    const inventoryTotal = $derived(inventory.reduce((sum, item) => sum + BigInt(item.quantity ?? 0) * BigInt(item.unit_cost ?? 0), 0n));
    const totalDebit = $derived(activeLines.reduce((sum, line) => sum + BigInt(line.debit ?? 0), 0n)
        + receivables.reduce((sum, row) => sum + BigInt(row.amount ?? 0), 0n) + inventoryTotal);
    const totalCredit = $derived(activeLines.reduce((sum, line) => sum + BigInt(line.credit ?? 0), 0n)
        + payables.reduce((sum, row) => sum + BigInt(row.amount ?? 0), 0n));
    const balanced = $derived(totalDebit > 0n && totalDebit === totalCredit && totalDebit <= max);
    const detailsValid = $derived([...receivables, ...payables].every(row => row.partner.trim() && row.reference.trim()
        && (row.amount ?? 0) > 0 && row.invoice_date <= selectedDate && (!row.due_date || row.due_date >= row.invoice_date))
        && inventory.every(item => item.sku.trim() && item.name.trim() && (item.quantity ?? 0) > 0 && (item.unit_cost ?? 0) > 0));
    const ready = $derived(balanced && detailsValid && activeLines.every(line => line.account_id !== null
        && ((line.debit ?? 0) > 0) !== ((line.credit ?? 0) > 0)));

    function submit(event: SubmitEvent) {
        event.preventDefault();
        if (!ready || processing) return;
        router.post(storeUrl, {
            request_id: requestId,
            date: selectedDate,
            description: description || null,
            lines: activeLines.map(({ account_id, debit, credit }) => ({ account_id, debit: debit ?? 0, credit: credit ?? 0 })),
            receivables: receivables.map(({ partner, reference, invoice_date, due_date, amount }) => ({
                partner, reference, invoice_date, due_date: due_date || null, amount: amount ?? 0,
            })),
            payables: payables.map(({ partner, reference, invoice_date, due_date, amount }) => ({
                partner, reference, invoice_date, due_date: due_date || null, amount: amount ?? 0,
            })),
            inventory: inventory.map(({ sku, name, quantity, unit_cost }) => ({ sku, name, quantity: quantity ?? 0, unit_cost: unit_cost ?? 0 })),
        }, {
            preserveScroll: true,
            onStart: () => { processing = true; errors = {}; },
            onError: (validationErrors) => { errors = validationErrors; },
            onFinish: () => { processing = false; },
        });
    }
</script>

<AppHead title="Saldo Awal" />
<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header>
        <Link href={indexUrl} class="underline underline-offset-4">Kembali ke COA</Link>
        <h1 class="mt-2 text-3xl font-bold">Saldo Awal</h1>
        <p class="mt-1 text-muted-foreground">Masukkan saldo pembukuan lama beserta tagihan dan rincian persediaannya.</p>
    </header>
    <form class="flex flex-col gap-5" onsubmit={submit}>
        {#if Object.keys(errors).length}
            <div class="rounded-md border border-destructive/50 p-4 text-sm text-destructive" role="alert">
                {#each [...new Set(Object.values(errors))] as message (message)}<p>{message}</p>{/each}
            </div>
        {/if}
        <fieldset disabled={processing} class="grid min-w-0 gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-5">
            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="opening-date">Saldo per tanggal</Label>
                <DateRangePicker id="opening-date" bind:value={selectedDate} min="1900-01-01" max={maxDate} required disabled={processing} />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="opening-description">Keterangan saldo awal</Label>
                <Input id="opening-description" bind:value={description} maxlength={500} />
            </div>
            <p class="text-sm text-muted-foreground sm:col-span-2">Tanggal saldo awal harus lebih awal dari seluruh jurnal terposting. Transaksi operasional berikutnya harus bertanggal setelah tanggal ini.</p>
        </fieldset>

        <section class="overflow-hidden rounded-xl border bg-card" aria-labelledby="opening-accounts-title">
            <header class="border-b px-4 py-4 sm:px-5">
                <h2 id="opening-accounts-title" class="font-semibold">Saldo akun</h2>
                <p class="mt-1 text-sm text-muted-foreground">Isi kas, bank, modal, dan akun lainnya. Piutang, utang, serta persediaan diisi melalui rincian di bawah.</p>
            </header>
            <fieldset disabled={processing} class="divide-y">
                {#each lines as line, index (line.key)}
                    <div class="grid min-w-0 grid-cols-2 items-end gap-3 px-4 py-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:px-5">
                        <div class="col-span-2 flex min-w-0 flex-col gap-1.5 md:col-span-1">
                            <Label for={'opening-account-' + line.key}>Akun saldo awal {index + 1}</Label>
                            <AccountSelect id={'opening-account-' + line.key} {accounts} bind:value={line.account_id} disabled={processing} />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'opening-debit-' + line.key}>Debit saldo awal {index + 1}</Label>
                            <NumberInput id={'opening-debit-' + line.key} bind:value={line.debit} currency min={0} max={Number(max)}
                                disabled={(line.credit ?? 0) > 0} placeholder="0" />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'opening-credit-' + line.key}>Kredit saldo awal {index + 1}</Label>
                            <NumberInput id={'opening-credit-' + line.key} bind:value={line.credit} currency min={0} max={Number(max)}
                                disabled={(line.debit ?? 0) > 0} placeholder="0" />
                        </div>
                        <Button type="button" variant="outline" class="col-span-2 justify-self-end md:col-span-1"
                            onclick={() => { lines = lines.filter(item => item.key !== line.key); }}>Hapus baris {index + 1}</Button>
                    </div>
                {/each}
            </fieldset>
            <div class="border-t p-4 sm:px-5">
                <Button type="button" variant="outline" disabled={processing || lines.length >= 50} onclick={() => { lines.push(blank()); }}>Tambah baris akun</Button>
            </div>
        </section>

        <PartnerRows bind:rows={receivables} kind="receivables" date={selectedDate} disabled={processing} {errors} />
        <PartnerRows bind:rows={payables} kind="payables" date={selectedDate} disabled={processing} {errors} />

        <details class="overflow-hidden rounded-xl border bg-card">
            <summary class="cursor-pointer px-4 py-4 font-semibold focus-visible:outline-2 focus-visible:outline-ring sm:px-5">
                Persediaan awal <span class="font-normal text-muted-foreground">· {inventory.length} barang · IDR {money.format(inventoryTotal)}</span>
            </summary>
            <div class="flex flex-col gap-4 border-t p-4 sm:p-5">
                {#each inventory as item, index (item.key)}
                    <fieldset disabled={processing} class="grid min-w-0 gap-3 border-b pb-4 sm:grid-cols-2">
                        <legend class="mb-2 text-sm font-medium">Barang {index + 1}</legend>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'opening-sku-' + item.key}>Kode barang {index + 1}</Label>
                            <Input id={'opening-sku-' + item.key} bind:value={item.sku} maxlength={80} required />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'opening-item-' + item.key}>Nama barang {index + 1}</Label>
                            <Input id={'opening-item-' + item.key} bind:value={item.name} maxlength={160} required />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'opening-qty-' + item.key}>Kuantitas awal {index + 1}</Label>
                            <NumberInput id={'opening-qty-' + item.key} bind:value={item.quantity} min={1} max={1000000} required />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'opening-cost-' + item.key}>Harga perolehan per unit {index + 1}</Label>
                            <NumberInput id={'opening-cost-' + item.key} bind:value={item.unit_cost} currency min={1} max={999999999999} required />
                        </div>
                        <p class="text-sm tabular-nums">Nilai IDR {money.format(BigInt(item.quantity ?? 0) * BigInt(item.unit_cost ?? 0))}</p>
                        <Button type="button" variant="outline" class="justify-self-start"
                            onclick={() => { inventory = inventory.filter(row => row.key !== item.key); }}>Hapus barang {index + 1}</Button>
                    </fieldset>
                {:else}
                    <p class="text-sm text-muted-foreground">Belum ada rincian persediaan awal.</p>
                {/each}
                <Button type="button" variant="outline" class="self-start" disabled={processing || inventory.length >= 100}
                    onclick={() => { inventory.push({ key: nextKey++, sku: '', name: '', quantity: 1 }); }}>Tambah barang awal</Button>
            </div>
        </details>

        <footer class="flex flex-wrap items-end justify-between gap-4 rounded-xl border bg-muted/40 p-4">
            <div>
                <dl class="flex flex-wrap gap-x-6 gap-y-2">
                    <div><dt class="text-muted-foreground">Total debit</dt><dd class="font-semibold tabular-nums">IDR {money.format(totalDebit)}</dd></div>
                    <div><dt class="text-muted-foreground">Total kredit</dt><dd class="font-semibold tabular-nums">IDR {money.format(totalCredit)}</dd></div>
                </dl>
                <p class="mt-2 text-sm" role="status">
                    {#if totalDebit > max || totalCredit > max}Total saldo awal melebihi batas nominal.
                    {:else if balanced}Saldo awal seimbang.
                    {:else}Selisih IDR {money.format(totalDebit > totalCredit ? totalDebit - totalCredit : totalCredit - totalDebit)}.{/if}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">Periksa rincian sebelum posting. Satu paket saldo awal dicatat untuk setiap perusahaan.</p>
            </div>
            <Button type="submit" disabled={processing || !ready}>{processing ? 'Memposting…' : 'Posting saldo awal'}</Button>
        </footer>
    </form>
</main>
