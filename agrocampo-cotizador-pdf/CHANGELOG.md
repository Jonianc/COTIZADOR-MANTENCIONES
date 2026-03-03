## 1.3.33 (2026-03-03)
- Corrige autosave en cambios programáticos de tipo de cotización: `setQuoteTypeUI()` ahora solo guarda borrador cuando se invoca con `autosave: true`.
- Evita re-crear borradores en inicialización y flujo `PREFILL`, eliminando la carrera con `clearLocalStorage()` y el prompt de recuperación innecesario.

## 1.3.32 (2026-03-03)
- Agrega tipo de cotización **Insumos** en el formulario frontend.
- Modo Insumos funciona similar a Reparación: sin pauta ni set/tipo de horas.
- En Insumos no se muestran campos/costos de mano de obra, traslado ni servicios externos.
- Backend/PDF ahora aceptan `quote_type=insumos` y generan título `MODELO INSUMOS`.

## 1.3.31 (2026-02-13)
- UX Gestor (iteración 2): tabla del gestor con clase dedicada (`gestor-table`) y mejoras de escaneo visual (zebra suave, hover más claro, acciones con mejor estabilidad de ancho).
- UX filtros/paginación: barra sticky con mejor jerarquía visual y foco accesible; paginación con estado actual más visible.
- Ajustes responsive del gestor: sticky header de tabla desactivado en móvil y ancho de acciones optimizado para pantallas pequeñas.

## 1.3.30 (2026-02-13)
- Ajusta empaquetado de release: `build_release_zip.py` ahora genera el ZIP por defecto fuera de la carpeta del plugin (`dist-releases/` a nivel repo).
- Permite configurar ruta de salida por `--out-dir` o variable de entorno `ACPDF_RELEASE_OUT_DIR`.
- Mejora hook `post-commit`: ahora empaqueta solo cuando detecta cambio real de versión en `agrocampo-cotizador-pdf.php`.

## 1.3.29 (2026-02-13)
- Mejora UX del Gestor: acciones de fila ahora incluyen texto + ícono (Ver PDF, Editar, Duplicar, Eliminar) para mayor claridad.
- Mejora barra de filtros del Gestor: estilo sticky, borde visual y mensaje de atajo (Enter para filtrar).
- Ajusta layout responsive de acciones del Gestor para móviles (acciones en columna y ancho completo).

## 1.3.28 (2026-02-13)
- Agrega script de release `scripts/build_release_zip.py` para generar automáticamente ZIP versionado del plugin (`dist/agrocampo-cotizador-pdf-vX.Y.Z.zip`).
- Agrega hook local opcional (`scripts/git-hooks/post-commit`) + instalador (`scripts/install_git_hook.sh`) para empaquetar ZIP automáticamente en commits que incluyan cambio de versión.
- Documenta flujo de empaquetado manual/automático en `scripts/README_RELEASE_ZIP.md`.

## 1.3.27 (2026-02-13)
- Con pauta activa en LOVOL/FARMTRAC (además de MF), el selector "Set de horas" ahora se bloquea por pauta inferida (A/B) igual que en Massey Ferguson.
- Ajusta etiqueta visual del set con pauta activa para mostrar rango desde 100h (sin "10–50") y mantener coherencia con "Tipo mantención (horas)".

## 1.3.26 (2026-02-13)
- Con pauta precargada en MF/LOVOL/FARMTRAC, el selector de "Tipo mantención (horas)" ahora excluye 10 y 50 y ofrece horas desde 100h, replicando la validación esperada para set de horas.
- Mantiene el rango completo por set A/B para pautas (desde 100h hasta 4800/5000 según set).

## 1.3.25 (2026-02-13)
- Ajusta el overlay "Generando PDF..." en envío de formulario: evita que quede visible por largo tiempo cuando la descarga del PDF inicia de inmediato.
- Reemplaza el timeout fijo largo por cierre rápido controlado y agrega eventos de recuperación (`pageshow`, `focus`, `visibilitychange`) para ocultar el overlay cuando el usuario vuelve a la vista.

## 1.3.24 (2026-02-13)
- Corrige selector de "Tipo mantención (horas)" para LOVOL y FARMTRAC con pauta activa: ahora respeta el set A/B completo (no queda truncado en 1200).
- La precarga por frecuencia en LOVOL/FARMTRAC se expande sobre todo el rango del set seleccionado (ej. Set A hasta 4800), alineado con la tabla "Cantidad Unidad y/o Litros por Tractor por horas" de las pautas.

## 1.3.23 (2026-02-13)
- Agrega comando `fill-missing-qty` al importador para completar automáticamente cantidades `qty` faltantes por hora en pautas LOVOL/FARMTRAC.
- Ejecuta validación/carga de faltantes en `pautas.json`; no se detectaron llaves `qty` faltantes para LOVOL/FARMTRAC con los datos actuales.

## 1.3.22 (2026-02-11)
- Agrega utilitario de importación de pautas para LOVOL/FARMTRAC desde flujo PDF -> CSV -> `pautas.json`.
- Incluye guía rápida de uso para extracción de texto e importación al catálogo del plugin.

## 1.3.21 (2026-02-11)
- Corrige actualización dinámica del campo Modelo: con pauta activa se sincroniza automáticamente con la plantilla seleccionada.
- Mejora coherencia UI/UX del formulario: Modelo queda de solo lectura al usar pauta y vuelve editable al quitarla/cambiar marca.

## 1.3.20 (2026-02-11)
- Elimina la tabla "Cantidad por Máquina (pauta)" de ambos PDFs (interno y cliente).
- Ajusta el bloque de encabezado: limita/ancla el logo para evitar que desplace visualmente el título de la cotización.

## 1.3.19 (2026-02-11)
- Oculta la tabla "Cantidad por Máquina (pauta)" en el PDF interno y la mantiene solo para PDF cliente.
- Evita el bloque adicional que se estaba montando sobre la zona de totales/datos del vendedor en el interno.

## 1.3.18 (2026-02-11)
- Con pauta MF activa, el selector de set muestra solo el set correspondiente (A o B) inferido por plantilla y bloquea el alternativo.
- Mantiene la precarga de ítems periódicos por frecuencia (incluyendo 2000h) con el set correcto aplicado desde la selección de pauta.

## 1.3.17 (2026-02-11)
- Corrige asignación de set en pautas MF: ahora se infiere automáticamente desde la pauta (ej. MF7S-155T4K_CL usa Set B, evitando fallback a Set A).
- Ajusta expansión de cantidades por frecuencia para no perder ítems periódicos cuando la pauta trae `qty` en cero (caso visible en 2000h).

## 1.3.16 (2026-02-11)
- Ajusta pauta MF Serie 7S para usar rango completo del set de horas seleccionado (100 a 5000 en Set B), manteniendo precarga y cálculo de ítems por frecuencia.
- Revierte el recorte por `raw.hours` en MF que ocultaba horas válidas como 2000+ y podía aparentar falta de ítems.

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
