# Trendyol Sync

Plugin WordPress pentru integrarea magazinului **WooCommerce** cu API-ul **Trendyol** (piața locală și internațională). Sincronizarea produselor este proiectată să ruleze în fundal, în mod controlat, fără timeout-uri la volume mari.

**Repository:** [github.com/alexandrubala/trendyol-sync](https://github.com/alexandrubala/trendyol-sync)

**Versiune curentă:** 1.0.7

## Cerințe

| Componentă | Versiune minimă |
|------------|-----------------|
| WordPress  | 6.0+            |
| PHP        | 7.4+ (OpenSSL)  |
| WooCommerce| 7.0+            |

## Instalare

1. Clonează repository-ul în `wp-content/plugins/`:

   ```bash
   git clone https://github.com/alexandrubala/trendyol-sync.git
   ```

   Sau descarcă ultimul release ZIP din [GitHub Releases](https://github.com/alexandrubala/trendyol-sync/releases) și extrage-l în `wp-content/plugins/trendyol-sync/`.

2. Activează pluginul **Trendyol Sync** din **Plugins** în WordPress.
3. Asigură-te că **WooCommerce** este activ (pluginul nu se activează fără el).

## Configurare

1. Mergi la **WooCommerce → Trendyol Sync**.
2. Tab **Credentials**:
   - **Supplier ID** — din panoul Trendyol (*Entegrasyon Bilgileri*)
   - **API Key** / **API Secret** — salvate criptat în baza de date
   - **Integrator Name** — folosit în header-ul `User-Agent` (ex. `SelfIntegration`)
3. Tab **Environment**:
   - **Stage** — `https://stageapigw.trendyol.com` (test; poate necesita IP whitelist)
   - **Production** — `https://apigw.trendyol.com`

> Credențialele Stage și Production sunt diferite. Nu partaja cheile API în repository-uri publice.

### Catalog (branduri & categorii)

Pe tab-ul **Credentials**, apasă **Sincronizează catalog** pentru a descărca brandurile și categoriile Trendyol în cache local. Dropdown-urile de pe pagina de produs depind de acest pas.

Pluginul detectează automat piața Trendyol din:

- **Țara magazinului** WooCommerce (Setări → General → Locație magazin)
- **Limba site-ului** WordPress (Setări → General → Limba site-ului)

Exemplu: magazin în **România** + limbă **română** → trimite `storeFrontCode: RO` și `Accept-Language: ro` la API, iar categoriile apar în română (nu în turcă).

Piețe suportate: RO, GR, DE, BG, HU, CZ, SK, AZ, SA, AE.

Dacă piața nu poate fi detectată, sincronizarea catalogului este blocată — nu se importă categorii irelevante.

### Actualizări plugin

Update-urile vin din **GitHub Releases**. După update, mergi la **Dashboard → Updates** sau rulează `git pull` dacă ai instalat din git.

Pentru repository privat (opțional), definește în `wp-config.php`:

```php
define( 'TRENDYOL_SYNC_GITHUB_TOKEN', 'ghp_xxxxxxxx' );
```

## Structură proiect

```
trendyol-sync/
├── trendyol-sync.php              # Bootstrap, constante, autoload PSR-4
├── includes/
│   ├── Plugin.php                 # Orchestrator (Singleton)
│   ├── Activator.php              # Tabele DB, capabilities, verificare WC
│   ├── Deactivator.php            # Curățare Action Scheduler la dezactivare
│   ├── Admin/                     # Settings, catalog sync, tab produs WC
│   │   ├── Catalog_Syncer.php     # AJAX sincronizare catalog
│   │   ├── Catalog_Options.php    # Branduri / categorii din cache
│   │   ├── Product_Data_Tab.php   # Tab Trendyol Sync pe produs
│   │   └── Updater.php            # Auto-update din GitHub Releases
│   ├── API/
│   │   ├── Client.php             # HTTP client + rate limiting
│   │   ├── Auth.php               # Basic Auth + storeFrontCode
│   │   └── Market_Context.php     # Detectare piață din WC / WP locale
│   ├── Cache/Transient_Cache.php  # Cache categorii, branduri (per piață)
│   ├── Data/Schema.php            # wp_trendyol_sync_jobs, _batches, _logs
│   └── Security/Encryption.php
├── assets/
│   ├── css/
│   └── js/admin-product-data.js   # Select2 AJAX brand / categorie
└── languages/trendyol-sync.pot
```

## Changelog

### v1.0.7

- Coloană nouă **Trendyol Sync** în lista de produse WooCommerce (X roșu / bifa verde / pending / eroare / parțial).
- Status separat pentru prezența pe platformă (`_trendyol_platform_live`) + tooltip-uri cu detalii de eroare/sincronizare.
- Agregare pentru produse variabile (status părinte calculat din variații).

### v1.0.6

- Dropdown brand/categorie: paginare AJAX (50/pagină) și scroll corect în admin WooCommerce
- Dropdown atașat la `body` — nu mai e tăiat de panoul produsului

### v1.0.5

- Detectare automată piață Trendyol (`Market_Context`) din țara WooCommerce + limba WordPress
- Header-e API `storeFrontCode` și `Accept-Language` — categorii în română pe site-uri RO
- Blocare sincronizare catalog dacă piața nu e recunoscută (evită categorii turcești/irelevante)
- Cache catalog separat per piață și limbă (ex. `RO_ro`)
- Căutare AJAX rapidă brand/categorie (Select2) — nu mai încarcă mii de `<option>` în pagină
- Opțiuni brand/categorie pre-procesate în cache după sincronizare

### v1.0.4

- Fix updater GitHub: verificare update-uri funcționează și via WP-Cron
- Release GitHub cu ZIP WordPress (`trendyol-sync/` root) în loc de zipball GitHub

### v1.0.3

- Buton **Sincronizează catalog** în setări (branduri + categorii Trendyol în cache)
- Avertisment pe tab-ul produs când cache-ul catalog lipsește

### v1.0.2

- Auto-update nativ din GitHub Releases
- Suport token opțional pentru repository privat (`TRENDYOL_SYNC_GITHUB_TOKEN`)
- Endpoint stub comenzi `get_shipment_packages($args)`
- `uninstall.php` cu cleanup complet
- Bază i18n (`/languages/trendyol-sync.pot`)

### v1.0.0

- Scaffolding OOP cu autoload PSR-4 nativ
- Tabele custom la activare, capability `manage_trendyol_sync`
- Pagină setări Credentials / Environment
- Criptare AES-256-CBC pentru chei API

## Roadmap

- [ ] Client API complet + test conexiune stabil (*Check API Status*)
- [ ] Mapare produse WooCommerce → Trendyol + validare atribute categorie
- [ ] Coadă Action Scheduler + `createProducts` v2 + polling `getBatchRequestResult`
- [ ] Logger și dashboard sync
- [ ] Comenzi (`getShipmentPackages`) + procesare shipment flow

## Securitate

- Cheile API sunt stocate criptat în opțiunea `trendyol_sync_settings`.
- Nu loga și nu comite niciodată credențiale reale în Git.
- Trendyol impune header `User-Agent` și rate limit **50 cereri / 10 secunde** per endpoint.

## Dezvoltare

```bash
cd wp-content/plugins/trendyol-sync
git pull origin main
```

## Licență

GPL-2.0-or-later — vezi [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Autor

[alexandrubala](https://github.com/alexandrubala)
