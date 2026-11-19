<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { AccountSelect } from '@/components/ui/select';
    import { Button } from '@/components/ui/button';
    import { rupiah } from '@/components/trade/documents';
    import { index, store, depreciate } from '@/routes/assets';
    import { show } from '@/routes/journals';

    type Asset = {id:number;code:string;name:string;category:string|null;location:string|null;acquired:string;start_month:string;cost:number;residual:number;months:number;accumulated:number|null;book:number;next:{month:string;amount:number}|null};
    type Account = {id:number;code:string;name:string;type:string};
    let {team,assets,selected,canPost,accounts,history}:{team:string;assets:Asset[];selected:Asset|null;canPost:boolean;accounts:Account[];history:{month:string;amount:number;journal_entry_id:number|null}[]}=$props();
    let query=$state(''), busy=$state(false),errors=$state<Record<string,string>>({});
    let form=$state({code:'',name:'',category:'',location:'',acquired:'',start_month:'',cost:undefined as number|undefined,residual:0,months:undefined as number|undefined,asset_account:null as number|null,expense_account:null as number|null,accumulation_account:null as number|null});
    const options={preserveState:'errors' as const,preserveScroll:true,onStart:()=>{busy=true;errors={};},onFinish:()=>busy=false,onError:(e:Record<string,string>)=>errors=e};
    function submit(e:SubmitEvent){e.preventDefault();router.post(store.url(team),form,options);}
    function post(){if(!selected?.next||busy)return;router.post(depreciate.url([team,selected.id]),{month:selected.next.month},options);}
</script>
<AppHead title="Aset Tetap"/>
<main class="mx-auto grid w-full max-w-6xl gap-5 p-4 sm:p-6">
    <h1 class="text-3xl font-bold">Aset Tetap</h1>
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <section class="grid gap-3 rounded-xl border bg-card p-4">
            <label>Cari aset<Input bind:value={query} placeholder="Kode atau nama"/></label>
            {#each assets.filter(a=>(a.code+' '+a.name).toLowerCase().includes(query.toLowerCase())) as a (a.id)}
                <Link class="flex flex-wrap justify-between gap-2 border-t py-3" href={index.url(team,{query:{asset:a.id}})}><span>{a.code} · {a.name}</span><span class="tabular-nums">{rupiah(a.book)}</span></Link>
            {:else}<p>Belum ada aset yang cocok.</p>{/each}
        </section>
        <section class="grid gap-4 rounded-xl border bg-card p-4">
            {#if selected}
                <div class="flex flex-wrap justify-between gap-2"><h2 class="text-xl font-semibold">{selected.name}</h2>{#if canPost}<Link class="underline" href={index(team)}>Tambah aset</Link>{/if}</div>
                <p>{selected.code} · {selected.category || 'Tanpa kategori'} · {selected.location || 'Lokasi belum diisi'}</p>
                <dl class="grid grid-cols-2 gap-3">
                    <div><dt>Tanggal perolehan</dt><dd>{selected.acquired}</dd></div>
                    <div><dt>Mulai penyusutan</dt><dd>{selected.start_month} · {selected.months} bulan</dd></div>
                    <div><dt>Harga perolehan</dt><dd>{rupiah(selected.cost)}</dd></div>
                    <div><dt>Nilai residu</dt><dd>{rupiah(selected.residual)}</dd></div>
                    <div><dt>Akumulasi penyusutan</dt><dd>{rupiah(selected.accumulated??0)}</dd></div>
                    <div><dt>Nilai buku</dt><dd class="font-semibold">{rupiah(selected.book)}</dd></div>
                </dl>
                {#if selected.next}<div class="grid gap-2 border-t pt-3">
                    <p>Penyusutan {selected.next.month} · {rupiah(selected.next.amount)}</p>
                    {#if canPost}<Button disabled={busy} onclick={post}>Posting penyusutan bulan ini</Button>{/if}
                </div>{:else}<p>Penyusutan selesai.</p>{/if}
                <h3 class="font-semibold">Riwayat penyusutan</h3>
                {#each history as row}<div class="flex flex-wrap justify-between gap-2 border-t py-2"><span>{row.month} · {rupiah(row.amount)}</span>{#if row.journal_entry_id}<Link class="underline" href={show([team,row.journal_entry_id])}>Lihat jurnal</Link>{/if}</div>{:else}<p>Belum ada penyusutan terposting.</p>{/each}
            {:else if canPost}
                <h2 class="text-xl font-semibold">Daftarkan aset</h2>
                <p class="text-sm text-muted-foreground">Daftarkan perolehan yang sudah dibukukan. Metode garis lurus menghitung satu bulan penuh sejak bulan mulai yang dipilih.</p>
                <form onsubmit={submit} class="grid gap-3">
                    <label>Kode<Input bind:value={form.code} maxlength={40} required/></label>
                    <label>Nama aset<Input bind:value={form.name} maxlength={160} required/></label>
                    <label>Kategori<Input bind:value={form.category} maxlength={100}/></label>
                    <label>Lokasi<Input bind:value={form.location} maxlength={160}/></label>
                    <label>Tanggal perolehan<DateRangePicker id="asset-acquired" bind:value={form.acquired} min="1900-01-01" max="9999-12-31" required/></label>
                    <label>Bulan mulai penyusutan<DateRangePicker id="asset-start" mode="month" bind:value={form.start_month} min={form.acquired.slice(0,7)||'1900-01'} max="9999-12" required/></label>
                    <label>Harga perolehan<NumberInput currency bind:value={form.cost} min={1} max={999999999999999} required/></label>
                    <label>Nilai residu<NumberInput currency bind:value={form.residual} min={0} max={Math.max(0,(form.cost??1)-1)} required/></label>
                    <label>Masa manfaat (bulan)<NumberInput bind:value={form.months} min={1} max={600} required/></label>
                    <label>Akun aset<AccountSelect id="asset-account" accounts={accounts.filter(a=>a.type==='asset')} bind:value={form.asset_account} required/></label>
                    <label>Akun akumulasi penyusutan<AccountSelect id="asset-accumulation" accounts={accounts.filter(a=>a.type==='asset' && a.id!==form.asset_account)} bind:value={form.accumulation_account} required/></label>
                    <label>Akun beban penyusutan<AccountSelect id="asset-expense" accounts={accounts.filter(a=>a.type==='expense')} bind:value={form.expense_account} required/></label>
                    <Button type="submit" disabled={busy}>Simpan aset</Button>
                </form>
            {:else}<p>Pilih aset untuk melihat nilai buku dan jurnal penyusutannya.</p>{/if}
            {#each Object.values(errors) as error}<p role="alert" class="text-destructive">{error}</p>{/each}
        </section>
    </div>
</main>
