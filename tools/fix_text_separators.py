#!/usr/bin/env python3
"""Replace corrupted box-drawing separators and emoji mojibake with plain text."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIRS = {"vendor", "node_modules", ".git", "storage", "bootstrap/cache"}
EXTENSIONS = {".php", ".blade.php", ".js"}

REPLACEMENTS: list[tuple[str, str]] = [
    ("\u252c\u2556", " - "),  # ┬╖ (wrong middle-dot substitute)
    ("\u251c\u00ac\u2561", " - "),
    ("\u0393\u00c7\u00f6", " - "),  # em dash mojibake
    ("\u2261\u0192\u00f3\u20a7 ", "Phone: "),
    ("\u2261\u0192\u00f3\u20a7", "Phone: "),
    ("\u0393\u00a3\u00e2 ", "Email: "),
    ("\u2261\u0192\u00f2\u00a1 ", "Tip: "),
]


def main() -> None:
    changed: list[str] = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            path = Path(dirpath) / fn
            if path.suffix not in EXTENSIONS and not fn.endswith(".blade.php"):
                continue
            try:
                text = path.read_text(encoding="utf-8")
            except (OSError, UnicodeError):
                continue
            original = text
            for old, new in REPLACEMENTS:
                text = text.replace(old, new)
            if text != original:
                path.write_text(text, encoding="utf-8", newline="\n")
                changed.append(str(path.relative_to(ROOT)))
    report = ROOT / "_unicode_debug.txt"
    report.write_text(f"Updated {len(changed)} files\n" + "\n".join(changed), encoding="utf-8")


if __name__ == "__main__":
    main()
