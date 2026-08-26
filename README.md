# IMANAWORLD Pickup Network (IPN)

Click & Collect fulfilment network for IMANAWORLD, built on WooCommerce and Dokan.
Pilot partner: Choppies.

Customers shop a chosen branch's real stock, pay online, and collect in store against
a single-use emailed code. Branch staff work their own queue from a phone-shaped
dashboard; IMANAWORLD admins run branches, stock, partners, disputes, and reporting
from wp-admin.

Each Click & Collect partner is one Dokan vendor account, and that vendor's branches
are its collection points — onboarding a new partner is configuration, not code.

## Requirements

- WordPress 6.0+
- WooCommerce
- Dokan (Lite or Pro)
- PHP 7.4+

## Repository layout

```
imanaworld-pickup-network/     The plugin (this is what ships)
├── classes/                   One class per concern; IPN_Init is the composition root
├── templates/
│   ├── admin/                 wp-admin screens under the "IPN" menu
│   ├── staff/                 Branch staff dashboard (the shortcode below)
│   ├── storefront/            Branch selector, indicator bar, availability, checkout
│   ├── my-account/            Customer order tracker
│   └── emails/                Customer, staff, and admin notifications
├── assets/                    Scoped CSS/JS — nothing leaks onto the theme or wp-admin
├── vendor/plugin-update-checker/   Bundled updater library (MIT, © Janis Elsts)
├── readme.txt                 Plugin readme + full changelog
├── PROJECT_CHECKLIST.md       Scope status, verified against the code
└── IPN_CSV_Import_Specification.md  Catalogue file format handed to the partner

.github/workflows/release.yml  Builds the plugin zip and publishes a GitHub Release
```

## Who sees what

| Role | Where they work | Scope |
|------|-----------------|-------|
| WordPress admin | "IPN" menu in wp-admin | Everything |
| Vendor (Dokan seller, flagged as a partner) | "Click & Collect" section of Dokan's vendor dashboard at `/dashboard/` | Their own branches only |
| IPN Branch Staff | The `[ipn_staff_dashboard]` page | The one branch they are assigned to |

A vendor becomes a Click & Collect partner when an admin ticks **Make IPN
Partner** on their user profile. Scoping is enforced server-side on every read
and write in `classes/class-ipn-access.php` — never by which options a screen
happens to render.

## Shortcodes

One shortcode, and it takes no attributes:

| Shortcode | Description |
|-----------|-------------|
| `[ipn_staff_dashboard]` | The branch staff dashboard — order queue, order detail with the collection-code check, and that branch's stock. Scoped to the one branch the logged-in staff user is assigned to. |

Put it on a single page (e.g. `/branch-staff/`) and give branch staff that URL. Branch
staff have no wp-admin access, so this page is their entire interface.

Anyone who is not a logged-in **IPN Branch Staff** user sees a sign-in prompt instead —
no branch data is exposed. A staff user with no branch assigned yet is told to contact
an admin.

The dashboard navigates itself with query parameters on that page; you never need to
write them by hand:

| Parameter | Purpose |
|-----------|---------|
| `?ipn_screen=queue` | Order queue (the default) |
| `?ipn_screen=detail&order_id=123` | One order — collection-code check, Accept / Preparing / Ready / Reject |
| `?ipn_screen=stock` | Branch stock, with `stock_q` (search) and `stock_page` (pagination) |

## Front-end surfaces (no shortcode needed)

Everything customers see hooks onto WooCommerce's own templates, so there is nothing to
place on a page:

| Where | What appears |
|-------|--------------|
| Dokan vendor dashboard | A "Click & Collect" section: branches, staff, per-branch stock, products, orders |
| Before the shop loop | Branch selector, until a branch is chosen |
| Above every WooCommerce template | "Shopping at X · Change branch" indicator bar |
| Single product page | Click & Collect availability per branch, above add-to-cart |
| Checkout | Collection type, nominated recipient, and the branch picker if none is selected |
| My Account → Orders | Live order tracker under the order table |

## Admin

Under the **IPN** menu in wp-admin (requires `manage_woocommerce`): Dashboard, Partners,
Branches, Staff, Stock, Catalogue Import, Orders & Disputes, Disputes & Returns, Daily
Digest, Audit Trail, Reports, Settings.

There is also a **Click & Collect Branch Stock** box on the WooCommerce product edit
screen for setting per-branch stock on any product without going through the importer.

## Setting it up

1. Create the partner's Dokan vendor account the normal way.
2. Open that user under **Users**, tick **Make IPN Partner**, and save. They now appear under **IPN → Partners**.
3. Add their branches — either from **IPN → Branches** as an admin, or let the vendor add their own from the Click & Collect section of their Dokan dashboard.
4. Add branch staff and assign each to a branch — again either from **IPN → Staff**, or by the vendor from their own dashboard.
5. Publish a page containing `[ipn_staff_dashboard]` and give staff its URL.
6. Load stock — **IPN → Catalogue Import**, per product from the product edit screen, or per branch from the vendor dashboard.

## Who moves an order along

An order runs New → Accepted → Preparing → Ready → Collected. Both the vendor and the
branch staff assigned to that branch can take the first three steps, from the Orders tab
of the vendor dashboard or from the branch dashboard; a branch that has no staff account
of its own is therefore still workable by the partner.

The last step is not shared. An order becomes Collected only when the collection code the
customer brings to the counter is checked on the branch dashboard, because that code is
the only evidence the right person took the goods.

An order placed by bank transfer, cheque, or payment at the counter arrives **awaiting
payment**, and its stock is not reserved yet. It has no fulfilment step until somebody
records the money as having arrived — the vendor with **Mark payment received** on the
Orders tab, or an administrator setting the order to Processing in WooCommerce. Either
route reserves the branch stock and drops the order into the queue as New.

That step exists because WooCommerce parks every offline payment method in "on hold", and
a store taking payment at the counter never leaves that state on its own; without it such
a branch has a queue that can never start. It is a claim about money, so it asks for
confirmation and is recorded in the audit trail against whoever made it.

## Database

All custom tables use the `ipn_` prefix (`wp_ipn_branches`, `wp_ipn_branch_stock`,
`wp_ipn_order_meta`, `wp_ipn_otp_codes`, `wp_ipn_audit_log`, …). The full schema, and the
versioned self-upgrade that keeps it current, live in `classes/class-ipn-install.php`.

WooCommerce's native stock field is deliberately not used for IPN-tracked products —
`ipn_branch_stock` is the single source of truth, with available = total − reserved.

## Releases

Pushing to `main` with changes under `imanaworld-pickup-network/` triggers
`.github/workflows/release.yml`, which reads the `Version:` header from the plugin file,
builds a correctly-rooted zip, and publishes it as a GitHub Release. The bundled Plugin
Update Checker points at that release asset, so sites running the plugin see a normal
"update available" in wp-admin.

Bump the `Version:` header (and `IPN_VERSION`) for a new release; re-pushing without a
bump updates the existing release's asset in place.

The repo is public, so the updater needs no credential. If it is ever made private, set
`IPN_GITHUB_TOKEN` in `wp-config.php` to a personal access token with read access —
never hardcode a token in plugin source.

## Branches

`develop` is the working branch; `main` is what ships. Merge `develop` into `main` to
cut a release.
