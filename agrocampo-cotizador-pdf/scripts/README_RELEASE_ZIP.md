# Empaquetado automático de ZIP por versión

## Generación manual

Desde la raíz del repo:

```bash
python3 agrocampo-cotizador-pdf/scripts/build_release_zip.py
```

Salida esperada:

- `agrocampo-cotizador-pdf/dist/agrocampo-cotizador-pdf-vX.Y.Z.zip`

## Automatizar en cada nueva versión

Instala el hook local de git (una sola vez por clon):

```bash
bash agrocampo-cotizador-pdf/scripts/install_git_hook.sh
```

Con el hook `post-commit` instalado:

- Cada vez que un commit incluya cambios en `agrocampo-cotizador-pdf/agrocampo-cotizador-pdf.php` (donde vive la versión),
- se ejecutará automáticamente el empaquetado del ZIP versionado.

## Notas

- El ZIP excluye carpetas de desarrollo (`.git`, `.github`, `node_modules`, `dist`, `__pycache__`).
- También excluye artefactos temporales (`*.pyc`, `*.zip`).
