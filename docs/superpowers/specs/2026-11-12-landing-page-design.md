# Landing Page Product Showcase

## Intent

Replace the Laravel starter `Welcome` screen with a public landing page that explains the accounting product clearly and moves visitors toward registration or login. The page borrows the product-showcase rhythm of Accurate Online—strong hero, visible product interface, grouped capabilities, business fit, FAQ, and closing CTA—without copying Accurate's identity, copy, assets, claims, or layout pixel-for-pixel.

The displayed product name comes from the existing shared `name`/application configuration so the page remains correct if the brand name changes.

## Goals

- Explain the product within the first viewport: journal entry, transaction workflow, approvals, and financial reporting.
- Make `Daftar gratis` the primary action and `Masuk` the secondary action for guests.
- Send authenticated users directly to their current company dashboard.
- Demonstrate the product with a code-native accounting dashboard preview based only on features that already exist.
- Work cleanly at 320, 375, 414, 768, 1024, and wide desktop sizes, in light and dark modes.
- Preserve keyboard navigation, visible focus, semantic headings, and reduced-motion behavior.

## Non-goals

- No pricing table, checkout, consultation form, WhatsApp CTA, or sales-contact workflow.
- No invented customer counts, testimonials, awards, integrations, tax automation, inventory, manufacturing, or other unsupported capabilities.
- No Accurate logos, screenshots, copy, colors, or downloadable assets.
- No backend, authentication, registration, or accounting workflow changes.

## Approaches Considered

1. **Accurate-inspired product showcase — selected.** A complete but bounded marketing page with product preview and capability sections. Best balance between product clarity and trust.
2. **Minimal landing for accountants.** Faster and more restrained, but does not satisfy the requested Accurate-like depth.
3. **Long corporate marketing page.** Closest in length to Accurate, but would require fabricated or unavailable pricing, testimonial, and industry content.

## Visual Direction

- Preserve the current Inter typography, navy logo, monochrome application tokens, and restrained radius/shadow system.
- Use white/near-black surfaces, navy accents derived from the logo, thin borders, and one warm neutral supporting surface. No gradients, glassmorphism, decorative blobs, excessive pills, or fake browser chrome.
- The landing page remains visually related to the authenticated product: compact controls, tabular numbers, ledger lines, report cards, and the same border language.
- Motion is limited to subtle opacity/translate entry on the hero preview and interactive hover/focus transitions. Disable entry motion under `prefers-reduced-motion`.

## Page Structure and Copy

### 1. Sticky navigation

- Left: current J logo plus configured application name.
- Desktop anchors: `Fitur`, `Cara kerja`, `Laporan`, `FAQ`.
- Guest actions: `Masuk` and primary `Daftar gratis`.
- Authenticated action: primary `Buka dashboard`.
- Mobile: logo/name and the relevant authentication actions remain visible; nonessential anchor navigation may be omitted rather than hidden behind a custom menu.

### 2. Hero

- Eyebrow: `Sistem akuntansi untuk bisnis Indonesia`.
- Heading: `Jurnal rapi. Laporan siap. Bisnis lebih terkendali.`
- Supporting copy: `Catat transaksi sekali, susun debit dan kredit dengan lebih cepat, lalu pantau laporan keuangan setiap perusahaan dari satu tempat.`
- Guest CTAs: `Daftar gratis` and anchor `Lihat cara kerja`.
- Authenticated CTA: `Buka dashboard` and anchor `Lihat fitur`.
- Product preview: code-native dashboard surface with a balanced journal indicator, monthly revenue/expense summary labels, recent journal rows, and an approval status. Values use `—` or clearly illustrative state labels instead of fabricated business results.

### 3. Capability strip

Four factual capabilities without invented metrics:

- `Multi-perusahaan`
- `Jurnal debit & kredit`
- `Approval transaksi`
- `Impor & ekspor COA`

### 4. Core value section (`#fitur`)

Section heading: `Pekerjaan akuntansi yang rumit, dibuat lebih terarah.`

Three feature groups:

- `Jurnal yang membantu Anda berpikir` — manual entry, saved templates, account suggestions, debit/credit balancing.
- `Transaksi yang mengikuti alur bisnis` — sales/receivables and purchases/payables, including orders, invoices, payments, advances, and returns.
- `Kontrol sebelum angka menjadi laporan` — staff drafts, supervisor approval, accounting periods, reversals, and audit history.

### 5. Workflow (`#cara-kerja`)

Three numbered steps:

1. `Catat transaksi` — choose a template or use manual journal entry.
2. `Tinjau dan setujui` — validate balances and route financial transactions through approval.
3. `Baca laporan` — use period filters to inspect the trial balance, financial position, profit and loss, and general ledger.

Use a connected ledger-line visual, not generic floating cards.

### 6. Reports showcase (`#laporan`)

- Editorial split layout with a compact code-native report table.
- Heading: `Dari jurnal harian ke laporan yang siap diperiksa.`
- Describe the currently implemented monthly/yearly financial report, profit and loss, trial balance, and general ledger.
- Include period selector and balanced totals as interface labels, with illustrative dashes instead of invented values.

### 7. Business fit

Two columns only:

- `Perusahaan produk` — sales, purchases, receivables, payables, and account structure.
- `Perusahaan jasa` — service revenue, operating expenses, receivables, and period reporting.

Avoid claiming inventory or manufacturing features that do not exist.

### 8. FAQ (`#faq`)

Use native `<details>` elements for keyboard and no-JS resilience:

- `Apakah bisa membuat jurnal manual tanpa template?`
- `Bagaimana sistem menjaga debit dan kredit tetap seimbang?`
- `Apakah setiap transaksi harus melalui persetujuan?`
- `Bisakah COA dipindahkan melalui Excel?`
- `Laporan apa saja yang tersedia?`

Answers must match current implemented behavior.

### 9. Closing CTA and footer

- Heading: `Mulai pembukuan yang lebih mudah ditelusuri.`
- Guest primary action: `Buat akun gratis`; secondary: `Masuk`.
- Authenticated primary action: `Buka dashboard`.
- Footer contains logo/name, a short product description, anchor links, and current year. Do not add nonexistent legal, social, support, or company links.

## Interaction and Routing

- Use existing generated routes for `login`, `register`, and the current-team dashboard.
- Anchor links use native hash navigation and account for the sticky header through scroll margins.
- All CTA copy stays on one line at supported widths.
- The logo link returns to `/`.
- FAQ works without client-side state management.

## SEO and Metadata

- Page title: `Software Akuntansi` through `AppHead`.
- Retain the existing global product description and Open Graph metadata from the Blade shell; do not emit duplicate page-specific description or Open Graph tags from Svelte.
- Remove the obsolete `rsms.me` Inter stylesheet because Inter is already loaded globally from Google Fonts.

## Accessibility and Responsive Rules

- One `<h1>`, sequential section headings, semantic `<nav>`, `<main>`, `<section>`, and `<footer>`.
- Focus-visible styling for every link and `<summary>`.
- Minimum 44 px touch targets for primary navigation and CTA controls.
- No horizontal scroll at 320 px; decorative preview elements may simplify rather than overflow.
- Product preview and feature layouts collapse to a single column below tablet widths.
- Ensure logo and navy accent remain legible in dark mode.

## Files and Boundaries

- Replace: `resources/js/pages/Welcome.svelte`.
- May add focused landing components under `resources/js/components/landing/` only if `Welcome.svelte` becomes difficult to review as one file.
- Keep `AppHead.svelte` unchanged unless implementation reveals a title-specific issue; global Blade metadata remains the default for this page and authenticated screens.
- Add frontend render tests for guest/authenticated CTA behavior and required semantic sections.
- Do not change routes or controllers unless a failing test proves the existing route cannot supply the page.

## Verification

- TDD render tests for guest navigation, authenticated dashboard CTA, section anchors, and factual feature copy.
- `npm run check`, Svelte diagnostics, production build, PHP tests, and `git diff --check`.
- Browser QA at 320, 375, 414, 768, and desktop widths in light and dark modes.
- Browser checks: page identity, meaningful DOM, no framework overlay, no console errors, keyboard navigation through header/FAQ, CTA destinations, no horizontal overflow, and stable hero layout.

## Reference

- Accurate Online public product page, consulted for information hierarchy only: `https://accurate.id/online/`.
