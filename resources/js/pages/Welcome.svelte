<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import ArrowRightLeft from '@lucide/svelte/icons/arrow-right-left';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import Building2 from '@lucide/svelte/icons/building-2';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
    import FileSearch from '@lucide/svelte/icons/file-search';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Truck from '@lucide/svelte/icons/truck';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import ProductPreview from '@/components/landing/ProductPreview.svelte';
    import ReportPreview from '@/components/landing/ReportPreview.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login } from '@/routes';
    import { register } from '@/routes';
    import { index as teamsIndex } from '@/routes/teams';
    import type { Team, User } from '@/types';

    let {
        name,
        auth,
        currentTeam,
    }: {
        name: string;
        auth: { user: User | null };
        currentTeam: Team | null;
    } = $props();

    const isAuthenticated = $derived(Boolean(auth.user));
    const authenticatedUrl = $derived(
        currentTeam ? dashboard(currentTeam.slug) : teamsIndex(),
    );
    const authenticatedLabel = $derived(
        currentTeam ? 'Buka dashboard' : 'Pilih perusahaan',
    );

    const linkBase =
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#021d4f] focus-visible:ring-offset-2 dark:focus-visible:ring-[#9dbcf0]';
    const primaryLink = `${linkBase} bg-[#021d4f] text-white hover:bg-[#082f68]`;
    const secondaryLink = `${linkBase} border border-border bg-background text-foreground hover:bg-muted`;

    const capabilities = [
        { label: 'Multi-perusahaan', icon: Building2 },
        { label: 'Jurnal debit & kredit', icon: BookOpen },
        { label: 'Approval transaksi', icon: ClipboardCheck },
        { label: 'Impor & ekspor COA', icon: ArrowRightLeft },
    ];

    const featureGroups = [
        {
            title: 'Jurnal yang membantu Anda berpikir',
            description:
                'Gunakan jurnal manual atau template, temukan akun COA lebih cepat, dan jaga debit serta kredit tetap seimbang.',
            icon: BookOpen,
        },
        {
            title: 'Transaksi yang mengikuti alur bisnis',
            description:
                'Kelola penjualan dan piutang serta pembelian dan utang, dari dokumen awal hingga pembayaran dan retur.',
            icon: ArrowRightLeft,
        },
        {
            title: 'Kontrol sebelum angka menjadi laporan',
            description:
                'Tinjau draf, jalankan persetujuan, kunci periode, buat pembalikan, dan telusuri riwayat perubahan.',
            icon: ShieldCheck,
        },
    ];

    const workflowSteps = [
        {
            title: 'Catat transaksi',
            description:
                'Pilih template atau gunakan jurnal manual sesuai kebutuhan transaksi.',
        },
        {
            title: 'Tinjau dan setujui',
            description:
                'Periksa keseimbangan dan ajukan transaksi ke supervisor sebelum diposting.',
        },
        {
            title: 'Baca laporan',
            description:
                'Gunakan filter periode untuk memahami posisi dan kinerja keuangan.',
        },
    ];

    const faqs = [
        {
            question: 'Apakah bisa membuat jurnal manual tanpa template?',
            answer:
                'Bisa. Anda dapat memilih akun, tanggal, dan nominal debit atau kredit secara langsung tanpa memakai template.',
        },
        {
            question: 'Bagaimana sistem menjaga debit dan kredit tetap seimbang?',
            answer:
                'Saat satu sisi diisi, sisi lainnya pada baris yang sama dinonaktifkan. Nominal pasangan juga disarankan pada baris berikutnya, dan jurnal harus seimbang sebelum diposting.',
        },
        {
            question: 'Apakah setiap transaksi harus melalui persetujuan?',
            answer:
                'Transaksi keuangan yang dibuat staf masuk sebagai draf untuk ditinjau supervisor. Pemilik atau admin tetap dapat menjalankan kewenangan sesuai perannya.',
        },
        {
            question: 'Bisakah COA dipindahkan melalui Excel?',
            answer:
                'Bisa. Daftar akun dapat diimpor dan diekspor dalam format Excel dengan validasi kode, nama, jenis, status aktif, dan penanda kas atau bank.',
        },
        {
            question: 'Laporan apa saja yang tersedia?',
            answer:
                'Tersedia neraca saldo, laporan keuangan, laporan laba rugi, dan buku besar dengan filter periode yang sesuai.',
        },
    ];

    const currentYear = new Date().getFullYear();
</script>

<AppHead title="Software Akuntansi" />

<div class="min-h-screen overflow-x-clip bg-background text-foreground">
    <header
        class="sticky top-0 z-40 border-b border-border/80 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/85"
    >
        <nav
            class="mx-auto flex min-h-18 max-w-7xl flex-wrap items-center gap-2 px-4 py-3 sm:h-18 sm:flex-nowrap sm:gap-4 sm:px-6 sm:py-0 lg:px-8"
            aria-label="Navigasi utama"
        >
            <a
                href="/"
                class="flex min-w-0 items-center gap-2.5 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#021d4f] focus-visible:ring-offset-2"
                aria-label={name}
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white ring-1 ring-border"
                >
                    <AppLogoIcon class="size-8 object-contain" />
                </span>
                <span
                    class="max-w-48 truncate text-sm font-bold sm:max-w-none sm:text-lg"
                    data-test="landing-brand-name"
                >
                    {name}
                </span>
            </a>

            <div class="ml-auto hidden items-center gap-7 lg:flex">
                <a class="text-sm font-medium hover:text-[#021d4f] dark:hover:text-[#9dbcf0]" href="#fitur">Fitur</a>
                <a class="text-sm font-medium hover:text-[#021d4f] dark:hover:text-[#9dbcf0]" href="#cara-kerja">Cara kerja</a>
                <a class="text-sm font-medium hover:text-[#021d4f] dark:hover:text-[#9dbcf0]" href="#laporan">Laporan</a>
                <a class="text-sm font-medium hover:text-[#021d4f] dark:hover:text-[#9dbcf0]" href="#faq">FAQ</a>
            </div>

            <div
                class="ml-auto flex shrink-0 items-center gap-2 max-sm:w-full lg:ml-5"
            >
                {#if isAuthenticated}
                    <Link
                        href={toUrl(authenticatedUrl)}
                        class="{primaryLink} px-3 max-sm:w-full sm:px-5"
                    >
                        {authenticatedLabel}
                    </Link>
                {:else}
                    <Link
                        href={toUrl(login())}
                        class="{secondaryLink} flex-1 px-4 sm:flex-none"
                        data-test="landing-header-login"
                    >
                        Masuk
                    </Link>
                    <Link
                        href={toUrl(register())}
                        class="{primaryLink} flex-1 px-3 sm:flex-none sm:px-5"
                    >
                        Daftar gratis
                    </Link>
                {/if}
            </div>
        </nav>
    </header>

    <main>
        <section
            class="mx-auto max-w-7xl px-4 pt-14 pb-12 sm:px-6 sm:pt-20 lg:px-8 lg:pt-24 lg:pb-16"
        >
            <div
                class="grid items-center gap-12 lg:grid-cols-[0.86fr_1.14fr] lg:gap-16"
            >
                <div class="min-w-0">
                    <h1
                        class="max-w-3xl text-4xl leading-[0.98] font-bold tracking-[-0.045em] text-balance sm:text-5xl lg:text-6xl"
                    >
                        Jurnal rapi. Laporan siap. Bisnis lebih terkendali.
                    </h1>
                    <p
                        class="mt-6 max-w-2xl text-lg leading-8 text-muted-foreground"
                    >
                        Catat transaksi sekali, susun debit dan kredit dengan
                        lebih cepat, lalu pantau laporan keuangan setiap
                        perusahaan dari satu tempat.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        {#if isAuthenticated}
                            <Link href={toUrl(authenticatedUrl)} class={primaryLink}>
                                {authenticatedLabel}
                                <ArrowRight class="size-4" />
                            </Link>
                            <a href="#fitur" class={secondaryLink}>Lihat fitur</a>
                        {:else}
                            <Link href={toUrl(register())} class={primaryLink}>
                                Daftar gratis
                                <ArrowRight class="size-4" />
                            </Link>
                            <a href="#cara-kerja" class={secondaryLink}>
                                Lihat cara kerja
                            </a>
                        {/if}
                    </div>
                </div>

                <div
                    class="min-w-0 motion-safe:animate-[landing-in_500ms_ease-out_both]"
                >
                    <ProductPreview />
                </div>
            </div>
        </section>

        <section class="border-y border-border" aria-label="Kapabilitas utama">
            <div
                class="mx-auto grid max-w-7xl grid-cols-2 px-4 sm:px-6 lg:grid-cols-4 lg:px-8"
            >
                {#each capabilities as capability, index (capability.label)}
                    <div
                        class="flex min-w-0 flex-col items-center justify-center gap-3 px-3 py-7 text-center sm:flex-row sm:text-left lg:py-8 {index > 0 ? 'lg:border-l lg:border-border' : ''}"
                    >
                        <capability.icon
                            class="size-5 shrink-0 text-[#021d4f] dark:text-[#9dbcf0]"
                        />
                        <span class="text-sm font-semibold sm:text-base">
                            {capability.label}
                        </span>
                    </div>
                {/each}
            </div>
        </section>

        <section
            id="fitur"
            class="scroll-mt-24 py-20 sm:py-24 lg:py-32"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-4xl">
                    <h2
                        class="text-3xl leading-tight font-bold tracking-[-0.035em] text-balance sm:text-4xl lg:text-5xl"
                    >
                        Pekerjaan akuntansi yang rumit, dibuat lebih terarah.
                    </h2>
                </div>

                <div
                    class="mt-12 grid border-t border-border md:grid-cols-3 md:divide-x md:divide-border"
                >
                    {#each featureGroups as feature (feature.title)}
                        <article class="border-b border-border py-8 md:border-b-0 md:px-8 md:first:pl-0 md:last:pr-0">
                            <feature.icon
                                class="size-6 text-[#021d4f] dark:text-[#9dbcf0]"
                            />
                            <h3 class="mt-6 text-xl font-semibold tracking-tight">
                                {feature.title}
                            </h3>
                            <p class="mt-3 leading-7 text-muted-foreground">
                                {feature.description}
                            </p>
                        </article>
                    {/each}
                </div>
            </div>
        </section>

        <section
            id="cara-kerja"
            class="scroll-mt-24 border-y border-border bg-muted/35 py-20 sm:py-24"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2
                    class="max-w-4xl text-3xl leading-tight font-bold tracking-[-0.035em] text-balance sm:text-4xl lg:text-5xl"
                >
                    Dari catat hingga laporan, tetap dalam satu alur.
                </h2>

                <ol
                    class="relative mt-14 grid gap-10 before:absolute before:top-5 before:right-0 before:left-0 before:hidden before:h-px before:bg-[#021d4f]/35 md:grid-cols-3 md:gap-8 md:before:block dark:before:bg-[#9dbcf0]/35"
                >
                    {#each workflowSteps as step, index (step.title)}
                        <li class="relative min-w-0">
                            <span
                                class="relative z-10 flex size-10 items-center justify-center rounded-full bg-[#021d4f] text-sm font-bold text-white ring-8 ring-muted/90"
                            >
                                {index + 1}
                            </span>
                            <h3 class="mt-6 text-xl font-semibold tracking-tight">
                                {step.title}
                            </h3>
                            <p class="mt-2 max-w-sm leading-7 text-muted-foreground">
                                {step.description}
                            </p>
                        </li>
                    {/each}
                </ol>
            </div>
        </section>

        <section
            id="laporan"
            class="scroll-mt-24 py-20 sm:py-24 lg:py-32"
        >
            <div
                class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20 lg:px-8"
            >
                <div>
                    <FileSearch
                        class="size-7 text-[#021d4f] dark:text-[#9dbcf0]"
                    />
                    <h2
                        class="mt-6 text-3xl leading-tight font-bold tracking-[-0.035em] text-balance sm:text-4xl lg:text-5xl"
                    >
                        Dari jurnal harian ke laporan yang siap diperiksa.
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-muted-foreground">
                        Setiap transaksi yang sudah disetujui tersusun menjadi
                        laporan periode, sehingga posisi dan kinerja keuangan
                        lebih mudah dipahami dan ditelusuri.
                    </p>
                </div>
                <div class="min-w-0">
                    <ReportPreview />
                </div>
            </div>
        </section>

        <section class="border-y border-border py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2
                    class="text-3xl leading-tight font-bold tracking-[-0.035em] sm:text-4xl"
                >
                    Cocok untuk bisnis produk maupun jasa.
                </h2>
                <div class="mt-10 grid border-t border-border md:grid-cols-2 md:divide-x md:divide-border">
                    <article class="border-b border-border py-8 md:border-b-0 md:pr-12">
                        <ShoppingCart
                            class="size-6 text-[#021d4f] dark:text-[#9dbcf0]"
                        />
                        <h3 class="mt-5 text-2xl font-semibold tracking-tight">
                            Perusahaan produk
                        </h3>
                        <p class="mt-3 leading-7 text-muted-foreground">
                            Kelola penjualan, pembelian, piutang, utang, dan
                            struktur akun secara teratur dalam satu sistem.
                        </p>
                    </article>
                    <article class="py-8 md:pl-12">
                        <Truck
                            class="size-6 text-[#021d4f] dark:text-[#9dbcf0]"
                        />
                        <h3 class="mt-5 text-2xl font-semibold tracking-tight">
                            Perusahaan jasa
                        </h3>
                        <p class="mt-3 leading-7 text-muted-foreground">
                            Catat pendapatan jasa, biaya operasional, piutang,
                            dan hasil laporan per periode dengan rapi.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section
            id="faq"
            class="scroll-mt-24 py-20 sm:py-24 lg:py-32"
        >
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <h2
                    class="text-3xl leading-tight font-bold tracking-[-0.035em] sm:text-4xl lg:text-5xl"
                >
                    Pertanyaan yang sering ditanyakan.
                </h2>
                <div class="mt-10 border-t border-border">
                    {#each faqs as faq, index (faq.question)}
                        <details class="group border-b border-border" open={index === 0}>
                            <summary
                                class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-5 py-4 text-left font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#021d4f] focus-visible:ring-inset dark:focus-visible:ring-[#9dbcf0] [&::-webkit-details-marker]:hidden"
                            >
                                <span>{faq.question}</span>
                                <ChevronDown
                                    class="size-5 shrink-0 transition-transform group-open:rotate-180"
                                />
                            </summary>
                            <p class="max-w-3xl pb-6 leading-7 text-muted-foreground">
                                {faq.answer}
                            </p>
                        </details>
                    {/each}
                </div>
            </div>
        </section>

        <section class="border-t border-border bg-muted/35 py-16 sm:py-20">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                <h2
                    class="text-3xl leading-tight font-bold tracking-[-0.035em] text-balance sm:text-4xl"
                >
                    Mulai pembukuan yang lebih mudah ditelusuri.
                </h2>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    {#if isAuthenticated}
                        <Link href={toUrl(authenticatedUrl)} class={primaryLink}>
                            {authenticatedLabel}
                            <ArrowRight class="size-4" />
                        </Link>
                    {:else}
                        <Link href={toUrl(register())} class={primaryLink}>
                            Buat akun gratis
                        </Link>
                        <Link href={toUrl(login())} class={secondaryLink}>Masuk</Link>
                    {/if}
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-[#021d4f] text-white">
        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.2fr_0.8fr] lg:px-8"
        >
            <div class="max-w-md">
                <a
                    href="/"
                    class="inline-flex items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    aria-label={name}
                >
                    <span
                        class="flex size-9 items-center justify-center overflow-hidden rounded-md bg-white"
                    >
                        <AppLogoIcon class="size-8 object-contain" />
                    </span>
                    <span class="text-lg font-bold">{name}</span>
                </a>
                <p class="mt-4 text-sm leading-6 text-white/70">
                    Aplikasi pembukuan untuk membantu bisnis mencatat
                    transaksi, mengatur akun, dan menyusun laporan keuangan
                    dengan rapi.
                </p>
            </div>
            <div class="md:justify-self-end">
                <nav class="flex flex-wrap gap-x-6 gap-y-3 text-sm" aria-label="Navigasi footer">
                    <a href="#fitur" class="hover:text-white/70">Fitur</a>
                    <a href="#cara-kerja" class="hover:text-white/70">Cara kerja</a>
                    <a href="#laporan" class="hover:text-white/70">Laporan</a>
                    <a href="#faq" class="hover:text-white/70">FAQ</a>
                </nav>
                <p class="mt-6 text-sm text-white/60 md:text-right">
                    © {currentYear} {name}
                </p>
            </div>
        </div>
    </footer>
</div>

<style>
    @keyframes landing-in {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
