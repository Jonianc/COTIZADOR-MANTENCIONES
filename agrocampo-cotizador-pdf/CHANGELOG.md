## 1.3.15 (2026-02-11)
- Con pauta activa, el selector de horas respeta el rango definido por cada pauta (evita mostrar horas fuera de alcance del modelo).
- Corrige la incoherencia en Serie 7S (ej. MF7S-155T4K_CL): ya no se extiende a 2000/4800 cuando la pauta llega hasta 1500, y aplica el mismo criterio al resto de modelos con pauta.


## 1.3.14 (2026-02-10)
- "Cantidad por Máquina" en PDF interno: columnas completas según set A/B (desde 100h; sin 10/50).
- Set de horas A/B ahora aplica incluso con pauta activa (no se desactiva el selector).
- Set B incluye 10/50 en el selector de horas (como en pautas MF).

## 1.3.12 (2026-02-10)
- Completa los sets de horas (incluye 10/50 y rangos hasta 4800/5000 según set).

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
