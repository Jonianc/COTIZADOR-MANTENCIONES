#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
HOOK_SRC="$REPO_ROOT/agrocampo-cotizador-pdf/scripts/git-hooks/post-commit"
HOOK_DST="$REPO_ROOT/.git/hooks/post-commit"

cp "$HOOK_SRC" "$HOOK_DST"
chmod +x "$HOOK_DST"

echo "Hook instalado en $HOOK_DST"
