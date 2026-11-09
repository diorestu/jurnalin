<script lang="ts">
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import type { HTMLSelectAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    let { value = $bindable(), class: className, children, ...attributes }: HTMLSelectAttributes = $props();
</script>

<div class="relative min-w-0">
    <select
        bind:value
        class={cn(
            'peer h-9 w-full appearance-none rounded-md border border-input bg-background ps-3 pe-11 text-base shadow-xs sm:text-sm',
            'hover:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive pointer-coarse:min-h-11',
            className,
        )}
        {...attributes}
    >
        {@render children?.()}
    </select>
    <ChevronDown class="pointer-events-none absolute end-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground peer-disabled:opacity-50" aria-hidden="true" />
</div>
