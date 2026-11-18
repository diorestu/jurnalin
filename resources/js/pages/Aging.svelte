<script lang="ts">
    import { Link,router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { NativeSelect } from '@/components/ui/select';
    import { rupiah } from '@/components/trade/documents';
    let {base,date,direction,contact,contacts,rows,totals,net,ledger,difference}:{base:string;date:string;direction:string;contact:string|number|null;contacts:{id:number;code:string;name:string}[];rows:{entries:{label:string;date:string;amount:string;url:string}[];original:string;partner:string;reference:string;date:string;due:string|null;amount:string;bucket:string;url:string}[];totals:Record<string,string>;net:string;ledger:string|null;difference:string|null}=$props();
    let cutoff=$state(untrack(()=>date));
    function filter(side:string,id:string){router.get(base,{as_of:cutoff,direction:side,...(id?{contact:id}:{})});}
</script>
<AppHead title="Umur Piutang & Utang"/>
<main class="mx-auto grid w-full min-w-0 max-w-6xl gap-5 p-4 sm:p-6">
    <h1 class="text-3xl font-bold">Umur Piutang &amp; Utang</h1>
    <form class="flex flex-wrap items-end gap-3" onsubmit={e=>{e.preventDefault();filter(direction,String(contact??''));}}>
        <label>Jenis laporan<NativeSelect value={direction} onchange={e=>filter(e.currentTarget.value,'')}><option value="penjualan">Piutang</option><option value="pembelian">Utang</option></NativeSelect></label>
        <label>Kontak<NativeSelect value={String(contact??'')} onchange={e=>filter(direction,e.currentTarget.value)}><option value="">Semua kontak</option>{#each contacts as p}<option value={String(p.id)}>{p.code} · {p.name}</option>{/each}</NativeSelect></label>
        <label>Per tanggal<DateRangePicker id="aging-date" bind:value={cutoff} min="1900-01-01" max="9999-12-31" required/></label>
        <Button type="submit">Tampilkan</Button>
    </form>
    <section class="rounded-xl border p-4"><h2 class="font-semibold">Ringkasan per {date}</h2><dl class="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4">
        {#each Object.entries(totals) as [bucket,value]}<div><dt class="text-sm text-muted-foreground">{bucket}</dt><dd>{rupiah(value)}</dd></div>{/each}
        <div><dt>Total bersih</dt><dd class="font-semibold">{rupiah(net)}</dd></div>
    </dl>{#if ledger!==null && difference!==null}<p class="mt-4 border-t pt-3">Saldo akun kontrol {rupiah(ledger)} · Selisih dengan rincian {rupiah(difference)}</p>{/if}</section>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr>{#each ['Kontak','Faktur','Tanggal','Jatuh tempo','Kelompok','Sisa'] as h}<th class="border-b p-2">{h}</th>{/each}</tr></thead><tbody>
    {#each rows as r}<tr><td class="p-2">{r.partner}</td><td class="p-2"><details><summary class="cursor-pointer">{r.reference}</summary><div class="grid min-w-44 gap-2 py-2"><Link class="underline" href={r.url}>Faktur · {rupiah(r.original)}</Link>{#each r.entries as item}<Link class="underline" href={item.url}>{item.date} · {item.label} · {rupiah(item.amount)}</Link>{/each}</div></details></td><td>{r.date}</td><td>{r.due??'—'}</td><td>{r.bucket}</td><td class="text-right tabular-nums">{rupiah(r.amount)}</td></tr>{:else}<tr><td colspan="6" class="p-4">Tidak ada faktur terbuka pada tanggal ini.</td></tr>{/each}</tbody></table></div>
</main>
