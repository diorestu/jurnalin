<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { rupiah, type AccountBalance } from './format';

    let { title, rows, total }: { title: string; rows: AccountBalance[]; total: string } = $props();
</script>

<section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-label={title}>
    <h2 class="border-b px-4 py-3 text-lg font-semibold">{title}</h2>
    <table class="w-full table-fixed text-base">
        <thead class="bg-muted/60 text-muted-foreground">
            <tr>
                <th scope="col" class="px-4 py-2 text-left font-medium">Akun</th>
                <th scope="col" class="w-[42%] px-4 py-2 text-right font-medium">Saldo</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            {#each rows as row (row.id)}
                <tr>
                    <td class="break-words px-4 py-2">
                        {#if row.ledgerUrl}
                            <Link href={row.ledgerUrl} class="underline decoration-muted-foreground/40 underline-offset-4">
                                <span class="block text-sm text-muted-foreground">{row.code}</span>{row.name}
                            </Link>
                        {:else}
                            <span class="block text-sm text-muted-foreground">{row.code}</span>{row.name}
                        {/if}
                    </td>
                    <td class="break-words px-4 py-2 text-right tabular-nums">
                        {#if row.ledgerUrl}<Link href={row.ledgerUrl} class="underline decoration-muted-foreground/40 underline-offset-4">{rupiah(row.balance)}</Link>{:else}{rupiah(row.balance)}{/if}
                    </td>
                </tr>
            {:else}
                <tr><td colspan="2" class="px-4 py-4 text-muted-foreground">Belum ada saldo pada akun {title.toLowerCase()}.</td></tr>
            {/each}
        </tbody>
        <tfoot class="border-t bg-muted/60 font-semibold">
            <tr>
                <th scope="row" class="px-4 py-3 text-left font-semibold">Total {title.toLowerCase()}</th>
                <td class="break-words px-4 py-3 text-right tabular-nums">{rupiah(total)}</td>
            </tr>
        </tfoot>
    </table>
</section>
