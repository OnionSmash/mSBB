#!/usr/bin/env python3
"""Bing Webmaster API automation for sitemap-driven URL submission.

Supports API key or OAuth token auth.
- API key: set BING_WEBMASTER_API_KEY
- OAuth: set BING_WEBMASTER_OAUTH_TOKEN
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import pathlib
import sys
import time
import urllib.parse
import xml.etree.ElementTree as ET
from datetime import datetime, timezone

import requests

HOST = os.environ.get("BING_SITE_HOST", "vigilantvoices.com")
SCHEME = "https"
SITE_URL = f"{SCHEME}://{HOST}"
SITE_ROOT = pathlib.Path(__file__).resolve().parent.parent
SITEMAP_PATH = SITE_ROOT / "sitemap.xml"
ROBOTS_PATH = SITE_ROOT / "robots.txt"
LOG_DIR = pathlib.Path(__file__).resolve().parent / "logs"
LOG_PATH = LOG_DIR / "bing-webmaster-submissions.log"
STATE_PATH = LOG_DIR / "bing-webmaster-state.json"

API_BASE = "https://ssl.bing.com/webmaster/api.svc/json"
REQUEST_TIMEOUT_SECONDS = 25
MAX_URLS_PER_REQUEST = 500
DEFAULT_MAX_RETRIES = 3
DEFAULT_RETRY_BACKOFF_SECONDS = 2


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def append_log(records: list[dict]) -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    with LOG_PATH.open("a", encoding="utf-8") as fp:
        for rec in records:
            fp.write(json.dumps(rec, separators=(",", ":"), ensure_ascii=True) + "\n")


def normalize_url(url: str) -> str:
    parsed = urllib.parse.urlparse(url.strip())
    safe_path = urllib.parse.quote(urllib.parse.unquote(parsed.path or "/"), safe="/%:@-._~!$&'()*+,;=")
    query_pairs = urllib.parse.parse_qsl(parsed.query, keep_blank_values=True)
    safe_query = urllib.parse.urlencode(query_pairs, doseq=True, safe=":@-._~!$'()*+,;=")
    return urllib.parse.urlunparse((parsed.scheme, parsed.netloc.lower(), safe_path, "", safe_query, ""))


def is_host_url(url: str) -> bool:
    p = urllib.parse.urlparse(url)
    return p.scheme in ("http", "https") and p.netloc.lower() == HOST.lower()


def dedupe_preserve_order(items: list[str]) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for item in items:
        if item not in seen:
            seen.add(item)
            out.append(item)
    return out


def read_sitemap_urls(path: pathlib.Path) -> list[str]:
    if not path.exists():
        raise FileNotFoundError(f"Sitemap not found: {path}")
    root = ET.parse(path).getroot()
    urls: list[str] = []
    for elem in root.iter():
        if elem.tag.endswith("loc") and elem.text:
            urls.append(elem.text.strip())
    return [u for u in urls if u]


def read_robots_sitemaps(path: pathlib.Path) -> list[str]:
    if not path.exists():
        return []
    out: list[str] = []
    for line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        row = line.strip()
        if row.lower().startswith("sitemap:"):
            val = row.split(":", 1)[1].strip()
            if val:
                out.append(val)
    return out


def gather_urls() -> list[str]:
    fixed_urls = [f"{SITE_URL}/", f"{SITE_URL}/robots.txt", f"{SITE_URL}/sitemap.xml"]
    all_urls = read_sitemap_urls(SITEMAP_PATH) + fixed_urls + read_robots_sitemaps(ROBOTS_PATH)
    all_urls = [normalize_url(u) for u in all_urls if u and is_host_url(u)]
    return dedupe_preserve_order(all_urls)


def url_set_hash(urls: list[str]) -> str:
    digest = hashlib.sha256()
    for u in sorted(urls):
        digest.update(u.encode("utf-8"))
        digest.update(b"\n")
    return digest.hexdigest()


def load_previous_hash() -> str:
    if not STATE_PATH.exists():
        return ""
    try:
        data = json.loads(STATE_PATH.read_text(encoding="utf-8"))
        return str(data.get("url_set_hash", ""))
    except Exception:
        return ""


def save_current_hash(current_hash: str, url_count: int) -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    payload = {
        "timestamp": utc_now_iso(),
        "host": HOST,
        "url_set_hash": current_hash,
        "url_count": url_count,
    }
    STATE_PATH.write_text(json.dumps(payload, indent=2), encoding="utf-8")


def chunked(items: list[str], size: int) -> list[list[str]]:
    return [items[i : i + size] for i in range(0, len(items), size)]


def get_auth() -> tuple[str, str | None]:
    api_key = os.environ.get("BING_WEBMASTER_API_KEY", "").strip()
    oauth = os.environ.get("BING_WEBMASTER_OAUTH_TOKEN", "").strip()
    if api_key:
        return "apikey", api_key
    if oauth:
        return "oauth", oauth
    return "none", None


def call_method(
    session: requests.Session,
    method_name: str,
    payload: dict | None,
    auth_mode: str,
    credential: str | None,
    http_method: str = "POST",
) -> requests.Response:
    params = {}
    headers = {"Content-Type": "application/json; charset=utf-8"}

    if auth_mode == "apikey":
        params["apikey"] = credential
    elif auth_mode == "oauth":
        headers["Authorization"] = f"Bearer {credential}"

    url = f"{API_BASE}/{method_name}"
    if http_method.upper() == "GET":
        return session.get(
            url,
            params=params,
            headers=headers,
            timeout=REQUEST_TIMEOUT_SECONDS,
        )

    return session.post(
        url,
        params=params,
        data=json.dumps(payload or {}),
        headers=headers,
        timeout=REQUEST_TIMEOUT_SECONDS,
    )


def verify_auth(session: requests.Session, auth_mode: str, credential: str | None) -> tuple[bool, str]:
    methods = ["GetUserSites", "GetUserSiteList"]
    for m in methods:
        try:
            r = call_method(session, m, None, auth_mode, credential, http_method="GET")
            if r.status_code == 200:
                return True, f"Auth check OK via {m}."
        except requests.RequestException as exc:
            return False, f"Auth check failed: {exc}"
    return False, "Auth check failed: unable to call GetUserSites/GetUserSiteList with current credential."


def submit_batch(
    session: requests.Session,
    batch: list[str],
    auth_mode: str,
    credential: str | None,
    max_retries: int,
    retry_backoff_seconds: int,
) -> tuple[int, str, int, str]:
    payload = {"siteUrl": SITE_URL, "urlList": batch}
    methods = ["SubmitUrlbatch", "SubmitUrlBatch"]

    attempts = 0
    last_status = 0
    last_body = ""
    method_used = methods[0]

    for attempt in range(0, max_retries + 1):
        attempts = attempt + 1
        for m in methods:
            method_used = m
            try:
                response = call_method(session, m, payload, auth_mode, credential)
            except requests.RequestException as exc:
                last_status = 0
                last_body = str(exc)
                continue

            last_status = response.status_code
            last_body = (response.text or "").strip()

            if last_status in (404, 405):
                continue
            if last_status in (429, 500, 502, 503, 504) and attempt < max_retries:
                break
            return last_status, last_body, attempts, method_used

        if last_status in (429, 500, 502, 503, 504) and attempt < max_retries:
            time.sleep(retry_backoff_seconds * (attempt + 1))
            continue
        break

    return last_status, last_body, attempts, method_used


def run(dry_run: bool, max_retries: int, retry_backoff_seconds: int, force: bool) -> int:
    auth_mode, credential = get_auth()
    if auth_mode == "none":
        print("Missing auth. Set BING_WEBMASTER_API_KEY or BING_WEBMASTER_OAUTH_TOKEN.")
        return 1

    urls = gather_urls()
    if not urls:
        print("No URLs found in sitemap/robots.")
        return 0

    current_hash = url_set_hash(urls)
    previous_hash = load_previous_hash()
    if not force and not dry_run and previous_hash and previous_hash == current_hash:
        print("URL set unchanged; skipping submission.")
        append_log([
            {
                "timestamp": utc_now_iso(),
                "host": HOST,
                "method": "SubmitUrlbatch",
                "http_status": None,
                "submission_result": "skipped-unchanged",
                "batch_size": len(urls),
            }
        ])
        return 0

    print(f"Prepared {len(urls)} URLs for {HOST}")
    if dry_run:
        for u in urls[:15]:
            print("DRY RUN URL:", u)
        if len(urls) > 15:
            print(f"... and {len(urls)-15} more")
        append_log([
            {
                "timestamp": utc_now_iso(),
                "host": HOST,
                "method": "SubmitUrlbatch",
                "http_status": None,
                "submission_result": "dry-run",
                "batch_size": len(urls),
            }
        ])
        return 0

    session = requests.Session()
    ok, msg = verify_auth(session, auth_mode, credential)
    print(msg)
    if not ok:
        append_log([
            {
                "timestamp": utc_now_iso(),
                "host": HOST,
                "method": "GetUserSites",
                "http_status": None,
                "submission_result": "failed-auth",
                "batch_size": len(urls),
                "response_snippet": msg,
            }
        ])
        return 1

    batches = chunked(urls, MAX_URLS_PER_REQUEST)
    had_error = False

    for idx, batch in enumerate(batches, start=1):
        status, body, attempts, used_method = submit_batch(
            session,
            batch,
            auth_mode,
            credential,
            max_retries,
            retry_backoff_seconds,
        )
        success = status in (200, 201, 202)
        if not success:
            had_error = True

        print(f"Batch {idx}/{len(batches)} via {used_method}: HTTP {status}")
        if body:
            print("Response:", body[:400])

        append_log([
            {
                "timestamp": utc_now_iso(),
                "host": HOST,
                "method": used_method,
                "http_status": status,
                "submission_result": "accepted" if success else "failed",
                "retry_attempts": attempts - 1,
                "batch_index": idx,
                "batch_size": len(batch),
                "response_snippet": body[:400] if body else "",
            }
        ])

        if idx < len(batches):
            time.sleep(1)

    if not had_error:
        save_current_hash(current_hash=current_hash, url_count=len(urls))

    print(f"Bing submission logs: {LOG_PATH}")
    return 1 if had_error else 0


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Submit sitemap URLs to Bing Webmaster API")
    p.add_argument("--dry-run", action="store_true", help="Build URL list only; do not submit")
    p.add_argument("--force", action="store_true", help="Submit even if URL hash unchanged")
    p.add_argument("--max-retries", type=int, default=DEFAULT_MAX_RETRIES)
    p.add_argument("--retry-backoff-seconds", type=int, default=DEFAULT_RETRY_BACKOFF_SECONDS)
    return p.parse_args()


if __name__ == "__main__":
    args = parse_args()
    sys.exit(
        run(
            dry_run=args.dry_run,
            max_retries=max(0, args.max_retries),
            retry_backoff_seconds=max(1, args.retry_backoff_seconds),
            force=args.force,
        )
    )
