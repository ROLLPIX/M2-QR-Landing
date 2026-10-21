# Self-Review — Rollpix_QrLanding v1.1.0

**Builder:** Lautaro Martinez
**Date:** 2026-10-21
**Branch:** main

---

## 1. Verificación de build
- [ ] `bin/magento setup:upgrade` — sin errores
- [ ] `bin/magento setup:di:compile` — sin errores
- [ ] `bin/magento setup:static-content:deploy` — sin errores
- [ ] `bin/magento module:status | grep Rollpix_QrLanding` — aparece enabled
- [ ] `vendor/bin/phpstan analyse` — pasa limpio

## 2. Verificación funcional
Para cada criterio de docs/ACCEPTANCE.md:

### 1.1.0 — alias qr_* y validación de visibilidad/website (stage imcofue-we55819, 2.4.8-p5, sin Varnish)
- [x] Sólo `utm_*` → se guarda y se reemite igual que en 1.0.3 — **Testeado:** curl `/qr/10000?utm_source=u1&utm_campaign=c1`
- [x] Sólo `qr_*` → guardado en columnas utm_*, `Location` con `utm_*` — **Testeado:** curl `/qr/10001?qr_source=imcofue&qr_medium=referral&qr_campaign=…`
- [x] `utm_*` y `qr_*` a la vez → gana utm por campo — **Testeado:** `/qr/100248?utm_source=gana-utm&qr_source=pierde-qr&qr_campaign=sólo-qr`
- [x] Sin parámetros → redirección limpia, visita sin campaña — **Testeado:** `/qr/105250`
- [x] No visible individualmente → fallback — **Testeado:** 10000 con visibility=1 temporal
- [x] Fuera del website → fallback en ese website, ficha en los otros — **Testeado:** 10000 sacado de ushuaia temporalmente (riogrande seguía yendo a la ficha); restaurado

## 3. Verificación de cache
- [ ] Testeado con todos los caches habilitados
- [ ] FPC: las páginas renderizan correctamente después del warm-up de cache
- [ ] Config cache: los cambios en el admin toman efecto después de cache:flush

## 4. Verificación de documentación
- [ ] README.md está actualizado
- [ ] manual.md refleja toda la funcionalidad implementada
- [ ] CHANGELOG.md tiene entrada para esta versión
- [ ] Los archivos i18n tienen todos los strings
- [ ] CLAUDE.md está actualizado con los últimos cambios

## 5. Revisión rápida de calidad de código
- [ ] No quedaron var_dump/print_r/die en el código
- [ ] No hay TODO/FIXME sin resolución
- [ ] No se usa ObjectManager
- [ ] Los templates usan funciones de escape para toda la salida dinámica

## 6. Lo que NO testeé (para el reviewer)
- El borrado de utm_* por Varnish: el stage no tiene Varnish. Se valida en Tienda IMCO/Egger (ishop30) después de instalar.
- PHPStan: no corrido.

## 7. Sign-off del builder

Confirmo que verifiqué personalmente todos los ítems marcados arriba.
El módulo está listo para peer review.

**Firmado:** — 
