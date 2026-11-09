<script lang="ts">
    import Bold from '@lucide/svelte/icons/bold';
    import Italic from '@lucide/svelte/icons/italic';
    import Underline from '@lucide/svelte/icons/underline';
    import type Quill from 'quill';
    import { onMount } from 'svelte';
    import 'quill/dist/quill.core.css';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';

    let {
        id,
        labelledBy,
        value = $bindable(''),
        disabled = false,
        invalid = false,
        maxLength,
    }: {
        id: string;
        labelledBy: string;
        value?: string;
        disabled?: boolean;
        invalid?: boolean;
        maxLength?: number;
    } = $props();

    let toolbar: HTMLDivElement;
    let surface: HTMLDivElement;
    let editor = $state.raw<Quill | null>(null);
    let failed = $state(false);
    let length = $state(0);
    let active = false;
    let published: string | null = null;
    const tooLong = $derived(maxLength !== undefined && length > maxLength);

    function readText(quill: Quill): string {
        return quill.getText().slice(0, -1);
    }

    function updateValue() {
        if (!editor) return;

        const text = readText(editor);
        length = [...text].length;
        published = text.trim() ? JSON.stringify(editor.getContents().ops) : '';
        value = published;
    }

    async function load() {
        failed = false;

        try {
            const { default: QuillConstructor } = await import('quill');
            if (!active) return;

            const instance = new QuillConstructor(surface, {
                formats: ['bold', 'italic', 'underline'],
                modules: { toolbar },
            });

            instance.on('text-change', updateValue);
            editor = instance;
        } catch {
            if (active) failed = true;
        }
    }

    onMount(() => {
        active = true;
        void load();

        return () => {
            active = false;
            editor?.off('text-change', updateValue);
        };
    });

    $effect(() => {
        const instance = editor;
        if (!instance) return;

        instance.root.id = id;
        instance.root.setAttribute('role', 'textbox');
        instance.root.setAttribute('aria-labelledby', labelledBy);
        instance.root.setAttribute('aria-multiline', 'true');
        instance.root.setAttribute('aria-invalid', String(invalid || tooLong));
        instance.root.setAttribute('aria-disabled', String(disabled));
        if (maxLength !== undefined) instance.root.setAttribute('aria-describedby', id + '-count');
        instance.enable(!disabled);
    });

    $effect(() => {
        const instance = editor;
        if (!instance || published === value) return;

        published = value;
        instance.setContents(value ? JSON.parse(value) : [], 'silent');
        length = [...readText(instance)].length;
    });

    const toolbarClass = 'flex size-9 items-center justify-center rounded-sm text-foreground hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring active:bg-accent aria-pressed:bg-accent disabled:cursor-not-allowed disabled:opacity-50 pointer-coarse:size-11';
</script>

<div class={cn('quill-field rounded-md border bg-background focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ring', invalid || tooLong ? 'border-destructive' : 'border-input')}>
    <div bind:this={toolbar} class="flex flex-wrap gap-1 border-b px-2 py-1" role="group" aria-label="Format catatan">
        <button type="button" class={'ql-bold ' + toolbarClass} aria-label="Tebal" aria-pressed="false" disabled={!editor || disabled}>
            <Bold class="size-4" aria-hidden="true" />
        </button>
        <button type="button" class={'ql-italic ' + toolbarClass} aria-label="Miring" aria-pressed="false" disabled={!editor || disabled}>
            <Italic class="size-4" aria-hidden="true" />
        </button>
        <button type="button" class={'ql-underline ' + toolbarClass} aria-label="Garis bawah" aria-pressed="false" disabled={!editor || disabled}>
            <Underline class="size-4" aria-hidden="true" />
        </button>
    </div>

    <div bind:this={surface} class="min-h-20"></div>

    {#if failed}
        <div class="flex flex-wrap items-center gap-2 px-3 pb-3 text-sm text-destructive" role="alert">
            Editor gagal dimuat.
            <Button type="button" variant="outline" size="sm" onclick={load}>Coba lagi</Button>
        </div>
    {:else if !editor}
        <p class="px-3 pb-3 text-sm text-muted-foreground" role="status">Memuat editor catatan…</p>
    {/if}
</div>

{#if maxLength !== undefined}
    <p id={id + '-count'} class={tooLong ? 'text-xs text-destructive' : 'text-xs text-muted-foreground'}>
        {#if tooLong}Catatan melebihi batas. {/if}{length}/{maxLength} karakter
    </p>
{/if}

<style>
    .quill-field :global(.ql-container) {
        font: inherit;
    }

    .quill-field :global(.ql-editor) {
        min-height: 5rem;
        padding: 0.75rem;
        overflow-wrap: anywhere;
    }

    .quill-field :global(.ql-editor[aria-disabled='true']) {
        cursor: not-allowed;
    }
</style>
