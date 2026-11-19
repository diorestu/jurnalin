<script lang="ts">
    import { Link,router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ReportFilters from '@/components/reports/ReportFilters.svelte';
    import { NativeSelect } from '@/components/ui/select';
    import { rupiah } from '@/components/trade/documents';
    import { show } from '@/routes/journals';
    import { classify } from '@/routes/reports';
    type Detail={id:number;date:string;description:string|null;amount:string;category:string};
    let {team,type,url,period,month,year,currentLabel,previousLabel,report,previous,canEdit}:{team:string;type:string;url:string;period:'monthly'|'yearly';month:string;year:string;currentLabel:string;previousLabel:string;report:{rows:Record<string,string>;details:Detail[]};previous:Record<string,string>;canEdit:boolean}=$props();
    const title=$derived(type==='arus-kas'?'Arus Kas':'Perubahan Ekuitas');
    const labels:Record<string,string>={operating:'Operasi',investing:'Investasi',financing:'Pendanaan',unclassified:'Belum diklasifikasikan',opening:'Saldo awal'};
    let error=$state(''),busy=$state(false);
    function categorize(id:number,category:string){
        router.patch(classify.url([team,id]),{category},{preserveScroll:true,onStart:()=>{error='';busy=true;},onError:e=>error=Object.values(e).join(' '),onFinish:()=>busy=false});
    }
</script>
<AppHead {title}/>
<main class="mx-auto grid w-full min-w-0 max-w-5xl gap-5 p-4 sm:p-6">
    <h1 class="text-3xl font-bold">{title}</h1>
    <ReportFilters {url} {period} {month} {year}/>
    <div class="overflow-x-auto rounded-xl border"><table class="w-full text-sm">
        <thead><tr class="bg-muted"><th class="p-3 text-left">Uraian</th><th>{currentLabel}</th><th>{previousLabel}</th><th>Selisih</th></tr></thead>
        <tbody>{#each Object.entries(report.rows) as [name,value]}<tr class="border-t"><th class="p-3 text-left font-medium">{name}</th><td class="p-2 text-right">{rupiah(value)}</td><td class="p-2 text-right">{rupiah(previous[name])}</td><td class="p-2 text-right">{rupiah(BigInt(value)-BigInt(previous[name]))}</td></tr>{/each}</tbody></table></div>
    <h2 class="text-xl font-semibold">Rincian periode {currentLabel}</h2>
    {#if error}<p role="alert" class="text-destructive">{error}</p>{/if}
    {#each report.details as row}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b py-2">
            <Link class="underline" href={show([team,row.id])}>{row.date} · {row.description||'Jurnal #'+row.id}</Link>
            <span>{rupiah(row.amount)}</span>
            {#if canEdit && row.category && row.category!=='opening'}
                <NativeSelect aria-label={'Aktivitas jurnal '+row.id} value={row.category} disabled={busy} onchange={e=>categorize(row.id,e.currentTarget.value)}>
                    {#each ['operating','investing','financing','unclassified'] as key}<option value={key}>{labels[key]}</option>{/each}
                </NativeSelect>
            {:else if row.category}<span>{labels[row.category]}</span>{/if}
        </div>
    {:else}<p>Tidak ada mutasi pada periode ini.</p>{/each}
</main>
