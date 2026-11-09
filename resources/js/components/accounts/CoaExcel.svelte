<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import type Dropzone from 'dropzone';
    import { Button } from '@/components/ui/button';

    let { exportUrl, importUrl }: { exportUrl: string; importUrl: string } = $props();
    let zone: HTMLDivElement;
    let choose: HTMLButtonElement;
    let uploader: Dropzone | undefined;
    let file = $state<Parameters<Dropzone['removeFile']>[0] | null>(null);
    let error = $state('');
    let processing = $state(false);
    let ready = $state(false);

    onMount(() => {
        let active = true;
        void import('dropzone').then(({ default: DropzoneConstructor }) => {
            if (!active) return;
            uploader = new DropzoneConstructor(zone, {
                url: importUrl, autoProcessQueue: false, clickable: choose,
                acceptedFiles: '.xlsx', maxFiles: 1, maxFilesize: 5,
                previewsContainer: false, createImageThumbnails: false,
                hiddenInputContainer: zone,
            });
            uploader.on('addedfile', selected => {
                if (file) uploader?.removeFile(file);
                file = selected;
                error = '';
            });
            uploader.on('error', (selected, message) => {
                error = typeof message === 'string' ? message : 'File tidak valid.';
                uploader?.removeFile(selected);
                if (file === selected) file = null;
            });
            ready = true;
        }).catch(() => { error = 'Pemilih file gagal dimuat. Muat ulang halaman.'; });
        return () => { active = false; uploader?.destroy(); };
    });

    function submit() {
        if (!file || processing) return;
        router.post(importUrl, { file }, {
            forceFormData: true, preserveScroll: true,
            onStart: () => { processing = true; error = ''; },
            onError: errors => { error = String(errors.file ?? Object.values(errors)[0]); },
            onSuccess: () => { uploader?.removeAllFiles(true); file = null; },
            onFinish: () => { processing = false; },
        });
    }
</script>

<details class="rounded-xl border bg-card">
    <summary class="cursor-pointer px-4 py-3 font-semibold">Impor / ekspor Excel</summary>
    <div class="flex flex-col gap-3 border-t p-4">
        <div class="flex flex-wrap gap-3">
            <a href={exportUrl} class="underline underline-offset-4">Ekspor COA (.xlsx)</a>
            <a href={exportUrl + '?template=1'} class="underline underline-offset-4">Unduh template impor</a>
        </div>
        <p class="text-sm text-muted-foreground">Impor menambahkan akun baru. Kode yang sudah ada dilewati. Maksimal 5 MB dan 5.000 akun.</p>
        <div bind:this={zone} class="rounded-md border border-dashed p-4">
            <p class="mb-2 text-sm">Tarik file Excel ke sini atau pilih file.</p>
            <button bind:this={choose} type="button" disabled={!ready || processing}
                class="min-h-10 rounded-md border px-3 text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50">Pilih file Excel</button>
            {#if file}<p class="mt-2 break-all text-sm">{file.name}</p>{/if}
        </div>
        {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
        <Button class="self-start" disabled={!file || processing} onclick={submit}>{processing ? 'Mengimpor…' : 'Impor akun'}</Button>
    </div>
</details>
