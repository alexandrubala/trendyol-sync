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

## Features

- Encrypted API credential storage (AES-256-CBC)
- Automatic Trendyol market detection from WooCommerce store country and WordPress locale
- Local cache for Trendyol brands and categories
- Per-product admin tab (category, brand, barcode, VAT, sync toggle)
- Category and brand mapping with global defaults and per-product overrides
- Background sync queue with batch polling and admin dashboard
- API rate limiting (50 requests per 10 seconds)

## Screenshots

| Settings - Credentials | Settings - Environment |
|------------------------|------------------------|
| ![API credentials and catalog sync](docs/screenshots/settings-credentials.png) | ![Stage / Production environment](docs/screenshots/settings-environment.png) |

| Product tab | Category mapping |
|-------------|------------------|
| ![Trendyol Sync product tab](docs/screenshots/product-tab.png) | ![Category and brand mapping](docs/screenshots/mapping-page.png) |

| Sync queue |
|------------|
| ![Sync queue dashboard](docs/screenshots/sync-queue.png) |

Add PNG screenshots under `docs/screenshots/` using the filenames above before publishing to WordPress.org.

## Installation

1. Clone into `wp-content/plugins/`:

   ```bash
   git clone https://github.com/alexandrubala/trendyol-sync-for-woocommerce.git
   ```

   Or download the latest release ZIP from [GitHub Releases](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/releases) and extract it to `wp-content/plugins/trendyol-sync-for-woocommerce/`.

2. Activate **Trendyol Sync for WooCommerce** under **Plugins**.
3. Ensure **WooCommerce** is active (required at activation).

### Migrating from `trendyol-sync` (1.1.x and older)

1. Deactivate the old **Trendyol Sync** plugin (`wp-content/plugins/trendyol-sync/`).
2. Install the new `trendyol-sync-for-woocommerce/` folder.
3. Activate **Trendyol Sync for WooCommerce**. Settings, jobs, and product meta are preserved.
4. Remove the old `trendyol-sync/` folder once everything works.

## Configuration

1. Open **Trendyol Sync** in the WordPress admin sidebar.
2. **Credentials** tab:
   - **Supplier ID** from the Trendyol seller panel
   - **API Key** and **API Secret** (stored encrypted)
   - **Integrator Name** for the `User-Agent` header (e.g. `SelfIntegration`)
3. **Environment** tab:
   - **Stage** - `https://stageapigw.trendyol.com` (testing; may require IP whitelist)
   - **Production** - `https://apigw.trendyol.com`

Stage and Production credentials are different. Never commit real API keys to a public repository.

### Catalog (brands and categories)

On the **Credentials** tab, click **Sync catalog** to download Trendyol brands and categories into a local cache.

The plugin detects the Trendyol market from:

- WooCommerce store country (Settings - General - Store location)
- WordPress site language (Settings - General - Site language)

Example: store in **Romania** with language **Romanian** sends `storeFrontCode: RO` and `Accept-Language: ro`.

Supported markets: RO, GR, DE, BG, HU, CZ, SK, AZ, SA, AE.

### Plugin updates

**WordPress.org installs** receive updates through the official WordPress plugin directory.

**GitHub installs** can opt in to GitHub Releases updates by adding to `wp-config.php`:

```php
define( 'TRENDYOL_SYNC_GITHUB_UPDATES', true );
```

For a private GitHub repository, also define:

```php
define( 'TRENDYOL_SYNC_GITHUB_TOKEN', 'ghp_xxxxxxxx' );
```

## Project structure

```
trendyol-sync-for-woocommerce/
├── trendyol-sync-for-woocommerce.php
├── includes/
│   ├── Plugin.php
│   ├── Activator.php
│   ├── Deactivator.php
│   ├── Admin/
│   ├── API/
│   ├── Cache/
│   ├── Data/
│   └── Security/
├── assets/
├── languages/trendyol-sync-for-woocommerce.pot
├── readme.txt
├── LICENSE
└── uninstall.php
```

## Changelog

### v1.2.2

- Mapping page: Trendyol categories loaded inline; brand AJAX search fixed
- Improved API category tree parsing (v2/v3 formats)
- WordPress.org readiness: `readme.txt`, `LICENSE`, English plugin header
- GitHub updater disabled by default (opt-in via `TRENDYOL_SYNC_GITHUB_UPDATES`)
- Logger redacts sensitive fields; duplicate sync jobs blocked
- Safer uninstall with opt-in table purge

### v1.2.1

- Mapping page selectWoo fixes; AJAX dropdowns for categories and brands
- Catalog sync rate-limit wait; extended download timeout

### v1.2.0

- Renamed to **Trendyol Sync for WooCommerce** (`trendyol-sync-for-woocommerce`)
- New text domain and automatic legacy plugin deactivation

See [readme.txt](readme.txt) for the full WordPress.org changelog.

## Roadmap

- [Attribute mapping UI](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/issues/1)
- [CSV import/export for mappings](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/issues/2)
- [Order sync support](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/issues/3)
- [Webhook/email error notifications](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/issues/4)
- [WordPress.org compliance review](https://github.com/alexandrubala/trendyol-sync-for-woocommerce/issues/5)

## Security

- API keys are stored encrypted in the database.
- Secrets are redacted from logs (API keys, tokens, Authorization headers).
- AJAX endpoints use nonces and capability checks.
- Forms sanitize input and escape output with WordPress helpers.
- Trendyol enforces **50 requests per 10 seconds** per endpoint; the plugin respects this limit.

## Development

```bash
cd wp-content/plugins/trendyol-sync-for-woocommerce
git pull origin main
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

[Alexandru Bala](https://github.com/alexandrubala)
