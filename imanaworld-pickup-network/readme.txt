=== IMANAWORLD Pickup Network ===
Contributors: idealwebdesign
Tags: woocommerce, dokan, click-and-collect, multi-vendor
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.7.3
License: GPLv2 or later

Click & Collect fulfilment network for IMANAWORLD, built on WooCommerce and Dokan. Pilot partner: Choppies.

== Description ==

IMANAWORLD Pickup Network (IPN) adds a store-first Click & Collect experience on top of
WooCommerce/Dokan: a per-branch stock layer, branch staff order dashboards, Email OTP
collection verification, an uncollected-orders workflow, customer order tracking, a full
operational audit trail, and IPN-specific reporting. Each Click & Collect partner (starting
with Choppies) is a Dokan vendor with IPN mode enabled — onboarding future partners is
configuration only, no code changes.

See the full scope document for the complete requirements list.

== Requirements ==

* WordPress 6.0+
* WooCommerce
* Dokan (Lite or Pro)

== User roles ==

Three roles reach IPN, each with its own surface and its own slice of the data:

* **WordPress admin** (`manage_woocommerce`) — the "IPN" menu in wp-admin. Every
  partner, branch, and order. Also where a vendor is promoted to a Click &
  Collect partner: tick "Make IPN Partner" on their user profile.
* **Vendor** (Dokan seller, flagged as a partner) — a "Click & Collect" section
  inside Dokan's own vendor dashboard at /dashboard/. Their own branches, the
  staff running them, per-branch stock, and their orders. Never another
  vendor's.
* **IPN Branch Staff** — the `[ipn_staff_dashboard]` page below, locked to the
  single branch they are assigned to.

Scoping is enforced server-side on every read and write (see
classes/class-ipn-access.php), not by which options a screen happens to render.

== Shortcodes ==

The plugin registers exactly one shortcode. Everything else customers see is
attached to WooCommerce's own templates through hooks, so there is nothing to
place on a page by hand — see "Front-end surfaces" below.

`[ipn_staff_dashboard]`

    The branch staff order dashboard: today's order queue, an order detail
    screen with the collection-code (OTP) check, and that branch's stock.
    Takes no attributes.

    Put it on one page (e.g. /branch-staff/) and give branch staff that URL.
    Branch staff never get wp-admin access, so this page *is* their entire
    interface.

    Who sees what:
    * Logged out, or logged in as anyone other than an IPN Branch Staff user
      — a sign-in prompt. Nothing about any branch is exposed.
    * Logged in as IPN Branch Staff — the dashboard, scoped to the single
      branch that user is assigned to under IPN → Staff. There is no way to
      reach another branch's orders from here.
    * IPN Branch Staff with no branch assigned yet — a notice telling them
      to contact an admin.

    The dashboard drives itself with query parameters on whatever page holds
    the shortcode; you never need to write these by hand, but they are worth
    knowing when reading a support link a staff member has pasted you:

    * `?ipn_screen=queue`   Order queue (the default).
    * `?ipn_screen=detail&order_id=123`  One order, with the collection-code
      check and the Accept / Preparing / Ready / Reject actions.
    * `?ipn_screen=stock`   That branch's stock, with `stock_q` (search) and
      `stock_page` (pagination).

== Front-end surfaces ==

These are not shortcodes and need no page setup — they hook onto WooCommerce
templates and appear wherever those already render:

* Branch selector — before the shop loop, shown until a branch is chosen.
* Branch indicator bar ("Shopping at X · Change branch") — above every
  WooCommerce template once a branch is selected.
* Click & Collect availability panel — on the single product page, above the
  add-to-cart button, for products that are in the per-branch stock model.
* Collection type, nominated recipient, and (when no branch is selected yet)
  the branch picker — on the checkout page.
* Order tracker — under the order table in My Account → Orders.

The admin side lives under the "IPN" menu in wp-admin and needs the
`manage_woocommerce` capability.

== Database ==

All custom tables use the `ipn_` prefix (e.g. `wp_ipn_branches`, `wp_ipn_branch_stock`,
`wp_ipn_otp_codes`, `wp_ipn_audit_log`). See classes/class-ipn-install.php for the full schema.

== Updates ==

This plugin checks GitHub (IdealWebDesignLk/imanaworld) for updates using the
Plugin Update Checker library (bundled in vendor/plugin-update-checker/, MIT
licensed, (c) Janis Elsts). Pushing to main triggers a GitHub Action
(.github/workflows/release.yml) that builds a plugin zip and publishes it as
a GitHub Release — WordPress then offers that release as a normal plugin
update, the same as a wordpress.org-hosted plugin.

== Changelog ==

= 0.7.3 =
* Add: the branch staff dashboard page is now created by the plugin instead
  of being a manual setup step. Branch staff have no wp-admin access, so that
  page is their entire interface — shipping the dashboard without it left
  them with no way in. Created on activation and, for installs updating in
  place, on the next admin page load.
* Add: IPN -> Staff shows the staff sign-in URL, with a note that opening it
  as an administrator shows a sign-in prompt rather than the dashboard,
  because the gate is the IPN Branch Staff role.
* Note: if a page carrying the [ipn_staff_dashboard] shortcode already
  exists, it is adopted rather than duplicated, so a hand-made page keeps
  working. On uninstall the page is removed only under the existing
  "delete data" opt-in, and only if it carries nothing but the shortcode.

= 0.7.2 =
* Fix: Orders & Disputes listed ordinary WooCommerce orders as Click & Collect
  orders, every one of them with a blank Branch column. The blank was
  accurate — those orders have no branch — but they should never have been
  listed. Both lookups now require a real branch: the ipn_order_meta query
  demands branch_id > 0, and the mirrored order-meta query matches on a
  value greater than zero instead of on the key merely existing, which an
  earlier build had written as zero on branchless orders. An order that
  resolves to no branch is skipped outright.
* Note: on a site whose only orders predate branch selection, this correctly
  empties the Orders & Disputes list until a real Click & Collect order is
  placed. That is the accurate state, not a regression.

= 0.7.1 =
* Fix: the vendor dashboard's "Click & Collect" link returned a 404 after
  updating from an earlier version. The nav item is added by a filter and so
  appeared immediately, but the /dashboard/ipn/ route is a rewrite endpoint,
  and rewrite rules are only regenerated on activation — never on an
  in-place plugin update. Rules are now regenerated once per plugin version,
  so an update heals itself. Confirmed against a live install, where the nav
  item rendered but the route 404'd.

= 0.7.0 =
* Add: vendors now get a "Click & Collect" section inside Dokan's vendor
  dashboard — their branches (add/edit/delete, opening hours, collection
  settings), their staff (add/move/remove), per-branch stock
  (add/update/remove), and their orders, filterable by branch. Everything is
  restricted to that vendor's own branches.
* Add: a "Make IPN Partner" checkbox on a vendor's user profile. Only vendors
  flagged there appear on IPN → Partners or can be picked as a branch's
  partner — a marketplace has far more vendors than pickup partners.
* Add: the branch edit form now names the partner a branch belongs to
  ("Selected Partner: …") and lets it be reassigned.
* Add: branch staff can now add products to their branch and remove them, not
  only edit the total of a row that already existed, and can keep their own
  branch's opening hours up to date from a new Hours screen.
* Add: branches and per-branch stock rows can be deleted. Both are guarded — a
  branch with orders still in progress, or a product with units reserved
  against an unfulfilled order, is refused rather than silently orphaned.
* Change: the Add Vendor button and the activate/deactivate toggle added to
  IPN → Partners in 0.6.0 are no longer shown. The underlying methods remain,
  so they can be restored without a rebuild.
* Change: all role scoping now runs through one place (IPN_Access), so a
  vendor or staff member cannot reach another branch by editing a form field.

= 0.6.0 =
* Fix: the Branch column on Orders & Disputes was blank for every order —
  the branch is now resolved from either ipn_order_meta or the mirrored
  order meta, and that mirror is written through the WooCommerce order CRUD
  so it also works with HPOS (custom order tables) enabled.
* Add: Orders & Disputes now shows when each order was placed, counters for
  the statuses still needing action (New / Accepted / Preparing / Ready /
  Disputed), a highlight on new orders, and a real server-side branch
  filter that reaches a branch's whole order history.
* Change: Stock overview is now one row per product with a per-branch
  drill-down, searched, branch-filtered, and paged in SQL instead of
  loading every product-branch combination into the page. The branch staff
  dashboard's stock screen is paged the same way.
* Add: Partners screen can create a Dokan vendor account, approve a vendor
  pending approval, and activate/deactivate an existing one. No password is
  handled in the form — the vendor is emailed a link to set their own.
* Change: Partners screen is searched and paged rather than listing every
  vendor account on the site at once.
* Add: single product pages show which branches have the item and how many
  units each has left; picking a collection branch is now required before
  a Click & Collect item can be added to the cart, instead of the customer
  only finding out at checkout.
* Fix: the Branches admin list showed a branch as "Active" with no
  indication it was closed for the day — lifecycle status and today's
  operating state (with the reason: closure date, closed today, outside
  hours) are now shown separately.

= 0.5.3 =
* Fix: hardened the Orders & Disputes / Disputes & Returns admin screens
  against a critical error (defensive fix — exact root cause unconfirmed
  without a live error report).
* Fix: branch-hours and closures modals clipped their own content in a
  480px-wide modal; widened and made them wrap/scroll instead.
* Fix: checkout had no way to select a Click & Collect branch if you
  arrived without one already chosen — the branch picker now also renders
  inline at checkout.
* Fix: checkout now re-validates the selected branch is still active and
  re-checks cart items against current branch stock before payment.
* Add: "Click & Collect Branch Stock" meta box on the WooCommerce product
  edit screen — set per-branch stock for any product, not just ones
  brought in through the CSV importer.
* Add: branch staff now get an email when a new order lands in their
  queue, instead of only finding out by opening the dashboard.
* Change: Partners screen now lists real Dokan vendors instead of a
  hardcoded row; "Manage branches" scopes the Branches screen to that
  vendor and pre-fills it as the default when adding another branch.

= 0.5.2 =
* Fix: every admin screen with a popup (Branches add/edit + closures, Stock
  adjust, Orders & Disputes detail) rendered its modal as unstyled, always-
  visible plain HTML instead of a hidden overlay, because the modal markup
  sat outside the .ipn-admin wrapper that admin.css's modal rules are scoped
  to. Moved the modal markup inside that wrapper in all 4 templates.

= 0.5.1 =
* GitHub-based auto-updates: the plugin now checks IdealWebDesignLk/imanaworld
  for new releases and can update through the normal wp-admin Plugins screen.
  A GitHub Action builds and publishes a release automatically on every push
  to main.

= 0.5.0 =
* Reporting dashboard: all 8 reports now real (orders by branch, collection
  success rate, uncollected orders, prep/turnaround time, product and branch
  sales performance, Express/Standard split), with working date-range/branch
  filters and CSV export.
* Admin Orders & Disputes and Disputes & Returns screens: real order data,
  search/status filter, and a detail modal (items, recipient, audit trail).
* Storefront: catalogue and product availability now reflect real per-branch
  stock; add-to-cart is validated against it. WooCommerce's native stock is
  bypassed for IPN-tracked products.
* Branch admin: add/edit now saves for real, plus a weekly operating-hours
  editor and a one-off closure-dates manager.
* Staff-to-branch assignment UI, stock "Adjust" write UI (admin + staff),
  audit-log CSV export, a disputed-order admin email alert, and a scheduled
  daily digest email (with preview).

= 0.4.0 =
* CSV/Excel catalogue import: create/update products by SKU, per-branch
  stock via the import file, category + image handling on creation,
  per-row error reporting in the admin log.

= 0.3.0 =
* Checkout: branch-closed advisory notice, Express Collection surcharge
  now applied to the cart total live via AJAX.

= 0.2.0 =
* Order-status wiring (IPN_Order): stock reserve/release/deduct, the
  wc-ipn-* collection statuses, and checkout/staff-dashboard/My Account
  now driven by real order data instead of stubs. Email notifications,
  the uncollected-orders cron, and collection-code resend now work end
  to end.

= 0.1.0 =
* Initial plugin scaffold: bootstrap, hook loader, DB schema, custom roles,
  branch/stock/OTP/audit-log data layer, and the IPN admin menu framework.
