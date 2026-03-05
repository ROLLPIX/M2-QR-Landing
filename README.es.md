# Rollpix_QrLanding

> **[English version](README.md)**

**SPONSOR:** [www.rollpix.com](https://www.rollpix.com)

Módulo de Magento 2 que sirve como landing page para códigos QR de productos en tiendas físicas. Cuando un cliente escanea un código QR impreso junto a un producto, el módulo registra la visita y redirige a la página de detalle del producto (PDP), preservando los parámetros UTM para el tracking en Google Analytics.

## Compatibilidad

| Requisito | Versión |
|---|---|
| PHP | 8.1 \| 8.2 \| 8.3 |
| Magento | 2.4.7 \| 2.4.7-p1~p4 \| 2.4.8 |

## Instalación

### Vía Composer (recomendado)

```bash
composer config repositories.rollpix-qr-landing vcs https://github.com/ROLLPIX/M2-qr-landing.git
composer require rollpix/module-qr-landing
bin/magento module:enable Rollpix_QrLanding
bin/magento setup:upgrade
bin/magento cache:flush
```

### Manual

1. Crear `app/code/Rollpix/QrLanding` y copiar todos los archivos del repo allí
2. Ejecutar:
```bash
bin/magento module:enable Rollpix_QrLanding
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuración Admin

**Ruta:** Stores > Configuration > Rollpix > QR Landing

| Campo | Tipo | Default | Scope |
|---|---|---|---|
| Habilitar Módulo | Sí/No | Sí | Store View |
| Habilitar Tracking | Sí/No | Sí | Store View |
| Tipo de Identificador | Select | SKU | Store View |
| URL de Fallback | Texto | `/` | Store View |

## Qué Hace

### Controller QR Landing (`/qr/{sku}`)

- Recibe el escaneo QR vía URL como `/qr/ABC123?utm_source=qr_tienda`
- Valida que el módulo esté habilitado para la store view actual
- Resuelve el SKU a un producto y verifica que exista y esté habilitado
- Registra la visita en `rollpix_qr_visits` (automático cuando el tracking está habilitado, con deduplicación de 5 minutos por IP+SKU)
- Sanitiza todos los inputs almacenados (strip_tags + truncado por longitud)
- Redirige (302) a la página del producto, agregando los parámetros UTM
- Si el producto no se encuentra, redirige a la **URL de Fallback** (por defecto la homepage); muestra 404 si el fallback está vacío

### API REST — Status (`GET /V1/rollpix-qr/status`)

- **Auth:** Bearer token (admin)
- **ACL:** `Rollpix_QrLanding::status`
- Devuelve: `{ "enabled", "tracking_enabled", "module_version", "identifier_type" }`

### API REST — Reporte (`GET /V1/rollpix-qr/report`)

- **Auth:** Bearer token (admin)
- **ACL:** `Rollpix_QrLanding::report`
- **Params query:** `from`, `to` (Y-m-d), `sku`, `page`, `pageSize`
- Devuelve agregaciones de visitas por SKU y conteos diarios

## Comportamiento Cuando Está Deshabilitado

- `/qr/{sku}` devuelve 404 (o redirige a la URL de fallback si está configurada)
- Las APIs REST siguen respondiendo (reportan `enabled: false`)
- No se registran visitas

## Arquitectura Técnica

```
M2-qr-landing/                          ← raíz del repo
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── di.xml                           (preferences de interfaces)
│   ├── acl.xml
│   ├── config.xml
│   ├── db_schema.xml                    (tabla rollpix_qr_visits)
│   ├── db_schema_whitelist.json
│   ├── webapi.xml                       (endpoints status + report)
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       ├── di.xml                       (registro del router custom)
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

| Componente | Tipo | Propósito |
|---|---|---|
| `QrRouter` | Router Custom | Parsea `/qr/{sku}` en parámetros del controller |
| `Controller\Qr\Index` | HttpGetActionInterface | Lógica del landing: validar, trackear, redirigir |
| `Helper\Config` | Lector de config | Lee `rollpix_qrlanding/general/*` |
| `Model\Status` | Implementación API | Devuelve estado del módulo |
| `Model\Report` | Implementación API | Reporte agregado de visitas |

## Guía de Testing Manual

```bash
# 1. Redirect funciona con SKU existente
curl -sI "https://tutienda.com/qr/SKU_EXISTENTE"
# → HTTP 302 + Location: URL del producto

# 2. Tracking registra visita (automático cuando el tracking está habilitado)
curl -sI "https://tutienda.com/qr/SKU_EXISTENTE?utm_source=test"
# → Verificar tabla rollpix_qr_visits

# 3. SKU inexistente devuelve 404
curl -sI "https://tutienda.com/qr/SKU_FALSO"
# → HTTP 404

# 4. API Status
curl -s -H "Authorization: Bearer $TOKEN" "https://tutienda.com/rest/V1/rollpix-qr/status"

# 5. API Report
curl -s -H "Authorization: Bearer $TOKEN" "https://tutienda.com/rest/V1/rollpix-qr/report?from=2026-01-01"
```

## Desinstalación

### Vía Composer
```bash
bin/magento module:uninstall Rollpix_QrLanding
# o
composer remove rollpix/module-qr-landing
bin/magento setup:upgrade
```

### Manual
```bash
bin/magento module:disable Rollpix_QrLanding
rm -rf app/code/Rollpix/QrLanding
bin/magento setup:upgrade
```

La tabla `rollpix_qr_visits` se eliminará automáticamente al desinstalar vía composer. Para eliminación manual, borrarla manualmente.

## Licencia

[MIT](LICENSE)
