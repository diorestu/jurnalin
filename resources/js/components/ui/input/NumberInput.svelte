<script lang="ts">
    import type { HTMLInputAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';
    import Input from './Input.svelte';
    import { formatInteger, parseInteger } from './number-mask';

    type Props = Omit<HTMLInputAttributes, 'value' | 'type' | 'min' | 'max' | 'oninput' | 'onchange' | 'name' | 'onpaste' | 'onbeforeinput'> & {
        value?: number;
        min?: number;
        max?: number;
        currency?: boolean;
        name?: string;
    };

    let {
        value = $bindable(),
        min = 0,
        max = Number.MAX_SAFE_INTEGER,
        currency = false,
        class: className,
        name,
        onkeydown,
        onblur,
        'aria-describedby': describedBy,
        'aria-invalid': invalid,
        ...rest
    }: Props = $props();

    let input = $state<HTMLInputElement | null>(null);
    let feedback = $state('');
    const messageId = $props.id();
    const display = $derived(formatInteger(value));
    const validity = $derived(value !== undefined && (value < min || value > max || !Number.isSafeInteger(value))
        ? 'Masukkan angka dari ' + formatInteger(min) + ' sampai ' + formatInteger(max) + '.'
        : '');

    $effect(() => { input?.setCustomValidity(validity); });

    function update(raw: string, digitPosition: number, editing: boolean) {
        const parsed = parseInteger(raw, max, currency, editing);
        if (parsed.error) {
            feedback = parsed.error;
            if (input) input.value = display;
            return;
        }

        feedback = '';
        value = parsed.value;
        if (!input) return;
        const formatted = formatInteger(parsed.value);
        input.value = formatted;

        let position = 0;
        let digits = 0;
        while (position < formatted.length && digits < digitPosition) {
            if (/\d/.test(formatted[position])) digits++;
            position++;
        }
        input.setSelectionRange(position, position);
    }

    function beforeInput(event: InputEvent & { currentTarget: HTMLInputElement }) {
        if (event.isComposing || !event.data) return;
        const oneCharacter = event.data.length === 1;
        const error = oneCharacter
            ? (!/^\d$/.test(event.data) ? 'Gunakan angka bulat tanpa tanda minus atau desimal.' : undefined)
            : parseInteger(event.data, max, currency).error;
        if (error) {
            event.preventDefault();
            feedback = error;
        }
    }

    function handleInput(event: Event & { currentTarget: HTMLInputElement }) {
        const target = event.currentTarget;
        const raw = target.value;
        const position = (raw.slice(0, target.selectionStart ?? raw.length).match(/\d/g) ?? []).length;
        const editing = event instanceof InputEvent && (
            event.inputType.startsWith('delete') || (event.data?.length === 1 && /^\d$/.test(event.data))
        );
        update(raw, position, !!editing);
    }

    function paste(event: ClipboardEvent & { currentTarget: HTMLInputElement }) {
        if (event.currentTarget.readOnly || event.currentTarget.disabled) return;
        event.preventDefault();
        const parsed = parseInteger(event.clipboardData?.getData('text') ?? '', max, currency);
        if (parsed.error) {
            feedback = parsed.error;
            return;
        }
        const target = event.currentTarget;
        const before = target.value.slice(0, target.selectionStart ?? 0).replaceAll('.', '');
        const after = target.value.slice(target.selectionEnd ?? target.value.length).replaceAll('.', '');
        const inserted = parsed.value === undefined ? '' : String(parsed.value);
        update(before + inserted + after, before.length + inserted.length, false);
    }
</script>

<div class="relative min-w-0">
    {#if currency}
        <span class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground" aria-hidden="true">IDR</span>
    {/if}
    <Input
        {...rest}
        bind:ref={input}
        value={display}
        type="text"
        inputmode="numeric"
        autocomplete="off"
        spellcheck="false"
        class={cn('text-right tabular-nums pointer-coarse:min-h-11', currency && 'ps-12', className)}
        aria-invalid={invalid || !!validity}
        aria-describedby={[describedBy, feedback ? messageId : undefined].filter(Boolean).join(' ') || undefined}
        onbeforeinput={beforeInput}
        oninput={handleInput}
        onpaste={paste}
        {onkeydown}
        onblur={(event) => {
            feedback = validity;
            onblur?.(event);
        }}
    />
</div>
{#if name}<input type="hidden" {name} value={value ?? ''} disabled={rest.disabled} />{/if}
{#if feedback}
    <p id={messageId} class="text-sm text-destructive" role="status">{feedback}</p>
{/if}
