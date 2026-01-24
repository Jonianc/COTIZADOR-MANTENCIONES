# Agrocampo – Cotizador PDF (Codex project)

Este repo contiene el plugin WordPress y un entorno local reproducible para iterar rápido (ideal para Codex).

## Requisitos
- Docker + Docker Compose

## Levantar WordPress local
```bash
./scripts/dev-up.sh
./scripts/wp-init.sh
```

- WP: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (admin / admin)
- Formulario (frontend sin theme): http://localhost:8080/agrocampo-cotizador

## Construir ZIP instalable
```bash
./scripts/build-zip.sh
```
Salida:
- `dist/Agrocampo-Cotizador-PDF.zip`

## Flujo recomendado en Codex
1) Editar código en `plugin/agrocampo-cotizador-pdf/`
2) Probar en local con el WP de Docker
3) Generar ZIP con `./scripts/build-zip.sh`
4) Versionar con git tags por release

## Notas de implementación
- Se usa FPDF embebido dentro del plugin (sin depender de otros plugins/temas).
- La descarga de PDF debe ser directa (Content-Disposition: attachment) y con buffers limpiados.

## Prueba manual recomendada
1) Ir a http://localhost:8080/agrocampo-cotizador.
2) Elegir una fecha distinta (para validar el correlativo por día).
3) Completar modelo con acentos/ñ (ej: "Tractor Ñandú 5075") y seleccionar 400 o 500 horas.
4) Agregar ítems con:
   - Detalle largo con acentos y símbolos (ej: "Mantención general, revisión de frenos y correas, cambio de aceite hidráulico y motor").
   - Descuento (ej: 12,5%).
   - Cantidades > 1.
5) En Ajustes del plugin, subir un logo grande y configurar un ancho (ej: 70 mm).
6) Generar PDF y verificar:
   - Título "Modelo + MANTENCION {horas} HORAS".
   - Tabla sin solapes, con columnas V. Total.
   - Logo escalado al ancho configurado.
