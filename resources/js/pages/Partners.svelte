<script lang="ts">
    import { Link,router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input,NumberInput } from '@/components/ui/input';
    import { NativeSelect } from '@/components/ui/select';
    import { rupiah } from '@/components/trade/documents';
    type Partner={id:number;direction:string;code:string;name:string;contact:string|null;address:string|null;npwp:string|null;terms:number|null};
    let {base,partners,selected,canEdit,history,agingUrl}:{base:string;partners:Partner[];selected:Partner|null;canEdit:boolean;agingUrl:string|null;history:{data:{number:string;date:string;url:string;total:string}[];prev_page_url:string|null;next_page_url:string|null}|null}=$props();
    let query=$state('');
    const matches=$derived(partners.filter(p=>(p.name+' '+p.code).toLowerCase().includes(query.toLowerCase())));
    let form=$state(untrack(()=>({direction:selected?.direction??'penjualan',code:selected?.code??'',name:selected?.name??'',contact:selected?.contact??'',address:selected?.address??'',npwp:selected?.npwp??'',terms:selected?.terms??undefined})));
    let errors=$state<Record<string,string>>({});
    let busy=$state(false);
    function save(e:SubmitEvent){
        e.preventDefault();
        router.post(selected?base+'/'+selected.id:base,{...form},{preserveState:'errors',onStart:()=>busy=true,onError:e=>errors=e,onFinish:()=>busy=false});
    }
</script>
<AppHead title="Pelanggan & Pemasok"/>
<main class="mx-auto grid w-full max-w-6xl gap-6 p-4 sm:p-6">
    <h1 class="text-3xl font-bold">Pelanggan &amp; Pemasok</h1>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="grid gap-3 rounded-xl border p-4">
            <label>Cari kontak<Input bind:value={query} placeholder="Kode atau nama"/></label>
            {#each matches as p (p.id)}
                <Link href={base+'?contact='+p.id} class="border-t py-2 hover:underline">{p.name} · {p.code}<small class="block text-muted-foreground">{p.direction==='penjualan'?'Pelanggan':'Pemasok'}</small></Link>
            {:else}<p>Belum ada kontak yang cocok.</p>{/each}
        </section>
        <section class="grid gap-4 rounded-xl border p-4">
            <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">{selected?.name??'Kontak baru'}</h2>{#if selected && canEdit}<Link href={base} class="underline">Tambah kontak</Link>{/if}</div>
            {#if selected || canEdit}
            <form onsubmit={save}><fieldset disabled={!canEdit || busy} class="grid gap-3">
                <label>Jenis<NativeSelect bind:value={form.direction} disabled={!!selected}><option value="penjualan">Pelanggan</option><option value="pembelian">Pemasok</option></NativeSelect></label>
                <label>Kode<Input bind:value={form.code} required maxlength={40}/></label>
                <label>Nama<Input bind:value={form.name} required maxlength={160}/></label>
                <label>Telepon atau email<Input bind:value={form.contact} maxlength={255}/></label>
                <label>Alamat<Input bind:value={form.address} maxlength={255}/></label>
                <label>NPWP<Input bind:value={form.npwp} maxlength={32}/></label>
                <label>Termin pembayaran (hari)<NumberInput bind:value={form.terms} min={0} max={3650}/></label>
                {#each Object.values(errors) as error}<p role="alert">{error}</p>{/each}
                {#if canEdit}<Button type="submit">Simpan kontak</Button>{/if}
            </fieldset></form>
            {:else}<p>Pilih kontak untuk melihat rinciannya.</p>{/if}
            {#if history && agingUrl}
                <Link href={agingUrl} class="underline">Lihat saldo dan umur tagihan</Link>
                <h3 class="font-semibold">Riwayat transaksi</h3>
                {#each history.data as d}<Link href={d.url} class="flex flex-wrap justify-between gap-2 border-t py-2"><span>{d.date} · {d.number}</span><span>{rupiah(d.total)}</span></Link>{:else}<p>Belum ada transaksi.</p>{/each}
                <div class="flex justify-between">{#if history.prev_page_url}<Link href={history.prev_page_url}>Sebelumnya</Link>{/if}{#if history.next_page_url}<Link href={history.next_page_url}>Berikutnya</Link>{/if}</div>
            {/if}
        </section>
    </div>
</main>
