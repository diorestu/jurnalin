<script lang="ts">
    import { Combobox } from 'bits-ui';
    import Check from '@lucide/svelte/icons/check';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';

    type Account = { id: number; code: string; name: string; active?: boolean };
    let {
        id,
        accounts,
        value = $bindable(null),
        disabled = false,
        required = false,
        invalid = false,
    }: {
        id: string;
        accounts: Account[];
        value?: number | null;
        disabled?: boolean;
        required?: boolean;
        invalid?: boolean;
    } = $props();

    let open = $state(false);
    let search = $state('');
    let display = $state('');
    let input = $state<HTMLInputElement | null>(null);
    const options = $derived(accounts.map(account => ({
        value: String(account.id),
        label: account.code + ' · ' + account.name + (account.active === false ? ' (nonaktif)' : ''),
    })));
    const selected = $derived(options.find(option => option.value === String(value)));
    const normalized = $derived(search.trim().toLocaleLowerCase('id-ID'));
    const filtered = $derived(normalized
        ? options.filter(option => option.label.toLocaleLowerCase('id-ID').includes(normalized))
        : options);

    $effect(() => {
        if (!open) {
            display = selected?.label ?? '';
            search = '';
        }
    });

    $effect(() => {
        input?.setCustomValidity((required || value !== null) && !selected ? 'Pilih akun COA dari daftar.' : '');
    });

    function choose(next: string) {
        value = next === '' ? null : Number(next);
        display = options.find(option => option.value === next)?.label ?? '';
        search = '';
    }
</script>

<Combobox.Root
    type="single"
    bind:open
    bind:value={() => value === null ? '' : String(value), choose}
    inputValue={display}
    items={options}
    {disabled}
    {required}
    allowDeselect={false}
>
    <div class="relative min-w-0">
        <Combobox.Input
            {id}
            bind:ref={input}
            autocomplete="off"
            placeholder="Cari kode atau nama akun"
            aria-invalid={invalid}
            class="h-9 w-full min-w-0 rounded-md border border-input bg-background ps-3 pe-12 text-base shadow-xs hover:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive sm:text-sm pointer-coarse:min-h-11"
            onclick={() => { open = true; }}
            oninput={event => { open = true; search = event.currentTarget.value; display = search; }}
        />
        <Combobox.Trigger
            type="button"
            aria-label="Buka pilihan akun"
            class="absolute inset-y-0 end-0 flex w-12 items-center justify-center rounded-e-md text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50"
        >
            <ChevronDown class="size-4" aria-hidden="true" />
        </Combobox.Trigger>
    </div>

    <Combobox.Portal>
        <Combobox.Content
            sideOffset={4}
            collisionPadding={8}
            class="z-50 w-[var(--bits-combobox-anchor-width)] max-w-[calc(100vw-1rem)] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
        >
            <Combobox.Viewport class="max-h-64 overflow-y-auto p-1">
                {#each filtered as option (option.value)}
                    <Combobox.Item
                        value={option.value}
                        label={option.label}
                        class="flex min-h-10 items-center justify-between gap-2 rounded-sm px-2.5 py-2 text-sm break-words outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground pointer-coarse:min-h-11"
                    >
                        {#snippet children({ selected: checked })}
                            <span class="min-w-0">{option.label}</span>
                            {#if checked}<Check class="size-4 shrink-0" aria-hidden="true" />{/if}
                        {/snippet}
                    </Combobox.Item>
                {:else}
                    <p class="px-2.5 py-3 text-sm text-muted-foreground" role="status">Akun tidak ditemukan.</p>
                {/each}
            </Combobox.Viewport>
        </Combobox.Content>
    </Combobox.Portal>
</Combobox.Root>
