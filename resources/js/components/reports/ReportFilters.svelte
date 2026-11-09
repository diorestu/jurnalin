<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Label } from '@/components/ui/label';
    import { AccountSelect, NativeSelect } from '@/components/ui/select';

    let { url, month, period, year, start, end, accounts, accountId }: {
        url: string;
        month?: string;
        period?: 'monthly' | 'yearly';
        year?: string;
        start?: string;
        end?: string;
        accounts?: { id: number; code: string; name: string; active: boolean }[];
        accountId?: number | null;
    } = $props();

    let selectedPeriod = $state(untrack(() => period ?? 'monthly'));
    let selectedYear = $state(untrack(() => year ?? month?.slice(0, 4) ?? ''));
    let date = $state(untrack(() => month ?? ''));
    let from = $state(untrack(() => start ?? ''));
    let until = $state(untrack(() => end ?? ''));
    let account = $state(untrack(() => accountId ?? null));
    let loading = $state(false);

    function apply(event: SubmitEvent) {
        event.preventDefault();
        if (loading) return;

        const params: { period?: 'monthly' | 'yearly'; month?: string; year?: string; start?: string; end?: string; account?: number | null } =
            month === undefined ? { start: from, end: until }
                : selectedPeriod === 'yearly' ? { period: selectedPeriod, year: selectedYear }
                    : { period: selectedPeriod, month: date };
        if (accounts !== undefined) params.account = account;

        router.get(url, params, {
            preserveScroll: true,
            onStart: () => { loading = true; },
            onFinish: () => { loading = false; },
        });
    }
</script>

<form class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" onsubmit={apply}>
    {#if accounts !== undefined}
        <div class="flex w-full min-w-0 flex-col gap-1.5 sm:max-w-sm">
            <Label for="report-account">Akun COA</Label>
            <AccountSelect id="report-account" {accounts} bind:value={account} disabled={loading} required />
        </div>
    {/if}
    {#if month !== undefined}
        <div class="flex w-full min-w-0 flex-col gap-1.5 sm:w-40">
            <Label for="report-period-type">Jenis periode</Label>
            <NativeSelect id="report-period-type" value={selectedPeriod} disabled={loading}
                onchange={event => {
                    selectedPeriod = event.currentTarget.value as 'monthly' | 'yearly';
                    if (selectedPeriod === 'yearly') selectedYear = date.slice(0, 4);
                    else date = selectedYear + date.slice(4);
                }}>
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
            </NativeSelect>
        </div>
    {/if}
    <div class="flex w-full min-w-0 flex-col gap-1.5 sm:w-auto">
        <Label for="report-period">{month === undefined ? 'Periode laporan' : selectedPeriod === 'yearly' ? 'Tahun laporan' : 'Periode bulan'}</Label>
        {#if month !== undefined && selectedPeriod === 'yearly'}
            <DateRangePicker id="report-period" mode="year" bind:value={selectedYear} min="1900" max="9999" disabled={loading} required />
        {:else if month !== undefined}
            <DateRangePicker id="report-period" mode="month" bind:value={date} min="1900-01" max="9999-12" disabled={loading} required />
        {:else}
            <DateRangePicker id="report-period" mode="range" bind:value={from} bind:endValue={until}
                min="1900-01-01" max="9999-12-31" disabled={loading} required />
        {/if}
    </div>
    <Button type="submit" disabled={loading || (accounts !== undefined && account === null)}>
        {loading ? 'Memuat…' : 'Tampilkan laporan'}
    </Button>
    {#if Object.keys(page.props.errors).length}
        <p class="w-full text-sm text-destructive" role="alert">{Object.values(page.props.errors)[0]}</p>
    {/if}
</form>
