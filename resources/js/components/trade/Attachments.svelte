<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import type Dropzone from 'dropzone';
    import { Button } from '@/components/ui/button';

    let { url, files }: { url:string; files:{id:number;name:string;url:string;size:number;uploader:string;created_at:string}[] } = $props();
    let zone:HTMLDivElement, choose:HTMLButtonElement;
    let selected=$state<File|null>(null), ready=$state(false), busy=$state(false), error=$state('');
    let uploader:Dropzone|undefined;
    onMount(()=>{
        let active=true;
        void import('dropzone').then(({default:Zone})=>{
            if (!active) return;
            uploader=new Zone(zone,{url,clickable:choose,autoProcessQueue:false,acceptedFiles:'.pdf,.png,.jpg,.jpeg',maxFilesize:5,createImageThumbnails:false,previewsContainer:false});
            uploader.on('addedfile',f=>{
                if(busy){uploader?.removeFile(f);return;}
                for(const old of uploader?.files??[]) if(old!==f) uploader?.removeFile(old);
                selected=f;error='';
            });
            uploader.on('error',f=>{uploader?.removeFile(f);selected=null;error='Pilih PDF, PNG atau JPEG hingga 5 MB.';});
            ready=true;
        }).catch(()=>error='Pemilih file gagal dimuat.');
        return ()=>{active=false;uploader?.destroy();};
    });
    function upload(){
        if(!selected||busy)return;
        router.post(url,{file:selected},{preserveScroll:true,onStart:()=>{busy=true;error='';},onError:e=>error=Object.values(e).join(' '),onSuccess:()=>{selected=null;uploader?.removeAllFiles(true);},onFinish:()=>busy=false});
    }
</script>
<section class="grid gap-3 rounded-xl border bg-card p-4 sm:p-5">
    <h2 class="font-semibold">Lampiran transaksi</h2>
    {#if files.length}<ul class="divide-y">{#each files as file (file.id)}<li class="py-2">
        <a class="break-all underline" href={file.url}>{file.name}</a>
        <p class="text-sm text-muted-foreground">{file.uploader} · {file.created_at} · {Math.ceil(file.size/1024)} KB</p>
    </li>{/each}</ul>{/if}
    <div bind:this={zone} class="grid gap-2 rounded-md border border-dashed p-4">
        <p>Tarik PDF, PNG, atau JPEG hingga 5 MB ke sini.</p>
        <button type="button" bind:this={choose} disabled={!ready||busy} class="w-fit rounded border px-3 py-2">Pilih lampiran</button>
        {#if selected}<span class="break-all">{selected.name}</span><Button class="justify-self-start" disabled={busy} onclick={upload}>Unggah lampiran</Button>{/if}
    </div>
    {#if error}<p role="alert" class="text-destructive">{error}</p>{/if}
</section>
