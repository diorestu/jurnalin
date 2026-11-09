# Journal approvals, period locks, and audit

**Goal**. Staff save and submit financial work for supervisor approval, posted journals can be corrected with linked reversals, and locked monthly periods protect reporting.

**Architecture**. Draft commands live in a separate journal_drafts table. Only supervisor approval creates the journal or financial trade document, so existing reports and document balances keep reading posted data. Approval revalidates current accounts, dates, source quantities, and remaining balances under the shared team lock.

- [x] Draft storage, revisions, optimistic versions, submission, return with reason, and approval for manual/template journals.
- [x] Approval of staff invoices, supplier bills, deposits, payments, returns, and refunds, with editable returned trade drafts.
- [x] Manual/template journal reversal with immutable original, reason, and link to the reversing journal. Source-generated entries keep correction through their transaction documents.
- [x] Monthly lock/reopen with mandatory reason, pending-request checks, and a cutoff protecting the selected month plus earlier balances. Reopen the latest locked month first. Account types used through the cutoff are protected.
- [x] Append-only audit events for journal/draft/period/COA/template/opening/trade changes.
- [x] Verify retries, competing payments, roles, company boundaries, closed dates, reversal direction, browser flows, and mobile layouts.

Owner/Admin are supervisors and can post directly. Staff must submit financial work. Opening balances already require supervisor access. Operational quotation/order/delivery/receipt documents retain their existing flow. Historical journals remain posted and are not rewritten.
