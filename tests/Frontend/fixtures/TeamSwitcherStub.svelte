<script lang="ts">
    let { inHeader = false }: { inHeader?: boolean } = $props();
</script>

<button type="button">
    {inHeader ? 'PT Nusantara' : 'Switcher sidebar'}
</button>
