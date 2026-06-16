=== Trendyol Sync for WooCommerce ===
Contributors: webgems
Tags: woocommerce, trendyol, marketplace, sync, inventory
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 7.0
WC tested up to: 9.0

Controlled background synchronization of WooCommerce products with the Trendyol marketplace API (local and international storefronts).

== Description ==

Trendyol Sync for WooCommerce connects your WooCommerce store to Trendyol's seller API. Product sync runs in the background via Action Scheduler so large catalogs do not hit PHP timeouts.

**Features**

* Encrypted storage for API credentials (AES-256-CBC)
* Automatic Trendyol market detection from WooCommerce store country and WordPress locale
* Catalog sync for Trendyol brands and categories (cached locally)
* Per-product Trendyol tab on WooCommerce products (category, brand, barcode, VAT, sync toggle)
* Category and brand mapping (global defaults, per WooCommerce category, per-product overrides)
* Background sync queue with batch polling and admin dashboard
* Rate limiting aligned with Trendyol (50 requests / 10 seconds)

**Supported storefronts**

RO, GR, DE, BG, HU, CZ, SK, AZ, SA, AE (detected from store settings).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/trendyol-sync-for-woocommerce/` or install the ZIP from the WordPress.org plugin directory.
2. Activate **Trendyol Sync for WooCommerce** through the **Plugins** menu.
3. Ensure **WooCommerce** is active (required).
4. Go to **Trendyol Sync - Settings**, enter your Supplier ID, API Key, and API Secret from the Trendyol seller panel.
5. Choose **Stage** or **Production**, then click **Sync catalog** to download brands and categories.
6. Map WooCommerce categories and enable sync on products you want to push to Trendyol.

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No. WooCommerce must be active. If WooCommerce is deactivated, the plugin shows an admin notice and does not load WooCommerce-dependent code.

= Are API keys stored securely? =

Yes. API Key and API Secret are encrypted in the database using OpenSSL (AES-256-CBC). They are never logged.

= What happens when I uninstall the plugin? =

By default, plugin settings and transients are removed. Custom database tables (sync jobs, batches, logs) are only dropped if you enable **Remove all plugin data on uninstall** in **Settings - Environment** before uninstalling. Product meta (barcodes, sync status) is never deleted automatically.

= How do I migrate from the old `trendyol-sync` plugin? =

Deactivate the old plugin, install this one, and activate it. Settings, jobs, and product meta use the same database keys. Delete the old plugin folder when everything works.

== Screenshots ==

1. API credentials and catalog sync on the Settings page
2. Stage / Production environment selection
3. Trendyol Sync tab on a WooCommerce product
4. Category and brand mapping page
5. Sync queue dashboard with job progress

== Changelog ==

= 1.2.2 =
* Mapping page: Trendyol categories loaded inline (no AJAX on page open); brand AJAX search fixed
* Improved API category tree parsing (v2/v3 formats)
* Removed `minimumResultsForSearch: 0` that caused empty dropdowns
* WordPress.org readiness: readme.txt, LICENSE, English plugin header, optional GitHub updater
* Security: logger redacts secrets; duplicate sync jobs blocked; safer uninstall with opt-in table purge

= 1.2.1 =
* Mapping page: correct selectWoo on custom admin screens; AJAX dropdowns for categories/brands
* Catalog sync: automatic wait on 50 req/10s rate limit; extended timeout for full download
* Catalog search: `manage_trendyol_sync` capability; improved cache detection

= 1.2.0 =
* Renamed plugin to **Trendyol Sync for WooCommerce** (`trendyol-sync-for-woocommerce`)
* New main file and text domain; automatic legacy plugin deactivation when both are active

= 1.1.0 =
* Full automation: category/brand mapping, barcode strategies, scheduled sync, bulk actions, onboarding wizard

= 1.0.5 =
* Automatic Trendyol market detection; per-market catalog cache; AJAX catalog search

== Upgrade Notice ==

= 1.2.2 =
WordPress.org readiness, security hardening, and mapping page UX fixes.
