=== IMANAWORLD Pickup Network ===
Contributors: idealwebdesign
Tags: woocommerce, dokan, click-and-collect, multi-vendor
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.5.3
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
