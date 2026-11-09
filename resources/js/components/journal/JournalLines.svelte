<script lang="ts">
    import type { JournalLine } from './types';

    let {
        lines,
        amount,
        label,
    }: {
        lines: JournalLine[];
        amount?: number | null;
        label: string;
    } = $props();

    const rupiah = new Intl.NumberFormat('id-ID');
    const totals = $derived(
        lines.reduce(
            (sum, line) => {
                sum[line.side] += line.amount ?? amount ?? 0;
                return sum;
            },
            { debit: 0, credit: 0 },
        ),
    );

    function display(value?: number | null): string {
        return value ? rupiah.format(value) : '—';
    }
</script>

<div class="overflow-x-auto">
    <table class="w-full text-sm sm:table-fixed" aria-label={label}>
        <colgroup><col /><col class="w-1/4" /><col class="w-1/4" /></colgroup>
        <thead class="bg-muted/60 text-muted-foreground">
            <tr>
                <th scope="col" class="px-3 py-2 text-left font-medium sm:px-4">Akun</th>
                <th scope="col" class="px-3 py-2 text-right font-medium sm:px-4">Debit (Rp)</th>
                <th scope="col" class="px-3 py-2 text-right font-medium sm:px-4">Kredit (Rp)</th>
            </tr>
        </thead>
        <tbody>
            {#each lines as line, index (index)}
                <tr class="border-b">
                    <td class="px-3 py-2.5 sm:px-4">
                        <span class={line.side === 'credit' ? 'block pl-2.5 sm:pl-4' : 'block'}>
                            <span class="mr-1.5 inline-block text-xs tabular-nums text-muted-foreground">{line.account_code}</span>
                            {line.account_name}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums sm:px-4">
                        {line.side === 'debit' ? display(line.amount ?? amount) : '—'}
                    </td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums sm:px-4">
                        {line.side === 'credit' ? display(line.amount ?? amount) : '—'}
                    </td>
                </tr>
            {:else}
                <tr>
                    <td colspan="3" class="px-3 py-5 text-center text-muted-foreground">
                        Pilih template untuk melihat baris jurnal.
                    </td>
                </tr>
            {/each}
        </tbody>
        <tfoot class="bg-muted/60 font-medium">
            <tr>
                <th scope="row" class="px-3 py-2 text-left font-medium sm:px-4">Total jurnal</th>
                <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-4">{display(totals.debit)}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-4">{display(totals.credit)}</td>
            </tr>
        </tfoot>
    </table>
</div>
