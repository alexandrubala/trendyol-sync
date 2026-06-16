# Trendyol Sync for WooCommerce

WordPress plugin that integrates your **WooCommerce** store with the **Trendyol** seller API (local and international storefronts). Product synchronization runs in the background in a controlled way, without PHP timeouts on large catalogs.

**Repository:** [github.com/alexandrubala/trendyol-sync-for-woocommerce](https://github.com/alexandrubala/trendyol-sync-for-woocommerce)

**Current version:** 1.2.2

## Requirements

| Component   | Minimum |
|-------------|---------|
| WordPress   | 6.0+    |
| PHP         | 7.4+ (OpenSSL) |
| WooCommerce | 7.0+    |

## Screenshots

| Settings — Credentials | Settings — Environment |
|------------------------|------------------------|
| ![API credentials and catalog sync](docs/screenshots/settings-credentials.png) | ![Stage / Production environment](docs/screenshots/settings-environment.png) |

| Product tab | Category mapping |
|-------------|------------------|
| ![Trendyol Sync product tab](docs/screenshots/product-tab.png) | ![Category and brand mapping](docs/screenshots/mapping-page.png) |

| Sync queue |
|------------|
| ![Sync queue dashboard](docs/screenshots/sync-queue.png) |

> Add PNG screenshots under `docs/screenshots/` using the filenames above before publishing to WordPress.org.

## Installation

1. Clone into `wp-content/plugins/`:

   ```bash
   git clone https://github.com/alexandrubala/trendyol-sync-for-woocommerce.git
   ```

   Or download the latest release ZIP from [GitHub Releases](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/releases) and extract it to `wp-content/plugins/trendyol-sync-for-woocommerce/`.

2. Activate **Trendyol Sync for WooCommerce** under **Plugins**.
3. Ensure **WooCommerce** is active (the plugin cannot be activated without it).

### Migrating from `trendyol-sync` (≤ 1.1.x)

1. Deactivate the old **Trendyol Sync** plugin (`wp-content/plugins/trendyol-sync/`).
2. Install the new `trendyol-sync-for-woocommerce/` folder.
3. Activate **Trendyol Sync for WooCommerce** — settings, jobs, and product meta are preserved (same database keys).
4. Remove the old `trendyol-sync/` folder once everything works.

## Configuration

1. Open **Trendyol Sync** in the WordPress admin sidebar.
2. **Credentials** tab:
   - **Supplier ID** — from the Trendyol seller panel (*Entegrasyon Bilgileri*)
   - **API Key** / **API Secret** — stored encrypted in the database
   - **Integrator Name** — used in the `User-Agent` header (e.g. `SelfIntegration`)
3. **Environment** tab:
   - **Stage** — `https://stageapigw.trendyol.com` (testing; may require IP whitelist)
   - **Production** — `https://apigw.trendyol.com`

> Stage and Production credentials are different. Never commit real API keys to a public repository.

### Catalog (brands & categories)

On the **Credentials** tab, click **Sync catalog** to download Trendyol brands and categories into a local cache. Product-page and Mapping dropdowns depend on this step.

The plugin detects the Trendyol market from:

- **WooCommerce store country** (Settings → General → Store location)
- **WordPress site language** (Settings → General → Site language)

Example: store in **Romania** + language **Romanian** → sends `storeFrontCode: RO` and `Accept-Language: ro`, so categories appear in Romanian.

Supported markets: RO, GR, DE, BG, HU, CZ, SK, AZ, SA, AE.

If the market cannot be detected, catalog sync is blocked to avoid importing irrelevant categories.

### Plugin updates

Updates come from **GitHub Releases** (`alexandrubala/trendyol-sync-for-woocommerce`). After updating, visit **Dashboard → Updates** or run `git pull` for git installs.

For a private repository (optional), add to `wp-config.php`:

```php
define( 'TRENDYOL_SYNC_GITHUB_TOKEN', 'ghp_xxxxxxxx' );
```

## Project structure

```
trendyol-sync-for-woocommerce/
├── trendyol-sync-for-woocommerce.php   # Bootstrap, constants, PSR-4 autoload
├── includes/
│   ├── Plugin.php                      # Orchestrator (Singleton)
│   ├── Activator.php                   # DB tables, capabilities, WC check
│   ├── Deactivator.php                 # Action Scheduler cleanup on deactivate
│   ├── Migration/From_Legacy_Plugin.php
│   ├── Admin/                          # Settings, catalog sync, WC product tab
│   ├── API/                            # HTTP client, auth, rate limiting
│   ├── Cache/Transient_Cache.php
│   ├── Data/Schema.php
│   └── Security/Encryption.php
├── assets/
├── languages/trendyol-sync-for-woocommerce.pot
└── uninstall.php
```

## Changelog

### v1.2.2

- **Mapping** page: Trendyol categories loaded inline (no AJAX on open); brand AJAX search fixed
- Improved API category tree parsing (v2/v3 formats)
- Removed `minimumResultsForSearch: 0` that caused empty dropdowns
- Added `LICENSE`, `readme.txt`, English README, optional full data purge on uninstall
- Logger redacts sensitive fields; duplicate sync jobs blocked while one is running

### v1.2.1

- **Mapping** page: correct selectWoo on custom admin screens; AJAX dropdowns for categories/brands
- Catalog sync: automatic wait on 50 req/10s rate limit; extended timeout for full download
- Catalog search: `manage_trendyol_sync` capability; improved cache detection

### v1.2.0

- Renamed plugin: **Trendyol Sync for WooCommerce** (`trendyol-sync-for-woocommerce`)
- GitHub repository: `alexandrubala/trendyol-sync-for-woocommerce`
- New main file: `trendyol-sync-for-woocommerce.php`
- Text domain: `trendyol-sync-for-woocommerce`
- Automatic migration: deactivates legacy install when both are active

### v1.1.0

- Full automation: WooCommerce `product_cat` → Trendyol category/brand mapping, barcode strategies, scheduled sync, bulk actions, onboarding wizard, sync dashboard

### v1.0.5

- Automatic Trendyol market detection; per-market catalog cache; AJAX catalog search

See [readme.txt](readme.txt) for the full WordPress.org changelog.

## Roadmap

Tracked as [GitHub Issues](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/issues):

- Attribute mapping UI (no manual JSON)
- CSV import/export for mappings
- Order sync support
- Webhook / email error notifications
- WordPress.org compliance review

## Security

- API keys are stored encrypted in the `trendyol_sync_settings` option.
- Never log or commit real credentials to Git.
- Trendyol requires a `User-Agent` header and enforces **50 requests / 10 seconds** per endpoint.
- AJAX endpoints use nonces and capability checks; forms use `sanitize_*` and `esc_*` helpers.

## Development

```bash
cd wp-content/plugins/trendyol-sync-for-woocommerce
git pull origin main
```

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

## Author

[alexandrubala](https://github.com/alexandrubala)
