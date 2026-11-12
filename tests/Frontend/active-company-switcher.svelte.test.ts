import { render } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/components/TeamSwitcher.svelte', async () => {
    const stub = await import('./fixtures/TeamSwitcherStub.svelte');

    return { default: stub.default };
});

vi.mock('@/components/Breadcrumbs.svelte', async () => {
    const stub = await import('./fixtures/BreadcrumbsStub.svelte');

    return { default: stub.default };
});

vi.mock('@/components/ui/sidebar', async () => {
    const stub = await import('./fixtures/SidebarTriggerStub.svelte');

    return { SidebarTrigger: stub.default };
});

import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';

describe('active company switcher', () => {
    it('shows the active company control in the content header', () => {
        const { container, getByRole, getByText } = render(AppSidebarHeader);

        expect(
            container.querySelector('[data-test="active-company-switcher"]'),
        ).not.toBeNull();
        expect(getByText('Perusahaan aktif')).toBeTruthy();
        expect(getByRole('button', { name: 'PT Nusantara' })).toBeTruthy();
    });

    it('keeps long breadcrumbs out of the narrow header layout', () => {
        const { container } = render(AppSidebarHeader, {
            props: {
                breadcrumbs: [
                    { title: 'Teams', href: '/teams' },
                    {
                        title: 'PT Nusantara Indonesia Bersama Sejahtera',
                        href: '/teams/nusantara',
                    },
                ],
            },
        });

        const breadcrumbs = container.querySelector(
            '[data-test="header-breadcrumbs"]',
        );

        expect(breadcrumbs).not.toBeNull();
        expect(breadcrumbs?.classList.contains('hidden')).toBe(true);
        expect(breadcrumbs?.classList.contains('sm:block')).toBe(true);
    });
});
