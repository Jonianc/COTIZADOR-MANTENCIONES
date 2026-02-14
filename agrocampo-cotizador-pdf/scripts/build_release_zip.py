#!/usr/bin/env python3
"""Empaqueta el plugin en un ZIP versionado para releases."""

from __future__ import annotations

import argparse
import os
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPO_ROOT = ROOT.parent
PLUGIN_MAIN = ROOT / "agrocampo-cotizador-pdf.php"

EXCLUDE_DIRS = {
    ".git",
    ".github",
    "node_modules",
    "dist",
    "__pycache__",
    ".idea",
    ".vscode",
}

EXCLUDE_SUFFIXES = {
    ".zip",
    ".pyc",
}


def read_version() -> str:
    text = PLUGIN_MAIN.read_text(encoding="utf-8")
    m_header = re.search(r"^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", text, re.M)
    m_const = re.search(r"define\('ACPDF_VER',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\);", text)

    if not m_header or not m_const:
        raise SystemExit("No se pudo leer la versión del plugin.")

    if m_header.group(1) != m_const.group(1):
        raise SystemExit("Version header y ACPDF_VER no coinciden.")

    return m_header.group(1)


def should_skip(path: Path) -> bool:
    parts = set(path.parts)
    if parts & EXCLUDE_DIRS:
        return True
    if path.name.startswith(".") and path.name not in {".htaccess"}:
        return True
    if path.suffix.lower() in EXCLUDE_SUFFIXES:
        return True
    return False


def build_zip(output: Path) -> Path:
    output.parent.mkdir(parents=True, exist_ok=True)

    base_dir_name = ROOT.name
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for file_path in sorted(ROOT.rglob("*")):
            if file_path.is_dir() or should_skip(file_path.relative_to(ROOT)):
                continue
            rel = file_path.relative_to(ROOT)
            arc = Path(base_dir_name) / rel
            zf.write(file_path, arcname=str(arc))
    return output


def resolve_out_dir(raw: str) -> Path:
    out = Path(raw).expanduser()
    if out.is_absolute():
        return out
    return REPO_ROOT / out


def main() -> int:
    parser = argparse.ArgumentParser(description="Genera ZIP versionado del plugin")
    parser.add_argument(
        "--out-dir",
        default=os.getenv("ACPDF_RELEASE_OUT_DIR", "dist-releases"),
        help="Directorio de salida (relativo al repo o absoluto)",
    )
    args = parser.parse_args()

    version = read_version()
    out_dir = resolve_out_dir(args.out_dir)
    out_file = out_dir / f"{ROOT.name}-v{version}.zip"

    built = build_zip(out_file)
    print(f"ZIP generado: {built}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
