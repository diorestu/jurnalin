<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { tick } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { AccountSelect } from '@/components/ui/select';

    type Account = { id: number; code: string; name: string };
    type Line = { key: number; accountId: number | null; debit?: number; credit?: number; autoFrom?: number };
    let { accounts, storeUrl, draftKey, today, canPost }: {
        accounts: Account[]; storeUrl: string; draftKey: string; today: string; canPost: boolean;
    } = $props();

    let nextKey = 0;
    const blank = (): Line => ({ key: nextKey++, accountId: null });
    let lines = $state<Line[]>([blank(), blank()]);
    let date = $state('');
    let description = $state('');
    let processing = $state(false);
    let posted = $state(false);
    let errors = $state<Record<string, string>>({});
    const money = new Intl.NumberFormat('id-ID');
    const maxAmount = 999_999_999_999_999n;
    const totals = $derived(lines.reduce((total, line) => ({
        debit: total.debit + BigInt(line.debit ?? 0),
        credit: total.credit + BigInt(line.credit ?? 0),
    }), { debit: 0n, credit: 0n }));
    const balanced = $derived(totals.debit > 0n && totals.debit === totals.credit && totals.debit <= maxAmount);
    const ready = $derived(balanced && lines.every(line =>
        line.accountId !== null && ((line.debit ?? 0) > 0) !== ((line.credit ?? 0) > 0),
    ));
    const difference = $derived(totals.debit > totals.credit ? totals.debit - totals.credit : totals.credit - totals.debit);

    async function addLine() {
        if (lines.length >= 50) return;
        const line = blank();
        lines.push(line);
        errors = {};
        await tick();
        document.getElementById('manual-account-' + line.key)?.focus();
    }

    function updateAmount(line: Line, side: 'debit' | 'credit', value: number | undefined) {
        line[side] = value;
        line.autoFrom = undefined;
        errors = {};
        posted = false;

        const next = lines[lines.findIndex(item => item.key === line.key) + 1];
        if (!next) return;

        const linked = next.autoFrom === line.key;
        const empty = !(next.debit ?? 0) && !(next.credit ?? 0);
        if (!linked && !empty) return;

        if ((value ?? 0) <= 0) {
            if (linked) {
                next.debit = undefined;
                next.credit = undefined;
                next.autoFrom = undefined;
            }
            return;
        }

        next.debit = side === 'credit' ? value : undefined;
        next.credit = side === 'debit' ? value : undefined;
        next.autoFrom = line.key;
    }

    function removeLine(key: number) {
        lines = lines.filter(line => line.key !== key);
        for (const line of lines) {
            if (line.autoFrom === key) {
                line.debit = undefined;
                line.credit = undefined;
                line.autoFrom = undefined;
            }
        }
        errors = {};
    }

    function preventImplicitPost(event: KeyboardEvent) {
        if (event.key === 'Enter') event.preventDefault();
    }

    function post(event?: SubmitEvent, action: 'post' | 'draft' | 'submit' = canPost ? 'post' : 'submit') {
        event?.preventDefault();
        if (processing || (action === 'draft' ? !description.trim() && !lines.some(line => line.accountId !== null || (line.debit ?? 0) > 0 || (line.credit ?? 0) > 0) : !ready)) return;

        router.post(storeUrl, {
            request_id: draftKey,
            action,
            date: date || today,
            description,
            lines: lines.map(line => ({ account_id: line.accountId, debit: line.debit ?? 0, credit: line.credit ?? 0 })),
        }, {
            preserveScroll: true,
            onStart: () => { processing = true; errors = {}; posted = false; },
            onError: (validationErrors) => { errors = validationErrors; },
            onSuccess: () => { lines = [blank(), blank()]; description = ''; posted = true; },
            onFinish: () => { processing = false; },
        });
    }
</script>

<section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="manual-journal-title">
    <header class="border-b px-4 py-4 sm:px-5">
        <h2 id="manual-journal-title" class="font-semibold">Entri jurnal manual</h2>
        <p class="mt-0.5 text-sm text-muted-foreground">Isi debit atau kredit. Nominal yang sama akan disarankan pada sisi lawan di baris berikutnya.</p>
    </header>
    <form onsubmit={post}>
        <fieldset disabled={processing} class="flex flex-col gap-4">
            <div class="grid gap-4 px-4 pt-4 sm:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)] sm:px-5">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="manual-date">Tanggal jurnal manual</Label>
                    <DateRangePicker id="manual-date" bind:value={() => date || today, value => { date = value; }}
                        min="1900-01-01" max="9999-12-31" required disabled={processing} invalid={!!errors.date} />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="manual-description">Keterangan jurnal manual</Label>
                    <Input id="manual-description" bind:value={description} maxlength={500}
                        onkeydown={preventImplicitPost} aria-invalid={!!errors.description} />
                </div>
            </div>
            <div class="divide-y border-y">
                {#each lines as line, index (line.key)}
                    <div class="grid grid-cols-2 items-start gap-3 px-4 py-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:px-5">
                        <div class="col-span-2 flex min-w-0 flex-col gap-1.5 md:col-span-1">
                            <Label for={'manual-account-' + line.key}>Akun COA <span class="sr-only">baris {index + 1}</span></Label>
                            <AccountSelect id={'manual-account-' + line.key} {accounts} bind:value={line.accountId}
                                required disabled={processing} invalid={!!errors['lines.' + index + '.account_id']} />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'manual-debit-' + line.key}>Debit <span class="sr-only">baris {index + 1}</span></Label>
                            <NumberInput id={'manual-debit-' + line.key} currency min={0} max={Number(maxAmount)}
                                bind:value={() => line.debit, value => updateAmount(line, 'debit', value)}
                                disabled={processing || (line.credit ?? 0) > 0} placeholder="0" onkeydown={preventImplicitPost} />
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for={'manual-credit-' + line.key}>Kredit <span class="sr-only">baris {index + 1}</span></Label>
                            <NumberInput id={'manual-credit-' + line.key} currency min={0} max={Number(maxAmount)}
                                bind:value={() => line.credit, value => updateAmount(line, 'credit', value)}
                                disabled={processing || (line.debit ?? 0) > 0} placeholder="0" onkeydown={preventImplicitPost} />
                        </div>
                        <Button type="button" variant="ghost" size="icon" class="col-span-2 justify-self-end md:col-span-1 md:mt-5"
                            disabled={lines.length <= 2} aria-label={'Hapus baris manual ' + (index + 1)}
                            onclick={() => removeLine(line.key)}>
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                        {#if (line.debit ?? 0) > 0 && (line.credit ?? 0) > 0}
                            <p class="col-span-full text-sm text-destructive">Isi debit atau kredit saja pada baris ini.</p>
                        {/if}
                        {#if Object.keys(errors).some(key => key.startsWith('lines.' + index + '.'))}
                            <p class="col-span-full text-sm text-destructive" role="alert">{Object.entries(errors).find(([key]) => key.startsWith('lines.' + index + '.'))?.[1]}</p>
                        {/if}
                    </div>
                {/each}
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5">
                <Button type="button" variant="outline" disabled={lines.length >= 50} onclick={addLine}>
                    <Plus class="size-4" aria-hidden="true" /> Tambah baris
                </Button>
                <dl class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <div><dt class="text-muted-foreground">Total debit (IDR)</dt><dd class="font-semibold tabular-nums">{money.format(totals.debit)}</dd></div>
                    <div><dt class="text-muted-foreground">Total kredit (IDR)</dt><dd class="font-semibold tabular-nums">{money.format(totals.credit)}</dd></div>
                </dl>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 pb-4 sm:px-5">
                <div class="text-sm" role="status">
                    {#if Object.keys(errors).some(key => !key.startsWith('lines.'))}
                        <p class="text-destructive">{Object.entries(errors).find(([key]) => !key.startsWith('lines.'))?.[1]}</p>
                    {:else if posted}
                        <p>Jurnal manual berhasil diposting.</p>
                    {:else if totals.debit > maxAmount || totals.credit > maxAmount}
                        <p class="text-destructive">Total jurnal maksimal IDR {money.format(maxAmount)}.</p>
                    {:else if balanced && !ready}
                        <p class="text-muted-foreground">Total seimbang. Lengkapi akun dan isi satu sisi pada setiap baris.</p>
                    {:else if balanced}
                        <p>Jurnal seimbang.</p>
                    {:else}
                        <p class="text-muted-foreground">Selisih debit dan kredit IDR {money.format(difference)}.</p>
                    {/if}
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" disabled={processing} onclick={() => post(undefined, 'draft')}>Simpan draf</Button>
                    <Button type="submit" disabled={!ready || processing}>{processing ? 'Menyimpan…' : canPost ? 'Posting jurnal manual' : 'Ajukan persetujuan'}</Button>
                </div>
            </div>
        </fieldset>
    </form>
</section>
