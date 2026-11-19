<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import Truck from '@lucide/svelte/icons/truck';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import ChartColumn from '@lucide/svelte/icons/chart-column';
    import LayoutDashboard from '@lucide/svelte/icons/layout-dashboard';
    import ListTree from '@lucide/svelte/icons/list-tree';
    import type { Snippet } from 'svelte';
    import {index as catalogIndex} from '@/routes/catalog';
    import Package from '@lucide/svelte/icons/package';
    import AppLogo from '@/components/AppLogo.svelte';
    import {index as assetsIndex} from '@/routes/assets';
    import Building from '@lucide/svelte/icons/building';
    import { index as bankIndex } from '@/routes/bank';
    import Landmark from '@lucide/svelte/icons/landmark';
    import { index as partnerIndex } from '@/routes/partners';
    import Users from '@lucide/svelte/icons/users';
    import Upload from '@lucide/svelte/icons/upload';
    import { index as importsIndex } from '@/routes/imports';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { dashboard } from '@/routes';
    import { index as chartOfAccounts } from '@/routes/chart-of-accounts';
    import { index as journalIndex } from '@/routes/journals';
    import { index as reportIndex } from '@/routes/reports';
    import { index as periodsIndex } from '@/routes/accounting-periods';
    import { index as auditIndex } from '@/routes/accounting-audit';
    import { create as openingBalances } from '@/routes/opening-balances';
    import { index as tradeIndex } from '@/routes/trade';
    import type { NavItem, Team } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const currentTeam = $derived(page.props.currentTeam as Team | null);
    const dashboardUrl = $derived(
        currentTeam ? dashboard(currentTeam.slug) : '/',
    );

    const mainNavItems = $derived.by<NavItem[]>(() => {
        const items: NavItem[] = [
            { title: 'Dashboard', href: dashboardUrl, icon: LayoutDashboard },
            { title: 'Buku Jurnal', href: currentTeam ? journalIndex(currentTeam.slug) : '/', icon: BookOpen },
        ];

        if (currentTeam) {
            items.push(
                {title:'Aset Tetap',href:assetsIndex(currentTeam.slug),icon:Building},
                {title:'Barang & Jasa',href:catalogIndex(currentTeam.slug),icon:Package},
                { title: 'Kas & Bank', href: bankIndex(currentTeam.slug), icon: Landmark },
                { title: 'Pelanggan & Pemasok', href: partnerIndex(currentTeam.slug), icon: Users },
                { title: 'Impor Data', href: importsIndex(currentTeam.slug), icon: Upload },
                { title: 'Penjualan & Piutang', href: tradeIndex([currentTeam.slug, 'penjualan']), icon: ShoppingCart },
                { title: 'Pembelian & Utang', href: tradeIndex([currentTeam.slug, 'pembelian']), icon: Truck },
            );
        }

        if (currentTeam?.role === 'owner' || currentTeam?.role === 'admin') {
            items.push(
                { title: 'Kelola COA', href: chartOfAccounts(currentTeam.slug), icon: ListTree },
                { title: 'Saldo Awal', href: openingBalances(currentTeam.slug), icon: BookOpen },
                { title: 'Periode Akuntansi', href: periodsIndex(currentTeam.slug), icon: BookOpen },
                { title: 'Riwayat Akuntansi', href: auditIndex(currentTeam.slug), icon: ListTree },
            );
        }

        return items;
    });

    const footerNavItems = $derived<NavItem[]>(currentTeam
        ? [{ title: 'Report', href: reportIndex(currentTeam.slug), icon: ChartColumn }]
        : [],
    );
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={dashboardUrl}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={footerNavItems} />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
