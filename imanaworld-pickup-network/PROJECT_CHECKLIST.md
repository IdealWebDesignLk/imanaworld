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

Last verified: 2026-08-25 (v0.7.0 role-scoped dashboards; v0.6.0 batch fixing the five open GitHub issues — #7, #8, #12, #15, #16).

**v0.7.1 — vendor dashboard 404 (2026-08-26):**
- **Found by finally testing against the live install** (iwd-devserver.online), which is where the plugin actually runs — imanaworld.com does not have it installed at all. The "Click & Collect" nav item rendered correctly in Dokan's vendor dashboard, but `/dashboard/ipn/` returned a 404, so every one of the four tabs was unreachable. v0.7.0 shipped and was closed against issue #17 as working; it was not.
- **Cause:** Dokan turns each query var from `dokan_query_var_filter` into a rewrite endpoint, and WordPress only regenerates rewrite rules on plugin *activation*. An in-place update never fires the activation hook, so the endpoint was never added to the rules table. The symptom is deceptive — the nav item is added by a plain filter and appears immediately, which reads as "integration works" while the route does not exist.
- **Diagnosis, not a guess:** `/dashboard/settings/store/` rendered fine (Dokan's routing healthy), the nav item was present (our hooks firing), and `/dashboard/?ipn=1` fell through to the Overview (query var unknown to WP). Only the rules table could explain all three.
- **Fix:** `IPN_Vendor_Dashboard::maybe_flush_rewrites()` on `init` priority 999 — after Dokan registers its own rules — flushing once per plugin version and stamping `ipn_rewrite_version`. Soft flush, since endpoints live in the `rewrite_rules` option and there is no reason to touch .htaccess. The activator stamps the same marker so a fresh install does not flush twice.
- **Lesson worth keeping:** static verification (`php -l`) says nothing about whether a WordPress feature is reachable. Anything that registers a rewrite endpoint, a role, a cron event, or a DB table needs the update path tested, not just the code path.

**v0.7.0 — three-role scoping: admin, vendor, branch staff (2026-08-25):**
- **Authorization spine (`IPN_Access`)**: one place answers "may this user touch this branch?", and every vendor/staff read and write goes through it. Admin sees everything; a vendor sees branches whose `vendor_id` is their own account; staff see the single branch in their `_ipn_branch_id` user meta. Guards run against the session, never against the `branch_id` in the request — both new dashboards are front-end forms where that field is one edit away from pointing at somebody else's branch.
- **"Make IPN Partner"** (`IPN_User_Profile`): a checkbox on the vendor's own user-edit screen writing `_ipn_is_partner` user meta. IPN → Partners now lists only flagged vendors, and only they are offered when picking a branch's partner. Chosen over a column on Dokan's vendor list so it does not depend on Dokan's markup. Unticking hides the vendor from the network but deliberately leaves their branches intact.
- **Branch → partner selection**: the branch form now renders "Selected Partner: …" above the dropdown (JS mirrors the `<select>`, kept in sync on change) and the dropdown itself is restricted to flagged partners. A branch whose vendor is no longer flagged still shows that vendor's name, resolved via `get_userdata()`, rather than appearing unassigned.
- **#15 partially reverted, deliberately**: the Add Vendor modal and the activate/deactivate toggle shipped in v0.6.0 are no longer rendered, at the client's request ("for now"). `IPN_Vendor::create()` / `set_selling_enabled()` are untouched, so restoring them is a template change. ⚠️ GitHub issue #15 was closed as completed against v0.6.0 and should be reopened or superseded so the history does not claim a live feature that is now hidden.
- **Vendor dashboard** (`IPN_Vendor_Dashboard`, `templates/vendor/`): a "Click & Collect" section inside Dokan's own vendor dashboard rather than a separate shortcode page — vendors already authenticate there, and Dokan owns the nav and theme. Integration is one query var (`dokan_query_var_filter`), one nav entry (`dokan_get_dashboard_nav`), and one render hook (`dokan_load_custom_template`); the Branches/Staff/Stock/Orders tabs inside are ours via `?ipn_tab=`, which avoids depending on Dokan's sub-navigation API. Covers branch CRUD incl. hours and collection settings, staff add/move/remove, per-branch stock add/update/remove, and orders filterable by branch. Orders are read-only here: advancing a status belongs to the branch physically handing the goods over, against the collection code.
- **Staff dashboard**: gained stock *add* and *remove* (it could previously only edit the total of an existing row) and an Hours screen for keeping its own branch's opening times current. The bottom tab bar was duplicated across templates and is now one partial (`templates/staff/partials/tabbar.php`).
- **Guarded deletes**: `IPN_Branch::delete()` refuses while the branch has orders that are not collected/cancelled/refunded/expired — those orders reference it for collection address, stock reservation, and OTP. `IPN_Branch_Stock::delete_row()` refuses while `reserved > 0`, which is stock a customer has already paid for. Deactivating remains the way to retire a branch.
- **Staff removal is not user deletion**: removing a staff member drops the `ipn_branch_staff` role and branch meta and leaves them a `customer` account, because orders and audit rows reference the user ID and a front-end form should not be able to delete a WordPress user.
- **Not done / carried forward**: none of this has been exercised against a running WordPress install — no test site in this environment — and Dokan's source is not available locally, so the three Dokan hook names above were written from their documented API and need confirming against the live install. Vendor "settings" was read as the IPN-side branch/collection settings; Dokan already owns the store profile, so that was deliberately left alone.

**v0.6.0 — open client issues and their follow-ups (2026-08-25):**
- **#16 (Orders & Disputes: blank Branch column, no new-order visibility, no branch filter)**: three separate problems. (a) *Blank Branch column* — the row only ever read `ipn_order_meta.branch_id`, so any order missing that row showed nothing; `IPN_Order::branch_id_for()` now falls back to the mirrored `_ipn_branch_id` order meta, and `IPN_Order::save_checkout_meta()` writes that mirror through the WooCommerce order CRUD instead of `update_post_meta()`, which is also what makes it work under HPOS. The list itself is now sourced from `ipn_order_meta` first (HPOS-safe) with the old `wc_get_orders()` meta query kept as a fallback. ⚠️ Root cause on the client's site is still unconfirmed — no live DB access from here — so this is belt-and-braces: whichever of the two sources has the branch, the column fills. (b) *New-order visibility* — added a Placed column (date + "20 mins ago"), clickable status counters above the table for the five statuses that still need action, and a left accent on `new` rows. (c) *Branch filter* — real server-side filter (`?branch_id=`, scoping the `ipn_order_meta` query), deliberately not client-side: filtering only the loaded 200 rows would silently hide a branch's older orders. Search and status filters stay client-side over the loaded set as before.
- **#7 follow-up (Stock overview doesn't scale)**: the flat product×branch table is replaced by one row per product, aggregated across branches, with an expandable per-branch drill-down. Search, branch scoping, ordering and paging (25/page) all happen in SQL via `IPN_Branch_Stock::query_products()`/`count_products()`; the drill-down rows for a whole page come from one `get_branch_breakdown()` query, so expanding costs nothing. Product titles come from the join rather than a `wc_get_product()` call per row. The branch staff dashboard's stock screen was doing the same unbounded load-everything-then-filter-in-PHP and is now paged the same way. Not done: no new DB index was added — the existing `UNIQUE (product_id, branch_id)` covers the `GROUP BY product_id`, but the title `LIKE '%…%'` search cannot use an index and will need revisiting (FULLTEXT, or searching by SKU) if the catalogue really reaches the scale the issue describes.
- **#8 follow-up (customer-facing branch availability + branch required before add-to-cart)**: added an "Available at" panel on the single product page (`templates/storefront/product-availability.php`, hooked at `woocommerce_single_product_summary` priority 25) listing every active branch stocking the product with its available units; each row doubles as a branch picker when none is selected. `IPN_Storefront::validate_branch_stock()` now blocks add-to-cart for an IPN-tracked product when no branch is selected, so the requirement surfaces at the product page rather than at checkout with a cart already built. Only products with at least one `ipn_branch_stock` row are affected — plain WooCommerce products are untouched.
- **#12 follow-up ("Kandy Mall is closed but still shows as Active")**: not a bug in the status field — lifecycle status (in the network / taken offline) and today's operating state (open right now) are different things and the Branches table only showed the first. Added `IPN_Branch::open_state()` (returns is_open plus the reason: closure date, closed today, outside hours, hours not set) and the Branches list now shows both, e.g. "Active" + "Closed now — Opens 08:00". Deliberately *not* changed: a closed branch stays selectable and orderable, per the confirmed decision that operating hours are advisory.
- **#15 (Partners screen has no vendor management)**: added `IPN_Vendor` — a thin wrapper over the Dokan vendor user and its `dokan_enable_selling` flag — plus, on the Partners screen, an "Add vendor" modal, and a per-row Approve / Activate / Deactivate control. Approving a pending signup and re-enabling a suspended vendor are the same write in Dokan; IPN keeps its own `ipn_vendor_disabled_at` marker only so the button can be labelled correctly. Creating a vendor sets no password: the account gets a random one and WordPress emails the vendor a link to set their own, and `dokan_new_seller_created` is fired so Dokan finishes its own vendor setup. The screen is now searched and paged — it previously rendered every `seller` user on the site.

**v0.5.3 — client-reported issues (2026-08-10):**
- **#13/#14 (critical errors on Orders & Disputes / Disputes & Returns)**: hardened defensively — loosened `IPN_Order::customer_name()`/`time_label()`'s strict `WC_Order` type hint to a duck-typed check, and wrapped both the `wc_get_orders()` call and each order's row-building in `IPN_Admin::get_all_ipn_orders()` in try/catch so one bad order (or an unexpected WC/HPOS query response) can't fatal the whole page. Applied without the actual PHP error text from the client's site, since there's no live-server access from here — real root cause unconfirmed; ask them to report back if the critical error persists after updating, ideally with the exact error text next time.
- **#3/#4 (modal overflow)**: the branch-hours editor and closures modals were tight enough to clip content in a 480px-wide modal. Widened the modal to 560px, made the hours editor wrap/scroll instead of clipping, fixed checkbox sizing, and stacked the closures date/note fields instead of squeezing them side by side.
- **#2 (vendor should be partner-level, not branch-level)**: Partners screen now shows real Dokan vendors (no more hardcoded "Choppies" row) with a "Manage branches" link that scopes the Branches screen to that vendor via `?vendor_id=`. The Branches "Add" modal now defaults its vendor dropdown to that scoped vendor (or the only vendor already in use, if there's just one) instead of a blank "select vendor" prompt — doesn't remove the dropdown outright (still needed to onboard a genuinely new partner), but the common case (another branch for an existing partner) no longer invites a mismatched pick.
- **#12 (no branch selector at checkout)**: real gap — the branch selector only ever rendered pre-shop-loop, so a customer landing on checkout without one selected (direct product link, old cart) hit a dead-end error notice with no way to act on it. `checkout-fields.php` now renders the same branch-picker grid inline when no branch is selected.
- **#11 (no branch/stock re-validation before payment)**: real gap — `IPN_Checkout::validate_collection_fields()` now also checks the selected branch is still `active`, and re-checks every IPN-tracked cart item against current branch stock (not just at add-to-cart time).
- **#6/#7/#8 (no way to manage stock for existing products / no branch-availability visibility)**: added a "Click & Collect Branch Stock" meta box to the WooCommerce product edit screen (wp-admin only, consistent with staff/vendors never getting wp-admin access) — shows total/reserved/available per branch for *any* product and lets an admin set it directly, which is the only way to bring a product created outside the CSV importer into the per-branch stock model. This also makes #9's existing catalogue filtering correctly apply to those products once stock rows exist. ⚠️ #8's screenshot suggests this might have meant the *customer-facing* product page rather than the admin edit screen — worth confirming with the client; the admin-side fix is valuable regardless but a storefront-facing "available at" indicator would be a separate, additional piece.
- **#10 (branch staff not notified of new orders)**: real gap — the reserve/release/deduct mechanics and customer notifications already existed, but nothing told branch staff a new order existed except opening the dashboard. Added `IPN_Notifications::send_branch_new_order_alert()`, firing off the existing `ipn_order_placed` hook, emailing the branch's own contact address (`ipn_branches.email`).
- **#9 (branch filtering)**: no code change — the existing `filter_products_by_branch()`/`filter_product_availability()`/`validate_branch_stock()` logic (built earlier) already does this correctly wherever a product has real `ipn_branch_stock` rows; the reported gap was really about products lacking those rows at all, which #6/#7's meta box now fixes.

**Bug fix (2026-08-10, reported as GitHub issue #1):** every admin screen with a popup (Branches add/edit + closures, Stock adjust, Orders & Disputes detail) rendered its modal as unstyled, permanently-visible plain HTML — "click Add branch and nothing happens" was actually the modal already being stuck open, unstyled. Root cause: the modal markup in all 4 templates sat as a sibling *after* the closing `</div>` of `.wrap.ipn-admin`, but every modal CSS rule in `admin.css` is scoped `.ipn-admin .modal-scrim` (descendant selector) — so the CSS never matched. Pre-existing bug from the original scaffold, not something introduced by this session's earlier work; just hadn't been clicked on a live site until now. Fixed by nesting the modal markup inside `.wrap.ipn-admin` in all 4 files. Shipped as v0.5.2.

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

## 0.5. Deployment infrastructure (not part of the client scope doc, added 2026-08-10)

- ✅ GitHub-based auto-updates via the bundled Plugin Update Checker library ([vendor/plugin-update-checker/](vendor/plugin-update-checker/), MIT licensed) — wired in [imanaworld-pickup-network.php](imanaworld-pickup-network.php) in "release assets" mode, since the plugin lives in a repo subdirectory rather than at the repo root.
- ✅ `.github/workflows/release.yml` builds a correctly-rooted plugin zip and publishes/updates a GitHub Release on every push to `main` that touches the plugin — this is what makes "push to main → update available in wp-admin" work with no manual step.
- ⏳ **Requires the `IdealWebDesignLk/imanaworld` repo to be public** (Rajika's call, in progress as of this note) — the updater has no GitHub token configured by default. If the repo goes back to private, set `IPN_GITHUB_TOKEN` in `wp-config.php` on the WordPress site (a GitHub personal access token with read access to the repo); never hardcode a token in plugin source.
- ⏳ Not yet verified end-to-end against a live WordPress install (no test site in this environment) — zip structure and version-extraction logic were verified locally, but the actual wp-admin "update available" flow hasn't been observed firsthand yet.

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
