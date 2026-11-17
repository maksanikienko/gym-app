#!/usr/bin/env python3
"""Generic parser for program PDFs (pdftotext -layout output) → program-pack JSON.

Handles two table layouts:
  A) NAME | SETS | REPS | RPE/%1RM | REST | 1 2 3 4 | NOTES | LSRPE      (one header per day, title before SETS)
  B) [label] [video] EXERCISE | # WARMUP SETS | # WORKING SETS | REPS | RPE | REST | NOTES [...]
     (one header per day, day label in the left margin)

Usage: python3 -I tools/parse_program_v2.py <txt> <out.json> --slug S --name-en N --name-ru N [--sections]

Output is for PRIVATE import only.
"""
import argparse
import json
import os
import re
import sys
from collections import OrderedDict

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from exercise_aliases import ALIASES  # noqa: E402

DAY_RU = [
    (r"^Back Focused Full Body$", "Фулбади: акцент на спину"), (r"^Chest Focused Full Body$", "Фулбади: акцент на грудь"),
    (r"^Deltoid Focused Full Body$", "Фулбади: акцент на дельты"), (r"^Lower Focused Full Body$", "Фулбади: акцент на ноги"),
    (r"^Legs\b", "Ноги"), (r"^Push\b", "Жим"), (r"^Pull\b", "Тяга"), (r"^Lower\b", "Низ"), (r"^Upper\b", "Верх"),
    (r"^Full-body\b", "Фулбади"), (r"^Back/biceps$", "Спина / бицепс"), (r"^Chest/tris$", "Грудь / трицепс"),
    (r"^Glutes/hamstrings$", "Ягодицы / бицепс бедра"), (r"^Glutes/quads$", "Ягодицы / квадрицепсы"),
    (r"^Shoulders/abs$", "Плечи / пресс"),
    (r"^Back Focused Full Body$", "Фулбади: акцент на спину"), (r"^Chest Focused Full Body$", "Фулбади: акцент на грудь"),
    (r"^Deltoid Focused Full Body$", "Фулбади: акцент на дельты"), (r"^Lower Focused Full Body$", "Фулбади: акцент на ноги"),
]


def day_ru(t):
    for pat, ru in DAY_RU:
        if re.search(pat, t, re.I):
            return re.sub(pat, ru, t, flags=re.I)
    return None

RPE = r"(?:(?:RPE|REP)\s?\d+(?:\.\d+)?(?:\s?TEST)?|PRE\s?\d+|RP\s?\d+|\d+(?:\.\d+)?\s?%|\d+(?:\.\d+)?(?:\s?,\s?\d+(?:\.\d+)?)*|N/A|-|~)"
SETS = r"`?(?:\d+\s(?:or|OR)\s\d+|\d+)"
REST = r"(?:\d+(?:\.\d+)?(?:\s?-\s?\d+(?:\.\d+)?)?\s?(?:MINS?|SECS?)|N/A|-)"
ROW_A = re.compile(rf"^(?P<pre>.*?\S)\s{{2,}}(?P<sets>{SETS})\s+(?P<reps>\S(?:.*?\S)?)\s{{2,}}(?P<rpe>{RPE})\s{{2,}}(?P<rest>{REST})(?P<notes>(?:\s.*)?)$", re.I)
ROW_B = re.compile(rf"^(?P<pre>.*?)\s(?P<wu>\d+|-)\s{{2,}}(?P<sets>{SETS})\s{{2,}}(?P<reps>\S(?:.*?\S)?)\s{{2,}}(?P<rpe>{RPE})\s{{2,}}(?P<rest>{REST})(?P<notes>(?:\s.*)?)$", re.I)
HEADER_A = re.compile(r"^\s*(?P<title>\S.*?)\s{2,}SETS\s+REPS\b.*REST")
HEADER_B = re.compile(r"EXERCISE\s+# OF WARMUP")
WEEK = re.compile(r"WEEK\s+(\d+)\s*(?::|/|\(|$)")
SECTION = re.compile(r"^(\d)-DAY\s+([A-Z/ -]+?)\s*$")
FOOTER = re.compile(r"(PROGRAM|FOUNDATIONS)\s+\d+\s*$|TOTAL (SET VOLUME|TRAINING TIME)|REST TIMES ARE GIVEN")
LABEL_WORDS = {"DAY", "WORKOUT", "FULL", "BODY", "FOCUSED", "LOWER", "UPPER", "CHEST", "BACK", "ARMS", "ARM",
               "SHOULDERS", "SHOULDER", "GLUTES", "GLUTE", "LEGS", "PUSH", "PULL", "&", "AND", "POSTERIOR", "CHAIN"}
URL = re.compile(r"HTTPS?://\S+", re.I)
# Page furniture that must never become part of an exercise name. Running page headers are
# detected automatically (see Parser.detect_page_headers); these are the generic section markers.
NOISE = re.compile(r"PROGRAM EXPLAINED|REFERENCES|TABLE|TOTAL (SET VOLUME|TRAINING TIME)|REST TIMES|"
                   r"WEAK POINT EXERCISES|^\s*(WEEK|BLOCK)\s+\d", re.I)


def segments(line: str):
    return [(m.start(), m.end(), m.group(0)) for m in re.finditer(r"\S+(?: \S+)*", line)]


def title(s: str) -> str:
    s = re.sub(r"\s+", " ", s).strip()
    small = {"and", "or", "of", "to", "on", "with"}
    out = [w if w.lower() in small and i else (w[:1].upper() + w[1:].lower()) for i, w in enumerate(s.split(" "))]
    return " ".join(out).replace("Db ", "DB ").replace("Ez ", "EZ ").replace("Rdl", "RDL").replace("T-bar", "T-Bar")


def slug(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", s.lower().replace("’", "").replace("'", "")).strip("-")


def parse_reps(raw: str):
    r = re.sub(r"\s+", " ", raw.strip().upper())
    if m := re.fullmatch(r":?(\d+)\s?(?:SEC|SECS|S)?", r) if (":" in r or "SEC" in r) else None:
        return None, None, int(m.group(1)), None
    if m := re.fullmatch(r"(\d+)\s?-\s?(\d+)", r):
        return int(m.group(1)), int(m.group(2)), None, None
    if re.fullmatch(r"\d+", r):
        return int(r), None, None, None
    first = re.match(r"(\d+)", r)
    return (int(first.group(1)) if first else None), None, None, r.capitalize() if r.isalpha() else r.lower()


def parse_rpe(raw: str):
    r = raw.strip().upper().replace(" ", "")
    if r.endswith("%"):
        return None, float(r[:-1])
    if m := re.search(r"(\d+(?:\.\d+)?)", r):
        v = float(m.group(1))
        return (v if 1 <= v <= 10 else None), None
    return None, None


def parse_rest(raw: str):
    r = raw.strip().upper()
    nums = [float(x) for x in re.findall(r"\d+(?:\.\d+)?", r)]
    if not nums:
        return None, None
    if "SEC" in r:
        nums = [round(n / 60) for n in nums]
    lo, hi = int(round(nums[0])), int(round(nums[-1]))
    return lo, hi


class Parser:
    def __init__(self, lines, sections: bool, ignore=()):
        self.lines = lines
        self.page_headers = {i.upper() for i in ignore}
        self.sections = sections
        self.programs: "OrderedDict[str, dict]" = OrderedDict()
        self.section = "main"
        self.week = None
        self.day = None
        self.layout = None
        self.block = []  # (line_index, line) of current day
        self.raw_week = None
        self.week_offset = 0
        self.cur_block = None
        self.meta = {}  # (section, week) -> block label

    def is_noise(self, line):
        return bool(NOISE.search(line)) or any(h in line.upper() for h in self.page_headers)

    # ---------- structure ----------
    def program(self):
        return self.programs.setdefault(self.section, OrderedDict())

    def start_day(self, layout, title_text, cols):
        self.flush()
        self.layout = layout
        self.cols = cols
        self.day = {"title": title_text, "labels": [], "rows": []}
        self.block = []

    def flush(self):
        if not self.day:
            return
        self.build_rows()
        if self.day["rows"] and self.week:
            weeks = self.program()
            days = weeks.setdefault(self.week, [])
            t = self.day["title"] or self.label_title() or f"Day {len(days) + 1}"
            days.append({"title_en": title(t), "exercises": self.day["rows"]})
        self.day = None

    def label_title(self):
        labels = [l for l in self.day["labels"] if not self.is_noise(l)]
        words = " ".join(labels)
        if m := re.search(r"[A-Z][A-Z/-]*\s?#\s?\d", words):
            return m.group(0)
        tokens = [w for w in re.sub(r"\bDAY\s+\d+\b", "", words).split() if not w.isdigit()]
        if "FOCUSED" in tokens:
            focus = next((w for w in tokens if w not in {"FOCUSED", "FULL", "BODY"}), "")
            return f"{focus} FOCUSED FULL BODY".strip()
        seen = []
        for w in tokens:
            if w not in seen:
                seen.append(w)
        return " ".join(seen) or None

    # ---------- rows ----------
    def build_rows(self):
        rx = ROW_A if self.layout == "A" else ROW_B
        anchors, frags = [], []
        block = list(self.block)
        merged = []
        i = 0
        while i < len(block):
            idx, line = block[i]
            if i + 1 < len(block) and not rx.match(URL.sub(lambda m: " " * len(m.group(0)), line)):
                nxt = block[i + 1][1]
                combo = line.rstrip() + "  " + nxt.strip()
                if re.match(rf"^\s+{RPE}\s{{2,}}{REST}", nxt, re.I) and rx.match(combo):
                    merged.append((idx, combo))
                    i += 2
                    continue
            merged.append((idx, line))
            i += 1
        for idx, line in merged:
            body = URL.sub(lambda m: " " * len(m.group(0)), line)
            m = rx.match(body)
            if m and not re.search(r"\bSETS\s+REPS\b|# OF WARMUP", line):
                anchors.append((idx, line, m))
            else:
                frags.append((idx, line))

        rows = []
        for idx, line, m in anchors:
            name_parts, labels = self.split_pre(line[: m.start("pre") + len(m.group("pre"))])
            self.day["labels"].extend(labels)
            urls = URL.findall(line)
            reps, reps_max, seconds, reps_text = parse_reps(m.group("reps"))
            rpe, pct = parse_rpe(m.group("rpe"))
            rest_min, rest_max = parse_rest(m.group("rest"))
            rows.append({
                "idx": idx,
                "name": [name_parts],
                "notes": [self.clean_notes(m.group("notes"), m.start("notes"))],
                "video": urls[0] if urls else None,
                "sets": max(int(x) for x in re.findall(r"\d+", m.group("sets"))),
                "sets_note": m.group("sets").replace("`", "") if " " in m.group("sets") else None,
                "warmup_sets": int(m.group("wu")) if self.layout == "B" and m.group("wu").isdigit() else None,
                "reps": reps, "reps_max": reps_max, "seconds": seconds, "reps_text": reps_text,
                "target_rpe": rpe, "load_pct": pct, "rest_min": rest_min, "rest_max": rest_max,
            })

        # Wrapped names: an anchor without a name on its own line takes the name lines right above it
        # (a vertically centred cell); otherwise name lines directly below continue the anchor's name.
        # Paragraph breaks (blank lines, page furniture) and other anchors stop the search.
        by_idx = {r["idx"]: r for r in rows}
        lines = {idx: line for idx, line in merged}
        order = [idx for idx, _ in merged]
        pos = {idx: k for k, idx in enumerate(order)}
        claimed = set()

        def name_bits(idx):
            line = lines[idx]
            if not line.strip() or self.is_noise(line):
                return None  # stop
            bits = []
            for s0, e0, text in segments(line):
                if URL.fullmatch(text) or re.fullmatch(r"DAY\s+\d+", text):
                    continue
                if e0 <= self.cols["name_end"]:
                    nb, labels = self.split_pre(" " * s0 + text)
                    self.day["labels"].extend(labels)
                    if nb:
                        bits.append(nb)
            return bits

        def walk(start_idx, step):
            found, k = [], pos[start_idx] + step
            while 0 <= k < len(order):
                idx = order[k]
                if idx in by_idx:
                    break
                bits = name_bits(idx)
                if bits is None:
                    break
                if bits and idx not in claimed:
                    found.append((idx, " ".join(bits)))
                k += step
            return found

        for r in sorted(rows, key=lambda r: r["idx"]):
            own = " ".join(n for n in r["name"] if n).strip()
            if not own:
                above = walk(r["idx"], -1)
                picks = sorted(above) if above else walk(r["idx"], 1)
            else:
                picks = walk(r["idx"], 1)
            claimed.update(idx for idx, _ in picks)
            before = [text for idx, text in sorted(picks) if idx < r["idx"]]
            after = [text for idx, text in sorted(picks) if idx > r["idx"]]
            r["name"] = before + r["name"] + after

        # Notes continuation: nearest anchor within 3 lines.
        for idx, line in frags:
            if not rows or self.is_noise(line):
                continue
            target = min(rows, key=lambda r: (abs(r["idx"] - idx), -r["idx"]))
            if abs(target["idx"] - idx) > 3:
                continue
            for s0, e0, text in segments(line):
                if s0 >= self.cols["notes_start"] - 2 and s0 < self.cols["notes_end"] and e0 > self.cols["name_end"]:
                    target["notes"].append(text)

        out = []
        for r in sorted(rows, key=lambda r: r["idx"]):
            name = re.sub(r"\s+", " ", " ".join(n for n in r["name"] if n)).strip(" :")
            superset = None
            if m := re.match(r"^([A-Z]\d)\s*:\s*(.+)$", name):
                superset, name = m.group(1), m.group(2)
            if not name:
                continue
            notes = re.sub(r"\s+", " ", " ".join(n for n in r["notes"] if n)).strip()
            if superset:
                notes = f"SUPERSET {superset}. {notes}".strip()
            if r.get("sets_note"):
                notes = f"{r['sets_note']} SETS. {notes}".strip()
            out.append({
                "name": name, "sets": r["sets"], "warmup_sets": r["warmup_sets"],
                "reps": r["reps"], "reps_max": r["reps_max"], "seconds": r["seconds"], "reps_text": r["reps_text"],
                "target_rpe": r["target_rpe"], "load_pct": r["load_pct"],
                "rest_min": r["rest_min"], "rest_max": r["rest_max"],
                "notes_en": notes.capitalize() if notes else None, "video": r["video"],
            })
        self.day["rows"] = out

    def split_pre(self, pre: str):
        """Split the text left of the numeric columns into (name, [left-margin labels])."""
        labels, name = [], []
        for s, e, text in segments(URL.sub(lambda m: " " * len(m.group(0)), pre)):
            if e <= self.cols["label_end"]:
                labels.append(text)
                continue
            if s < self.cols["label_end"]:
                words = text.split(" ")
                while words and (words[0] in LABEL_WORDS or words[0].isdigit() or re.fullmatch(r"#?\d", words[0])):
                    labels.append(words.pop(0))
                text = " ".join(words)
            if text:
                name.append(text)
        return " ".join(name), labels

    def clean_notes(self, notes: str, offset: int):
        if not notes:
            return ""
        end = self.cols["notes_end"] - offset
        return notes[: max(0, end)].strip()

    # ---------- main loop ----------
    def run(self):
        for i, raw in enumerate(self.lines):
            line = raw.rstrip()
            if self.sections and (m := SECTION.match(line.strip())):
                if m.group(0) != self.section:
                    self.flush()
                    self.section = m.group(0)
                    self.week = None
                    self.raw_week = None
                    self.week_offset = 0
                continue
            if HEADER_B.search(line) or ("SETS" in line and HEADER_A.match(line)):
                if HEADER_B.search(line):
                    ex = line.index("EXERCISE")
                    wu = line.index("# OF WARMUP")
                    notes = line.index("NOTES")
                    extra = re.search(r"JOINT ACTIONS|LSRPE", line)
                    label_end = 11 if "WORKOUT" in line else (line.index("VIDEO") if "VIDEO" in line else 10)
                    self.start_day("B", None, {
                        "label_end": label_end, "name_end": wu - 1,
                        "notes_start": notes - 25, "notes_end": extra.start() if extra else 10_000,
                    })
                else:
                    m = HEADER_A.match(line)
                    sets = line.index("SETS")
                    notes = line.index("NOTES")
                    extra = re.search(r"LSRPE", line)
                    self.start_day("A", m.group("title").strip(), {
                        "label_end": 0, "name_end": sets - 1,
                        "notes_start": notes - 40, "notes_end": extra.start() - 2 if extra else 10_000,
                    })
                continue
            if mb := re.fullmatch(r"\s*BLOCK\s+(\d+)\b.*", line):
                self.cur_block = f"block-{mb.group(1)}"
            if (m := WEEK.search(line)) and ("PROGRAM" in line or re.fullmatch(r"\s*WEEK\s+\d+\s*", line)):
                raw_w = int(m.group(1))
                # Week numbers restart in a new block (e.g. block 2 = weeks 1..8 again) → continue numbering.
                if self.raw_week is not None and raw_w < self.raw_week:
                    self.week_offset = max(self.program().keys(), default=0)
                self.raw_week = raw_w
                w = raw_w + self.week_offset
                tag = None
                if mw := re.search(r"WAVE\s+(\d+)", line):
                    tag = f"wave-{mw.group(1)}"
                elif mp := re.search(r"WEEK\s+\d+\s*\(([^)]+)\)", line):
                    t = mp.group(1).strip().lower()
                    tag = "deload" if "deload" in t else "amrap" if "amrap" in t else t.replace(" ", "-")
                elif self.cur_block:
                    tag = self.cur_block
                if tag:
                    self.meta[(self.section, w)] = tag
                if w != self.week:
                    self.flush()
                    self.week = w
                continue
            if self.day is not None and re.fullmatch(r"\s*(PROGRAM EXPLAINED|REFERENCES|WEAK POINT EXERCISES.*|EXERCISE SUBSTITUTIONS?)\s*", line):
                self.flush()
                continue
            if self.day is not None:
                if line.strip() and re.fullmatch(r"\s*[A-Z][A-Z/ -]*#\s?\d\s*", line):
                    self.day["labels"].append(line.strip())
                    continue
                self.block.append((i, line))
        self.flush()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("src")
    ap.add_argument("dst")
    ap.add_argument("--slug", required=True)
    ap.add_argument("--name-en", required=True)
    ap.add_argument("--name-ru", required=True)
    ap.add_argument("--sections", action="store_true", help="file holds several programs split by 'N-DAY ...' headers")
    ap.add_argument("--ignore", action="append", default=[], help="running page header text to skip (repeatable)")
    a = ap.parse_args()

    p = Parser(open(a.src, encoding="utf-8").read().splitlines(), a.sections, a.ignore)
    p.run()

    catalogue, programs = {}, []
    for section, weeks in p.programs.items():
        if not weeks:
            continue
        SECTION_NAMES = {
            "3-DAY FULL BODY": ("3-Day Full Body", "3 дня, фулбади"),
            "4-DAY UPPER/LOWER": ("4-Day Upper/Lower", "4 дня, верх/низ"),
            "5-DAY BODY PART SPLIT": ("5-Day Body Part Split", "5 дней, сплит"),
        }
        sec_en, sec_ru = SECTION_NAMES.get(section, (title(section), title(section)))
        suffix_en = "" if section == "main" else f" · {sec_en}"
        suffix_ru = "" if section == "main" else f" · {sec_ru}"
        out_weeks = []
        for wno, days in sorted(weeks.items()):
            out_days = []
            for di, d in enumerate(days):
                exs = []
                for x in d["exercises"]:
                    s = slug(x["name"])
                    s, alias_note = ALIASES.get(s, (s, None))
                    if alias_note:
                        x["notes_en"] = f"{alias_note}. {x['notes_en'] or ''}".strip()
                    e = catalogue.setdefault(s, {"slug": s, "name_en": title(x["name"])})
                    if x["video"] and not e.get("video_url"):
                        e["video_url"] = x["video"].lower().replace("https://youtu.be/", "https://youtu.be/")
                    exs.append({k: v for k, v in {
                        "exercise": s, "sets": x["sets"], "warmup_sets": x["warmup_sets"],
                        "reps": x["reps"], "reps_max": x["reps_max"], "seconds": x["seconds"], "reps_text": x["reps_text"],
                        "target_rpe": x["target_rpe"], "load_pct": x["load_pct"],
                        "rest_min": x["rest_min"], "rest_max": x["rest_max"], "notes_en": x["notes_en"],
                    }.items() if v is not None})
                out_days.append({"day_no": di + 1, "title_en": d["title_en"], "title_ru": day_ru(d["title_en"]), "exercises": exs})
            out_weeks.append({"week_no": wno, "block": p.meta.get((section, wno)), "days": out_days})
        programs.append({
            "slug": a.slug + ("" if section == "main" else "-" + slug(section)),
            "name_en": a.name_en + suffix_en,
            "name_ru": a.name_ru + suffix_ru,
            "days_per_week": max(len(w["days"]) for w in out_weeks),
            "weeks": out_weeks,
        })

    pack = {"format": "gym-app.program-pack.v1", "visibility": "private",
            "exercises": sorted(catalogue.values(), key=lambda e: e["slug"]), "programs": programs}
    json.dump(pack, open(a.dst, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    for pr in programs:
        print(f"{pr['slug']}: weeks={len(pr['weeks'])} days/wk={[len(w['days']) for w in pr['weeks']]} "
              f"slots={sum(len(d['exercises']) for w in pr['weeks'] for d in w['days'])}")
    print("exercises:", len(catalogue))


if __name__ == "__main__":
    main()
