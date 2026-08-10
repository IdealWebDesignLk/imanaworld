# Imana Dokan Plugin

A WordPress plugin for marketplace management, vendor portals, and e-commerce operations, tightly integrated with WooCommerce and Dokan.

## 🚀 Features

### 🌟 Core Features
- **Multi-Vendor Marketplace**: Convert WooCommerce to a full-featured marketplace with vendor commissions, dashboards, and global settings.
- **Comprehensive Dokan Support**: Extend and enhance Dokan Vendor capabilities with custom features and integrations.
- **Product & Inventory Management**: Advanced product handling, including file-based products with custom pricing and stock control.
- **Custom Pricing Engine**:
  - Zone-based shipping and pricing.
  - Volume-based discounts (tiered pricing).
  - Flexible pricing tiers (small, medium, large).
  - Fixed vs. Percentage-based pricing.

### 🏪 Vendor Portal Features
- **Centralized Dashboard**: Vendor overview with sales stats, order counts, and traffic analytics.
- **Order Management**: Dedicated order list, bulk order status updates, and detailed order views.
- **Product Management**:
  - Add and edit products with detailed attributes.
  - Support for physical and virtual products.
  - Product listing status tracking.
- **Coupon System**: Vendor-specific coupon management with flexible rules.
- **Store Settings**: Store appearance customization and business information.
- **Shipping Configuration**: Custom shipping zone setup and management.

### 📦 WooCommerce Integration
- **Admin Settings**: Granular control over shipping, product display, store layout, and global settings.
- **Zone Management**: Manage shipping zones with custom methods and pricing.
- **Product Settings**: Configure product visibility, image sizes, and display options.
- **Storefront Options**: Customize marketplace header, footer, and seller card layouts.

### 🧰 Advanced Capabilities
- **Custom Shortcodes**: Ready-to-use shortcodes for marketplace features.
- **Template Overrides**: Seamless integration with WooCommerce and Dokan template system.
- **REST API**: API endpoints for custom integrations.
- **Widget Support**: Ready-to-use widgets for marketplace features.

## 🌐 Available Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[iw_vendor_dashboard]` | Displays the main vendor dashboard. |
| `[iw_store_list]` | Lists all vendor stores with filters. |
| `[iw_store_card]` | Displays a compact store card (Vendor Info + List).
| `[iw_product_listing]` | Lists products with search and filters. |
| `[iw_vendor_product_listing]` | Lists products filtered by the current vendor. |
| `[iw_vendor_product_form]` | Form for adding/editing products by vendors. |
| `[iw_vendor_orders]` | Lists orders for the current vendor. |
| `[iw_vendor_coupons]` | Lists and manages vendor coupons. |

## 📂 Plugin Structure

```
imana-world-plugin/
├── classes/
│   ├── class-imana-init.php               # Core plugin initialization
│   ├── class-imana-admin.php              # Main admin settings and page
│   ├── class-imana-vendor-dashboard.php   # Vendor dashboard
│   ├── class-imana-vendor-orders.php      # Vendor order management
│   ├── class-imana-vendor-products.php    # Vendor product management
│   ├── class-imana-vendor-coupons.php     # Vendor coupon management
│   ├── class-imana-store-list.php         # Store directory
│   ├── class-imana-shortcodes.php         # All shortcode handlers
│   ├── class-imana-global-settings.php    # Global store settings
│   ├── class-imana-vendor-settings.php    # Vendor-specific settings
│   ├── class-imana-dokan-integration.php  # Dokan integration
│   └── class-imana-product-features.php   # Custom product features
├── assets/
│   ├── css/
│   │   ├── admin-style.css                # Admin styles
│   │   ├── vendor-style.css                 # Vendor dashboard styles
│   │   └── frontend-style.css             # Frontend styles
│   └── js/
│       ├── admin.js                       # Admin JavaScript
│       └── vendor.js                      # Vendor JavaScript
├── templates/
│   ├── vendor-dashboard.php               # Dashboard template
