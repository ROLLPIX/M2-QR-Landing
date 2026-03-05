# CLAUDE.md - Rollpix_QrLanding

## Qué es este proyecto

Rollpix_QrLanding es un módulo de Magento 2 que actúa como landing para códigos QR de productos en tiendas físicas. Cuando un consumidor escanea un QR impreso junto a un producto, la URL apunta a este módulo que:
1. Registra la visita (tracking interno)
2. Redirige al PDP (Product Detail Page) del producto correspondiente
3. Preserva parámetros UTM para tracking en Google Analytics

El módulo también expone endpoints REST para que la Web App externa (Rollpix QR Manager) consulte el estado del módulo y obtenga reportes de uso.

## Stack y Compatibilidad

- **PHP**: 8.1 | 8.2 | 8.3
- **Magento**: 2.4.7 | 2.4.7-p1~p4 | 2.4.8
- **Vendor/Module**: Rollpix/QrLanding
- **Tipo Composer**: magento2-module
- **Distribución**: RCPM (Rollpix Composer Package Manager)

## Arquitectura del Módulo

### Componentes principales

| Componente | Responsabilidad |
|------------|----------------|
| Frontend Controller (`/qr/{identifier}`) | Recibe escaneo QR, registra visita, redirect 302 al PDP |
| REST API `/V1/rollpix-qr/status` | Informa si el módulo está habilitado (consumido por Web App) |
| REST API `/V1/rollpix-qr/report` | Devuelve reporte de visitas agregado (consumido por Web App) |
| Admin Config (system.xml) | Habilitar/deshabilitar módulo y tracking por Store View |
| DB Table `rollpix_qr_visits` | Registro de cada escaneo de QR |

### Scope Multi-tienda

Toda la configuración tiene scope **Store View**, permitiendo habilitar/deshabilitar independientemente por tienda en un setup multi-store. Los endpoints REST respetan el store_code en la URL (`/rest/{store_code}/V1/...`).

## Estructura de Archivos

```
M2-qr-landing/                          ← repo root (NO app/code/)
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── di.xml                           (interface preferences)
│   ├── acl.xml
│   ├── config.xml
│   ├── db_schema.xml
│   ├── db_schema_whitelist.json
│   ├── webapi.xml
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       ├── di.xml                       (custom router registration)
│       └── routes.xml
├── Api/
│   ├── StatusInterface.php
│   └── ReportInterface.php
├── Block/
│   └── Adminhtml/
│       └── System/
│           └── Config/
│               └── ModuleInfo.php
├── Controller/
│   └── Qr/
│       └── Index.php
├── Helper/
│   └── Config.php
├── Model/
│   ├── Status.php
│   ├── Report.php
│   ├── Visit.php
│   ├── Config/
│   │   └── Source/
│   │       └── IdentifierType.php
│   └── ResourceModel/
│       ├── Visit.php
│       └── Visit/
│           └── Collection.php
├── Router/
│   └── QrRouter.php
├── view/
│   └── frontend/
│       └── layout/
│           └── qr_qr_index.xml
├── i18n/
│   └── es_AR.csv
├── README.md
├── README.es.md
├── LICENSE
└── .gitignore
```

## Modelo de Datos

### Tabla: rollpix_qr_visits

| Campo | Tipo | Notas |
|-------|------|-------|
| entity_id | INT UNSIGNED AUTO_INCREMENT PK | |
| store_id | SMALLINT UNSIGNED NOT NULL | FK a store.store_id |
| sku | VARCHAR(64) NOT NULL | SKU del producto escaneado |
| product_id | INT UNSIGNED NULL | FK referencial, puede ser NULL si se borra el producto |
| visitor_ip | VARCHAR(45) | IPv4/IPv6 |
| user_agent | TEXT | Browser del visitante |
| referer | VARCHAR(512) | HTTP Referer |
| utm_source | VARCHAR(255) | |
| utm_medium | VARCHAR(255) | |
| utm_campaign | VARCHAR(255) | |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | Momento del escaneo |

**Índices**: (store_id, sku), (store_id, created_at), (sku), (visitor_ip, sku, created_at)

## Configuración Admin

**Path**: Stores > Configuration > Rollpix > QR Landing

| Campo | Tipo | Default | Scope | Config Path |
|-------|------|---------|-------|-------------|
| Habilitar módulo | Yes/No | Yes | Store View | rollpix_qrlanding/general/enabled |
| Habilitar tracking | Yes/No | Yes | Store View | rollpix_qrlanding/general/tracking_enabled |
| Tipo de identificador | Select (sku) | SKU | Store View | rollpix_qrlanding/general/identifier_type |
| URL de fallback | Text | `/` | Store View | rollpix_qrlanding/general/fallback_url |

## Endpoints REST (webapi.xml)

### GET /V1/rollpix-qr/status
- **Auth**: Bearer token (admin)
- **ACL**: Rollpix_QrLanding::status
- **Service**: Rollpix\QrLanding\Api\StatusInterface::getStatus
- **Response**: `{ "enabled": bool, "tracking_enabled": bool, "module_version": "1.0.2", "identifier_type": "sku" }`

### GET /V1/rollpix-qr/report
- **Auth**: Bearer token (admin)
- **ACL**: Rollpix_QrLanding::report
- **Service**: Rollpix\QrLanding\Api\ReportInterface::getReport
- **Params query**: from (date Y-m-d), to (date Y-m-d), sku (string, opcional), page (int), pageSize (int)
- **Response**:
```json
{
  "items": [
    { "sku": "ABC123", "product_name": "Producto X", "visit_count": 42, "last_visit": "2026-03-01" }
  ],
  "total_count": 150,
  "daily": [
    { "date": "2026-03-01", "count": 12 }
  ]
}
```

## Lógica del Controller (QR Landing)

**Ruta**: `/qr/{identifier}` (ej: `/qr/ABC123?utm_source=qr_tienda`)

1. Verificar módulo habilitado para store view actual → si no, 404
2. Validar longitud del identifier (máx 64 chars) → si excede, 404
3. Resolver identifier (SKU) a product_id via `ProductRepositoryInterface::get($sku)`
4. Si producto no existe o no visible en store view → redirect a fallback URL (o 404 si fallback vacío)
5. Si tracking habilitado en config → INSERT en `rollpix_qr_visits` (con dedup 5 min por IP+SKU, inputs sanitizados)
6. Obtener URL canónica del producto para la store view actual
7. Construir redirect URL preservando UTM params (utm_source, utm_medium, utm_campaign, utm_term, utm_content)
8. Redirect 302 a la URL del PDP con UTM params

## Convenciones de Código

- Seguir Magento Coding Standards (PSR-12 base)
- Usar inyección de dependencias vía constructor (no ObjectManager directo)
- Interfaces en `Api/` para todo lo que se expone via webapi.xml
- ResourceModel para acceso a DB, nunca queries SQL directas
- Helper/Config.php centraliza lectura de `scopeConfig->getValue()`
- Usar `\Magento\Framework\App\Action\HttpGetActionInterface` en el controller
- Los comentarios de código y los nombres de variables en inglés
- Los textos del admin (system.xml labels) en inglés (Magento standard)
- Composer type: `magento2-module`, autoload PSR-4

## Testing

- Verificar landing `/qr/SKU_EXISTENTE` redirige al PDP correcto
- Verificar landing `/qr/SKU_INEXISTENTE` redirige a fallback URL (o 404 si fallback vacío)
- Verificar que con tracking deshabilitado, no se registra visita
- Verificar que con tracking habilitado, se registra visita automáticamente con UTM params
- Verificar deduplicación: misma IP+SKU dentro de 5 minutos no genera visita duplicada
- Verificar que identifiers de más de 64 chars son rechazados
- Verificar endpoint /status devuelve estado correcto por store view
- Verificar endpoint /report devuelve datos filtrados correctamente
- Verificar que deshabilitar módulo por store view hace que /qr/SKU devuelva 404 en esa store pero funcione en otras
