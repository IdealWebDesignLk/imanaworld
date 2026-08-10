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

Last verified: 2026-08-10 (post order-status-wiring build).

---

## 0. Business / contract prerequisites (Section 9 of the scope doc)

None of these are code tasks — flagging them because several dev tasks below are blocked on them.

- ⏳ Signed scope agreement + 30% kickoff payment
- ⏳ Pilot store list from Choppies (branch names, addresses, GPS, contact emails) — needed to seed real `ipn_branches` rows
- ⏳ ~~Sample CSV/Excel catalogue file from Choppies~~ — resolved differently (2026-08-10): rather than wait, we built the importer against our own defined format (`IPN_CSV_Import_Specification.md`) and hand that to Choppies so *they* prepare their file to match it. Still pending: Choppies actually sending a real file in that format, so it can be test-imported end to end before pilot launch.
- ⏳ Express Collection surcharge amount confirmed for pilot
- ⏳ Returns/refunds SLA agreed between IMANAWORLD and Choppies

**Note on source-document discrepancy:** `Imana Doc.pdf` (the signed-scope PDF, "Final Revised Scope") quotes 360–380 estimated hours and a 15-week timeline with no dollar figure, vs. `IPN_Project_Context.md`'s USD 7,500–8,500 / 9–12 weeks — still unreconciled, worth resolving before quoting either figure externally. **The SMS half of this discrepancy is resolved as of 2026-08-10**: confirmed directly by Rajika — no SMS at all, OTP and all notifications are email-only (see `IPN_Project_Context.md`'s "Confirmed Build Decisions"). Two more decisions were locked in the same pass: refunds are always manual review (no auto-refund), and branch operating hours are advisory (checkout warns but doesn't block a closed branch).

---

## 1. Plugin foundation (Phase 1 — scaffold, DB schema, admin framework)

- ✅ Plugin bootstrap, naming convention (`IPN` menu label, `ipn_` table prefix, `imanaworld-pickup-network` slug) — [imanaworld-pickup-network.php](imanaworld-pickup-network.php)
- ✅ Hook loader / composition root — [classes/class-ipn-loader.php](classes/class-ipn-loader.php), [classes/class-ipn-init.php](classes/class-ipn-init.php)
- ✅ Full DB schema, 9 tables (added `ipn_order_meta` in this pass), versioned self-upgrade — [classes/class-ipn-install.php](classes/class-ipn-install.php)
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
- ✅ **Catalogue now filtered by branch stock** (2026-08-10) — `filter_products_by_branch()` hooks `woocommerce_product_query` and adds the branch's vendor's out-of-stock-at-this-branch products to `post__not_in`. Deliberately additive/exclude-only rather than forcing `author`/`post__in` on the query, so it can never hijack an unrelated query (another vendor's store page, search, related products) into showing only Choppies — see the code comment for the reasoning. Products a vendor never imported into the per-branch stock model at all are left untouched.
- ✅ `filter_product_availability()` now shows the real per-branch stock count ("N in stock at this branch" / "Unavailable at this branch") via WooCommerce's own `woocommerce_get_availability` filter, scoped to the same vendor+has-a-stock-row check.

### #2 — Per-branch stock layer
- ✅ Custom `ipn_branch_stock` table (product_id, branch_id, total_stock, reserved_stock) — [classes/class-ipn-install.php](classes/class-ipn-install.php)
- ✅ CRUD + reserve/release/deduct-sold logic, atomic (no oversell race) — [classes/class-ipn-branch-stock.php](classes/class-ipn-branch-stock.php)
- ✅ Admin stock overview (read, real data, search/filter) — [templates/admin/stock.php](templates/admin/stock.php)
- ✅ Branch staff stock view (read, real data, scoped to their branch) — [templates/staff/stock.php](templates/staff/stock.php)
- ✅ **Write UI now real** (2026-08-10) — admin Stock screen's "Adjust" opens a real modal (product/branch read-only, editable total) that calls `IPN_Branch_Stock::set_total()`; the staff Stock screen's per-row inputs are enabled and wired the same way, scoped to their own branch.
- ✅ **WooCommerce native stock is now bypassed for IPN-tracked products** (2026-08-10) — catalogue visibility, the availability label, and add-to-cart quantity validation (new `woocommerce_add_to_cart_validation` guard, closing a gap `claude.md`'s hooks list always called for but nothing wired) all read `ipn_branch_stock` instead of WC's native `_stock`. The atomic oversell guard remains `IPN_Branch_Stock::reserve()` at payment time — the new cart-level check is a UX nicety on top of it, not a replacement.

### #3 — CSV/Excel catalogue import
- ✅ `ipn_import_log` / `ipn_import_log_rows` tables + admin import-history view — [templates/admin/import.php](templates/admin/import.php)
- ✅ Upload form UI (dropzone, matches mockup) — now a real POST handler, not a toast stub
- ✅ **Real parsing/import logic**, built against IPN's own defined format rather than waiting on Choppies' file (confirmed decision, 2026-08-10) — [classes/class-ipn-csv-import.php](classes/class-ipn-csv-import.php). CSV via native `fgetcsv`; XLSX via a minimal hand-rolled reader (`ZipArchive` + `SimpleXML`, first sheet, shared/inline strings, cached cell values) — no PhpSpreadsheet/Composer dependency. Creates/updates products by SKU, assigns new products to the branch's Dokan vendor, sets categories + sideloads an image on creation only, writes stock via `IPN_Branch_Stock::set_total()`. One row = one SKU × Branch stock line.
- ✅ Per-row failures are logged with a specific reason (missing field, unknown branch code, non-numeric price, etc.) and shown inline under each run in the admin log
- ✅ IPN Import Template — downloadable as `.csv` (not `.xlsx` as originally listed) via a "Download IPN import template" link/admin-post handler; same 9-column format documented below
- ✅ CSV/Excel import specification document — `IPN_CSV_Import_Specification.md` (source) and `.html` (open in a browser, then "Save As" / copy into Word or Google Docs — this machine has no Node/pandoc/LibreOffice installed, so a native `.docx` couldn't be generated directly; the `.html` is the practical stand-in for the confirmed Word/Google Docs delivery format), meant to be handed to Choppies so they prepare their real catalogue file against it rather than us reverse-engineering theirs
- ⚠️ **Not real yet, deliberately**: since IPN's per-branch stock table is the source of truth, newly created products are saved with WooCommerce's own stock management left off (`manage_stock = false`, `stock_status = instock`) rather than synced to a native WC quantity — consistent with the still-open gap in #2 below (WC's native stock display isn't bypassed at the storefront yet either)

### #11/#12 — Stock reservation on payment / auto-release
- ✅ The mechanics exist and are correct: `IPN_Branch_Stock::reserve()`, `::release()`, `::deduct_sold()` — atomic, audit-logged
- ✅ **Now wired** — [classes/class-ipn-order.php](classes/class-ipn-order.php) hooks `woocommerce_order_status_processing` (reserve + generate OTP + set collection window), `woocommerce_order_status_cancelled/refunded/failed` (release), and `woocommerce_order_status_completed` (deduct_sold). This was the single biggest missing wire in the whole plugin — checkout persistence, staff dashboard, and the tracker below all build on it now.

---

## 3. Order collection workflow

### #4/#5 — Branch staff roles, filtered dashboard, order routing
- ✅ `IPN Branch Staff` role, no wp-admin access, `_ipn_branch_id` user-meta assignment mechanism — [classes/class-ipn-roles.php](classes/class-ipn-roles.php)
- ✅ Front-end dashboard shortcode `[ipn_staff_dashboard]`, 4 screens (login/queue/detail/stock) routed server-side via `?ipn_screen=` — [classes/class-ipn-staff-dashboard.php](classes/class-ipn-staff-dashboard.php)
- ✅ Mobile-responsive UI matching the approved mockup
- ✅ **Admin UI to assign a staff account to a branch now exists** (2026-08-10) — a per-row branch dropdown on the Staff screen, auto-saving on change, wired to `IPN_Roles::set_branch_id()`
- ✅ **Order routing now real** — `ipn_order_meta.branch_id` (set at checkout) links a WooCommerce order to its branch; `get_branch_orders()` / `get_order_detail()` query it for real and are scoped so staff can't view another branch's order by guessing an ID — [classes/class-ipn-staff-dashboard.php](classes/class-ipn-staff-dashboard.php)
- ✅ Status controls (Accept / Preparing / Ready for Collection) — the sticky action button in `order-detail.php` now posts a nonce-checked form that calls `IPN_Order::advance()`, which transitions the underlying WC order status and fires the matching hook

### #6/#9 — Standard Collection + Email OTP verification
- ✅ OTP generate/verify, hashed storage, expiry, failed-attempt counting, audit-logged — [classes/class-ipn-otp.php](classes/class-ipn-otp.php)
- ✅ Staff dashboard OTP verification form is **really wired** — nonce-checked, calls `IPN_OTP::verify()` for real, shows success/failure — [templates/staff/order-detail.php](templates/staff/order-detail.php)
- ✅ `IPN_OTP::generate()` is now called from `IPN_Order::on_ready()` — confirmed decision (2026-08-10): the code is issued once, when there's actually something to collect, not at payment
- ✅ Verifying an OTP now flips the order to Completed ("Collected") via `IPN_Order::advance( $order_id, 'ipn-ready', 'completed' )`, which deducts stock and stamps `collected_at`/`collected_by`
- ✅ Resend capability: `IPN_Notifications::resend()` rotates the code (old one marked `superseded`, stops verifying) and re-emails it — callable from a button on the staff order-detail screen or a "Resend IPN collection code" WooCommerce order action in wp-admin
- ⚠️ **Deliberate deviation from `claude.md`'s schema**: the OTP code itself stays only in `ipn_otp_codes` (hashed). `claude.md` proposed storing `otp_code`/`otp_expires_at` in `ipn_order_meta` too — that would mean keeping a second, plaintext copy of a one-time secret, which is a regression from the hash-only design already in place. Not implemented as spec'd; resolved instead by emailing once and offering a manual resend (see above) rather than ever redisplaying a stored code.

### #7 — Express Collection + surcharge
- ✅ Checkout UI renders Standard/Express choice with the branch's real surcharge/prep-time — [templates/storefront/checkout-fields.php](templates/storefront/checkout-fields.php), `IPN_Checkout::render_collection_fields()`
- ✅ Selecting Express now validates (branch required, recipient name+phone required if the toggle is on) and persists `collection_type` to `ipn_order_meta` — `IPN_Checkout::validate_collection_fields()` / `::save_collection_fields()`
- ✅ **Express surcharge now hits the order total** — `IPN_Checkout::add_express_surcharge()` adds the branch's flat `express_surcharge` as a cart fee on `woocommerce_cart_calculate_fees`; `storefront.js` triggers WooCommerce's `update_checkout` AJAX refresh when the customer switches the radio, so the total updates live before they place the order. Confirmed decision (2026-08-10): flat fee per branch, not a percentage.
- ✅ Staff dashboard order-detail still *displays* the branch's surcharge amount for express orders as a heads-up to staff.

### #8 — Nominated recipient
- ✅ Checkout UI renders the optional recipient fields (name, phone, ID) with a show/hide toggle — [assets/js/storefront.js](assets/js/storefront.js)
- ✅ Staff order-detail template has a recipient card ready to render real data
- ✅ Recipient fields now persist to `ipn_order_meta` and render for real in the staff order-detail screen and the My Account tracker

### #13/#14 — Uncollected orders workflow + auto-cancel/refund
- ✅ Per-branch config fields exist and are readable: `otp_expiry_hours`, `collection_window_days`, `reminder_after_hours` — [classes/class-ipn-install.php](classes/class-ipn-install.php)
- ✅ Hourly WP-Cron event is scheduled — [classes/class-ipn-uncollected-workflow.php](classes/class-ipn-uncollected-workflow.php)
- ✅ `ipn_order_meta.collection_window_expires` and the new `ready_at` column are set when the order is marked Ready for Collection — matches the spec's own stage order (Ready → 48h reminder → window expires), corrected from an earlier pass that counted it from payment instead
- ✅ **Cron logic implemented** — [classes/class-ipn-uncollected-workflow.php](classes/class-ipn-uncollected-workflow.php)'s hourly `run_daily_check()` now: sends the reminder (`ipn_order_collection_reminder`, which rotates+emails a fresh OTP) once a branch's `reminder_after_hours` has elapsed past `ready_at`; auto-transitions orders past `collection_window_expires` to `wc-ipn-expired`, which releases stock via `IPN_Order::on_expired()` and fires `ipn_order_cancelled` (sends the cancellation email)
- ✅ Refund is **never automatic** (confirmed decision) — expired/disputed orders just get flagged; an admin processes the actual refund through WooCommerce's normal refund tools
- ✅ Admin "Daily Digest" screen now shows real data — [templates/admin/digest.php](templates/admin/digest.php) queries `collection_expired` audit events from the last 24h via `IPN_Admin::get_expired_orders_digest()`; the "Refunds auto-issued" stat was removed (there's no such thing) in favor of "Refunds needing review"
- ✅ **Digest email now sends** (2026-08-10) — a daily WP-Cron event (`IPN_Uncollected_Workflow::DIGEST_CRON_HOOK`, first run next 6am then daily) fires `IPN_Notifications::send_daily_digest()`, which silently skips sending when nothing expired rather than emailing an empty digest every day. "Preview digest email" is real too — opens the exact same content in a new tab without actually sending.

---

## 4. Customer experience

### #10 — Email notifications
- ✅ All 6 templates fully designed and content-complete, matching the approved mockup, with real order/branch/OTP variables and inline styles for email-client compatibility — [templates/emails/](templates/emails/)
- ✅ Shared header/footer/helper partials — [templates/emails/partials/](templates/emails/partials/)
- ✅ **Now wired for real** — `IPN_Notifications` sends all 6 via `wp_mail()` off the `ipn_order_*` hooks `IPN_Order` actually fires — [classes/class-ipn-notifications.php](classes/class-ipn-notifications.php)
- ⚠️ **Timing deviation from the original spec, confirmed by Rajika**: the collection code is emailed once, at Ready for Collection — not also at order-placed as the original template draft showed (that OTP box was removed from `order-placed.php`). It's never redisplayed afterward (48h reminder, My Account) since `IPN_OTP` is hash-only. Branch staff have a "resend" button on the order-detail screen, and shop managers get a "Resend IPN collection code" WooCommerce order action in wp-admin — both rotate the code (old one stops verifying) and re-email it.

### #15 — Customer live order tracking in My Account
- ✅ Timeline UI matching the mockup, hooked onto `woocommerce_order_details_after_order_table` — [templates/my-account/order-tracker.php](templates/my-account/order-tracker.php)
- ✅ Stages now mark real current/done state from `ipn_order_meta` + the order's WC status; a distinct notice replaces the timeline for Disputed/Expired/Cancelled orders
- ✅ Branch name/address and nominated-recipient display are wired to real data
- ⚠️ **OTP is intentionally not redisplayed here** — same reasoning as #6/#9 above: it's only ever stored hashed, so there's no plaintext copy to show once it's been emailed. The "Ready" stage points the customer at their email/SMS instead. If the client needs an always-visible code in My Account, that's a resend/reveal flow to design, not a stored-plaintext fix.

---

## 5. Staff and branch operations

### #17 — Branch enable/disable
- ✅ `status` + `disabled_reason` columns, `IPN_Branch::set_status()` real backend method
- ✅ Admin Branches table reads and displays real status
- ✅ **Add/Edit modal now saves for real** (2026-08-10) — a genuine form POST (nonce-checked, handled in `IPN_Admin::maybe_handle_branch_save()`) creates or updates via `IPN_Branch::create()`/`update()`. Added a required Vendor dropdown (Dokan sellers) since `vendor_id` is NOT NULL in the schema and the CSV importer relies on it to assign new products — the mockup didn't have this field, added out of necessity.

### #18 — Configurable operating hours
- ✅ `ipn_branch_hours` / `ipn_branch_closures` tables, `IPN_Branch::get_hours()` / `is_open_now()` real logic
- ✅ Customer branch cards show an open/closed pill (informational)
- ✅ **Admin UI to set weekly hours now exists** (2026-08-10) — a 7-day editor (Closed checkbox + open/close time per day) inside the same branch modal, saved via the new `IPN_Branch::set_hours()` (replaces the full week in one call). New branches default to 08:00–19:00 daily rather than opening fully unconfigured.
- ✅ Checkout shows a warning when the selected branch is currently closed — confirmed decision (2026-08-10): advisory only, doesn't block checkout (deviates from the original scope line, which called for blocking)
- ✅ **One-off closure dates now have an admin UI** (2026-08-10) — a separate "Closures" modal per branch (kept out of the branch edit form so per-closure delete links don't end up as forms nested inside another form), add via a real POST form, delete via a GET+nonce link matching WP's own list-table row-action convention

---

## 6. Compliance and reporting

### #16 — Audit trail
- ✅ Central `IPN_Audit_Log::log()` write path, real table, real query/filter UI in admin — [classes/class-ipn-audit-log.php](classes/class-ipn-audit-log.php), [templates/admin/audit-log.php](templates/admin/audit-log.php)
- ✅ Actually logs the events that exist today: branch created/updated, stock reserved/released/sold, OTP generated/verify success/fail
- ✅ Now also logs order accepted/preparing/ready, collection completed, and collection disputed (from `IPN_Order`'s status hooks) — real entries render in the staff order-detail audit card
- ✅ Also logs `stock_adjusted` (manual stock edits) and `collection_expired` (uncollected-orders cron); cancellation itself still isn't a distinct logged event (only its `stock_released` side effect is), and there's still no separate refund-initiation event since refunds are a manual WooCommerce action outside IPN's own flow
- ✅ **CSV export now works** (2026-08-10) — real `admin-post.php` handler exports the same 500-row query the screen renders

### #19 — IPN operational reporting dashboard
- ✅ All 8 report panels laid out and styled to match the mockup — [templates/admin/reports.php](templates/admin/reports.php)
- ✅ **All 8 queries now real** (2026-08-10) — [classes/class-ipn-reports.php](classes/class-ipn-reports.php). Orders are found via `wc_get_orders()` against the real `_ipn_branch_id` order meta (works on both HPOS and legacy post-based orders, no raw `wp_posts` joins); the two duration reports (`average_preparation_time`, `collection_turnaround_time`) pair up `order_accepted`/`order_ready`/`collection_completed` audit-log timestamps rather than needing new columns.
- ✅ Date-range (7/30/90 days) and branch filters are now real GET-driven selects (auto-submit on change), not disabled — matches the rest of the admin's server-rendered, no-SPA convention
- ✅ Two panels that had no UI at all before now exist: **Uncollected orders** (live list of orders still Ready) and **Express vs Standard split** (count + revenue per collection type)
- ✅ **CSV export now works** — one combined file covering all 8 reports for the currently filtered range/branch, via a real `admin-post.php` handler (was a toast stub)

---

## 7. Returns / disputes

### #20 — Returns workflow (IPN ↔ Dokan)
- ✅ **Admin "Disputes & Returns" and "Orders & Disputes" screens now real** (2026-08-10) — [templates/admin/disputes.php](templates/admin/disputes.php), [templates/admin/orders.php](templates/admin/orders.php), backed by `IPN_Admin::get_all_ipn_orders()` (same `wc_get_orders()` + `_ipn_branch_id` meta pattern `IPN_Reports` uses). Orders list has working search/status filter and a detail modal (items, recipient, audit trail) populated from real per-row data; Disputes queue is the same list filtered to `status === 'disputed'`, plus a direct "Refund" link into the WooCommerce order edit screen (refund itself stays a manual WooCommerce action, per the confirmed no-auto-refund decision).
- ✅ **Reject Collection now works** — staff order-detail's reject panel posts a reason and moves the order to the new `wc-ipn-disputed` status via `IPN_Order::advance()`; reason is stored on `ipn_order_meta.dispute_reason` and shown to staff, admin (Disputes queue + order modal), and (as a generic notice) the customer's My Account tracker
- ✅ `wc-ipn-disputed` order status registered and wired to an audit-log entry (`collection_disputed`)
- ✅ **Disputed-order admin alert now sends** (2026-08-10) — `IPN_Order::on_disputed()` fires `ipn_order_disputed`, which `IPN_Notifications::send_dispute_alert()` emails to the site admin (plain text, internal ops alert — not one of the branded customer templates) with the order, branch, customer, reason, and a direct link to refund it in WooCommerce
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
- 🚫 SMS gateway for notifications *(resolved 2026-08-10 — confirmed out of scope entirely, not deferred to Phase 2. Email is the only notification channel.)*
- 🚫 Customer-facing store locator / map page
- 🚫 Courier/delivery fulfilment integration
- 🚫 Additional Click & Collect partner onboarding (Spar, BotswanaPost, Pick n Pay, Woolworths)

---

## What to build next, in dependency order

**Order status → IPN state wiring (item 1 below) is now done** — [classes/class-ipn-order.php](classes/class-ipn-order.php), added 2026-08-10. It hooks WooCommerce order-status transitions to stock reserve/release/deduct, OTP generation, the `wc-ipn-accepted/preparing/ready/disputed/expired` custom statuses, and fires the `ipn_order_*` action hooks. Checkout persistence, the staff dashboard queue/detail, Accept/Preparing/Ready/Reject actions, OTP-triggered completion, and the My Account tracker are now wired against real data. Remaining build order:

1. ~~Order-meta model + status hooks~~ — ✅ done.
2. ~~Fire the notification hooks and fill in `IPN_Notifications::send_*()`~~ — ✅ done (2026-08-10). All 6 emails send for real; OTP is emailed once at Ready for Collection with a staff/admin resend path.
3. ~~Uncollected-orders cron logic~~ — ✅ done (2026-08-10).
4. ~~CSV import~~ — ✅ done (2026-08-10), built against our own spec rather than waiting on a sample file. Real test import against Choppies' actual file still pending once they send one.
5. ~~Express surcharge on the cart total~~ — ✅ done (2026-08-10).
6. ~~Branch admin CRUD wiring and weekly operating-hours editor~~ — ✅ done (2026-08-10). One-off closure dates still not covered — small follow-up whenever picked up.
7. ~~Reporting queries~~ — ✅ done (2026-08-10). All 8 real, filters wired, CSV export works.
8. ~~Admin "Disputes & Returns" and "Orders & Disputes" screens~~ — ✅ done (2026-08-10).
9. ~~WooCommerce native stock bypass at the storefront~~ — ✅ done (2026-08-10). This was the last piece of the original core shopping experience (Section A of the scope doc) still unwired — every numbered item under "Core shopping experience" is now real except the admin stock "Adjust" write UI (item #2 above) and the CSV import's Excel-vs-CSV nuance already noted.
10. ~~Smaller loose ends~~ — ✅ done (2026-08-10): admin+staff stock "Adjust" write UI, staff-to-branch assignment UI, audit-log CSV export, disputed-order admin email alert, one-off branch closure dates UI, and the daily-digest email (+ real preview). All were backend-ready, UI-only gaps.
11. **Documentation package** (Section 9) — explicitly held back on Rajika's instruction (2026-08-10): do not start until told to, planned for the very end of the project. Not a dev gap — a deliberate sequencing call.
12. **Known remaining gaps, not blocking anything**: cancellation itself isn't a distinct audit-log event (only its stock-release side effect is logged); no separate refund-initiation event (refunds are a manual WooCommerce action outside IPN's flow); Returns policy page for the Choppies vendor profile not created; the PDF-vs-context-doc pricing/timeline discrepancy noted in §0 is still unreconciled with the client.

**With documentation excluded, every dev item in the confirmed v3.0 scope is done.** What's left is: (a) the two small gaps in item 12 above, (b) real end-to-end testing once Choppies' pilot store list and a real catalogue file arrive, and (c) the business-side items in §0 (kickoff payment, surcharge amount, returns SLA, the pricing discrepancy).
