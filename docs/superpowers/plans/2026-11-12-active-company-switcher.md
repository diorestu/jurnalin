# Active Company Switcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the existing company switcher from the sidebar into the top-right corner of the content header.

**Architecture:** Reuse `TeamSwitcher.svelte` and its existing Inertia navigation behavior. `AppSidebarHeader.svelte` owns the new responsive placement; `AppSidebar.svelte` stops rendering the duplicate control.

**Tech Stack:** Svelte 5, Inertia.js 3, Tailwind CSS 4, Bits UI dropdown.

**Spec:** `docs/superpowers/specs/2026-11-12-active-company-switcher-design.md`

## Global Constraints

- Do not change team endpoints, middleware, session behavior, or authorization.
- Keep the existing dropdown implementation and company-switching route.
- Show `Perusahaan aktif` on wider screens and keep the company name available at 375 px.
- Preserve keyboard access and light/dark mode behavior.

## Review Focus

- Long company names must truncate without pushing the header wider than the viewport.
- A user with one company must still see the current company and a usable trigger.
- A user with no current company must receive the existing fallback label without a crash.
- Sidebar collapsed mode must not leave an empty switcher row.
- The switcher must remain keyboard-openable and retain visible focus behavior.

---

### Task 1: Move the active-company switcher into the content header

**Files:**

- Create: `tests/Frontend/active-company-switcher.test.mjs`
- Modify: `resources/js/components/AppSidebarHeader.svelte`
- Modify: `resources/js/components/AppSidebar.svelte`

**Interfaces:**

- Consumes: `TeamSwitcher` with `inHeader={true}` and existing `data-test="team-switcher-trigger"`.
- Produces: header region `data-test="active-company-switcher"` with visible label `Perusahaan aktif` on `sm` and wider screens.

- [ ] **Step 1: Write the failing structural test**

Assert that `AppSidebarHeader.svelte` imports and renders `TeamSwitcher`, contains `Perusahaan aktif`, uses `ml-auto`, and exposes `active-company-switcher`; assert that `AppSidebar.svelte` no longer imports or renders `TeamSwitcher`.

- [ ] **Step 2: Run the test and verify RED**

Run: `node --test tests/Frontend/active-company-switcher.test.mjs`
Expected: FAIL because the header has no active-company switcher and the sidebar still owns it.

- [ ] **Step 3: Implement the minimal layout change**

Add the responsive labelled `TeamSwitcher inHeader={true}` region to `AppSidebarHeader.svelte`, and remove the import plus sidebar menu row from `AppSidebar.svelte`.

- [ ] **Step 4: Verify GREEN and quality gates**

Run: `node --test tests/Frontend/active-company-switcher.test.mjs && npm run check && npm run build`
Expected: all commands pass.

- [ ] **Step 5: Browser QA**

Verify desktop, 375 px, dark mode, dropdown keyboard opening, no horizontal overflow, and no console warnings or errors.
