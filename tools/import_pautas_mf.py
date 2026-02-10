#!/usr/bin/env python3
"""Importador de pautas MF desde PDF (sin dependencias externas).

Objetivo:
- Dejar un proceso versionado/re-ejecutable para auditar e importar cambios desde `PAUTAS MF.pdf`.
- Funciona con extracción de streams PDF + normalización heurística.

Uso:
  python tools/import_pautas_mf.py \
    --pdf "PAUTAS MF.pdf" \
    --catalog "agrocampo-cotizador-pdf/includes/data/pautas.json" \
    --report "tools/pautas_mf_report.json"

Opcional:
  --mapping tools/pautas_mf_mapping.json
    Permite mapear encabezados de plantilla detectados en PDF a template_key explícita.
"""

from __future__ import annotations

import argparse
import json
import re
import unicodedata
import zlib
from pathlib import Path
from typing import Dict, List


def _unescape_pdf_literal(raw: bytes) -> bytes:
    out = bytearray()
    i = 0
    while i < len(raw):
        c = raw[i]
        if c == 92 and i + 1 < len(raw):  # backslash
            n = raw[i + 1]
            if n in b"()\\":
                out.append(n)
                i += 2
                continue
            if n in b"nrtbf":
                out.append({ord("n"): 10, ord("r"): 13, ord("t"): 9, ord("b"): 8, ord("f"): 12}[n])
                i += 2
                continue
            if 48 <= n <= 55:
                j = i + 1
                octal = []
                while j < len(raw) and len(octal) < 3 and 48 <= raw[j] <= 55:
                    octal.append(raw[j])
                    j += 1
                out.append(int(bytes(octal), 8))
                i = j
                continue
        out.append(c)
        i += 1
    return bytes(out)


def _decode_pdf_token(token: bytes) -> str:
    if not token:
        return ""
    if len(token) % 2 == 0 and token.count(0) > len(token) // 6:
        try:
            return token.decode("utf-16-be")
        except UnicodeDecodeError:
            pass
    for enc in ("utf-8", "latin1"):
        try:
            return token.decode(enc)
        except UnicodeDecodeError:
            continue
    return ""


def _extract_lines_from_pdf(pdf_path: Path) -> List[str]:
    data = pdf_path.read_bytes()
    lines: List[str] = []

    for m in re.finditer(rb"stream\r?\n", data):
        start = m.end()
        end = data.find(b"endstream", start)
        if end < 0:
            continue

        raw = data[start:end].strip(b"\r\n")
        try:
            stream = zlib.decompress(raw)
        except Exception:
            continue

        for tj_m in re.finditer(rb"\((?:\\.|[^\\)])*\)\s*Tj", stream):
            lit = tj_m.group(0).rsplit(b")", 1)[0][1:]
            txt = _decode_pdf_token(_unescape_pdf_literal(lit)).strip()
            if txt:
                lines.append(txt)

        for arr_m in re.finditer(rb"\[(.*?)\]\s*TJ", stream, re.S):
            arr = arr_m.group(1)
            parts: List[str] = []
            for tok_m in re.finditer(rb"\((?:\\.|[^\\)])*\)|<([0-9A-Fa-f]+)>", arr):
                tok = tok_m.group(0)
                if tok.startswith(b"("):
                    bb = _unescape_pdf_literal(tok[1:-1])
                else:
                    try:
                        bb = bytes.fromhex(tok_m.group(1).decode())
                    except Exception:
                        continue
                txt = _decode_pdf_token(bb)
                if txt.strip():
                    parts.append(txt)
            line = "".join(parts).strip()
            if line:
                lines.append(line)

    return lines


def _normalize_for_match(s: str) -> str:
    # PDF de origen trae varias páginas con glifos no estándar;
    # normalizamos para poder hacer matching auditable.
    out: List[str] = []
    for ch in s:
        if ch.isascii():
            out.append(ch)
            continue
        try:
            out.append(str(unicodedata.digit(ch)))
            continue
        except Exception:
            pass
        cat = unicodedata.category(ch)
        if cat.startswith("L"):
            out.append(ch)
        elif ch in {"-", "_", "/", " ", "(" , ")"}:
            out.append(ch)
    text = "".join(out)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _template_headers(lines: List[str]) -> List[str]:
    headers = []
    for ln in lines:
        if '6HULH' in ln and ln.startswith('0)'):
            headers.append(ln.encode('unicode_escape').decode('ascii'))
        elif 'Serie' in ln and 'MF' in ln:
            headers.append(_normalize_for_match(ln))
    seen = set()
    unique = []
    for h in headers:
        if h and h not in seen:
            seen.add(h)
            unique.append(h)
    return unique


def _collect_frequencies(lines: List[str]) -> List[int]:
    vals = set()
    for ln in lines:
        for m in re.finditer(r"\((\d{2,5})\)", ln):
            n = int(m.group(1))
            if 50 <= n <= 10000:
                vals.add(n)
    return sorted(vals)


def _load_mapping(mapping_file: Path | None) -> Dict[str, str]:
    if not mapping_file or not mapping_file.exists():
        return {}
    data = json.loads(mapping_file.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise ValueError("El mapping debe ser objeto JSON {header_detectado: template_key}")
    return {str(k): str(v) for k, v in data.items()}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--pdf", required=True)
    ap.add_argument("--catalog", required=True)
    ap.add_argument("--report", required=True)
    ap.add_argument("--mapping", required=False)
    args = ap.parse_args()

    pdf = Path(args.pdf)
    catalog_file = Path(args.catalog)
    report_file = Path(args.report)
    mapping_file = Path(args.mapping) if args.mapping else None

    if not pdf.exists():
        raise SystemExit(f"No existe PDF: {pdf}")
    if not catalog_file.exists():
        raise SystemExit(f"No existe catálogo: {catalog_file}")

    catalog = json.loads(catalog_file.read_text(encoding="utf-8"))
    templates = (catalog.get("brands", {}).get("massey_ferguson", {}) or {}).get("templates", {})
    template_keys = set(templates.keys())

    lines = _extract_lines_from_pdf(pdf)
    headers = _template_headers(lines)
    freqs = _collect_frequencies(lines)
    mapping = _load_mapping(mapping_file)

    mapped = {h: mapping[h] for h in headers if h in mapping}
    unknown_headers = [h for h in headers if h not in mapping]
    missing_targets = sorted({v for v in mapped.values() if v not in template_keys})

    report = {
        "pdf": str(pdf),
        "detected_template_headers": headers,
        "detected_template_headers_count": len(headers),
        "detected_frequencies": freqs,
        "mapped_headers": mapped,
        "unknown_headers": unknown_headers,
        "missing_template_keys_in_catalog": missing_targets,
        "catalog_template_count_massey_ferguson": len(template_keys),
        "note": "El importador queda versionado para re-ejecutar auditorías y futuras importaciones cuando cambie el PDF.",
    }

    report_file.parent.mkdir(parents=True, exist_ok=True)
    report_file.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"Reporte generado: {report_file}")
    print(f"Headers detectados: {len(headers)}")
    print(f"Frecuencias detectadas: {len(freqs)}")
    if missing_targets:
        print("Template keys faltantes en catálogo:", ", ".join(missing_targets))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
