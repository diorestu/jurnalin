<script lang="ts">
    import { onMount } from 'svelte';
    import type Dropzone from 'dropzone';
    import AppHead from '@/components/AppHead.svelte';
    import { NativeSelect } from '@/components/ui/select';
    import { Button } from '@/components/ui/button';
    import { template, upload } from '@/routes/imports';
    import type { Team } from '@/types';

    let { currentTeam }: { currentTeam: Team } = $props();
    const supervisor = $derived(currentTeam.role === 'owner' || currentTeam.role === 'admin');
    let kind = $state('jurnal');
    let zone: HTMLDivElement;
    let choose: HTMLButtonElement;
    let uploader: Dropzone | undefined;
    let file = $state<File | null>(null);
    let busy = $state(false);
    let ready = $state(false);
    let message = $state('');
    let error = $state('');
    type Preview = { token: string; summary: string; headers: string[]; rows: string[][]; total: number };
    let preview = $state<Preview | null>(null);

    function reset() {
        uploader?.removeAllFiles(true);
        file = null;
        preview = null;
        message = '';
        error = '';
    }

    onMount(() => {
        let active = true;
        void import('dropzone').then(({ default: Zone }) => {
            if (!active) return;
            uploader = new Zone(zone, {
                url: '/', clickable: choose, autoProcessQueue: false, acceptedFiles: '.xlsx',
                maxFilesize: 5, previewsContainer: false, createImageThumbnails: false, hiddenInputContainer: zone,
            });
            uploader.on('addedfile', selected => {
                if (busy) { uploader?.removeFile(selected); return; }
                for (const old of uploader?.files ?? []) {
                    if (old !== selected) uploader?.removeFile(old);
                }
                file = selected;
                preview = null;
                message = '';
                error = '';
            });
            uploader.on('error', (selected) => {
                uploader?.removeFile(selected);
                if (file === selected) { file = null; preview = null; }
                error = 'Pilih file .xlsx berukuran maksimal 5 MB.';
            });
            ready = true;
        }).catch(() => { error = 'Pemilih file gagal dimuat.'; });
        return () => { active = false; uploader?.destroy(); };
    });

    async function processFile(confirm: boolean) {
        if (!file || busy || (confirm && !preview)) return;
        const data = new FormData();
        data.append('file', file);
        if (preview) data.append('token', preview.token);
        busy = true;
        error = '';
        try {
            const csrf = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice(11) ?? '';
            const response = await fetch(upload.url([currentTeam.slug, kind, confirm ? 'confirm' : 'preview']), {
                method: 'POST', body: data, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf) },
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat()[0]?.toString() ?? result.message ?? 'Impor gagal.');
            if (confirm) { reset(); message = 'Impor selesai. ' + result.summary; }
            else preview = result;
        } catch (cause) {
            preview = null;
            error = cause instanceof Error ? cause.message : 'Gagal membaca respons server.';
        } finally { busy = false; }
    }
</script>

<AppHead title="Impor Data" />
<main class="mx-auto grid w-full min-w-0 max-w-4xl gap-5 px-4 py-6">
    <header><h1 class="text-3xl font-bold">Impor Data</h1><p class="text-muted-foreground">{currentTeam.name}</p></header>
    <section class="grid gap-4 rounded-xl border bg-card p-5">
        <label class="grid max-w-sm gap-2">Jenis data
            <NativeSelect bind:value={kind} disabled={busy} onchange={reset}>
                <option value="jurnal">Jurnal umum</option>
                {#if supervisor}<option value="coa">Chart of Accounts</option><option value="saldo-awal">Saldo awal akun</option>{/if}
            </NativeSelect>
        </label>
        <a class="w-fit underline underline-offset-4" href={template.url([currentTeam.slug, kind])}>Unduh template Excel</a>
        {#if kind === 'jurnal'}<p>Gunakan satu referensi untuk semua baris satu jurnal. Hasil impor diajukan untuk persetujuan.</p>{/if}
        {#if kind === 'saldo-awal'}<p>Untuk saldo awal yang mencakup rincian piutang, utang, atau persediaan, masukkan seluruh paket melalui menu Saldo Awal.</p>{/if}
        <div bind:this={zone} class="grid justify-items-start gap-2 rounded-md border border-dashed p-4">
            <p>Tarik file .xlsx ke sini (maksimal 5 MB).</p>
            <button type="button" bind:this={choose} disabled={busy || !ready} class="rounded-md border px-4 py-2 focus-visible:outline-2">Pilih file Excel</button>
            {#if file}<span class="break-all">{file.name}</span>{/if}
        </div>
        {#if !preview}<Button class="justify-self-start" disabled={!file || busy} onclick={() => processFile(false)}>Periksa file</Button>{/if}
    </section>
    {#if preview}
        <section class="grid min-w-0 gap-3 rounded-xl border bg-card p-5">
            <h2 class="text-xl font-semibold">Pratinjau impor</h2>
            <p>{preview.summary} Menampilkan {preview.rows.length} dari {preview.total} baris.</p>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead><tr>{#each preview.headers as title}<th class="border-b p-2">{title}</th>{/each}</tr></thead>
                <tbody>{#each preview.rows as row}<tr>{#each row as cell}<td class="border-b p-2">{cell}</td>{/each}</tr>{/each}</tbody>
            </table></div>
            <Button disabled={busy} class="justify-self-start" onclick={() => processFile(true)}>Konfirmasi impor</Button>
        </section>
    {/if}
    {#if error}<p role="alert" class="text-destructive">{error}</p>{/if}
    <p role="status">{busy ? 'Memproses file…' : message}</p>
</main>
