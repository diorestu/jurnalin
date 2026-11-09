<script module lang="ts">
    import { index } from '@/routes/chart-of-accounts';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{
            title: 'Chart of Accounts',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        }],
    });
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import CoaExcel from '@/components/accounts/CoaExcel.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { NativeSelect } from '@/components/ui/select';

    type Account = {
        id: number;
        code: string;
        name: string;
        type: string | null;
        active: boolean;
        is_cash: boolean;
        trade_control: boolean;
        updateUrl: string;
    };

    let { accounts, accountTypes, storeUrl, templatesUrl, exportUrl, importUrl }: {
        accounts: Account[];
        accountTypes: { value: string; label: string }[];
        storeUrl: string;
        exportUrl: string;
        importUrl: string;
        templatesUrl: string;
    } = $props();

    const emptyDraft = () => ({ code: '', name: '', type: '', active: true, is_cash: false });
    let search = $state('');
    let showEditor = $state(false);
    let editing = $state<Account | null>(null);
    let draft = $state(emptyDraft());
    let processing = $state(false);
    let errors = $state<Partial<Record<keyof ReturnType<typeof emptyDraft>, string>>>({});
    let saved = $state('');

    const types = $derived(new Map(accountTypes.map(({ value, label }) => [value, label])));
    const query = $derived(search.trim().toLowerCase());
    const visibleAccounts = $derived(accounts.filter((account) =>
        [account.code, account.name].some((field) => field.toLowerCase().includes(query)),
    ));

    async function edit(account: Account | null) {
        editing = account;
        draft = account
            ? { code: account.code, name: account.name, type: account.type ?? '', active: account.active, is_cash: account.is_cash }
            : emptyDraft();
        errors = {};
        saved = '';
        showEditor = true;

        await tick();
        document.getElementById('coa-code')?.focus();
    }

    function save(event: SubmitEvent) {
        event.preventDefault();
        if (processing) return;

        router.visit(editing?.updateUrl ?? storeUrl, {
            method: editing ? 'put' : 'post',
            data: { ...draft, is_cash: draft.type === 'asset' && draft.is_cash },
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                processing = true;
                errors = {};
                saved = '';
            },
            onError: (validationErrors) => { errors = validationErrors; },
            onSuccess: () => {
                saved = `Akun ${draft.code.toUpperCase()} berhasil disimpan.`;
                showEditor = false;
            },
            onFinish: async () => {
                processing = false;
                if (saved) {
                    await tick();
                    document.getElementById('coa-add')?.focus();
                }
            },
        });
    }
</script>

<AppHead title="Chart of Accounts" />

<main class="mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-6 px-4 py-5 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Chart of Accounts</h1>
            <p class="mt-1 text-sm text-muted-foreground">Kelola daftar akun yang digunakan dalam jurnal perusahaan.</p>
        </div>
        <Button id="coa-add" type="button" disabled={processing} onclick={() => edit(null)}>Tambah akun</Button>
    </header>

    <CoaExcel {exportUrl} {importUrl} />

    {#if showEditor}
        <section class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5" aria-labelledby="coa-editor-title">
            <h2 id="coa-editor-title" class="font-semibold">{editing ? 'Ubah akun' : 'Tambah akun'}</h2>
            <form class="mt-4" onsubmit={save}>
                <fieldset class="grid gap-4 sm:grid-cols-2" disabled={processing}>
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="coa-code">Kode akun</Label>
                        <Input id="coa-code" bind:value={draft.code} required maxlength={30} placeholder="Contoh 2101"
                            aria-invalid={!!errors.code} aria-describedby={errors.code ? 'coa-code-error' : undefined} />
                        <div id="coa-code-error"><InputError message={errors.code} /></div>
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="coa-name">Nama akun</Label>
                        <Input id="coa-name" bind:value={draft.name} required maxlength={255} placeholder="Contoh Utang Usaha"
                            aria-invalid={!!errors.name} aria-describedby={errors.name ? 'coa-name-error' : undefined} />
                        <div id="coa-name-error"><InputError message={errors.name} /></div>
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="coa-type">Jenis akun</Label>
                        <NativeSelect id="coa-type" bind:value={draft.type} required disabled={editing?.trade_control}
                            aria-invalid={!!errors.type} aria-describedby={errors.type ? 'coa-type-error' : undefined}>
                            <option value="" disabled>Pilih jenis akun</option>
                            {#each accountTypes as type (type.value)}
                                <option value={type.value}>{type.label}</option>
                            {/each}
                        </NativeSelect>
                        <div id="coa-type-error"><InputError message={errors.type} /></div>
                    </div>
                    <div class="flex min-w-0 flex-col justify-center gap-1.5">
                        <label class="flex min-h-9 items-center gap-2 text-sm">
                            <input type="checkbox" bind:checked={draft.active} disabled={editing?.trade_control}
                                aria-invalid={!!errors.active} aria-describedby={errors.active ? 'coa-active-error' : undefined} />
                            Akun aktif
                        </label>
                        <div id="coa-active-error" aria-live="polite"><InputError message={errors.active} /></div>
                    </div>
                    {#if draft.type === 'asset' && !editing?.trade_control}
                        <div class="sm:col-span-2">
                            <label class="flex min-h-9 items-center gap-2 text-sm">
                                <input type="checkbox" bind:checked={draft.is_cash} />
                                Gunakan sebagai akun kas atau bank
                            </label>
                            <InputError message={errors.is_cash} />
                        </div>
                    {/if}
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <Button type="submit">{processing ? 'Menyimpan…' : 'Simpan akun'}</Button>
                        <Button type="button" variant="outline" onclick={() => { showEditor = false; document.getElementById('coa-add')?.focus(); }}>Batal</Button>
                    </div>
                </fieldset>
            </form>
        </section>
    {/if}

    <section class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="coa-list-title">
        <div class="flex flex-wrap items-end justify-between gap-3 px-4 py-4 sm:px-5">
            <div>
                <h2 id="coa-list-title" class="font-semibold">Daftar akun</h2>
                <p class="mt-0.5 text-sm text-muted-foreground" role="status">{saved || `${visibleAccounts.length} akun`}</p>
            </div>
            <div class="flex w-full flex-col gap-1.5 sm:w-64">
                <Label for="coa-search">Cari akun</Label>
                <Input id="coa-search" type="search" bind:value={search} oninput={() => { saved = ''; }} placeholder="Kode atau nama akun" />
            </div>
        </div>
        <table class="w-full table-fixed border-t text-sm">
            <thead class="bg-muted/60 text-muted-foreground">
                <tr>
                    <th scope="col" class="w-[24%] px-3 py-2.5 text-left font-medium sm:w-[18%] sm:px-5">Kode</th>
                    <th scope="col" class="px-3 py-2.5 text-left font-medium sm:px-4">Nama akun</th>
                    <th scope="col" class="hidden w-[20%] px-4 py-2.5 text-left font-medium sm:table-cell">Jenis</th>
                    <th scope="col" class="hidden w-[16%] px-4 py-2.5 text-left font-medium sm:table-cell">Status</th>
                    <th scope="col" class="w-[25%] px-2 py-2.5 text-right font-medium sm:w-[16%] sm:px-5">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                {#each visibleAccounts as account (account.id)}
                    {@const typeLabel = types.get(account.type ?? '') ?? 'Belum diklasifikasi'}
                    <tr>
                        <td class="break-all px-3 py-3 font-medium tabular-nums sm:px-5">{account.code}</td>
                        <td class="break-words px-3 py-3 sm:px-4">
                            {account.name}
                            <span class="mt-0.5 block text-xs text-muted-foreground sm:hidden">
                                {typeLabel} · {account.active ? 'Aktif' : 'Nonaktif'}
                            </span>
                        </td>
                        <td class="hidden px-4 py-3 sm:table-cell">{typeLabel}</td>
                        <td class="hidden px-4 py-3 sm:table-cell">
                            <Badge variant={account.active ? 'secondary' : 'outline'}>{account.active ? 'Aktif' : 'Nonaktif'}</Badge>
                        </td>
                        <td class="px-2 py-3 text-right sm:px-5">
                            <Button type="button" variant="outline" size="sm" aria-label={'Ubah ' + account.name}
                                disabled={processing} onclick={() => edit(account)}>Ubah</Button>
                        </td>
                    </tr>
                {:else}
                    <tr><td colspan="5" class="px-4 py-6 text-center text-muted-foreground">
                        {query ? 'Akun tidak ditemukan.' : 'Belum ada akun.'}
                    </td></tr>
                {/each}
            </tbody>
        </table>
    </section>

    <Link href={templatesUrl} class="self-start text-sm font-medium underline underline-offset-4">Kelola template jurnal</Link>
</main>
