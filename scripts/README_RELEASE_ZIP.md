# Empaquetado automático de ZIP por versión

## Generación manual

Desde la raíz del repo:

```bash
python3 agrocampo-cotizador-pdf/scripts/build_release_zip.py
```

Salida esperada por defecto:

- `dist-releases/agrocampo-cotizador-pdf-vX.Y.Z.zip` (fuera de la carpeta del plugin).

También puedes personalizar la salida:

```bash
python3 agrocampo-cotizador-pdf/scripts/build_release_zip.py --out-dir /ruta/salidas
```

o vía variable de entorno:

```bash
ACPDF_RELEASE_OUT_DIR=/ruta/salidas python3 agrocampo-cotizador-pdf/scripts/build_release_zip.py
```

## Automatizar en cada nueva versión

Instala el hook local de git (una sola vez por clon):

```bash
bash agrocampo-cotizador-pdf/scripts/install_git_hook.sh
```

Con el hook `post-commit` instalado:

- Cada vez que un commit incluya cambios en `agrocampo-cotizador-pdf/agrocampo-cotizador-pdf.php`,
- y detecte cambio real de versión (`X.Y.Z`),
- se ejecutará automáticamente el empaquetado del ZIP versionado.

## Notas

- El ZIP excluye carpetas de desarrollo (`.git`, `.github`, `node_modules`, `dist`, `__pycache__`).
- También excluye artefactos temporales (`*.pyc`, `*.zip`).
