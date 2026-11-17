#!/usr/bin/env python3
"""Parse the program PDF (pdftotext -layout output) into structured JSON.

Usage: python3 -I tools/parse_program.py <prog.txt> <out.json>

The output is meant for PRIVATE import into the app only — it must never be
committed or seeded as public content.
"""
import json
import re
import sys

ROW = re.compile(
    r"^(?P<indent>\s*)(?P<name>[A-Z0-9][A-Z0-9 ()/'’.&\-]*?)\s{2,}"
    r"(?P<sets>\d+)\s+(?P<reps>\d+(?:SEC)?)\s+(?P<rpe>\d+(?:\.\d+)?)\s+"
    r"(?P<rmin>\d+)-(?P<rmax>\d+)(?:MIN|min)(?P<rest>.*)$"
)
HEADER = re.compile(r"^\s*(?P<title>[A-Z#&/ 0-9]+?)\s{2,}SETS\s+REPS\s+RPE\s+REST")
WEEK = re.compile(r"FUNDAMENTALS PROGRAM\s*/\s*(?P<prog>[A-Z/ ]+?)\s{2,}WEEK (?P<week>\d+): DAYS")
BLOCK = re.compile(r"4 WEEK (MODIFIED )?STRENGTH BASE")

PROGRAMS = {
    "FULL BODY": ("full-body", "Full Body", "Фулбади", 3),
    "LOWER/UPPER": ("upper-lower", "Upper / Lower", "Верх / Низ", 4),
    "BODY PART SPLIT": ("body-part-split", "Body Part Split", "Сплит по группам мышц", 5),
}


DAY_RU = {
    "Full Body": "Фулбади", "Lower Body": "Низ", "Upper Body": "Верх",
    "Chest & Triceps": "Грудь и трицепс", "Legs & Abs": "Ноги и пресс",
    "Back & Biceps": "Спина и бицепс", "Shoulders & Arms": "Плечи и руки",
}


def day_ru(title_en: str) -> str:
    base, _, num = title_en.partition(" #")
    ru = DAY_RU.get(base, base)
    return f"{ru} #{num}" if num else ru


def slug(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", s.lower().replace("’", "")).strip("-")


def title(s: str) -> str:
    small = {"and", "of", "to"}
    out = []
    for w in s.lower().split():
        out.append(w if w in small else (w[:1].upper() + w[1:]))
    return " ".join(out).replace("Db ", "DB ")


def main(src: str, dst: str) -> None:
    lines = open(src, encoding="utf-8").read().splitlines()
    programs: dict = {}
    prog = week = day = None
    last = None  # last parsed exercise row (for wrapped lines)
    name_col = rest_col = 0

    for raw in lines:
        line = raw.rstrip()
        if m := WEEK.search(line):
            key = m["prog"].strip()
            pslug, en, ru, dpw = PROGRAMS[key]
            prog = programs.setdefault(pslug, {
                "slug": pslug, "name_en": en, "name_ru": ru,
                "days_per_week": dpw, "weeks": []})
            week = {"week_no": int(m["week"]), "block": "base", "days": []}
            prog["weeks"].append(week)
            day = last = None
            continue
        if prog is None:
            continue
        if m := BLOCK.search(line):
            week["block"] = "intensity" if m.group(1) else "base"
            continue
        if m := HEADER.match(line):
            t = m["title"].strip()
            day = {"day_no": len(week["days"]) + 1, "title_en": title(t), "title_ru": day_ru(title(t)), "exercises": []}
            week["days"].append(day)
            last = None
            continue
        if day is None:
            continue
        if "REST TIMES ARE GIVEN" in line or re.search(r"HYPERTROPHY PROGRAM\s+\d+\s*$", line) \
                or re.match(r"^\s*DAY \d+\s*$", line) or "PROGRAM EXPLAINED" in line:
            if "REST TIMES" in line:
                last = None
            continue
        if m := ROW.match(line):
            reps = m["reps"]
            ex = {
                "order": len(day["exercises"]) + 1,
                "name": m["name"].strip(),
                "sets": int(m["sets"]),
                "reps": None if reps.endswith("SEC") else int(reps),
                "seconds": int(reps[:-3]) if reps.endswith("SEC") else None,
                "target_rpe": float(m["rpe"]),
                "rest_min": int(m["rmin"]),
                "rest_max": int(m["rmax"]),
                "notes": m["rest"].strip(),
            }
            day["exercises"].append(ex)
            last = ex
            name_col = len(m["indent"]) + len(m["name"]) + 2
            rest_col = m.start("rest")
            continue
        if last is None or not line.strip():
            continue
        # Wrapped continuation: split into chunks by 2+ spaces, route by column.
        for cm in re.finditer(r"\S+(?: \S+)*", line):
            text = cm.group(0)
            if cm.start() < name_col and cm.end() <= rest_col - 10:
                last["name"] += " " + text
            else:
                last["notes"] = (last["notes"] + " " + text).strip()

    # Normalise names into an exercise catalogue.
    catalogue: dict = {}
    for p in programs.values():
        for w in p["weeks"]:
            for d in w["days"]:
                for ex in d["exercises"]:
                    name = re.sub(r"\s+", " ", ex.pop("name")).strip()
                    s = slug(name)
                    catalogue.setdefault(s, {"slug": s, "name_en": title(name)})
                    ex["exercise"] = s
                    ex["notes_en"] = ex.pop("notes").capitalize()

    out = {
        "format": "gym-app.program-pack.v1",
        "visibility": "private",
        "exercises": sorted(catalogue.values(), key=lambda e: e["slug"]),
        "programs": list(programs.values()),
    }
    json.dump(out, open(dst, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    n = sum(len(d["exercises"]) for p in programs.values() for w in p["weeks"] for d in w["days"])
    print(f"programs={len(programs)} exercises={len(catalogue)} slots={n}")
    for p in programs.values():
        print(p["slug"], [len(w["days"]) for w in p["weeks"]])


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
