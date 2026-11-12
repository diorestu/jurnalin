<script lang="ts">
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import TeamSwitcher from '@/components/TeamSwitcher.svelte';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();
</script>

<header
    class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
>
    <div class="flex min-w-0 items-center gap-2">
        <SidebarTrigger class="-ml-1" />
        {#if breadcrumbs && breadcrumbs.length > 0}
            <div
                class="hidden min-w-0 overflow-hidden sm:block"
                data-test="header-breadcrumbs"
            >
                <Breadcrumbs {breadcrumbs} />
            </div>
        {/if}
    </div>
    <div
        class="ml-auto flex min-w-0 shrink-0 items-center gap-2"
        role="group"
        aria-label="Perusahaan aktif"
        data-test="active-company-switcher"
    >
        <span class="hidden text-sm text-muted-foreground sm:inline">
            Perusahaan aktif
        </span>
        <TeamSwitcher inHeader={true} />
    </div>
</header>
