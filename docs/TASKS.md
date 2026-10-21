# TASKS — Rollpix_QrLanding

To-do vivo de la sesión actual de trabajo. Se mantiene actualizado durante el desarrollo.
No es un historial — para historial está git log.

## En progreso
- 1.1.0: alias `qr_*` + validación de visibilidad/website (WE-55819)

## Pendiente
- Release 1.1.0 (tag + GitHub release para RCPM) e instalación en Tienda IMCO y Egger
- QR Manager: generar los QR nuevos con `qr_*`

## Hecho (sesión actual)
- Probado en el stage efímero (4 casos de tracking + visibilidad + website), ver docs/SELF-REVIEW.md
- Controller: `resolveTrackingParams()` (utm_* > qr_*, siempre reemitido como utm_*), `canShowInStore()`
- README EN/ES, CLAUDE.md, CHANGELOG, ADR 001
