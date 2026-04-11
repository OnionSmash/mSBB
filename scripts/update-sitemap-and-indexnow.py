#!/usr/bin/env python3
from __future__ import annotations

import argparse
import datetime
import pathlib
import subprocess
import sys
import xml.dom.minidom
import xml.etree.ElementTree as ET

EXCLUDE_DIRS = {
    "scripts",
    "letsencrypt",
    "ssl",
    "api",
    "css",
    "js",
    "lib",
    "img",
    "partials",
}
EXCLUDE_PREFIXES = ("googlef", "logo-preview")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Regenerate sitemap.xml and run IndexNow for a site.")
    parser.add_argument("--site-root", required=True, help="Path to the site root directory.")
    parser.add_argument("--force", action="store_true", help="Regenerate sitemap and submit even if unchanged.")
    parser.add_argument("--dry-run", action="store_true", help="Generate sitemap but do not submit to IndexNow.")
    return parser.parse_args()


def should_include_html(path: pathlib.Path, site_root: pathlib.Path) -> bool:
    rel = path.relative_to(site_root)
    if any(part in EXCLUDE_DIRS for part in rel.parts):
        return False
    if rel.name.startswith(EXCLUDE_PREFIXES):
        return False
    return True


def collect_html_files(site_root: pathlib.Path) -> list[pathlib.Path]:
    html_files: list[pathlib.Path] = []
    for path in sorted(site_root.rglob("*.html")):
        if should_include_html(path, site_root):
            html_files.append(path)
    return html_files


def format_lastmod(path: pathlib.Path) -> str:
    return datetime.date.fromtimestamp(path.stat().st_mtime).isoformat()


def build_url(site_root: pathlib.Path, path: pathlib.Path) -> str:
    host = site_root.name
    rel = path.relative_to(site_root)
    if rel == pathlib.Path("index.html"):
        uri = "/"
    else:
        uri = f"/{rel.as_posix()}"
    return f"https://{host}{uri}"


def build_sitemap(site_root: pathlib.Path, html_paths: list[pathlib.Path]) -> str:
    urlset = ET.Element("urlset", xmlns="http://www.sitemaps.org/schemas/sitemap/0.9")
    for path in html_paths:
        url = ET.SubElement(urlset, "url")
        loc = ET.SubElement(url, "loc")
        loc.text = build_url(site_root, path)
        lastmod = ET.SubElement(url, "lastmod")
        lastmod.text = format_lastmod(path)
        changefreq = ET.SubElement(url, "changefreq")
        changefreq.text = "weekly"
        priority = ET.SubElement(url, "priority")
        priority.text = "1.0" if path.name == "index.html" and path.parent == site_root else "0.8"

    rough_xml = ET.tostring(urlset, encoding="utf-8", method="xml")
    parsed = xml.dom.minidom.parseString(rough_xml)
    pretty = parsed.toprettyxml(indent="  ", encoding="utf-8")
    return pretty.decode("utf-8")


def write_file(path: pathlib.Path, content: str) -> None:
    path.write_text(content, encoding="utf-8")


def run_indexnow(site_root: pathlib.Path, dry_run: bool) -> int:
    script = site_root / "scripts" / "indexnow.py"
    if not script.exists():
        print(f"IndexNow script not found at {script}")
        return 1
    if dry_run:
        cmd = [sys.executable, str(script), "--dry-run"]
    else:
        cmd = [sys.executable, str(script), "--force"]
    print("Running IndexNow:", " ".join(cmd))
    result = subprocess.run(cmd, cwd=str(script.parent))
    return result.returncode


def main() -> int:
    args = parse_args()
    site_root = pathlib.Path(args.site_root).resolve()
    if not site_root.is_dir():
        print(f"Site root not found: {site_root}")
        return 1

    html_paths = collect_html_files(site_root)
    if not html_paths:
        print("No HTML pages found to include in sitemap.")
        return 1

    sitemap_path = site_root / "sitemap.xml"
    sitemap_contents = build_sitemap(site_root, html_paths)

    if sitemap_path.exists() and not args.force:
        existing = sitemap_path.read_text(encoding="utf-8")
        if existing == sitemap_contents:
            print("sitemap.xml is up to date.")
        else:
            print("sitemap.xml changed, updating file.")
            write_file(sitemap_path, sitemap_contents)
    else:
        print("Writing sitemap.xml.")
        write_file(sitemap_path, sitemap_contents)

    if args.dry_run:
        print("Dry run complete; IndexNow not executed.")
        return 0

    return run_indexnow(site_root, dry_run=False)


if __name__ == "__main__":
    raise SystemExit(main())
