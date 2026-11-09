<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { AccountSelect } from '@/components/ui/select';

    type Account = { id: number; code: string; name: string };
    type Template = {
        id: number;
        keyword: string;
        name: string;
        debit_account_id: number | null;
        credit_account_id: number | null;
        active: boolean;
        update_url: string;
    };

    let { templates, accounts, storeUrl, journalUrl, coaUrl }: {
        templates: Template[];
        accounts: Account[];
        storeUrl: string;
        journalUrl: string;
        coaUrl: string;
    } = $props();

    const empty = () => ({ keyword: '', name: '', debit_account_id: null as number | null, credit_account_id: null as number | null, active: true });
    let draft = $state(empty());
    let editing = $state<Template | null>(null);
    let error = $state('');
    let saved = $state(false);
    let processing = $state(false);

    function edit(template: Template | null) {
        editing = template;
        draft = template ? {
            keyword: template.keyword,
            name: template.name,
            debit_account_id: template.debit_account_id,
            credit_account_id: template.credit_account_id,
            active: template.active,
        } : empty();
        error = '';
        saved = false;
        document.getElementById('template-name')?.focus();
    }

    function save(event: SubmitEvent) {
        event.preventDefault();
        if (processing) return;

        router.visit(editing?.update_url ?? storeUrl, {
            method: editing ? 'put' : 'post',
            data: { ...draft },
            preserveState: true,
            preserveScroll: true,
            onStart: () => { processing = true; error = ''; saved = false; },
            onError: (errors) => { error = String(Object.values(errors)[0] ?? 'Template gagal disimpan.'); },
            onSuccess: () => {
                editing = null;
                draft = empty();
                saved = true;
            },
            onFinish: () => { processing = false; },
        });
    }

</script>

<AppHead title="Template Jurnal" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header>
        <Link href={journalUrl} class="text-sm font-medium underline underline-offset-4">Kembali ke buku jurnal</Link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Template Jurnal</h1>
        <p class="mt-1 text-sm text-muted-foreground">Tentukan pasangan akun yang diisi saat template dipilih.</p>
        <Link href={coaUrl} class="mt-2 inline-block text-sm font-medium underline underline-offset-4">Kelola COA</Link>
    </header>

    <section class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5" aria-labelledby="template-form-title">
        <h2 id="template-form-title" class="font-semibold">{editing ? 'Ubah template' : 'Tambah template'}</h2>
        <form class="mt-4" onsubmit={save}>
            <fieldset class="grid gap-4 sm:grid-cols-2" disabled={processing}>
                <div class="flex flex-col gap-1.5">
                    <Label for="template-name">Nama template</Label>
                    <Input id="template-name" bind:value={draft.name} required maxlength={120} placeholder="Tagihan PLN" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="template-keyword">Kata kunci</Label>
                    <Input id="template-keyword" bind:value={draft.keyword} required maxlength={80}
                        pattern="[a-z0-9]+(_[a-z0-9]+)*" placeholder="biaya_pln" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="template-debit">Akun debit</Label>
                    <AccountSelect id="template-debit" {accounts} bind:value={draft.debit_account_id} required disabled={processing} />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="template-credit">Akun kredit</Label>
                    <AccountSelect id="template-credit" {accounts} bind:value={draft.credit_account_id} required disabled={processing} />
                </div>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" bind:checked={draft.active} />
                    Aktif dan muncul di saran jurnal
                </label>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <Button type="submit">{processing ? 'Menyimpan…' : 'Simpan template'}</Button>
                    {#if editing}
                        <Button type="button" variant="outline" onclick={() => edit(null)}>Batal</Button>
                    {/if}
                </div>
            </fieldset>
        </form>
        {#if error}
            <p class="mt-3 text-sm text-destructive" role="alert">{error}</p>
        {:else if saved}
            <p class="mt-3 text-sm" role="status">Template berhasil disimpan.</p>
        {/if}
    </section>

    <section aria-labelledby="template-list-title">
        <h2 id="template-list-title" class="font-semibold">Daftar template</h2>
        <ul class="mt-3 divide-y rounded-xl border">
            {#each templates as template (template.id)}
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-medium">{template.name}</p>
                        <p class="break-all text-xs text-muted-foreground">{template.keyword} · {template.active ? 'Aktif' : 'Nonaktif'}</p>
                    </div>
                    <Button type="button" size="sm" variant="outline" disabled={processing}
                        aria-label={'Ubah ' + template.name} onclick={() => edit(template)}>Ubah</Button>
                </li>
            {:else}
                <li class="px-4 py-3 text-sm text-muted-foreground">Belum ada template.</li>
            {/each}
        </ul>
    </section>
</main>
