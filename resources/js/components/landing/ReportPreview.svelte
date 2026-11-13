<script lang="ts">
    import FileText from '@lucide/svelte/icons/file-text';

    const reports = [
        'Neraca saldo',
        'Laporan keuangan',
        'Laba rugi',
        'Buku besar',
    ];
</script>

<div
    class="overflow-hidden rounded-xl border border-border bg-card shadow-[0_20px_50px_-38px_rgba(2,29,79,0.5)] dark:shadow-none"
    aria-label="Pratinjau laporan keuangan"
>
    <div class="flex items-center justify-between gap-4 border-b border-border p-4">
        <div>
            <p class="text-xs text-muted-foreground">Periode laporan</p>
            <p class="text-sm font-semibold">November 2026</p>
        </div>
        <div
            class="flex rounded-md border border-border bg-background p-1 text-xs font-semibold"
            aria-hidden="true"
        >
            <span class="rounded bg-[#021d4f] px-3 py-1.5 text-white">Bulanan</span>
            <span class="px-3 py-1.5 text-muted-foreground">Tahunan</span>
        </div>
    </div>

    <div
        class="grid grid-cols-[minmax(0,1fr)_5rem] gap-3 bg-muted/50 px-4 py-2.5 text-xs font-medium text-muted-foreground"
    >
        <span>Laporan</span>
        <span>Periode</span>
    </div>
    {#each reports as report (report)}
        <div
            class="grid grid-cols-[minmax(0,1fr)_5rem] items-center gap-3 border-t border-border px-4 py-3.5"
        >
            <div class="flex min-w-0 items-center gap-3">
                <FileText class="size-4 shrink-0 text-[#021d4f] dark:text-[#9dbcf0]" />
                <span class="truncate text-sm font-medium">{report}</span>
            </div>
            <span class="text-sm tabular-nums text-muted-foreground">—</span>
        </div>
    {/each}
</div>
