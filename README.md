# Trendyol Sync

Plugin WordPress pentru integrarea magazinului **WooCommerce** cu API-ul **Trendyol** (piața locală și internațională). Sincronizarea produselor este proiectată să ruleze în fundal, în mod controlat, fără timeout-uri la volume mari.

**Repository:** [github.com/alexandrubala/trendyol-sync](https://github.com/alexandrubala/trendyol-sync) (privat)

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

## Structură proiect

```
trendyol-sync/
├── trendyol-sync.php          # Bootstrap, constante, autoload PSR-4
├── includes/
│   ├── Plugin.php             # Orchestrator (Singleton)
│   ├── Activator.php          # Tabele DB, capabilities, verificare WC
│   ├── Deactivator.php        # Curățare Action Scheduler la dezactivare
│   ├── Admin/                 # Settings API, pagină setări
│   ├── Data/Schema.php        # wp_trendyol_sync_jobs, _batches, _logs
│   └── Security/Encryption.php
└── assets/css/
```

## Funcționalități implementate

### Sprint 1 (v1.0.0)

- [x] Scaffolding OOP cu autoload PSR-4 nativ (fără Composer obligatoriu)
- [x] Tabele custom la activare (`dbDelta`)
- [x] Capability `manage_trendyol_sync` (Administrator, Shop Manager)
- [x] Pagină setări cu tab-uri **Credentials** / **Environment**
- [x] Criptare `api_key` și `api_secret` (AES-256-CBC + `wp_salt('auth')`)
- [x] Mascare secrete în UI (câmpuri password; păstrare valoare la salvare goală)

### Sprint 2 (v1.0.1)

- [x] Auto-update nativ din GitHub Releases (hook-uri `site_transient_update_plugins` + `plugins_api`)
- [x] Suport token opțional pentru repository privat (`TRENDYOL_SYNC_GITHUB_TOKEN`)
- [x] Endpoint stub comenzi `get_shipment_packages($args)` pentru Faza 2
- [x] `uninstall.php` cu cleanup complet: opțiune, transient-uri `trendyol_*`, tabele custom
- [x] Bază i18n (`/languages/trendyol-sync.pot`) și încărcare textdomain pe `init`

### Roadmap (în dezvoltare)

- [ ] Client API + test conexiune (*Check API Status*)
- [ ] Cache Transient (categorii, branduri, atribute)
- [ ] Mapare produse WooCommerce → Trendyol
- [ ] Coadă Action Scheduler + `createProducts` v2 + polling `getBatchRequestResult`
- [ ] Logger și dashboard sync
- [ ] Implementare completă comenzi (`getShipmentPackages`) + procesare shipment flow

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
