#!/usr/bin/env python3
"""Check the 1.0 documentation's version, scope, and local links."""

from __future__ import annotations

from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit
import json
import re


ROOT = Path(__file__).resolve().parents[2]
REQUIRED = {
    "README.md": ("Wallos 5.8.3", "1.0.0", "Manage categories", "My Icons", "read-only"),
    "README.zh-CN.md": ("5.8.3", "1.0.0", "分类", "我的图标", "只读"),
    "CHANGELOG.md": ("1.0.0", "5.8.3"),
    "NOTICE.md": ("5.8.3", "neutral", "GPLv3"),
    "docs/UPGRADING.md": ("0.1.1", "1.0.0", "rollback", "PRAGMA integrity_check", "My Icons"),
    "docs/DEMO.md": ("5.8.3", "read-only", "categories.php", "synthetic"),
    "docs/SHOWCASE.md": ("1.0.0", "0.1.1", "synthetic"),
    "docs/RELEASE-NOTES-1.0.0.md": ("# Wallos Dial 1.0.0", "Wallos 5.8.3"),
    "docs/RELEASE-CHECKLIST-1.0.0.md": ("pending", "Demo", "rollback"),
    "demo/README.md": ("1.0.0", "5.8.3", "synthetic", "read-only"),
}
LINK_FILES = {
    *REQUIRED,
    "CONTRIBUTING.md",
    "docs/CAMPAIGN.md",
    "docs/CAMPAIGN.zh-CN.md",
    "docs/CATEGORIES.md",
    "docs/CATEGORIES.zh-CN.md",
    "docs/ICONS.md",
    "docs/zh-CN/ICONS.md",
    "docs/TROUBLESHOOTING.md",
    "docs/SEARCH.md",
    "docs/requirements/REQ-001-public-theme.md",
}


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


class LocalHTMLLinks(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.targets: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        for name, value in attrs:
            if name in {"href", "src"} and value:
                self.targets.append(value)


def local_targets(name: str, content: str) -> list[str]:
    if name.endswith(".html"):
        parser = LocalHTMLLinks()
        parser.feed(content)
        return parser.targets
    return re.findall(r"\]\(([^)]+)\)", content)


def validate_links(name: str, content: str) -> None:
    for raw in local_targets(name, content):
        url = urlsplit(raw.strip().strip("<>"))
        if url.scheme or url.netloc or not url.path or url.path.startswith("/"):
            continue
        target = (ROOT / name).parent / unquote(url.path)
        check(target.exists(), f"{name}: missing local link {raw}")


def main() -> None:
    check((ROOT / "WALLOS_VERSION").read_text().strip() == "5.8.3", "WALLOS_VERSION drift")
    check((ROOT / "VERSION").read_text().strip().startswith("1.0.0"), "VERSION drift")
    for name, phrases in REQUIRED.items():
        path = ROOT / name
        check(path.is_file(), f"missing {name}")
        content = path.read_text()
        for phrase in phrases:
            check(phrase in content, f"{name}: missing {phrase}")
    for name in sorted(LINK_FILES):
        path = ROOT / name
        check(path.is_file(), f"missing {name}")
        validate_links(name, path.read_text())
    for name in ("docs/index.html", "docs/zh-CN/index.html"):
        content = (ROOT / name).read_text()
        for phrase in ("5.8.3", "1.0.0", "read-only" if name == "docs/index.html" else "只读"):
            check(phrase in content, f"{name}: missing {phrase}")
        # Pages still describes the published 0.1.1 release until GitHub,
        # GHCR, and the public Demo have all been verified. The 1.0 source
        # candidate must not present itself as a released install path.
        check("0.1.1" in content and "1.0.0" in content and "1.0.0-rc.1" not in content,
              f"{name}: missing published/candidate split")
        check("docker compose up -d" not in content,
              f"{name}: premature 1.0 install command")
        description = re.search(r'<meta name="description" content="([^"]+)"', content)
        check(description is not None and "5.8.3" in description.group(1), f"{name}: stale meta description")
        structured = re.search(r'<script type="application/ld\+json">(.*?)</script>', content, re.S)
        check(structured is not None, f"{name}: missing JSON-LD")
        graph = json.loads(structured.group(1))["@graph"]
        software = next(item for item in graph if item.get("@type") == "SoftwareSourceCode")
        check(software.get("version") == "0.1.1" and "5.8.3" in software.get("description", ""),
              f"{name}: structured version must describe the published release and candidate")
        validate_links(name, content)
    print("1.0 documentation contract passed")


if __name__ == "__main__":
    main()
