<script lang="ts">
    import {Link,router} from '@inertiajs/svelte';
    import {onMount,untrack} from 'svelte';
    import type Dropzone from 'dropzone';
    import AppHead from '@/components/AppHead.svelte';
    import {AccountSelect,NativeSelect} from '@/components/ui/select';
    import {DateRangePicker} from '@/components/ui/date-picker';
    import {NumberInput,Input} from '@/components/ui/input';
    import {Button} from '@/components/ui/button';
    import {rupiah} from '@/components/trade/documents';
    import * as bank from '@/routes/bank';
    import {manual,show} from '@/routes/journals';
    type Account={id:number;name:string;code:string};
    type Line={id:number;entry:number;date:string;description:string;side:string;amount:number;reference:string|null};
    type Statement={id:number;reference:string;date:string;description:string;amount:number;matched_line_id:number|null;candidates:Line[]};
    type Paged<T>={data:T[];prev_page_url:string|null;next_page_url:string|null};
    let {summary,team,accounts,account,month,entries,statements,canMatch,allAccounts}:{summary:Record<string,string>;team:string;accounts:Account[];account:number;month:string;entries:Paged<Line>;statements:Paged<Statement>;canMatch:boolean;allAccounts:Account[]}=$props();
    let picked=$state<number|null>(untrack(()=>account||null)),period=$state(untrack(()=>month));
    let kind=$state('masuk'),other=$state<number|null>(null),amount=$state<number>(),date=$state(new Date().toLocaleDateString('en-CA')),description=$state('');
    let zone=$state<HTMLDivElement>(),button=$state<HTMLButtonElement>(),file=$state<File|null>(null),ready=$state(false),busy=$state(false),error=$state('');
    let choices=$state<Record<number,string>>({});
    let uuid=$state('');
    onMount(()=>{
        uuid=crypto.randomUUID();
        let active=true, uploader:Dropzone|undefined;
        if(canMatch) void import('dropzone').then(({default:Zone})=>{
            if(!active||!zone||!button)return;
            uploader=new Zone(zone,{url:'/',clickable:button,autoProcessQueue:false,acceptedFiles:'.csv,.xlsx',maxFilesize:5,previewsContainer:false});
            uploader.on('addedfile', f=>{file=f;error='';for(const old of uploader?.files??[])if(old!==f)uploader?.removeFile(old);});
            uploader.on('error',()=>{file=null;error='Pilih CSV atau XLSX hingga 5 MB.';});
            ready=true;
        }).catch(()=>error='Pemilih file gagal dimuat.');
        return ()=>{active=false;uploader?.destroy();};
    });
    const options={preserveScroll:true,onStart:()=>{busy=true;error='';},onFinish:()=>busy=false,onError:(e:Record<string,string>)=>error=Object.values(e).join(' ')};
    function post(){
        if(!amount||!other||!account||other===account)return;
        const debit=kind==='masuk'?account:other,credit=kind==='masuk'?other:account;
        router.post(manual.url(team),{request_id:uuid,date,description,lines:[{account_id:debit,debit:amount,credit:0},{account_id:credit,debit:0,credit:amount}]},options);
    }
</script>
<AppHead title="Kas & Bank"/>
<main class="mx-auto grid w-full min-w-0 max-w-6xl gap-5 p-4 sm:p-6">
    <h1 class="text-3xl font-bold">Kas &amp; Bank</h1>
    <form class="flex flex-wrap items-end gap-3" onsubmit={e=>{e.preventDefault();router.get(bank.index.url(team),{account:picked,month:period});}}>
        <label class="w-64">Rekening<AccountSelect id="bank-account" {accounts} bind:value={picked} required/></label>
        <label>Bulan<DateRangePicker id="bank-month" mode="month" bind:value={period} required/></label><Button type="submit">Tampilkan</Button>
    </form>
    {#if account}
    <dl class="grid grid-cols-2 gap-4 rounded-xl border p-4 md:grid-cols-4">{#each Object.entries(summary) as [label,value]}<div><dt class="text-sm text-muted-foreground">{label}</dt><dd class="font-semibold tabular-nums">{rupiah(value)}</dd></div>{/each}</dl>
    <details class="rounded-xl border p-4"><summary class="cursor-pointer font-semibold">Catat penerimaan, pengeluaran, atau transfer</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" onsubmit={e=>{e.preventDefault();post();}}>
            <label>Jenis transaksi<NativeSelect bind:value={kind} onchange={()=>other=null}><option value="masuk">Penerimaan</option><option value="keluar">Pengeluaran</option><option value="transfer">Transfer antar rekening</option></NativeSelect></label>
            <label>Akun lawan<AccountSelect id="bank-other" accounts={(kind==='transfer'?accounts:allAccounts).filter(a=>a.id!==account)} bind:value={other} required/></label>
            <label>Tanggal<DateRangePicker id="bank-date" bind:value={date} required/></label><label>Nominal<NumberInput currency bind:value={amount} min={1} max={999999999999999} required/></label>
            <label>Keterangan<Input bind:value={description} maxlength={500}/></label><Button type="submit" disabled={busy||!amount||!other}>{canMatch?'Posting transaksi':'Ajukan transaksi'}</Button>
        </form></details>
    <section class="grid gap-2"><h2 class="text-xl font-semibold">Buku rekening</h2>
    {#each entries.data as l}<Link href={show([team,l.entry])} class="flex flex-wrap justify-between gap-2 border-b py-2"><span>{l.date} · {l.description}</span><span>{rupiah(l.side==='debit'?l.amount:-l.amount)} · {l.reference??'Belum dicocokkan'}</span></Link>{:else}<p>Belum ada jurnal pada bulan ini.</p>{/each}
    {@render pagination(entries)}
    </section>
    <section class="grid gap-3"><h2 class="text-xl font-semibold">Rekonsiliasi rekening koran</h2>
    {#if canMatch}<div bind:this={zone} class="grid gap-2 rounded-xl border border-dashed p-4">
        <a class="underline" href={bank.template.url(team)}>Unduh template CSV</a>
        <p>Tarik CSV atau XLSX (sheet Mutasi) ke sini. Gunakan tanggal YYYY-MM-DD dan referensi unik dari bank.</p>
        <button type="button" bind:this={button} disabled={!ready||busy} class="w-fit rounded border p-2">Pilih file</button>
        {#if file}<span>{file.name}</span><Button disabled={busy} onclick={()=>file&&router.post(bank.importMethod.url(team),{file,account},{...options,preserveState:'errors'})}>Impor mutasi</Button>{/if}
    </div>{/if}
    {#each statements.data as s}<article class="grid gap-2 border-b py-3"><strong>{s.date} · {s.reference} · {rupiah(s.amount)}</strong><span>{s.description}</span>
    {#if s.matched_line_id}<span>Sudah cocok</span>{:else if canMatch && s.candidates.length}
    <label>Pasangan jurnal<NativeSelect bind:value={choices[s.id]}><option value="">Pilih jurnal</option>{#each s.candidates as l}<option value={String(l.id)}>#{l.entry} · {l.date} · {l.description}</option>{/each}</NativeSelect></label>
    <Button class="justify-self-start" disabled={busy||!choices[s.id]} onclick={()=>router.post(bank.match.url([team,s.id]),{line:Number(choices[s.id])},options)}>Konfirmasi pasangan</Button>
    {:else}<span>Belum ada pasangan terkonfirmasi</span>{/if}</article>{:else}<p>Belum ada mutasi impor pada bulan ini.</p>{/each}
    {@render pagination(statements)}</section>
    {/if}{#if error}<p role="alert" class="text-destructive">{error}</p>{/if}
</main>
{#snippet pagination(p:{prev_page_url:string|null;next_page_url:string|null})}<div class="flex gap-4">{#if p.prev_page_url}<Link href={p.prev_page_url}>Sebelumnya</Link>{/if}{#if p.next_page_url}<Link href={p.next_page_url}>Berikutnya</Link>{/if}</div>{/snippet}
