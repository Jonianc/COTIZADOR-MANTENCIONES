# Importar pautas desde PDF (LOVOL / FARMTRAC)

Este plugin usa `includes/data/pautas.json` como catálogo de pautas.

## 1) Extraer texto desde PDF

```bash
python scripts/pautas_importer.py extract-text \
  --pdf /ruta/a/pautas_lovol_farmtrac.pdf \
  --out /tmp/pautas_lovol_farmtrac.txt
```

> Requiere `pdftotext`.

## 2) Normalizar a CSV

Genera un CSV con estas columnas exactas:

- `brand_key`
- `brand_label`
- `template_key`
- `template_label`
- `hour`
- `description`
- `part`
- `um`
- `frequency`
- `qty`

## 3) Importar al catálogo JSON

```bash
python scripts/pautas_importer.py import-csv \
  --csv /ruta/a/pautas_normalizadas.csv \
  --catalog includes/data/pautas.json
```

## Notas

- El importador crea/actualiza marcas y plantillas por `brand_key` + `template_key`.
- Para conservar historial, respalda `includes/data/pautas.json` antes de importar.
- Luego valida en UI que las marcas y pautas nuevas aparezcan correctamente.


## 4) Completar cantidades faltantes por hora

Para asegurar consistencia de `qty` (todas las horas declaradas en la plantilla deben existir como llave):

```bash
python scripts/pautas_importer.py fill-missing-qty   --catalog includes/data/pautas.json   --brands lovol,farmtrac
```

