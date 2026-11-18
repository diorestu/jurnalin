<script lang="ts">
    import {Link,router} from '@inertiajs/svelte';
    import {untrack} from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import {Input,NumberInput} from '@/components/ui/input';
    import {NativeSelect,AccountSelect} from '@/components/ui/select';
    import {Button} from '@/components/ui/button';
    import {rupiah} from '@/components/trade/documents';
    import {index,save} from '@/routes/catalog';
    import {show} from '@/routes/trade';
    type Item={id:number;sku:string;name:string;unit:string;stock:boolean;buy_price:number;sell_price:number;sales_account:number|null;expense_account:number|null;quantity:number;value:number};
    type Move={id:number;date:string;reason:string;quantity:number;value:number;document:number|null;direction:string|null};
    let {team,items,selected,moves,accounts,canEdit}:{team:string;items:Item[];selected:Item|null;moves:{data:Move[];prev_page_url:string|null;next_page_url:string|null}|null;accounts:{id:number;name:string;code:string;type:string}[];canEdit:boolean}=$props();
    let search=$state(''),busy=$state(false),error=$state('');
    let form=$state(untrack(()=>({sku:selected?.sku??'',name:selected?.name??'',unit:selected?.unit??'unit',stock:selected?.stock?'barang':selected?'jasa':'barang',buy_price:selected?.buy_price??0,sell_price:selected?.sell_price??0,sales_account:selected?.sales_account??null,expense_account:selected?.expense_account??null})));
    function submit(e:SubmitEvent){
        e.preventDefault();
        router.post(save.url({current_team:team,item:selected?.id}),{...form,stock:form.stock==='barang'}, {preserveState:'errors',onStart:()=>busy=true,onFinish:()=>busy=false,onError:e=>error=Object.values(e).join(' ')});
    }
</script>
<AppHead title="Barang & Jasa"/>
<main class="mx-auto grid w-full max-w-6xl gap-5 p-4 sm:p-6"><h1 class="text-3xl font-bold">Barang &amp; Jasa</h1>
    <div class="grid items-start gap-5 lg:grid-cols-2"><section class="grid gap-3 rounded-xl border p-4">
    <label>Cari SKU atau nama<Input bind:value={search}/></label>
    {#each items.filter(i=>(i.sku+' '+i.name).toLowerCase().includes(search.toLowerCase())) as i}<Link href={index.url(team,{query:{item:i.id}})} class="border-t py-2"><strong>{i.sku} · {i.name}</strong><small class="block">{i.stock?i.quantity+' '+i.unit+' · '+rupiah(i.value):'Jasa'}</small></Link>{:else}<p>Belum ada item yang cocok.</p>{/each}</section>
    <section class="grid gap-3 rounded-xl border p-4"><h2 class="text-xl font-semibold">{selected?.name??'Item baru'}</h2>
    {#if selected && canEdit}<Link href={index(team)} class="underline">Tambah item</Link>{/if}
    {#if selected || canEdit}<form onsubmit={submit}><fieldset disabled={busy||!canEdit} class="grid gap-3">
        <label>Jenis<NativeSelect bind:value={form.stock} disabled={!!selected}><option value="barang">Barang stok</option><option value="jasa">Jasa</option></NativeSelect></label>
        <label>SKU<Input bind:value={form.sku} maxlength={80} required/></label><label>Nama<Input bind:value={form.name} maxlength={160} required/></label>
        <label>Satuan<Input bind:value={form.unit} readonly={!!selected} required/></label>
        <label>Harga beli<NumberInput currency bind:value={form.buy_price} max={999999999999} required/></label>
        <label>Harga jual<NumberInput currency bind:value={form.sell_price} max={999999999999} required/></label>
        <label>Akun pendapatan<AccountSelect id="item-sales" accounts={accounts.filter(a=>a.type==='revenue')} bind:value={form.sales_account}/></label>
        <label>{form.stock==='barang'?'Akun HPP':'Akun beban jasa'}<AccountSelect id="item-expense" accounts={accounts.filter(a=>a.type==='expense')} bind:value={form.expense_account}/></label>
        {#if canEdit}<Button type="submit">Simpan item</Button>{/if}
        {#if error}<p role="alert">{error}</p>{/if}
    </fieldset></form>{/if}</section></div>
    {#if selected?.stock && moves}<section class="grid gap-2"><h2 class="font-semibold">Kartu stok · {selected.sku}</h2>
    {#each moves.data as m}<div class="flex flex-wrap justify-between gap-2 border-b py-2"><span>{m.date} · {#if m.document && m.direction}<Link class="underline" href={show([team,m.direction,m.document])}>{m.reason} #{m.document}</Link>{:else}{m.reason}{/if}</span><span>{m.quantity} {selected.unit} · {rupiah(m.value)}</span></div>{:else}<p>Belum ada mutasi stok.</p>{/each}
    <div>{#if moves.prev_page_url}<Link href={moves.prev_page_url}>Sebelumnya</Link>{/if} {#if moves.next_page_url}<Link href={moves.next_page_url}>Berikutnya</Link>{/if}</div></section>{/if}
</main>
