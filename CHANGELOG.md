# Changelog

Todos los cambios notables en este módulo se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.1.0] - 2026-10-21

### Added
- Alias de seguimiento `qr_source`, `qr_medium`, `qr_campaign`, `qr_term`, `qr_content`: el VCL de Varnish de
  Magento borra los `utm_*` antes de PHP, y estos sí llegan. Se guardan en las columnas `utm_*` y se reemiten
  como `utm_*` en la redirección. Si llegan los dos, gana `utm_*` (WE-55819).

### Fixed
- Un producto no visible individualmente o no asignado al website actual ya no redirige a una ficha que da
  404: va a la URL de fallback.

### Changed
- Compatibilidad declarada: Magento 2.4.5 – 2.4.8 (en uso en 2.4.5-p12 y 2.4.8-p5).

## [1.0.3]
- Endpoint de visitas crudas, conteo diario de productos únicos.

## [1.0.2]
- URL de fallback y endurecimiento de seguridad.

## [1.0.1]
- Router movido al área frontend para que `/qr/{sku}` resuelva.

## [1.0.0]
- Versión inicial.
