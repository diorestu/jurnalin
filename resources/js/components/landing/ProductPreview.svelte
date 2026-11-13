<script lang="ts">
    import BookOpen from '@lucide/svelte/icons/book-open';
    import Check from '@lucide/svelte/icons/check';
    import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
    import FileText from '@lucide/svelte/icons/file-text';
    import ListTree from '@lucide/svelte/icons/list-tree';
</script>

<div
    class="overflow-hidden rounded-xl border border-border bg-card shadow-[0_24px_64px_-40px_rgba(2,29,79,0.45)] dark:shadow-none"
    aria-label="Pratinjau dashboard jurnal"
    data-test="landing-product-preview"
>
    <div class="grid min-h-[28rem] sm:grid-cols-[9rem_minmax(0,1fr)]">
        <aside
            class="hidden border-r border-border bg-muted/40 p-4 sm:block"
            aria-hidden="true"
        >
            <p class="mb-5 text-xs font-semibold tracking-wide text-foreground">
                Workspace
            </p>
            <div class="space-y-1 text-xs text-muted-foreground">
                <div
                    class="flex items-center gap-2 rounded-md bg-[#e8eef8] px-2.5 py-2 font-semibold text-[#021d4f] dark:bg-[#0d2347] dark:text-[#dce8ff]"
                >
                    <FileText class="size-4" />
                    Jurnal
                </div>
                <div class="flex items-center gap-2 px-2.5 py-2">
                    <BookOpen class="size-4" />
                    Buku besar
                </div>
                <div class="flex items-center gap-2 px-2.5 py-2">
                    <ListTree class="size-4" />
                    COA
                </div>
            </div>
        </aside>

        <div class="min-w-0 p-4 sm:p-5">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-muted-foreground">Buku jurnal</p>
                    <h2 class="text-xl font-semibold tracking-tight">Jurnal</h2>
                </div>
                <span
                    class="inline-flex min-h-9 items-center rounded-md bg-[#021d4f] px-3 text-xs font-semibold whitespace-nowrap text-white"
                >
                    Buat jurnal
                </span>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-border p-3.5">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                        >
                            <Check class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold">Jurnal seimbang</p>
                            <p class="text-xs text-muted-foreground">Debit = Kredit</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-border p-3.5">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#edf2fa] text-[#021d4f] dark:bg-[#0d2347] dark:text-[#dce8ff]"
                        >
                            <ClipboardCheck class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold">Persetujuan</p>
                            <p class="text-xs text-muted-foreground">Siap diperiksa</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-3 flex items-end justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold">Jurnal terbaru</h3>
                        <p class="text-xs text-muted-foreground">
                            Transaksi tersusun per periode.
                        </p>
                    </div>
                    <span class="text-xs font-medium text-[#021d4f] dark:text-[#9dbcf0]">
                        November 2026
                    </span>
                </div>

                <div class="overflow-hidden rounded-lg border border-border">
                    <div
                        class="grid grid-cols-[5.5rem_minmax(0,1fr)_4.5rem] gap-2 bg-muted/50 px-3 py-2 text-[11px] font-medium text-muted-foreground"
                    >
                        <span>Tanggal</span>
                        <span>Keterangan</span>
                        <span class="text-right">Status</span>
                    </div>
                    {#each ['Pendapatan jasa', 'Pembayaran pemasok', 'Biaya operasional'] as label}
                        <div
                            class="grid grid-cols-[5.5rem_minmax(0,1fr)_4.5rem] items-center gap-2 border-t border-border px-3 py-3 text-xs"
                        >
                            <span class="tabular-nums text-muted-foreground">—</span>
                            <span class="truncate font-medium">{label}</span>
                            <span class="text-right text-muted-foreground">Draf</span>
                        </div>
                    {/each}
                </div>
            </div>
        </div>
    </div>
</div>
