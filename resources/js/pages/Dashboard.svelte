<script module lang="ts">
    import { dashboard } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ArrowDownLeft from '@lucide/svelte/icons/arrow-down-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import ArrowUpRight from '@lucide/svelte/icons/arrow-up-right';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import Scale from '@lucide/svelte/icons/scale';
    import AppHead from '@/components/AppHead.svelte';
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Label } from '@/components/ui/label';
    import type { DashboardInvitation } from '@/types';

    type Totals = { revenue: string; expense: string; net: string; entries: number; unclassifiedLines: number };
    type Day = { day: number; entries: number };
    type RecentJournal = { id: number; date: string; title: string; description: string | null; amount: string };

    let {
        month,
        totals,
        activity,
        recentJournals,
        dashboardUrl,
        journalUrl,
        pendingInvitations = [],
    }: {
        month: string;
        totals: Totals;
        activity: Day[];
        recentJournals: RecentJournal[];
        dashboardUrl: string;
        journalUrl: string;
        pendingInvitations?: DashboardInvitation[];
    } = $props();

    const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' });
    const monthFormat = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    const countFormat = new Intl.NumberFormat('id-ID');

    let selectedMonth = $state('');
    let loading = $state(false);
    const period = $derived(monthFormat.format(new Date(month + '-01')));
    const maxDaily = $derived(Math.max(1, ...activity.map(({ entries }) => entries)));
    const activeDays = $derived(activity.filter(({ entries }) => entries > 0));
    const metrics = $derived([
        { label: 'Pendapatan', value: currency.format(BigInt(totals.revenue)), icon: ArrowDownLeft },
        { label: 'Beban', value: currency.format(BigInt(totals.expense)), icon: ArrowUpRight },
        { label: 'Selisih pendapatan dan beban', value: currency.format(BigInt(totals.net)), icon: Scale },
        { label: 'Jurnal terposting', value: countFormat.format(totals.entries), icon: BookOpen },
    ]);

    function applyMonth(event: SubmitEvent) {
        event.preventDefault();
        if (!selectedMonth || selectedMonth === month || loading) return;

        router.get(dashboardUrl, { month: selectedMonth }, {
            preserveScroll: true,
            onStart: () => { loading = true; },
            onFinish: () => { loading = false; },
        });
    }
</script>

<AppHead title="Dashboard" />

{#if pendingInvitations.length > 0}
    <PendingInvitationsModal invitations={pendingInvitations} />
{/if}

<main class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p class="mt-1 text-sm text-muted-foreground">Ringkasan jurnal terposting untuk {period}.</p>
        </div>
        <form class="flex flex-wrap items-end gap-2" onsubmit={applyMonth}>
            <div class="flex flex-col gap-1.5">
                <Label for="dashboard-month">Periode</Label>
                <DateRangePicker id="dashboard-month" mode="month" min="1900-01" max="9999-12"
                    bind:value={() => selectedMonth || month, (value) => { selectedMonth = value; }}
                    disabled={loading} required />
            </div>
            <Button type="submit" variant="outline" disabled={!selectedMonth || selectedMonth === month || loading}>
                {loading ? 'Memuat…' : 'Terapkan'}
            </Button>
        </form>
    </header>

    <section aria-label="Ringkasan periode" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {#each metrics as metric (metric.label)}
            <Card class="min-w-0 gap-3 py-4">
                <CardHeader class="flex items-start justify-between gap-3 px-4">
                    <CardTitle class="text-sm leading-snug font-medium text-muted-foreground">{metric.label}</CardTitle>
                    <metric.icon class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                </CardHeader>
                <CardContent class="px-4">
                    <p class="text-xl font-semibold break-words tabular-nums">{metric.value}</p>
                </CardContent>
            </Card>
        {/each}
    </section>

    {#if totals.unclassifiedLines > 0}
        <p class="text-sm text-muted-foreground">
            {countFormat.format(totals.unclassifiedLines)} baris jurnal memakai akun yang belum memiliki jenis COA dan tidak dihitung dalam pendapatan atau beban.
        </p>
    {/if}

    <Card class="min-w-0 gap-4">
        <CardHeader>
            <CardTitle>Aktivitas jurnal harian</CardTitle>
            {#if activeDays.length > 0}
                <p class="text-sm text-muted-foreground">{countFormat.format(totals.entries)} jurnal tercatat pada {activeDays.length} hari di {period}.</p>
            {/if}
        </CardHeader>
        <CardContent>
            {#if activeDays.length}
                <div class="flex gap-[2px] sm:gap-1.5" aria-hidden="true">
                    {#each activity as day (day.day)}
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <div class="flex h-24 w-full items-end border-b" title={day.day + ' · ' + day.entries + ' jurnal'}>
                                {#if day.entries > 0}
                                    <span class="w-full rounded-t-sm bg-primary/75" style:height={(100 * day.entries / maxDaily) + '%'}></span>
                                {/if}
                            </div>
                            <span class="h-4 text-xs tabular-nums text-muted-foreground">
                                {day.day === 1 || day.day === 10 || day.day === 20 || day.day === activity.length ? day.day : ''}
                            </span>
                        </div>
                    {/each}
                </div>
                <ul class="sr-only" aria-label="Jumlah jurnal menurut tanggal">
                    {#each activeDays as day (day.day)}
                        <li>Tanggal {day.day}, {day.entries} jurnal</li>
                    {/each}
                </ul>
            {:else}
                <p class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">Belum ada jurnal terposting pada periode ini.</p>
            {/if}
        </CardContent>
    </Card>

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="recent-journals-title">
        <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-5">
            <div>
                <h2 id="recent-journals-title" class="font-semibold">Jurnal terbaru</h2>
                <p class="mt-0.5 text-sm text-muted-foreground">Maksimal lima jurnal pada periode yang dipilih.</p>
            </div>
            <Link href={journalUrl} class="inline-flex items-center gap-1.5 text-sm font-medium underline underline-offset-4">
                Buka Buku Jurnal <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </header>

        {#if recentJournals.length}
            <table class="w-full table-fixed border-t text-sm">
                <thead class="bg-muted/60 text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-left font-medium sm:px-5">Jurnal</th>
                        <th scope="col" class="hidden w-1/5 px-4 py-2.5 text-left font-medium sm:table-cell">Tanggal</th>
                        <th scope="col" class="w-[42%] px-4 py-2.5 text-right font-medium sm:w-1/4 sm:px-5">Total debit</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#each recentJournals as journal (journal.id)}
                        {@const date = dateFormat.format(new Date(journal.date))}
                        <tr>
                            <td class="break-words px-4 py-3 sm:px-5">
                                <span class="block font-medium">{journal.title}</span>
                                <span class="block text-xs text-muted-foreground">
                                    JRN-{String(journal.id).padStart(4, '0')}
                                    <span class="sm:hidden">· {date}</span>
                                </span>
                                {#if journal.description}
                                    <span class="mt-0.5 block text-muted-foreground">{journal.description}</span>
                                {/if}
                            </td>
                            <td class="hidden px-4 py-3 text-muted-foreground sm:table-cell">{date}</td>
                            <td class="break-words px-4 py-3 text-right tabular-nums sm:px-5">{currency.format(BigInt(journal.amount))}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        {:else}
            <p class="border-t px-4 py-5 text-sm text-muted-foreground sm:px-5">Belum ada jurnal untuk ditampilkan.</p>
        {/if}
    </section>

    <p class="text-xs text-muted-foreground">Pendapatan dan beban mengikuti jenis akun pada COA saat ini.</p>
</main>
