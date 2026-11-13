<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        href,
        children,
        ...rest
    }: {
        href: string | { url: string };
        children?: Snippet;
        [key: string]: unknown;
    } = $props();

    const url = $derived(typeof href === 'string' ? href : href.url);
</script>

<a href={url} {...rest}>
    {@render children?.()}
</a>
