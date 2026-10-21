# 001. Alias `qr_*` para que la campaña sobreviva a Varnish

**Date:** 2026-10-21
**Status:** Accepted

## Context

El VCL que genera Magento (`varnish:vcl:generate`) hace, en `vcl_recv`, un `regsuball` sobre `req.url` que
borra `utm_*`, `gclid`, `fbclid`, `mc_*`, `origin`, `ie`, `cx` y otros, para no fragmentar la caché.
Comprobado en ishop30 (Tienda IMCO, Egger, WE-55819): `/qr/SKU?utm_source=x` llega a PHP como `/qr/SKU`, la
visita se guarda sin campaña y la redirección sale sin `utm_*`, así que Analytics del destino no la atribuye.

## Decision

Aceptar los mismos cinco campos con prefijo `qr_` (no están en la lista del VCL), guardarlos en las columnas
`utm_*` y reemitirlos como `utm_*` en el `Location`. Prioridad: `utm_*` si llegó (sitios sin Varnish, QR ya
impresos), si no `qr_*`.

Alternativa descartada: excluir `/qr/` de la regla del VCL. Es infraestructura compartida por todos los
sitios del box y depende de IT; el alias funciona en cualquier server sin tocar nada.

## Consequences

### Positive
- La campaña llega al módulo y al Analytics del destino detrás de cualquier Varnish de Magento.
- Compatible hacia atrás: los QR con `utm_*` se comportan igual que antes.

### Negative
- Los generadores de QR/links (QR Manager, Rollpix_CrossSiteRedirect) tienen que usar `qr_*` para aprovecharlo.

### Neutral
- `qr_origin` no sirve: `origin` está en la lista y la regex del VCL no está anclada al nombre del parámetro.
