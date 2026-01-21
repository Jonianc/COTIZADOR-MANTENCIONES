#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/plugin/agrocampo-cotizador-pdf"
DIST_DIR="$ROOT_DIR/dist"
OUT_ZIP="$DIST_DIR/Agrocampo-Cotizador-PDF.zip"

mkdir -p "$DIST_DIR"
rm -f "$OUT_ZIP"

(
  cd "$ROOT_DIR/plugin"
  # Clean common junk
  find . -name ".DS_Store" -delete || true
  find . -name "__MACOSX" -type d -prune -exec rm -rf {} + || true

  zip -r "$OUT_ZIP" "agrocampo-cotizador-pdf" \
    -x "*/.git/*" "*/node_modules/*" "*/vendor/*" "*/dist/*" "*/.DS_Store"
)

echo "OK -> $OUT_ZIP"
