# Accounting Landing Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Laravel's starter welcome screen with an Accurate-inspired, original accounting product landing page.

**Architecture:** `Welcome.svelte` owns semantic page structure, routing, copy, and authenticated/guest CTA decisions. Two focused presentational components render the code-native dashboard and report previews without browser chrome or invented business data. Existing Inertia routes, shared page props, global tokens, logo, and metadata remain the integration boundaries.

**Tech Stack:** Laravel 13, Inertia 3, Svelte 5, Tailwind CSS 4, Lucide Svelte, Vitest, Testing Library Svelte.

**Spec:** `docs/superpowers/specs/2026-11-12-landing-page-design.md`

## Global Constraints

- Preserve current Inter typography, navy logo, light/dark tokens, authentication routes, registration route, and current-team dashboard route.
- Do not add claims, metrics, testimonials, pricing, integrations, or capabilities not already implemented.
- No Accurate identity, copy, colors, screenshots, or assets.
- No gradients, fake browser chrome, decorative blobs, excessive pills, or horizontal overflow.
- Keep primary controls at least 44 px high and all anchor/FAQ interactions keyboard accessible.
- Use only code-native product previews with illustrative dashes or state labels.

## Review Focus

- Guest with no current company must see working login and registration CTAs.
- Authenticated user with a current company must get a dashboard link containing that company's slug.
- Authenticated user without a current company must not receive a malformed dashboard URL.
- Long configured application names must wrap or truncate without breaking 320 px navigation.
- Hash navigation and native FAQ details must remain usable with keyboard and sticky-header offsets.

---

### Task 1: Landing shell, navigation, hero, and product preview

**Files:**

- Create: `resources/js/components/landing/ProductPreview.svelte`
- Create: `tests/Frontend/fixtures/InertiaLinkStub.svelte`
- Create: `tests/Frontend/landing-page.svelte.test.ts`
- Replace: `resources/js/pages/Welcome.svelte`

**Interfaces:**

- `Welcome.svelte` consumes page props `name: string`, `auth: { user: User | null }`, and `currentTeam: Team | null`.
- `ProductPreview.svelte` accepts no props and renders a labelled, non-interactive accounting dashboard preview.

- [ ] **Step 1: Write failing render tests**

Test guest output for logo/name, `Masuk`, `Daftar gratis`, hero heading, and registration destination. Test authenticated output for one `Buka dashboard` CTA using `/acme/dashboard` and absence of guest registration CTA.

- [ ] **Step 2: Verify RED**

Run: `npx vitest run tests/Frontend/landing-page.svelte.test.ts --config vitest.config.ts`
Expected: FAIL because the Laravel starter page lacks the new heading, Indonesian CTA copy, and product preview.

- [ ] **Step 3: Implement the minimal shell and hero**

Replace starter markup with semantic sticky navigation, hero copy from the spec, responsive CTAs, capability strip, and `ProductPreview`. Use existing `AppHead`, `AppLogoIcon`, generated routes, and `toUrl`.

- [ ] **Step 4: Verify GREEN**

Run the focused Vitest file; expected two passing CTA/hero tests.

### Task 2: Feature narrative, workflow, reports, FAQ, and footer

**Files:**

- Create: `resources/js/components/landing/ReportPreview.svelte`
- Modify: `resources/js/pages/Welcome.svelte`
- Modify: `tests/Frontend/landing-page.svelte.test.ts`

**Interfaces:**

- `ReportPreview.svelte` accepts no props and renders the monthly/yearly selector plus four factual report rows.
- `Welcome.svelte` exposes anchors `fitur`, `cara-kerja`, `laporan`, and `faq`.

- [ ] **Step 1: Add failing semantic-content tests**

Assert the four section anchors, three workflow step headings, factual report names, both product/service business-fit headings, five native `<details>` FAQ items, and closing CTA.

- [ ] **Step 2: Verify RED**

Run the focused Vitest file; expected failure for missing lower-page sections.

- [ ] **Step 3: Implement lower-page sections**

Build the feature groups, connected ledger workflow, editorial report split with `ReportPreview`, product/service fit, native FAQ, closing CTA, and restrained footer exactly from the spec.

- [ ] **Step 4: Verify GREEN and code quality**

Run: `npx vitest run tests/Frontend/landing-page.svelte.test.ts --config vitest.config.ts && npm run check && npm run build`
Expected: all tests and checks pass.

### Task 3: Full verification and responsive browser QA

**Files:**

- Modify only if a verified browser or accessibility defect requires a TDD fix.

**Interfaces:**

- Consumes the complete landing page from Tasks 1–2.
- Produces browser evidence for the public home route.

- [ ] **Step 1: Run the full project suite**

Run: `composer ci:check`, focused landing Vitest, production build, and `git diff --check`.

- [ ] **Step 2: Browser QA**

At 320, 375, 414, 768, and desktop widths, verify page identity, meaningful DOM, console health, hero layout, CTA destinations, FAQ keyboard opening, dark mode, and no horizontal overflow.

- [ ] **Step 3: Fix verified defects with RED→GREEN tests**

For each Important/Critical defect, add the narrowest reproducing test, confirm RED, implement one fix, then confirm GREEN and rerun the full suite.
