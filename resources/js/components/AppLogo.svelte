<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const name = $derived(page.props.name);
</script>

<div
    class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-white ring-1 ring-sidebar-border"
>
    <AppLogoIcon class="size-7 object-contain" />
</div>
<div class="ml-1 grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">{name}</span>
</div>
