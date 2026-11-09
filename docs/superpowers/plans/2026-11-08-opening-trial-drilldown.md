# Opening balances, trial balance, and report navigation

**Goal**. Import one balanced opening package per company and let accountants inspect report amounts down to journals and source documents.

**Architecture**. Opening balances post one immutable journal. Receivable/payable details create opening invoices with no revenue/expense posting. Inventory details retain starting quantity and cost. Exact calculations use integer rupiah and BigInteger.

**Stack**. Laravel 13, Inertia Svelte 5, SQLite, existing COA and journal tables.

- [x] Test and implement opening tables and atomic posting with team locking, date boundary, authorization, and idempotency.
- [x] Provide the opening form and read-only detail page with shared form controls.
- [x] Implement trial balance opening/movement/closing columns and linked account balances.
- [x] Connect financial and profit/loss amounts to ledger filters, ledger rows to journal detail, and journals to source documents.
- [x] Verify source invoice payment, opening stock details, duplicate retries, invalid dates/accounts, company isolation, large amounts, UI flows, and responsive layouts.

Opening date is the balance as of that day. Subsequent journals and operational transactions must be later. Generic opening lines use balance-sheet accounts. AR/AP/inventory control balances come from their detail lists and are included once in the opening journal. Opening invoices represent outstanding amounts as of the import date, not original invoice gross amounts. Existing posted data is preserved.
