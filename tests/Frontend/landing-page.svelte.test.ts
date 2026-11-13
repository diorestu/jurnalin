import { cleanup, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({
    props: {
        auth: { user: null },
        currentTeam: null,
        name: 'Jurnalin',
    },
}));

vi.mock('@inertiajs/svelte', async () => {
    const link = await import('./fixtures/InertiaLinkStub.svelte');

    return {
        Link: link.default,
        page: {
            get props() {
                return inertia.props;
            },
        },
    };
});

import Welcome from '@/pages/Welcome.svelte';

afterEach(() => cleanup());

describe('landing page hero', () => {
    it('guides guests to login or free registration', () => {
        const { container, getByRole, getAllByRole } = render(Welcome, {
            props: {
                name: 'Jurnalin',
                auth: { user: null },
                currentTeam: null,
            },
        });

        expect(
            getByRole('heading', {
                level: 1,
                name: 'Jurnal rapi. Laporan siap. Bisnis lebih terkendali.',
            }),
        ).toBeTruthy();
        expect(
            getAllByRole('link', { name: 'Masuk' }).every(
                (link) => link.getAttribute('href') === '/login',
            ),
        ).toBe(true);
        expect(
            getAllByRole('link', { name: 'Daftar gratis' }).some(
                (link) => link.getAttribute('href') === '/register',
            ),
        ).toBe(true);
        expect(
            getAllByRole('link', { name: 'Jurnalin' }).length,
        ).toBeGreaterThan(0);
        expect(
            container
                .querySelector('[data-test="landing-header-login"]')
                ?.classList.contains('max-sm:hidden'),
        ).toBe(false);
        expect(
            container
                .querySelector('[data-test="landing-brand-name"]')
                ?.classList.contains('truncate'),
        ).toBe(true);
        expect(
            container
                .querySelector('[data-test="landing-brand-name"]')
                ?.classList.contains('max-w-48'),
        ).toBe(true);
    });

    it('sends authenticated users to their current company dashboard', () => {
        const { getAllByRole, queryByRole } = render(Welcome, {
            props: {
                name: 'Jurnalin',
                auth: {
                    user: {
                        id: 1,
                        name: 'Demo',
                        email: 'demo@example.test',
                        email_verified_at: null,
                        created_at: '2026-11-12T00:00:00Z',
                        updated_at: '2026-11-12T00:00:00Z',
                    },
                },
                currentTeam: {
                    id: 1,
                    name: 'Acme',
                    slug: 'acme',
                    isPersonal: true,
                },
            },
        });

        expect(
            getAllByRole('link', { name: 'Buka dashboard' }).every(
                (link) => link.getAttribute('href') === '/acme/dashboard',
            ),
        ).toBe(true);
        expect(queryByRole('link', { name: 'Daftar gratis' })).toBeNull();
    });
});

describe('landing page product narrative', () => {
    it('presents the complete accounting workflow and factual reports', () => {
        const { container, getByRole, getAllByText } = render(Welcome, {
            props: {
                name: 'Jurnalin',
                auth: { user: null },
                currentTeam: null,
            },
        });

        for (const id of ['fitur', 'cara-kerja', 'laporan', 'faq']) {
            expect(container.querySelector(`#${id}`)).not.toBeNull();
        }

        for (const step of [
            'Catat transaksi',
            'Tinjau dan setujui',
            'Baca laporan',
        ]) {
            expect(getByRole('heading', { name: step })).toBeTruthy();
        }

        for (const report of [
            'Neraca saldo',
            'Laporan keuangan',
            'Laba rugi',
            'Buku besar',
        ]) {
            expect(
                getAllByText(report, { exact: true }).length,
            ).toBeGreaterThan(0);
        }

        expect(
            getByRole('heading', { name: 'Perusahaan produk' }),
        ).toBeTruthy();
        expect(getByRole('heading', { name: 'Perusahaan jasa' })).toBeTruthy();
        expect(container.querySelectorAll('details')).toHaveLength(5);
        expect(
            getByRole('heading', {
                name: 'Mulai pembukuan yang lebih mudah ditelusuri.',
            }),
        ).toBeTruthy();
    });
});
