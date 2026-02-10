# Tools

## Importador `PAUTAS MF.pdf`

Script: `tools/import_pautas_mf.py`

### ¿Qué hace?
- Extrae texto del PDF sin dependencias externas.
- Detecta encabezados de plantillas y frecuencias encontradas.
- Genera un reporte JSON para auditoría/re-ejecución cuando cambie el PDF.

### Uso

```bash
python tools/import_pautas_mf.py \
  --pdf "PAUTAS MF.pdf" \
  --catalog "agrocampo-cotizador-pdf/includes/data/pautas.json" \
  --report "tools/pautas_mf_report.json"
```

Opcionalmente puedes pasar un mapping manual:

```bash
python tools/import_pautas_mf.py \
  --pdf "PAUTAS MF.pdf" \
  --catalog "agrocampo-cotizador-pdf/includes/data/pautas.json" \
  --report "tools/pautas_mf_report.json" \
  --mapping "tools/pautas_mf_mapping.json"
```

Usa `tools/pautas_mf_mapping.example.json` como referencia.
