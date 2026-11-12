<script lang="ts">
    let {
        class: className = '',
        ...rest
    }: {
        class?: string;
        [key: string]: unknown;
    } = $props();
</script>

<img
    src="/brand/jurnalin-logo.png"
    alt=""
    aria-hidden="true"
    width="512"
    height="512"
    class={className}
    {...rest}
/>
