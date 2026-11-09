<script module lang="ts">
    export const layout = (props: { indexUrl: string }) => ({
        breadcrumbs: [{ title: 'Periode Akuntansi', href: props.indexUrl }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { NativeSelect } from '@/components/ui/select';

    type Period = {
        month: string;
        locked: boolean;
        reason: string | null;
        actor: string | null;
        changedAt: string | null;
        version: number;
    };
    let { month, current, periods, indexUrl, changeUrl, auditUrl, lockedThrough }: {
        month: string;
        lockedThrough: string | null;
        current: Period;
        periods: { data: Period[]; prev_page_url: string | null; next_page_url: string | null };
        indexUrl: string;
        changeUrl: string;
        auditUrl: string;
    } = $props();

    const months = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    const timestamps = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });
    const displayMonth = (value: string) => months.format(new Date(value + '-01'));
    let selectedMonth = $state(untrack(() => month));
    let loadedVersion = $state(untrack(() => current.version));
    let targetState = $state(untrack(() => current.locked ? 'open' : 'locked'));
    let reason = $state('');
    let processing = $state(false);
    let loading = $state(false);
    let errors = $state<Record<string, string>>({});
    const currentSelection = $derived(selectedMonth === month);

    function selectMonth(event: SubmitEvent) {
        event.preventDefault();
        if (!selectedMonth || processing || loading) return;

        router.get(indexUrl, { month: selectedMonth }, {
            preserveScroll: true,
            onStart: () => { loading = true; },
            onFinish: () => { loading = false; },
        });
    }

    function change(event: SubmitEvent) {
        event.preventDefault();
        if (!currentSelection || processing || loading) return;

        router.post(changeUrl, {
            month,
            locked: targetState === 'locked',
            reason,
            version: loadedVersion,
        }, {
            preserveScroll: true,
            onStart: () => { processing = true; errors = {}; },
            onError: (validationErrors) => { errors = validationErrors; },
            onSuccess: response => { loadedVersion = (response.props.current as Period).version; reason = ''; targetState = targetState === 'locked' ? 'open' : 'locked'; },
            onFinish: () => { processing = false; },
        });
    }
</script>

<AppHead title="Periode Akuntansi" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-5 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold">Periode Akuntansi</h1>
            <p class="mt-1 text-muted-foreground">Kunci bulan yang selesai diperiksa untuk melindungi pencatatan dan klasifikasi akun.</p>
        </div>
        <Link href={auditUrl} class="text-base underline underline-offset-4">Lihat audit akuntansi</Link>
    </header>

    {#if lockedThrough}
        <p class="rounded-md border bg-muted/40 p-3">Pembukuan terkunci sampai akhir {displayMonth(lockedThrough)}, termasuk seluruh transaksi sebelumnya. Buka periode terkunci yang paling baru untuk melakukan koreksi.</p>
    {/if}

    <form class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" onsubmit={selectMonth}>
        <div class="flex w-full min-w-0 flex-col gap-1.5 sm:max-w-xs">
            <Label for="accounting-month">Periode bulan</Label>
            <DateRangePicker id="accounting-month" mode="month" bind:value={selectedMonth} min="1900-01" max="9999-12" required disabled={processing || loading} />
        </div>
        <Button type="submit" variant="outline" disabled={processing || loading}>{loading ? 'Memuat…' : 'Tampilkan periode'}</Button>
    </form>

    <section class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5" aria-labelledby="accounting-current-heading">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="accounting-current-heading" class="text-lg font-semibold">{displayMonth(month)}</h2>
            <Badge variant={current.locked ? 'default' : 'secondary'}>{current.locked ? 'Dikunci' : 'Terbuka'}</Badge>
        </header>
        {#if current.changedAt}
            <dl class="mt-4 grid gap-3 text-base sm:grid-cols-2">
                <div class="min-w-0"><dt class="text-muted-foreground">Diubah oleh</dt><dd class="break-words">{current.actor ?? 'Pengguna tidak tersedia'}</dd></div>
                <div><dt class="text-muted-foreground">Waktu perubahan (WIB)</dt><dd>{timestamps.format(new Date(current.changedAt))}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="text-muted-foreground">Alasan terakhir</dt><dd class="break-words">{current.reason}</dd></div>
            </dl>
        {:else}
            <p class="mt-3 text-muted-foreground">Periode ini belum pernah diubah.</p>
        {/if}

        <form class="mt-5 flex flex-col gap-3 border-t pt-4" onsubmit={change}>
            <fieldset class="grid gap-3 sm:grid-cols-2" disabled={processing || loading || !currentSelection}>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="accounting-state">Perubahan status</Label>
                    <NativeSelect id="accounting-state" bind:value={targetState}>
                        <option value="locked">Kunci periode</option>
                        <option value="open">Buka periode</option>
                    </NativeSelect>
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="accounting-reason">Alasan perubahan</Label>
                    <Input id="accounting-reason" bind:value={reason} required minlength={3} maxlength={500}
                        placeholder="Jelaskan alasan mengunci atau membuka periode" aria-invalid={!!errors.reason} />
                    <InputError message={errors.reason} />
                </div>
                <div class="sm:col-span-2">
                    <Button type="submit">{processing ? 'Menyimpan…' : targetState === 'locked' ? 'Kunci periode' : 'Buka periode'}</Button>
                </div>
            </fieldset>
            {#if !currentSelection}
                <p class="text-sm text-muted-foreground">Tampilkan periode yang dipilih sebelum mengubah statusnya.</p>
            {/if}
            {#if errors.month || errors.version || errors.locked}
                <p class="text-sm text-destructive" role="alert">{errors.month || errors.version || errors.locked}</p>
                {#if errors.version}
                    <Link href={indexUrl + '?month=' + month} class="self-start text-sm underline underline-offset-4">Muat ulang status periode</Link>
                {/if}
            {/if}
        </form>
    </section>

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="accounting-period-history">
        <h2 id="accounting-period-history" class="border-b px-4 py-4 text-lg font-semibold sm:px-5">Periode yang pernah diubah</h2>
        <ul class="divide-y">
            {#each periods.data as period (period.month)}
                <li class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <Link href={indexUrl + '?month=' + period.month} class="font-medium underline-offset-4 hover:underline">{displayMonth(period.month)}</Link>
                        <p class="mt-0.5 break-words text-sm text-muted-foreground">{period.reason}</p>
                        {#if period.changedAt}
                            <p class="mt-0.5 text-sm text-muted-foreground">{period.actor ?? 'Pengguna tidak tersedia'} · {timestamps.format(new Date(period.changedAt))} WIB</p>
                        {/if}
                    </div>
                    <Badge variant={period.locked ? 'default' : 'secondary'}>{period.locked ? 'Dikunci' : 'Terbuka'}</Badge>
                </li>
            {:else}
                <li class="px-4 py-5 text-muted-foreground sm:px-5">Belum ada perubahan periode.</li>
            {/each}
        </ul>
    </section>
    {#if periods.prev_page_url || periods.next_page_url}
        <nav class="flex flex-wrap gap-4" aria-label="Halaman periode akuntansi">
            {#if periods.prev_page_url}<Link href={periods.prev_page_url} class="underline">Sebelumnya</Link>{/if}
            {#if periods.next_page_url}<Link href={periods.next_page_url} class="underline">Berikutnya</Link>{/if}
        </nav>
    {/if}
</main>
