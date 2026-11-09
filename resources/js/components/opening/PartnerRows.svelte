<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import type { PartnerDraft } from './types';

    let { rows = $bindable([]), kind, date, disabled, errors }: {
        rows: PartnerDraft[];
        kind: 'receivables' | 'payables';
        date: string;
        disabled: boolean;
        errors: Record<string, string>;
    } = $props();

    let nextKey = 0;
    const title = $derived(kind === 'receivables' ? 'Piutang pelanggan' : 'Utang pemasok');
    const partnerLabel = $derived(kind === 'receivables' ? 'Pelanggan' : 'Pemasok');
    const total = $derived(rows.reduce((sum, row) => sum + BigInt(row.amount ?? 0), 0n));
    const money = new Intl.NumberFormat('id-ID');
</script>

<details class="overflow-hidden rounded-xl border bg-card text-card-foreground">
    <summary class="cursor-pointer px-4 py-4 font-semibold focus-visible:outline-2 focus-visible:outline-ring sm:px-5">
        {title} <span class="font-normal text-muted-foreground">· {rows.length} rincian · IDR {money.format(total)}</span>
    </summary>
    <div class="flex flex-col gap-4 border-t p-4 sm:p-5">
        <p class="text-sm text-muted-foreground">Isi sisa tagihan yang belum dilunasi pada tanggal saldo awal.</p>
        {#each rows as row, index (row.key)}
            {@const prefix = kind + '-' + row.key}
            <fieldset disabled={disabled} class="grid min-w-0 gap-3 border-b pb-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-medium">Rincian {index + 1}</legend>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for={prefix + '-partner'}>{partnerLabel} {index + 1}</Label>
                    <Input id={prefix + '-partner'} bind:value={row.partner} maxlength={160} required />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for={prefix + '-reference'}>Nomor tagihan {partnerLabel.toLowerCase()} {index + 1}</Label>
                    <Input id={prefix + '-reference'} bind:value={row.reference} maxlength={100} required />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for={prefix + '-date'}>Tanggal tagihan {index + 1}</Label>
                    <DateRangePicker id={prefix + '-date'} bind:value={row.invoice_date} min="1900-01-01" max={date} required {disabled} />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for={prefix + '-due'}>Jatuh tempo tagihan {index + 1}</Label>
                    <DateRangePicker id={prefix + '-due'} bind:value={row.due_date} min={row.invoice_date || '1900-01-01'} max="9999-12-31" clearable {disabled} />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for={prefix + '-amount'}>Sisa {kind === 'receivables' ? 'piutang' : 'utang'} {index + 1}</Label>
                    <NumberInput id={prefix + '-amount'} bind:value={row.amount} currency min={1} max={999999999999999} required />
                </div>
                <Button type="button" variant="outline" class="self-end justify-self-start" disabled={disabled}
                    onclick={() => { rows = rows.filter(item => item.key !== row.key); }}>Hapus rincian {index + 1}</Button>
                {#if row.invoice_date > date}
                    <p class="text-sm text-destructive sm:col-span-2">Tanggal tagihan harus pada atau sebelum tanggal saldo awal.</p>
                {:else if row.due_date && row.due_date < row.invoice_date}
                    <p class="text-sm text-destructive sm:col-span-2">Jatuh tempo harus pada atau setelah tanggal tagihan.</p>
                {/if}
                {#each Object.entries(errors).filter(([field]) => field.startsWith(kind + '.' + index + '.')) as [field, message] (field)}
                    <p class="text-sm text-destructive sm:col-span-2" role="alert">{message}</p>
                {/each}
            </fieldset>
        {:else}
            <p class="text-sm text-muted-foreground">Belum ada rincian {title.toLowerCase()}.</p>
        {/each}
        <Button type="button" variant="outline" class="self-start" disabled={disabled || rows.length >= 100}
            onclick={() => { rows.push({ key: nextKey++, partner: '', reference: '', invoice_date: date, due_date: '' }); }}>
            Tambah {kind === 'receivables' ? 'piutang' : 'utang'}
        </Button>
    </div>
</details>
