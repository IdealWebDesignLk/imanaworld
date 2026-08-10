# IMANAWORLD Pickup Network (IPN) — Project Checklist

Status snapshot of the `imanaworld-pickup-network` plugin against the confirmed v3.0 scope
(`Imana Doc.pdf` / `IPN_Project_Context.md`). Verified against the actual codebase, not
against what was reported when each piece was built — grep/read the referenced file to
double-check anything before relying on it.

**Legend**
- ✅ **Done** — real logic, actually wired to the trigger/UI that's supposed to call it
- 🟡 **Partial** — either the backend exists but nothing calls it yet, or the UI exists but shows a "not implemented" placeholder/toast instead of doing the real thing
- ⬜ **Not started** — no code for this yet
- 🚫 **Out of scope** — confirmed Phase 2, correctly excluded from this build
- ⏳ **Pending on client** — not a dev task; blocked on IMANAWORLD/Choppies

Last verified: 2026-08-10.

---

## 0. Business / contract prerequisites (Section 9 of the scope doc)

None of these are code tasks — flagging them because several dev tasks below are blocked on them.

- ⏳ Signed scope agreement + 30% kickoff payment
- ⏳ Pilot store list from Choppies (branch names, addresses, GPS, contact emails) — needed to seed real `ipn_branches` rows
- ⏳ Sample CSV/Excel catalogue file from Choppies — needed to build the real import mapping in `IPN_CSV_Import::process_file()`
- ⏳ Express Collection surcharge amount confirmed for pilot
- ⏳ Returns/refunds SLA agreed between IMANAWORLD and Choppies

**Note on source-document discrepancy:** `Imana Doc.pdf` (the signed-scope PDF, "Final Revised Scope") quotes 360–380 estimated hours and a 15-week timeline with no dollar figure. `IPN_Project_Context.md` quotes USD 7,500–8,500 and 9–12 weeks, and describes SMS notifications as in-scope where the PDF explicitly marks SMS as **"THIS IS FOR PHASE 2."** This checklist follows the PDF (it's the one marked "Pending Client Approval") for scope boundaries — worth reconciling with the client before quoting either figure externally.

---

## 1. Plugin foundation (Phase 1 — scaffold, DB schema, admin framework)

- ✅ Plugin bootstrap, naming convention (`IPN` menu label, `ipn_` table prefix, `imanaworld-pickup-network` slug) — [imanaworld-pickup-network.php](imanaworld-pickup-network.php)
- ✅ Hook loader / composition root — [classes/class-ipn-loader.php](classes/class-ipn-loader.php), [classes/class-ipn-init.php](classes/class-ipn-init.php)
- ✅ Full DB schema, 8 tables, versioned self-upgrade — [classes/class-ipn-install.php](classes/class-ipn-install.php)
- ✅ Activation/deactivation (creates tables, registers role, default options; deactivate keeps data) — [classes/class-ipn-activator.php](classes/class-ipn-activator.php), [classes/class-ipn-deactivator.php](classes/class-ipn-deactivator.php)
- ✅ Uninstall path (opt-in data deletion via `ipn_delete_data_on_uninstall`) — [uninstall.php](uninstall.php)
- ✅ Custom `IPN Branch Staff` WordPress role — [classes/class-ipn-roles.php](classes/class-ipn-roles.php)
- ✅ WooCommerce/Dokan active-plugin dependency check with admin notice
- ✅ Admin menu framework — 12 submenus registered and rendering — [classes/class-ipn-admin.php](classes/class-ipn-admin.php)

---

## 2. Core shopping experience

### #1 — Store-first branch selector
- ✅ Branch selector UI shown before catalogue, matches approved mockup — [templates/storefront/branch-selector.php](templates/storefront/branch-selector.php)
- ✅ Session-based branch persistence through browsing/checkout — `IPN_Storefront::SESSION_KEY`, [classes/class-ipn-storefront.php](classes/class-ipn-storefront.php)
- ✅ Branch switcher available anywhere via the persistent indicator bar — [templates/storefront/branch-indicator-bar.php](templates/storefront/branch-indicator-bar.php)
- ✅ Cart clears on branch switch
- 🟡 **Catalogue not actually filtered by branch stock yet** — `filter_products_by_branch()` is a documented no-op; `filter_product_availability()` (badge/"unavailable" state on product cards) is also a documented no-op. Shoppers currently see the full catalogue regardless of branch.

### #2 — Per-branch stock layer
- ✅ Custom `ipn_branch_stock` table (product_id, branch_id, total_stock, reserved_stock) — [classes/class-ipn-install.php](classes/class-ipn-install.php)
- ✅ CRUD + reserve/release/deduct-sold logic, atomic (no oversell race) — [classes/class-ipn-branch-stock.php](classes/class-ipn-branch-stock.php)
- ✅ Admin stock overview (read, real data, search/filter) — [templates/admin/stock.php](templates/admin/stock.php)
- ✅ Branch staff stock view (read, real data, scoped to their branch) — [templates/staff/stock.php](templates/staff/stock.php)
- 🟡 **No write UI** — nothing in the admin or staff screens actually calls `set_total()`/manual adjustment yet ("Adjust" button is a toast stub)
- 🟡 **WooCommerce native stock is not actually bypassed** — the spec calls for IPN stock to replace WC's own stock display/validation for IPN-enabled products; that hook-level override doesn't exist yet

### #3 — CSV/Excel catalogue import
- ✅ `ipn_import_log` / `ipn_import_log_rows` tables + admin import-history view — [templates/admin/import.php](templates/admin/import.php)
- ✅ Upload form UI (dropzone, matches mockup)
- ⬜ **Actual parsing/import logic** — `IPN_CSV_Import::process_file()` returns `WP_Error( 'ipn_not_implemented' )`. Nothing creates/updates a single product yet. Blocked on the real Choppies catalogue file (see §0).
- ⬜ IPN Import Template (downloadable .xlsx) — not created
- ⬜ CSV/Excel import specification document — not written

### #11/#12 — Stock reservation on payment / auto-release
- ✅ The mechanics exist and are correct: `IPN_Branch_Stock::reserve()`, `::release()`, `::deduct_sold()` — atomic, audit-logged
- 🚫 **Not triggered by anything** — no hook on `woocommerce_order_status_changed` (or equivalent) calls `reserve()` on payment, `release()` on cancel, or `deduct_sold()` on collection. This is the single biggest missing wire in the whole plugin — almost every other "done" piece (checkout fields, staff dashboard, notifications, tracker) is waiting on order status logic that doesn't exist yet.

---

## 3. Order collection workflow

### #4/#5 — Branch staff roles, filtered dashboard, order routing
- ✅ `IPN Branch Staff` role, no wp-admin access, `_ipn_branch_id` user-meta assignment mechanism — [classes/class-ipn-roles.php](classes/class-ipn-roles.php)
- ✅ Front-end dashboard shortcode `[ipn_staff_dashboard]`, 4 screens (login/queue/detail/stock) routed server-side via `?ipn_screen=` — [classes/class-ipn-staff-dashboard.php](classes/class-ipn-staff-dashboard.php)
- ✅ Mobile-responsive UI matching the approved mockup
- 🟡 **No admin UI to assign a staff account to a branch** — `IPN_Roles::set_branch_id()` exists but is never called from anywhere; branch assignment today is DB-only
- ⬜ **Order routing doesn't exist** — no order meta links a WooCommerce/Dokan order to a branch. `get_branch_orders()` and `get_order_detail()` are documented stubs that always return empty/null, so the order queue and order detail screens are permanently in their honest empty state
- ⬜ Status controls (Accept / Preparing / Ready for Collection) — UI buttons render in `order-detail.php` but are `disabled` placeholders; no order-status class exists to call

### #6/#9 — Standard Collection + Email OTP verification
- ✅ OTP generate/verify, hashed storage, expiry, failed-attempt counting, audit-logged — [classes/class-ipn-otp.php](classes/class-ipn-otp.php)
- ✅ Staff dashboard OTP verification form is **really wired** — nonce-checked, calls `IPN_OTP::verify()` for real, shows success/failure — [templates/staff/order-detail.php](templates/staff/order-detail.php)
- 🟡 Nothing calls `IPN_OTP::generate()` on order placement yet (depends on order-status wiring above)
- 🟡 Verifying an OTP doesn't flip the order to "Collected" — there's no order-status write-back yet

### #7 — Express Collection + surcharge
- ✅ Checkout UI renders Standard/Express choice with the branch's real surcharge/prep-time — [templates/storefront/checkout-fields.php](templates/storefront/checkout-fields.php), `IPN_Checkout::render_collection_fields()`
- ⬜ Selecting Express doesn't validate or persist anything — `validate_collection_fields()` and `save_collection_fields()` are empty stubs
- ⬜ Express surcharge is not actually added to the order total

### #8 — Nominated recipient
- ✅ Checkout UI renders the optional recipient fields (name, phone, ID) with a show/hide toggle — [assets/js/storefront.js](assets/js/storefront.js)
- ✅ Staff order-detail template has a recipient card ready to render real data
- ⬜ Nothing persists the recipient fields to order meta (same stub as above)

### #13/#14 — Uncollected orders workflow + auto-cancel/refund
- ✅ Per-branch config fields exist and are readable: `otp_expiry_hours`, `collection_window_days`, `reminder_after_hours` — [classes/class-ipn-install.php](classes/class-ipn-install.php)
- ✅ Hourly WP-Cron event is scheduled — [classes/class-ipn-uncollected-workflow.php](classes/class-ipn-uncollected-workflow.php)
- ⬜ **The cron does nothing** — `run_daily_check()` only fires an empty `do_action` hook. No reminder logic, no expiry check, no auto-cancel, no stock release, no refund initiation, no admin daily-digest email. (Depends on order-status wiring.)
- 🟡 Admin "Daily Digest" screen exists in the UI — [templates/admin/digest.php](templates/admin/digest.php) — but shows an honest empty state since there's no data to show

---

## 4. Customer experience

### #10 — Email notifications
- ✅ All 6 templates fully designed and content-complete, matching the approved mockup, with real order/branch/OTP variables and inline styles for email-client compatibility — [templates/emails/](templates/emails/)
- ✅ Shared header/footer/helper partials — [templates/emails/partials/](templates/emails/partials/)
- ⬜ **Nothing sends them** — `IPN_Notifications::send_order_placed()` etc. are all empty method bodies; the `ipn_order_placed` etc. action hooks they listen for are never fired anywhere in the codebase. No emails go out today even in a test environment.

### #15 — Customer live order tracking in My Account
- ✅ Timeline UI matching the mockup, hooked onto `woocommerce_order_details_after_order_table` — [templates/my-account/order-tracker.php](templates/my-account/order-tracker.php)
- 🟡 Renders as an inert/neutral timeline — no stage is ever marked current/done, since there's no IPN order-meta to read a real stage from
- ⬜ OTP display when Ready, branch address, nominated-recipient display — all depend on order-meta that doesn't exist yet

---

## 5. Staff and branch operations

### #17 — Branch enable/disable
- ✅ `status` + `disabled_reason` columns, `IPN_Branch::set_status()` real backend method
- ✅ Admin Branches table reads and displays real status
- 🟡 **Add/Edit modal doesn't save** — the modal UI matches the mockup and pre-fills correctly on Edit, but "Save branch" only shows a toast; `IPN_Branch::create()`/`update()` are correct and tested-by-syntax but never actually called from the admin UI

### #18 — Configurable operating hours
- ✅ `ipn_branch_hours` / `ipn_branch_closures` tables, `IPN_Branch::get_hours()` / `is_open_now()` real logic
- ✅ Customer branch cards show an open/closed pill (informational)
- ⬜ **No admin UI to actually set hours per branch/day** — the branch modal has no hours editor, so every branch is effectively unconfigured
- ⬜ Checkout doesn't block selecting a branch outside operating hours
- ⬜ One-off closure dates (public holidays) have a table but no admin UI

---

## 6. Compliance and reporting

### #16 — Audit trail
- ✅ Central `IPN_Audit_Log::log()` write path, real table, real query/filter UI in admin — [classes/class-ipn-audit-log.php](classes/class-ipn-audit-log.php), [templates/admin/audit-log.php](templates/admin/audit-log.php)
- ✅ Actually logs the events that exist today: branch created/updated, stock reserved/released/sold, OTP generated/verify success/fail
- ⬜ Doesn't yet log the events that don't exist yet: order accepted/preparing/ready, collection completed, cancellation, refund initiation (all depend on order-status wiring)
- 🟡 CSV export button is a toast stub, not implemented

### #19 — IPN operational reporting dashboard
- ✅ All 8 report panels laid out and styled to match the mockup — [templates/admin/reports.php](templates/admin/reports.php)
- ⬜ **Every query is a stub** — all 8 methods on `IPN_Reports` (`orders_by_branch`, `collection_success_rate`, `uncollected_orders`, `average_preparation_time`, `collection_turnaround_time`, `product_performance_by_branch`, `branch_sales_performance`, `express_vs_standard_split`) return `array()`. Every panel renders its honest empty state.

---

## 7. Returns / disputes

### #20 — Returns workflow (IPN ↔ Dokan)
- 🟡 Admin "Disputes & Returns" screen exists as a placeholder — [templates/admin/disputes.php](templates/admin/disputes.php)
- ⬜ No "Reject Collection" action anywhere yet (would live in the staff order-detail screen — currently a disabled placeholder button)
- ⬜ No Disputed order status / admin-notification flow
- ⬜ Returns policy page for the Choppies vendor profile — not created

---

## 8. Legal / IP

### #21 — Full IP transfer on final payment
- ⏳ Contractual clause, already drafted in the scope proposal (Section 5) — nothing for the codebase to do; triggers on final payment, not on a build milestone

---

## 9. Documentation & knowledge transfer package (Section 6 — 12 documents + KT session)

None of these exist yet. All ⬜:

- ⬜ System Architecture Document
- ⬜ Database Schema & Data Model doc (the schema itself is done and readable in [classes/class-ipn-install.php](classes/class-ipn-install.php), but there's no narrative doc)
- ⬜ Installation & Deployment Guide
- ⬜ Configuration & Administration Guide
- ⬜ Plugin Settings Reference
- ⬜ Administrator User Manual
- ⬜ Branch Staff User Manual
- ⬜ CSV/Excel Import Specification
- ⬜ IPN Import Template (.xlsx)
- ⬜ Returns Workflow Document
- ⬜ API & Integration Documentation (Phase 2 placeholder structure)
- ⬜ Source Code Repository Handover write-up
- ⬜ Knowledge Transfer session (2–3hr recorded call) — post-build, not a dev task

---

## 10. Out-of-scope items (Phase 2) — confirmed correctly excluded

Nothing below has been built, and nothing below should be built under the current scope:

- 🚫 Choppies iRetail API integration
- 🚫 Real-time stock sync from iRetail POS
- 🚫 WhatsApp Business API notifications
- 🚫 QR code scanning at collection counter
- 🚫 SMS gateway for notifications *(see the discrepancy note in §0 — `IPN_Project_Context.md` describes SMS as in-scope; the signed-scope PDF marks it Phase 2)*
- 🚫 Customer-facing store locator / map page
- 🚫 Courier/delivery fulfilment integration
- 🚫 Additional Click & Collect partner onboarding (Spar, BotswanaPost, Pick n Pay, Woolworths)

---

## What to build next, in dependency order

Almost everything still open depends on one missing piece: **order status → IPN state wiring.** Suggested build order:

1. **Order-meta model + status hooks** — decide the meta keys (`_ipn_branch_id`, `_ipn_collection_type`, `_ipn_status`, nominated-recipient fields), hook `woocommerce_order_status_changed` to call `IPN_Branch_Stock::reserve()`/`release()`/`deduct_sold()` and `IPN_OTP::generate()`. This one piece unblocks: checkout persistence (#7/#8), staff order queue/detail (#4/#5), My Account tracker (#15), audit trail completeness (#16), and is a prerequisite for the uncollected-orders cron (#13/#14).
2. **Fire the notification hooks** (`ipn_order_placed` etc.) from the status hooks above, and fill in `IPN_Notifications::send_*()` — the templates are ready, they just need to be called.
3. **Uncollected-orders cron logic** (#13/#14) — now has real order data to walk.
4. **CSV import** (#3) — blocked on the client providing a sample file (§0); build the parser once it lands.
5. **Branch admin CRUD wiring** (#17) and **operating-hours editor** (#18) — the backend is ready, this is "wire the Save button to the method that already exists."
6. **Reporting queries** (#19) — once real order data exists, these become straightforward `wpdb` queries.
7. **Disputes/returns flow** (#20) — needs the order-status model too (adds a "Disputed" status + Reject Collection action).
8. **Documentation package** (Section 9) — best written once the above stabilizes, so it describes the real system rather than needing a rewrite.
