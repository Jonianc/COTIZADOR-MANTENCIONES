## 1.3.13 (2026-02-10)
- Agrega importador versionado en `tools/import_pautas_mf.py` para auditar/re-ejecutar extracción desde `PAUTAS MF.pdf`.
- Agrega documentación de uso en `tools/README.md` y ejemplo de mapping manual.

## 1.3.12 (2026-02-10)
- Habilita modo Manual de horas incluso con pauta activa para cotizar mantenciones fuera de los sets A/B.
- Ajusta la lógica de cantidad por ítem para reutilizar la frecuencia inferior más cercana cuando la hora manual no existe en la pauta.


## 1.3.11 (2026-02-08)
- Actualiza el título del PDF al escribir horas manuales.

## 1.3.10 (2026-02-08)
- Agrega filtro por vendedor y agrupación visual en el gestor.

## 1.3.9 (2026-02-08)
- Incluye tipo de cotización reparación y campos asociados en el PDF.

## 1.3.8 (2026-02-08)
- Corrige watermark para que no altere el flujo de la tabla en PDF.

## 1.3.7 (2026-02-08)
- Mejora UX/UI del formulario con secciones, ayudas y placeholders.
- Validaciones más claras con mensajes en campo y resumen de errores.
- Ajusta validaciones para reparación (costos adicionales) y mantención (horas).

## 1.3.6 (2026-02-05)
- Alinea versión interna del plugin con el header.

## 1.3.5 (2026-02-05)
- Agrega tipo de cotización Mantención/Reparación (reparación sin precarga).
- Modo reparación: campos de falla/diagnóstico y costos (mano de obra, traslado, servicios externos) inyectados como líneas.
- Modo manual en Set de horas: input de horas y ocultar Tipo mantención (horas).
# Changelog

## 1.3.3
- Mueve el changelog a un archivo independiente.

## 1.3.2
- Corrige el botón de selección de logo en Ajustes (Media Library).

## 1.3.1
- Corrige la legibilidad de la tabla PDF con altura de fila dinámica y saltos de página con encabezado.

## 1.3.0
- Permite letras en el campo Serie y su impresión en PDF.
- Oculta el watermark en PDFs con repuestos alternativos.
- Ajusta tamaños, alineaciones, márgenes y tabla en la plantilla PDF.
