# Rollpix_QrLanding

> **[Versión en español](README.es.md)**

**SPONSOR:** [www.rollpix.com](https://www.rollpix.com)

Magento 2 module that serves as a QR code landing page for physical store products. When a customer scans a QR code printed next to a product, the module records the visit and redirects to the product detail page (PDP), preserving UTM parameters for Google Analytics tracking.

## Compatibility

| Requirement | Version |
|---|---|
| PHP | 8.1 \| 8.2 \| 8.3 |
| Magento | 2.4.7 \| 2.4.7-p1~p4 \| 2.4.8 |

## Installation

### Via Composer (recommended)

```bash
composer config repositories.rollpix-qr-landing vcs https://github.com/ROLLPIX/M2-qr-landing.git
composer require rollpix/module-qr-landing:1.0.0
bin/magento module:enable Rollpix_QrLanding
bin/magento setup:upgrade
bin/magento cache:flush
```

### Manual

1. Create `app/code/Rollpix/QrLanding` and copy all repo files there
2. Run:
```bash
bin/magento module:enable Rollpix_QrLanding
bin/magento setup:upgrade
bin/magento cache:flush
```

## Admin Configuration

**Path:** Stores > Configuration > Rollpix > QR Landing

| Field | Type | Default | Scope |
|---|---|---|---|
| Enable Module | Yes/No | Yes | Store View |
| Enable Tracking | Yes/No | Yes | Store View |
| Identifier Type | Select | SKU | Store View |

## What It Does

### QR Landing Controller (`/qr/{sku}`)

- Receives QR scan via URL like `/qr/ABC123?track=true&utm_source=qr_store`
- Validates that the module is enabled for the current store view
- Resolves the SKU to a product and verifies it exists and is enabled
- Records the visit in `rollpix_qr_visits` (if `track=true` and tracking is enabled)
- Redirects (302) to the product page, appending UTM parameters

### REST API — Status (`GET /V1/rollpix-qr/status`)

- **Auth:** Bearer token (admin)
- **ACL:** `Rollpix_QrLanding::status`
- Returns: `{ "enabled", "tracking_enabled", "module_version", "identifier_type" }`

### REST API — Report (`GET /V1/rollpix-qr/report`)

- **Auth:** Bearer token (admin)
- **ACL:** `Rollpix_QrLanding::report`
- **Query params:** `from`, `to` (Y-m-d), `sku`, `page`, `pageSize`
- Returns visit aggregations by SKU and daily counts

## Behavior When Disabled

- `/qr/{sku}` returns 404
- REST APIs still respond (they report `enabled: false`)
- No visits are recorded

## Technical Architecture

```
M2-qr-landing/                          ← repo root
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── di.xml                           (router + interface preferences)
│   ├── acl.xml
│   ├── config.xml
│   ├── db_schema.xml                    (rollpix_qr_visits table)
│   ├── db_schema_whitelist.json
│   ├── webapi.xml                       (status + report endpoints)
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       └── routes.xml
├── Api/
│   ├── StatusInterface.php
│   └── ReportInterface.php
├── Block/Adminhtml/System/Config/
│   └── ModuleInfo.php
├── Controller/Qr/
│   └── Index.php
├── Helper/
│   └── Config.php
├── Model/
│   ├── Status.php
│   ├── Report.php
│   ├── Visit.php
│   ├── Config/Source/
│   │   └── IdentifierType.php
│   └── ResourceModel/
│       ├── Visit.php
│       └── Visit/
│           └── Collection.php
├── Router/
│   └── QrRouter.php
├── view/frontend/layout/
│   └── qr_qr_index.xml
└── i18n/
    └── es_AR.csv
```

| Component | Type | Purpose |
|---|---|---|
| `QrRouter` | Custom Router | Parses `/qr/{sku}` into controller params |
| `Controller\Qr\Index` | HttpGetActionInterface | Landing logic: validate, track, redirect |
| `Helper\Config` | Config reader | Reads `rollpix_qrlanding/general/*` |
| `Model\Status` | API implementation | Returns module status |
| `Model\Report` | API implementation | Aggregated visit report |

## Manual Testing Guide

```bash
# 1. Redirect works with existing SKU
curl -sI "https://yourstore.com/qr/EXISTING_SKU"
# → HTTP 302 + Location: product URL

# 2. Tracking records visit
curl -sI "https://yourstore.com/qr/EXISTING_SKU?track=true&utm_source=test"
# → Check rollpix_qr_visits table

# 3. Non-existent SKU returns 404
curl -sI "https://yourstore.com/qr/FAKE_SKU"
# → HTTP 404

# 4. Status API
curl -s -H "Authorization: Bearer $TOKEN" "https://yourstore.com/rest/V1/rollpix-qr/status"

# 5. Report API
curl -s -H "Authorization: Bearer $TOKEN" "https://yourstore.com/rest/V1/rollpix-qr/report?from=2026-01-01"
```

## Uninstall

### Via Composer
```bash
bin/magento module:uninstall Rollpix_QrLanding
# or
composer remove rollpix/module-qr-landing
bin/magento setup:upgrade
```

### Manual
```bash
bin/magento module:disable Rollpix_QrLanding
rm -rf app/code/Rollpix/QrLanding
bin/magento setup:upgrade
```

The `rollpix_qr_visits` table will be removed automatically on module uninstall via composer. For manual removal, drop it manually.

## License

[MIT](LICENSE)
