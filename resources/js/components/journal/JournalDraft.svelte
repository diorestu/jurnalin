<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Search from '@lucide/svelte/icons/search';
    import { Button } from '@/components/ui/button';
    import { DateRangePicker } from '@/components/ui/date-picker';
    import { Input, NumberInput } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import JournalLines from './JournalLines.svelte';
    import type { JournalTemplate } from './types';

    let {
        templates,
        storeUrl,
        draftKey,
        today,
        canPost,
    }: {
        templates: JournalTemplate[];
        storeUrl: string;
        draftKey: string;
        today: string;
        canPost: boolean;
    } = $props();

    const normalize = (value: string) =>
        value.trim().toLowerCase().replaceAll('_', ' ').replace(/\s+/g, ' ');

    let query = $state('');
    let chosenId = $state<number | null>(null);
    let date = $state('');
    let numericAmount = $state<number>();
    let description = $state('');
    let open = $state(false);
    let activeIndex = $state(-1);
    let processing = $state(false);
    let error = $state('');
    let posted = $state(false);

    const search = $derived(normalize(query));
    const suggestions = $derived(templates.filter((template) =>
        normalize(template.name).includes(search) || normalize(template.keyword).includes(search),
    ).slice(0, 8));
    const selected = $derived.by(() => {
        if (chosenId !== null) return templates.find((template) => template.id === chosenId);

        const keywordMatch = templates.find((template) => normalize(template.keyword) === search);
        const nameMatches = templates.filter((template) => normalize(template.name) === search);
        return keywordMatch ?? (nameMatches.length === 1 ? nameMatches[0] : undefined);
    });
    const amount = $derived(numericAmount !== undefined && numericAmount > 0 ? numericAmount : null);

    function selectTemplate(template: JournalTemplate) {
        chosenId = template.id;
        query = template.name;
        open = false;
        activeIndex = -1;
        document.getElementById('journal-amount')?.focus();
    }

    function handleSearchKey(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            open = false;
            activeIndex = -1;
            return;
        }

        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && suggestions.length) {
            event.preventDefault();
            open = true;
            const last = suggestions.length - 1;
            activeIndex = event.key === 'ArrowDown'
                ? (activeIndex + 1) % suggestions.length
                : activeIndex <= 0 ? last : activeIndex - 1;
        }

        if (event.key === 'Enter' && (open || selected)) {
            event.preventDefault();
            const template = open && activeIndex >= 0 ? suggestions[activeIndex] : selected;
            if (template) selectTemplate(template);
        }
    }

    function preventImplicitPost(event: KeyboardEvent) {
        if (event.key === 'Enter') event.preventDefault();
    }

    function postJournal(event?: SubmitEvent, action: 'post' | 'draft' | 'submit' = canPost ? 'post' : 'submit') {
        event?.preventDefault();
        if (!selected || amount === null || processing) return;

        router.post(storeUrl, {
            request_id: draftKey,
            action,
            template_id: selected.id,
            signature: selected.signature,
            date: date || today,
            amount,
            description,
        }, {
            preserveScroll: true,
            onStart: () => {
                processing = true;
                error = '';
                posted = false;
            },
            onError: (errors) => {
                error = String(Object.values(errors)[0] ?? 'Jurnal gagal diposting.');
            },
            onSuccess: () => {
                query = '';
                chosenId = null;
                numericAmount = undefined;
                description = '';
                posted = true;
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<section class="rounded-xl border bg-card text-card-foreground" aria-labelledby="journal-draft-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-4 sm:px-5">
        <h2 id="journal-draft-title" class="font-semibold">Entri jurnal</h2>
        <span class="rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">Draf</span>
    </div>

    <form onsubmit={postJournal}>
        <fieldset disabled={processing} class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <div class="relative flex min-w-0 flex-col gap-1.5">
                <Label for="journal-template">Template jurnal</Label>
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                    <Input
                        id="journal-template"
                        class="pl-9"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded={open}
                        aria-controls="journal-suggestions"
                        aria-activedescendant={open && suggestions[activeIndex] ? `journal-option-${suggestions[activeIndex].id}` : undefined}
                        autocomplete="off"
                        placeholder="Cari pendapatan, listrik, atau setor"
                        value={query}
                        onfocus={() => { open = true; }}
                        onblur={() => { open = false; activeIndex = -1; }}
                        oninput={(event) => { query = event.currentTarget.value; chosenId = null; open = true; activeIndex = -1; }}
                        onkeydown={handleSearchKey}
                    />
                </div>
                <div class="absolute top-full z-10 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md" hidden={!open}>
                    <ul id="journal-suggestions" role="listbox" aria-label="Saran template jurnal">
                        {#each suggestions as template, index (template.id)}
                            <li role="presentation">
                                <button
                                    id={`journal-option-${template.id}`}
                                    type="button"
                                    role="option"
                                    aria-selected={activeIndex === index}
                                    tabindex="-1"
                                    class="block w-full rounded-sm px-2.5 py-2 text-left hover:bg-accent aria-selected:bg-accent"
                                    onpointerdown={(event) => event.preventDefault()}
                                    onclick={() => selectTemplate(template)}
                                >
                                    <span class="block text-sm font-medium">{template.name}</span>
                                    <span class="block text-xs text-muted-foreground">
                                        {template.keyword} · {template.lines[0].account_name} / {template.lines[1].account_name}
                                    </span>
                                </button>
                            </li>
                        {/each}
                    </ul>
                    {#if suggestions.length === 0}
                        <p class="px-2.5 py-2 text-sm text-muted-foreground" role="status">Template tidak ditemukan.</p>
                    {/if}
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="journal-amount">Nominal (Rp)</Label>
                <NumberInput id="journal-amount" bind:value={numericAmount} currency min={1} max={999999999999999}
                    placeholder="0" onkeydown={preventImplicitPost} aria-invalid={numericAmount !== undefined && amount === null} />
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="journal-date">Tanggal</Label>
                <DateRangePicker id="journal-date" min="1900-01-01" max="9999-12-31"
                    bind:value={() => date || today, (value) => { date = value; }}
                    disabled={processing} required />
            </div>

            <div class="flex min-w-0 flex-col gap-1.5 sm:col-span-2 lg:col-span-3">
                <Label for="journal-description">Keterangan</Label>
                <Input id="journal-description" bind:value={description} maxlength={500} placeholder="Contoh: Jasa konsultasi" onkeydown={preventImplicitPost} />
            </div>
        </fieldset>

        <JournalLines lines={selected?.lines ?? []} {amount} label="Pratinjau jurnal" />

        <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5">
            <div class="text-sm">
                {#if error}
                    <p class="text-destructive" role="alert">{error}</p>
                {:else if posted}
                    <p role="status">Jurnal berhasil diposting.</p>
                {:else if selected && amount !== null}
                    <p class="font-medium" role="status">Jurnal seimbang</p>
                {:else if numericAmount !== undefined && amount === null}
                    <p class="text-destructive">Masukkan nominal rupiah yang valid.</p>
                {/if}
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" disabled={!selected || amount === null || processing} onclick={() => postJournal(undefined, 'draft')}>Simpan draf</Button>
                <Button type="submit" disabled={!selected || amount === null || processing}>
                    {processing ? 'Menyimpan…' : canPost ? 'Posting jurnal' : 'Ajukan persetujuan'}
                </Button>
            </div>
        </div>
    </form>
</section>
