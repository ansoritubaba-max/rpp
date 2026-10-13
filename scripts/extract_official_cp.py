#!/usr/bin/env python3
"""Build a reviewable CP dataset from the official 046/H/KR/2025 PDF.

This is a development-time tool only. The Laravel seeder reads the generated
JSON and never needs Python, a network connection, or a PDF parser on hosting.
"""

import argparse
import bisect
import json
import re
from pathlib import Path

from pypdf import PdfReader


SOURCE_URL = (
    "https://bpmpbabel.kemendikdasmen.go.id/PPID/wp-content/uploads/"
    "2025/07/KepKaBSKAP-046_2025-ttg-CP.pdf"
)
SPECTRUM_URL = "https://kurikulum.kemendikdasmen.go.id/service/download.php?id=95&kategori=rujukan"
HEADING = re.compile(
    r"(?P<section>(?:[IVX]+(?:\.\d+)?)\.?)\s+CAPAIAN PEMBELAJARAN\s+"
    r"(?P<title>[A-Z][A-Z0-9 ,/&()\-–.]+?)\s+A\.\s+Rasional\b"
)
PHASE = re.compile(r"(?<!\w)(?:[1-6]\.\s*)?Fase\s+([A-F])\s*\(", re.I)
END_OF_CP = re.compile(r"\bD\.\s+Capaian Pembelajaran\b", re.I)
RELIGIOUS_TITLES = (
    "PENDIDIKAN AGAMA ",
    "PENDIDIKAN KHUSUS PENDIDIKAN AGAMA ",
)


def normalize(value: str) -> str:
    return " ".join(value.split())


def spectrum_mapping(spectrum_path: Path) -> dict[int, int]:
    reader = PdfReader(str(spectrum_path))
    # Lampiran I occupies PDF pages 4-9. Lampiran II is a conversion table and
    # must not be included: it repeats the same codes in a different order.
    text = " ".join((reader.pages[index].extract_text() or "") for index in range(3, 9))
    codes = re.findall(r"(?<!\d)(\d+\.\d+(?:\.\d+)?)(?!\d)", text)
    programs = list(dict.fromkeys(code for code in codes if code.count(".") == 1))
    concentrations = list(dict.fromkeys(code for code in codes if code.count(".") == 2))
    if len(programs) != 50 or len(concentrations) != 128:
        raise ValueError("Unexpected SMK spectrum structure; review the source PDF before regenerating.")
    return {
        index: programs.index(".".join(code.split(".")[:2])) + 1
        for index, code in enumerate(concentrations, start=1)
    }


def extract(pdf_path: Path, spectrum_path: Path) -> dict:
    vocational_mapping = spectrum_mapping(spectrum_path)
    reader = PdfReader(str(pdf_path))
    page_texts = [normalize(page.extract_text() or "") for page in reader.pages[:1190]]
    offsets = [0]
    for page_text in page_texts:
        offsets.append(offsets[-1] + len(page_text) + 1)
    text = " ".join(page_texts)
    headings = list(HEADING.finditer(text))
    entries = []
    skipped = []

    for position, heading in enumerate(headings):
        section = heading.group("section").rstrip(".")
        page = bisect.bisect_right(offsets, heading.start())
        if page < 23 or page > 1190:
            continue
        title = normalize(heading.group("title")).split(" CAPAIAN PEMBELAJARAN ")[0]
        if title.startswith(RELIGIOUS_TITLES):
            skipped.append({"section": section, "title": title, "reason": "2026 religious CP amendment"})
            continue
        end = headings[position + 1].start() if position + 1 < len(headings) else len(text)
        body = text[heading.end():end]
        cp_mark = END_OF_CP.search(body)
        if not cp_mark:
            skipped.append({"section": section, "title": title, "reason": "no D. Capaian Pembelajaran section"})
            continue
        cp = body[cp_mark.end():].strip()
        markers = list(PHASE.finditer(cp))
        phases = {}
        if markers:
            for index, marker in enumerate(markers):
                finish = markers[index + 1].start() if index + 1 < len(markers) else len(cp)
                phase = marker.group(1).upper()
                content = cp[marker.start():finish].strip()
                if len(content) >= 80:
                    phases[phase] = content
        else:
            match = re.search(r"Pada akhir (?:[Ff]ase|fase)\s+([A-F])", cp)
            if match and len(cp) >= 80:
                phases[match.group(1).upper()] = cp
        if not phases:
            skipped.append({"section": section, "title": title, "reason": "no phase text"})
            continue
        entries.append({
            "section": section,
            "title": title,
            "page": page,
            "phases": phases,
            **({"basic_section": f"V.{vocational_mapping[int(section.split('.')[1])]}"}
               if page >= 348 and section.startswith("VI.") else {}),
        })

    return {
        "source": SOURCE_URL,
        "spectrum_source": SPECTRUM_URL,
        "decision": "046/H/KR/2025",
        "note": "Religious CP omitted: amended by BKPDM decision 020/2026.",
        "entries": entries,
        "skipped": skipped,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--pdf", required=True, type=Path)
    parser.add_argument("--spectrum-pdf", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    data = extract(args.pdf, args.spectrum_pdf)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Extracted {len(data['entries'])} sections; skipped {len(data['skipped'])}.")
    for item in data["skipped"]:
        print(f"SKIP {item['section']} {item['title']}: {item['reason']}")


if __name__ == "__main__":
    main()
