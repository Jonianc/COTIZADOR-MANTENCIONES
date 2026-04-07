#!/usr/bin/env python3
"""
Utilidad para cargar pautas al catálogo JSON del plugin.

Flujo sugerido para PDFs (LOVOL/FARMTRAC):
1) Extrae texto/tablas del PDF a CSV (puedes usar pdftotext/Excel).
2) Normaliza el CSV con columnas:
   brand_key,brand_label,template_key,template_label,hour,description,part,um,frequency,qty
3) Ejecuta:
   python scripts/pautas_importer.py import-csv --csv rutas.csv --catalog includes/data/pautas.json
"""

from __future__ import annotations

import argparse
import csv
import json
import subprocess
from collections import defaultdict
from pathlib import Path
from typing import Dict, Any

REQUIRED_COLUMNS = {
    "brand_key",
    "brand_label",
    "template_key",
    "template_label",
    "hour",
    "description",
    "part",
    "um",
    "frequency",
    "qty",
}


def load_catalog(path: Path) -> Dict[str, Any]:
    if not path.exists():
        return {"brands": {}}
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        data = {"brands": {}}
    data.setdefault("brands", {})
    return data


def save_catalog(path: Path, data: Dict[str, Any]) -> None:
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def cmd_extract_text(args: argparse.Namespace) -> int:
    pdf = Path(args.pdf)
    out = Path(args.out)

    if not pdf.exists():
        raise SystemExit(f"PDF no encontrado: {pdf}")

    cmd = ["pdftotext", "-layout", str(pdf), str(out)]
    try:
        subprocess.run(cmd, check=True)
    except FileNotFoundError:
        raise SystemExit(
            "No se encontró 'pdftotext'. Instálalo o extrae el PDF manualmente a CSV y usa import-csv."
        )
    print(f"Texto extraído en: {out}")
    return 0


def cmd_import_csv(args: argparse.Namespace) -> int:
    csv_path = Path(args.csv)
    catalog_path = Path(args.catalog)

    if not csv_path.exists():
        raise SystemExit(f"CSV no encontrado: {csv_path}")

    with csv_path.open("r", encoding="utf-8-sig", newline="") as fh:
        reader = csv.DictReader(fh)
        cols = set(reader.fieldnames or [])
        missing = REQUIRED_COLUMNS - cols
        if missing:
            raise SystemExit(f"CSV inválido, faltan columnas: {', '.join(sorted(missing))}")

        grouped: Dict[str, Dict[str, Dict[str, Any]]] = defaultdict(lambda: defaultdict(dict))

        for row in reader:
            brand_key = row["brand_key"].strip()
            brand_label = row["brand_label"].strip() or brand_key
            template_key = row["template_key"].strip()
            template_label = row["template_label"].strip() or template_key
            desc = row["description"].strip()
            part = row["part"].strip()
            um = row["um"].strip() or "Un"
            freq = row["frequency"].strip()

            try:
                hour = int(float(row["hour"].strip()))
            except Exception:
                raise SystemExit(f"Hora inválida en fila: {row}")

            try:
                qty = float(row["qty"].strip())
            except Exception:
                raise SystemExit(f"Cantidad inválida en fila: {row}")

            tpl = grouped[brand_key].setdefault(template_key, {
                "brand_label": brand_label,
                "template_label": template_label,
                "hours": set(),
                "items": {},
            })

            tpl["hours"].add(hour)
            item_id = f"{desc}||{part}||{um}||{freq}"
            item = tpl["items"].setdefault(item_id, {
                "description": desc,
                "part": part,
                "um": um,
                "frequency": freq,
                "qty": {},
            })
            item["qty"][str(hour)] = int(qty) if abs(qty - round(qty)) < 1e-9 else qty

    catalog = load_catalog(catalog_path)
    brands = catalog.setdefault("brands", {})

    for brand_key, templates in grouped.items():
        brand_entry = brands.setdefault(brand_key, {"label": brand_key, "templates": {}})
        if templates:
            # usa el último brand_label leído para ese brand_key
            sample_tpl = next(iter(templates.values()))
            brand_entry["label"] = sample_tpl["brand_label"]
        tpls = brand_entry.setdefault("templates", {})

        for template_key, data in templates.items():
            items = []
            for it in data["items"].values():
                items.append({
                    "description": it["description"],
                    "part": it["part"],
                    "um": it["um"],
                    "frequency": it["frequency"],
                    "qty": it["qty"],
                })

            tpls[template_key] = {
                "label": data["template_label"],
                "hours": sorted(data["hours"]),
                "items": items,
            }

    save_catalog(catalog_path, catalog)
    print(f"Catálogo actualizado: {catalog_path}")
    return 0


def cmd_fill_missing_qty(args: argparse.Namespace) -> int:
    catalog_path = Path(args.catalog)
    catalog = load_catalog(catalog_path)

    selected_brands = {b.strip() for b in (args.brands or '').split(',') if b.strip()}
    if not selected_brands:
        selected_brands = {"lovol", "farmtrac"}

    brands = catalog.get("brands", {})
    missing_keys_added = 0

    for brand_key in selected_brands:
        brand = brands.get(brand_key)
        if not isinstance(brand, dict):
            continue
        templates = brand.get("templates", {})
        if not isinstance(templates, dict):
            continue

        for tpl in templates.values():
            if not isinstance(tpl, dict):
                continue
            hours = [str(int(h)) for h in (tpl.get("hours") or [])]
            items = tpl.get("items") or []
            if not isinstance(items, list):
                continue

            for item in items:
                if not isinstance(item, dict):
                    continue
                qty = item.get("qty")
                if not isinstance(qty, dict):
                    qty = {}
                    item["qty"] = qty
                for hour in hours:
                    if hour not in qty:
                        qty[hour] = 0
                        missing_keys_added += 1

    if missing_keys_added > 0:
        save_catalog(catalog_path, catalog)
        print(f"Catálogo actualizado: {catalog_path} (qty faltantes cargadas: {missing_keys_added})")
    else:
        print("No se detectaron qty faltantes para las marcas indicadas.")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Importador de pautas para el cotizador")
    sub = parser.add_subparsers(dest="command", required=True)

    p_extract = sub.add_parser("extract-text", help="Extrae texto de PDF a TXT con pdftotext")
    p_extract.add_argument("--pdf", required=True, help="Ruta al PDF origen")
    p_extract.add_argument("--out", required=True, help="Ruta al TXT de salida")
    p_extract.set_defaults(func=cmd_extract_text)

    p_import = sub.add_parser("import-csv", help="Importa CSV normalizado al catálogo JSON")
    p_import.add_argument("--csv", required=True, help="CSV normalizado")
    p_import.add_argument("--catalog", required=True, help="Ruta a pautas.json")
    p_import.set_defaults(func=cmd_import_csv)

    p_fill = sub.add_parser(
        "fill-missing-qty",
        help="Completa llaves qty faltantes por hora en pautas (default: LOVOL/FARMTRAC)",
    )
    p_fill.add_argument("--catalog", required=True, help="Ruta a pautas.json")
    p_fill.add_argument(
        "--brands",
        default="lovol,farmtrac",
        help="Marcas separadas por coma (default: lovol,farmtrac)",
    )
    p_fill.set_defaults(func=cmd_fill_missing_qty)

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    return args.func(args)


if __name__ == "__main__":
    raise SystemExit(main())
