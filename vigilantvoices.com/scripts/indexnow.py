#!/usr/bin/env python3
"""IndexNow submission utility for vigilantvoices.com.

This script follows IndexNow documentation by:
- Using POST /indexnow with host/key/keyLocation/urlList.
- Keeping a root key file at /<key>.txt (recommended ownership method).
- Submitting all URLs from sitemap.xml.
- Including sitemap.xml and robots.txt URLs in submission.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import pathlib
import sys
import time
import urllib.parse
import xml.etree.ElementTree as ET
from datetime import datetime, timezone

import requests


API_KEY = "a3f9b2d1-7c48-4e9a-9f6b-2d8c1e4f7a92"
HOST = "vigilantvoices.com"
SCHEME = "https"
SITE_ROOT = pathlib.Path(__file__).resolve().parent.parent
SITEMAP_PATH = SITE_ROOT / "sitemap.xml"
ROBOTS_PATH = SITE_ROOT / "robots.txt"
KEY_FILE_PATH = SITE_ROOT / f"{API_KEY}.txt"
KEY_LOCATION = f"{SCHEME}://{HOST}/{API_KEY}.txt"
INDEXNOW_API_URL = "https://api.indexnow.org/indexnow"
MAX_URLS_PER_REQUEST = 10000
REQUEST_TIMEOUT_SECONDS = 20
DEFAULT_MAX_RETRIES = 3
DEFAULT_RETRY_BACKOFF_SECONDS = 2
LOG_DIR = pathlib.Path(__file__).resolve().parent / "logs"
LOG_PATH = LOG_DIR / "indexnow-submissions.log"
STATE_PATH = LOG_DIR / "indexnow-state.json"


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def masked_key(key: str) -> str:
    if len(key) <= 8:
        return key
    return f"{key[:4]}...{key[-4:]}"


def append_log(records: list[dict]) -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    with LOG_PATH.open("a", encoding="utf-8") as fp:
        for rec in records:
            fp.write(json.dumps(rec, separators=(",", ":"), ensure_ascii=True) + "\n")


def normalize_url(url: str) -> str:
    parsed = urllib.parse.urlparse(url.strip())
    # Ensure path is URI-encoded and stable.
    safe_path = urllib.parse.quote(urllib.parse.unquote(parsed.path or "/"), safe="/%:@-._~!$&'()*+,;=")
    # Keep query semantics while ensuring proper escaping.
    query_pairs = urllib.parse.parse_qsl(parsed.query, keep_blank_values=True)
    safe_query = urllib.parse.urlencode(query_pairs, doseq=True, safe=":@-._~!$'()*+,;=")
    normalized = urllib.parse.urlunparse((parsed.scheme, parsed.netloc.lower(), safe_path, "", safe_query, ""))
    return normalized


def url_set_hash(urls: list[str]) -> str:
    digest = hashlib.sha256()
    for url in sorted(urls):
        digest.update(url.encode("utf-8"))
        digest.update(b"\n")
    return digest.hexdigest()


def load_previous_state() -> dict:
    if not STATE_PATH.exists():
        return {}
    try:
        data = json.loads(STATE_PATH.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {}
    except Exception:
        return {}


def save_current_state(current_hash: str, sitemap_hash: str, url_count: int) -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    payload = {
        "timestamp": utc_now_iso(),
        "host": HOST,
        "url_set_hash": current_hash,
        "sitemap_hash": sitemap_hash,
        "url_count": url_count,
    }
    STATE_PATH.write_text(json.dumps(payload, indent=2), encoding="utf-8")


def file_hash(path: pathlib.Path) -> str:
    if not path.exists():
        return ""

    digest = hashlib.sha256()
    with path.open("rb") as fp:
        while True:
            chunk = fp.read(8192)
            if not chunk:
                break
            digest.update(chunk)
    return digest.hexdigest()


def to_absolute_url(url_or_path: str) -> str:
    value = (url_or_path or "").strip()
    if not value:
        return ""
    if value.startswith("http://") or value.startswith("https://"):
        return value
    if not value.startswith("/"):
        value = "/" + value
    return f"{SCHEME}://{HOST}{value}"


def is_host_url(url: str) -> bool:
    parsed = urllib.parse.urlparse(url)
    return parsed.scheme in ("http", "https") and parsed.netloc.lower() == HOST.lower()


def read_sitemap_urls(path: pathlib.Path) -> list[str]:
    if not path.exists():
        raise FileNotFoundError(f"Sitemap not found: {path}")

    tree = ET.parse(path)
    root = tree.getroot()
    urls: list[str] = []

    for elem in root.iter():
        if elem.tag.endswith("loc") and elem.text:
            loc = elem.text.strip()
            if loc:
                urls.append(loc)

    return urls


def read_robots_sitemaps(path: pathlib.Path) -> list[str]:
    if not path.exists():
        return []

    sitemap_urls: list[str] = []
    for raw_line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = raw_line.strip()
        if line.lower().startswith("sitemap:"):
            sitemap_value = line.split(":", 1)[1].strip()
            if sitemap_value:
                sitemap_urls.append(sitemap_value)

    return sitemap_urls


def dedupe_preserve_order(items: list[str]) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for item in items:
        if item not in seen:
            seen.add(item)
            out.append(item)
    return out


def gather_urls() -> list[str]:
    sitemap_urls = read_sitemap_urls(SITEMAP_PATH)

    fixed_urls = [
        to_absolute_url("/robots.txt"),
        to_absolute_url("/sitemap.xml"),
    ]

    robots_sitemap_urls = read_robots_sitemaps(ROBOTS_PATH)

    all_urls = sitemap_urls + fixed_urls + robots_sitemap_urls
    all_urls = [normalize_url(u) for u in all_urls if u and is_host_url(u)]
    return dedupe_preserve_order(all_urls)


def ensure_key_file() -> None:
    expected_content = API_KEY + "\n"
    KEY_FILE_PATH.write_text(expected_content, encoding="utf-8")


def verify_key_file_publicly(session: requests.Session) -> tuple[bool, str]:
    try:
        response = session.get(KEY_LOCATION, timeout=REQUEST_TIMEOUT_SECONDS)
    except requests.RequestException as exc:
        return False, f"Key file request failed: {exc}"

    if response.status_code != 200:
        return False, f"Key file HTTP status {response.status_code} at {KEY_LOCATION}"

    key_text = (response.text or "").strip()
    if key_text != API_KEY:
        return False, "Key file content mismatch (must exactly match API key)."

    return True, "Key file is publicly accessible and valid."


def chunked(items: list[str], size: int) -> list[list[str]]:
    return [items[i : i + size] for i in range(0, len(items), size)]


def explain_status(code: int) -> str:
    mapping = {
        200: "OK: URLs accepted.",
        202: "Accepted: received; key validation may still be pending.",
        400: "Bad request: invalid payload format.",
        403: "Forbidden: key validation failed.",
        422: "Unprocessable entity: URL/host/key mismatch.",
        429: "Too many requests: rate-limited.",
    }
    return mapping.get(code, "Unexpected response code.")


def submit_batch(
    session: requests.Session,
    url_list: list[str],
    max_retries: int,
    retry_backoff_seconds: int,
) -> tuple[int, str, int]:
    payload = {
        "host": HOST,
        "key": API_KEY,
        "keyLocation": KEY_LOCATION,
        "urlList": url_list,
    }
    headers = {"Content-Type": "application/json; charset=utf-8"}
    attempts = 0
    last_status = 0
    last_body = ""

    for attempt in range(0, max_retries + 1):
        attempts = attempt + 1
        response = session.post(
            INDEXNOW_API_URL,
            data=json.dumps(payload),
            headers=headers,
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
        last_status = response.status_code
        last_body = response.text.strip()

        # Retry transient cases only.
        if last_status in (429, 500, 502, 503, 504) and attempt < max_retries:
            time.sleep(retry_backoff_seconds * (attempt + 1))
            continue

        break

    return last_status, last_body, attempts


def run(dry_run: bool, max_retries: int, retry_backoff_seconds: int, force: bool) -> int:
    ensure_key_file()
    urls = gather_urls()

    if not urls:
        print("No URLs found to submit.")
        return 0

    print(f"Prepared {len(urls)} URLs for host {HOST}.")
    print(f"Key file: {KEY_FILE_PATH}")
    print(f"Key location: {KEY_LOCATION}")

    current_hash = url_set_hash(urls)
    current_sitemap_hash = file_hash(SITEMAP_PATH)
    previous_state = load_previous_state()
    previous_hash = str(previous_state.get("url_set_hash", ""))
    previous_sitemap_hash = str(previous_state.get("sitemap_hash", ""))
    if (
        not force
        and not dry_run
        and previous_hash
        and previous_sitemap_hash
        and previous_hash == current_hash
        and previous_sitemap_hash == current_sitemap_hash
    ):
        print("URL set and sitemap unchanged from previous successful run; skipping submission.")
        append_log(
            [
                {
                    "timestamp": utc_now_iso(),
                    "url": "",
                    "host": HOST,
                    "method": "POST",
                    "http_status": None,
                    "submission_result": "skipped-unchanged",
                    "retry_attempts": 0,
                    "api_key": masked_key(API_KEY),
                    "batch_size": len(urls),
                    "url_set_hash": current_hash,
                    "sitemap_hash": current_sitemap_hash,
                }
            ]
        )
        print(f"Submission logs written to: {LOG_PATH}")
        return 0

    if dry_run:
        dry_records = []
        for sample in urls[:10]:
            print("DRY RUN URL:", sample)
            dry_records.append(
                {
                    "timestamp": utc_now_iso(),
                    "url": sample,
                    "host": HOST,
                    "method": "POST",
                    "http_status": None,
                    "submission_result": "dry-run",
                    "retry_attempts": 0,
                    "api_key": masked_key(API_KEY),
                    "batch_size": len(urls),
                }
            )
        if len(urls) > 10:
            print(f"... and {len(urls) - 10} more")
        append_log(dry_records)
        print(f"Wrote dry-run logs to: {LOG_PATH}")
        return 0

    batches = chunked(urls, MAX_URLS_PER_REQUEST)
    print(f"Submitting in {len(batches)} batch(es).")

    session = requests.Session()
    is_valid_key, key_message = verify_key_file_publicly(session)
    print(key_message)
    if not is_valid_key:
        append_log(
            [
                {
                    "timestamp": utc_now_iso(),
                    "url": "",
                    "host": HOST,
                    "method": "POST",
                    "http_status": None,
                    "submission_result": "failed-precheck",
                    "retry_attempts": 0,
                    "api_key": masked_key(API_KEY),
                    "batch_size": len(urls),
                    "response_snippet": key_message,
                }
            ]
        )
        print(f"Submission logs written to: {LOG_PATH}")
        return 1

    had_error = False

    for idx, batch in enumerate(batches, start=1):
        status, body, attempts = submit_batch(
            session,
            batch,
            max_retries=max_retries,
            retry_backoff_seconds=retry_backoff_seconds,
        )
        print(f"Batch {idx}/{len(batches)}: HTTP {status} - {explain_status(status)}")
        if body:
            print("Response:", body)

        if status not in (200, 202):
            had_error = True

        submission_result = "accepted" if status in (200, 202) else "failed"
        batch_logs = [
            {
                "timestamp": utc_now_iso(),
                "url": u,
                "host": HOST,
                "method": "POST",
                "http_status": status,
                "submission_result": submission_result,
                "retry_attempts": attempts - 1,
                "api_key": masked_key(API_KEY),
                "batch_index": idx,
                "batch_size": len(batch),
                "response_snippet": body[:300] if body else "",
            }
            for u in batch
        ]
        append_log(batch_logs)

        # Small pause between batches to reduce rate-limit risk.
        if idx < len(batches):
            time.sleep(1)

    if not had_error:
        save_current_state(
            current_hash=current_hash,
            sitemap_hash=current_sitemap_hash,
            url_count=len(urls),
        )
    print(f"Submission logs written to: {LOG_PATH}")
    return 1 if had_error else 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Submit vigilantvoices.com URLs to IndexNow")
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Build and show payload URLs without sending to IndexNow.",
    )
    parser.add_argument(
        "--max-retries",
        type=int,
        default=DEFAULT_MAX_RETRIES,
        help=f"Max retries for transient HTTP errors (default: {DEFAULT_MAX_RETRIES}).",
    )
    parser.add_argument(
        "--retry-backoff-seconds",
        type=int,
        default=DEFAULT_RETRY_BACKOFF_SECONDS,
        help=(
            "Base seconds for linear backoff between retries "
            f"(default: {DEFAULT_RETRY_BACKOFF_SECONDS})."
        ),
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Submit even if URL set has not changed since previous successful run.",
    )
    return parser.parse_args()


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