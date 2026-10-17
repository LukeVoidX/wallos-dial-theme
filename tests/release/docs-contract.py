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
    "README.md": ("Wallos 5.8.3", "ghcr.io/lukevoidx/wallos-dial:1.0.0",
                  "Manage categories", "My Icons", "read-only", "PHP-FPM"),
    "README.zh-CN.md": ("5.8.3", "ghcr.io/lukevoidx/wallos-dial:1.0.0",
                        "分类", "我的图标", "只读", "PHP-FPM"),
    "CHANGELOG.md": ("## 1.0.0", "Released as", "5.8.3"),
    "NOTICE.md": ("5.8.3", "neutral", "GPLv3"),
    "docs/UPGRADING.md": ("0.1.1", "1.0.0", "rollback", "PRAGMA integrity_check", "My Icons"),
    "docs/zh-CN/UPGRADING.md": ("0.1.1", "1.0.0", "回退", "PRAGMA integrity_check", "我的图标"),
    "docs/ICONS.md": ("1.0.0", "Wallos 5.8.3", "My Icons", "read-only"),
    "docs/zh-CN/ICONS.md": ("1.0.0", "Wallos 5.8.3", "我的图标", "只读"),
    "docs/DEMO.md": ("1.0.0", "5.8.3", "read-only", "categories.php", "synthetic"),
    "docs/SHOWCASE.md": ("1.0.0", "0.1.1", "synthetic"),
    "docs/RELEASE-NOTES-1.0.0.md": ("# Wallos Dial 1.0.0", "Wallos 5.8.3"),
    "docs/RELEASE-CHECKLIST-1.0.0.md": ("1.0.0", "Demo", "rollback"),
    "docs/CAMPAIGN.md": ("historical 0.1.x", "current release", "1.0.0"),
    "docs/CAMPAIGN.zh-CN.md": ("0.1.x", "当前正式版本", "1.0.0"),
    "docs/RECOMMENDATION.md": ("Published 1.0.0", "Wallos 5.8.3", "read-only"),
    "docs/TROUBLESHOOTING.md": ("wallos-dial:1.0.0", "/health.php"),
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
        # This post-publication contract is applied only after v1.0.0, GHCR,
        # and the separate public Demo switch are verified. Pages must then
        # describe the release, not an earlier candidate or 0.1.1 Demo.
        check("0.1.1" not in content and "candidate" not in content.lower()
              and "候选" not in content, f"{name}: stale pre-release claim")
        check("docker compose up -d" in content,
              f"{name}: versioned installation command missing")
        check("dial-1.0-overview.png" in content and "dial-1.0-icon-chooser.png" in content,
              f"{name}: 1.0 direct captures missing")
        description = re.search(r'<meta name="description" content="([^"]+)"', content)
        check(description is not None and "5.8.3" in description.group(1), f"{name}: stale meta description")
        structured = re.search(r'<script type="application/ld\+json">(.*?)</script>', content, re.S)
        check(structured is not None, f"{name}: missing JSON-LD")
        graph = json.loads(structured.group(1))["@graph"]
        software = next(item for item in graph if item.get("@type") == "SoftwareSourceCode")
        check(software.get("version") == "1.0.0" and
              software.get("softwareRequirements") == "Wallos 5.8.3" and
              software.get("downloadUrl", "").endswith("/releases/tag/v1.0.0"),
              f"{name}: structured data must describe the published 1.0 release")
        validate_links(name, content)
    for name in ("CHANGELOG.md", "README.md", "README.zh-CN.md", "docs/DEMO.md",
                 "docs/ICONS.md", "docs/zh-CN/ICONS.md",
                 "docs/RECOMMENDATION.md", "docs/CAMPAIGN.md",
                 "docs/CAMPAIGN.zh-CN.md", "docs/TROUBLESHOOTING.md"):
        content = (ROOT / name).read_text()
        check("local candidate" not in content.lower() and
              "尚未公开发布" not in content and "目前仍是本地候选" not in content,
              f"{name}: stale pre-release claim")
    check("wallos-dial:1.0.0" in (ROOT / "demo/compose.yaml").read_text(),
          "public Demo Compose default must follow verified 1.0.0")
    print("Published 1.0 documentation contract passed")


if __name__ == "__main__":
    main()
