=== IMANAWORLD Pickup Network ===
Contributors: idealwebdesign
Tags: woocommerce, dokan, click-and-collect, multi-vendor
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
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

== Changelog ==

= 0.1.0 =
* Initial plugin scaffold: bootstrap, hook loader, DB schema, custom roles,
  branch/stock/OTP/audit-log data layer, and the IPN admin menu framework.
