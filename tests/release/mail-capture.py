#!/usr/bin/env python3
"""Exercise five Wallos mail paths against Mailpit on a Docker internal network.

All recipients, credentials, subscriptions, and the Server URL are synthetic.
The application has no external network route, and Mailpit accepts only
example.test / example.invalid recipients with no relay configured.
"""

from __future__ import annotations

import email
from email import policy
import hashlib
import json
import os
import re
import sqlite3
import subprocess
import sys
import tempfile
import time
import uuid
from datetime import datetime, timedelta, timezone
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
SERVER_URL = "https://dial-mail.example.test/wallos"
RECIPIENT = "mail-fixture@example.test"
MAILPIT_IMAGE = os.environ.get(
    "DIAL_MAILPIT_IMAGE",
    "axllent/mailpit@sha256:23fe39e81bde596a918000b3135c0f85dc8bb4c9ea6be274b051143f3e300387",
)
OUTPUT = ROOT / "data" / "mail-qa-1.0"
LANGUAGE = os.environ.get("DIAL_MAIL_QA_LANGUAGE", "zh_cn")
if LANGUAGE not in {"en", "zh_cn", "zh_tw"}:
    raise SystemExit("DIAL_MAIL_QA_LANGUAGE must be en, zh_cn, or zh_tw")


def run(*args: str, check: bool = True, input: str | None = None) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(args, input=input, text=True, capture_output=True, check=False)
    if check and result.returncode:
        raise RuntimeError(f"Command failed: {args!r}\n{result.stdout}\n{result.stderr}")
    return result


def docker(*args: str, check: bool = True, input: str | None = None) -> subprocess.CompletedProcess[str]:
    return run("docker", *args, check=check, input=input)


def docker_bytes(*args: str) -> bytes:
    result = subprocess.run(("docker", *args), capture_output=True, check=False)
    if result.returncode:
        raise RuntimeError(f"Docker binary read failed: {args!r}\n{result.stderr.decode(errors='replace')}")
    return result.stdout


def wait_for_app(name: str) -> None:
    for _ in range(60):
        ready = docker("exec", name, "curl", "-fsS", "http://127.0.0.1/health.php", check=False)
        log = docker("logs", name, check=False).stdout + docker("logs", name, check=False).stderr
        if ready.returncode == 0 and "Updated next payment dates" in log:
            return
        time.sleep(1)
    raise RuntimeError(f"Application did not become ready:\n{docker('logs', name, check=False).stderr}")


def assert_fixture_owner(name: str) -> None:
    """The local runner must be able to seed and remove its bind-mounted data."""
    if os.geteuid() == 0:
        return
    actual = (int(docker("exec", name, "id", "-u", "www-data").stdout.strip()),
              int(docker("exec", name, "id", "-g", "www-data").stdout.strip()))
    expected = (os.geteuid(), os.getegid())
    if actual != expected:
        raise AssertionError(
            f"Disposable mail fixture runs as {actual}, but its host owner is {expected}; "
            "the host cannot seed or clean up 0755 bind-mounted data on Linux"
        )


def mailpit_get(name: str, path: str) -> str:
    return docker("exec", name, "wget", "-qO-", f"http://127.0.0.1:8025{path}").stdout


def wait_for_count(name: str, expected: int) -> list[dict]:
    for _ in range(40):
        entries = json.loads(mailpit_get(name, "/api/v1/messages"))["messages"]
        if len(entries) >= expected:
            if len(entries) != expected:
                raise AssertionError(f"Expected {expected} captured messages, found {len(entries)}")
            return entries
        time.sleep(0.25)
    raise AssertionError(f"Mailpit did not capture message {expected}")


def seed(db_path: Path) -> None:
    import importlib.util

    spec = importlib.util.spec_from_file_location("dial_demo_seed", ROOT / "demo" / "seed.py")
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    now = datetime.now(timezone.utc).date()
    module.seed(db_path, db_path.parent.parent / "logos", now)
    db = sqlite3.connect(db_path)
    try:
        db.execute("BEGIN IMMEDIATE")
        db.execute("""UPDATE user SET email=?, language=?, period_budget=1000,
                   budget_period_type='monthly', budget_period_anchor_date=? WHERE id=1""",
                   (RECIPIENT, LANGUAGE, now.isoformat()))
        db.execute("UPDATE household SET email=? WHERE id=1", (RECIPIENT,))
        db.execute("""UPDATE admin SET login_disabled=0, max_users=1,
                   require_email_verification=1, server_url=?,
                   smtp_address='mailpit', smtp_port=1025,
                   smtp_username='fixture', smtp_password='fixture',
                   from_email='wallos@example.test', encryption='none',
                   local_webhook_notifications_allowlist='mailpit:1025'
                   WHERE id=1""", (SERVER_URL,))
        db.execute("""INSERT INTO email_notifications
                   (enabled, smtp_address, smtp_port, smtp_username,
                    smtp_password, from_email, encryption, user_id)
                   VALUES (1, 'mailpit', 1025, 'fixture', 'fixture',
                           'wallos@example.test', 'none', 1)""")
        db.execute("""INSERT INTO notification_settings
                   (days, period_summary_at_period_start, user_id) VALUES (1, 1, 1)""")
        db.execute("UPDATE subscriptions SET notify=0, cancellation_date=NULL, notes='', url=''")
        db.execute("""UPDATE subscriptions SET name='Aster AI renewal', notify=1,
                   notify_days_before=1, next_payment=? WHERE id=1""",
                   ((now + timedelta(days=1)).isoformat(),))
        db.execute("""UPDATE subscriptions SET name='Northstar cancellation',
                   cancellation_date=?, next_payment=? WHERE id=2""",
                   (now.isoformat(), (now + timedelta(days=7)).isoformat()))
        db.execute("""INSERT INTO email_verification
                   (user_id, email, token, email_sent)
                   VALUES (1, ?, 'fixture-verify-token', 0)""", (RECIPIENT,))
        db.execute("""INSERT INTO password_resets
                   (user_id, email, token, email_sent)
                   VALUES (1, ?, 'fixture-reset-token', 0)""", (RECIPIENT,))
        if db.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
            raise AssertionError("Seeded SQLite database failed integrity_check")
        db.commit()
    finally:
        db.close()


def app_cron(name: str, script: str) -> str:
    result = docker("exec", "--workdir", "/var/www/html/endpoints/cronjobs",
                    name, "php", script)
    return result.stdout + result.stderr


def smtp_test_endpoint(name: str) -> str:
    login = docker("exec", name, "curl", "-fsSL", "-c", "/tmp/dial-mail.cookies",
                   "-b", "/tmp/dial-mail.cookies", "--data-urlencode", "username=demo",
                   "--data-urlencode", "password=dial-mail-fixture-only",
                   "http://127.0.0.1/login.php")
    match = re.search(r'window\.csrfToken = "([^"]+)"', login.stdout)
    if not match:
        raise AssertionError("Fixture login did not expose an authenticated CSRF token")
    payload = json.dumps({
        "smtpaddress": "mailpit", "smtpport": 1025, "encryption": "none",
        "smtpusername": "fixture", "smtppassword": "fixture",
        "fromemail": "wallos@example.test",
    })
    result = docker("exec", "-i", name, "curl", "-sS", "-X", "POST",
                    "-b", "/tmp/dial-mail.cookies", "-c", "/tmp/dial-mail.cookies",
                    "-H", "Content-Type: application/json",
                    "-H", f"X-CSRF-Token: {match.group(1)}",
                    "--data-binary", "@-",
                    "http://127.0.0.1/endpoints/notifications/testemailnotifications.php",
                    input=payload)
    response = json.loads(result.stdout)
    if response.get("success") is not True:
        raise AssertionError(f"Authenticated SMTP test failed: {response}")
    return result.stdout


def inspect_message(raw: bytes, path: str) -> dict:
    message = email.message_from_bytes(raw, policy=policy.default)
    if message.get_content_type() != "multipart/alternative":
        raise AssertionError(f"{path}: expected multipart/alternative")
    if str(message["To"]).find(RECIPIENT) < 0:
        raise AssertionError(f"{path}: unexpected recipient {message['To']}")
    plain = message.get_body(preferencelist=("plain",))
    html = message.get_body(preferencelist=("html",))
    if plain is None or html is None or not plain.get_content().strip() or not html.get_content().strip():
        raise AssertionError(f"{path}: missing plain or HTML body")
    html_text = html.get_content()
    plain_text = plain.get_content()
    html_locale = LANGUAGE.replace("_", "-")
    if f'lang="{html_locale}"' not in html_text.lower():
        raise AssertionError(f"{path}: HTML body did not use {LANGUAGE} locale")
    if SERVER_URL not in html_text:
        raise AssertionError(f"{path}: HTML body did not use configured fictional Server URL")
    if path in ("verification", "password reset") and SERVER_URL not in plain_text:
        raise AssertionError(f"{path}: plain account link did not use Server URL")
    expected = {
        "en": {"renewal": "Renewal reminder", "cancellation": "Cancellation reminder",
               "SMTP test": "Email notification test", "verification": "Verify your email",
               "password reset": "Reset your password"},
        "zh_cn": {"renewal": "订阅续费提醒", "cancellation": "订阅取消提醒",
                  "SMTP test": "邮件通知测试", "verification": "验证你的邮箱",
                  "password reset": "重置你的密码"},
        "zh_tw": {"renewal": "訂閱續費提醒", "cancellation": "訂閱取消提醒",
                  "SMTP test": "郵件通知測試", "verification": "驗證你的信箱",
                  "password reset": "重設你的密碼"},
    }[LANGUAGE][path]
    if expected not in html_text:
        raise AssertionError(f"{path}: expected translated heading {expected!r} absent")
    if path != "SMTP test" and expected not in plain_text:
        raise AssertionError(f"{path}: plain-text fallback did not use {LANGUAGE} locale")
    if path == "SMTP test" and LANGUAGE != "en" and not re.search(r"[\u3400-\u9fff]", plain_text):
        raise AssertionError(f"SMTP test: plain-text fallback did not use {LANGUAGE} locale")
    if path != "SMTP test":
        subject = str(message["Subject"])
        if expected not in subject:
            raise AssertionError(f"{path}: subject {subject!r} did not use {LANGUAGE} locale")
        raw_subject = next(value for key, value in message.raw_items() if key.lower() == "subject")
        if LANGUAGE != "en" and (not raw_subject.isascii() or "=?utf-8?" not in raw_subject.lower()):
            raise AssertionError(f"{path}: non-ASCII subject lacks RFC 2047 UTF-8 MIME encoding")
    if path == "renewal":
        period_labels = {
            "en": ("Amount for Pay Period", "Remaining"),
            "zh_cn": ("付款周期金额", "剩余"),
            "zh_tw": ("付款週期金額", "剩餘"),
        }[LANGUAGE]
        for label in period_labels:
            if label not in html_text or label not in plain_text:
                raise AssertionError(f"renewal: period summary label {label!r} missing from {LANGUAGE} HTML/plain")
    return {
        "path": path, "subject": str(message["Subject"]),
        "recipient": RECIPIENT, "mime": message.get_content_type(),
        "plain": True, "html": True, "locale": html_locale,
        "fictional_server_url": SERVER_URL,
        "sha256": hashlib.sha256(raw).hexdigest(),
    }


def exercise_cancellation_boundary(app: str, pit: str, db_path: Path,
                                   seen_message_ids: set[str]) -> dict:
    """A legacy payer ID from another account must never receive this account's mail."""
    today = datetime.now(timezone.utc).date()
    foreign_email = "other-account@example.test"
    db = sqlite3.connect(db_path)
    try:
        db.execute("BEGIN IMMEDIATE")
        db.execute("""INSERT INTO user
                   (id, username, email, password, main_currency, firstname, language, budget)
                   VALUES (2, 'other-account', ?, '!disabled!', 2, 'Other', 'en', 0)""",
                   (foreign_email,))
        db.execute("""INSERT INTO household (id, name, email, user_id)
                   VALUES (2, 'Other household', ?, 2)""", (foreign_email,))
        db.execute("UPDATE subscriptions SET name='Northstar &amp; Orbit cancellation' WHERE id=2")
        db.execute("""UPDATE subscriptions SET name='Foreign payer leak',
                   payer_user_id=2, cancellation_date=?, next_payment=?,
                   inactive=0, cycle=3, frequency=1 WHERE id=3""",
                   (today.isoformat(), (today + timedelta(days=7)).isoformat()))
        db.commit()
    finally:
        db.close()
    output = app_cron(app, "sendcancellationnotifications.php")
    entries = wait_for_count(pit, 6)
    fresh = {entry["ID"] for entry in entries} - seen_message_ids
    if len(fresh) != 1:
        raise AssertionError(f"Legacy cross-account payer: expected one owner-only mail; got {len(fresh)}; cron: {output[-500:]}")
    raw = docker_bytes("exec", pit, "wget", "-qO-",
                       f"http://127.0.0.1:8025/api/v1/message/{fresh.pop()}/raw")
    message = email.message_from_bytes(raw, policy=policy.default)
    if RECIPIENT not in str(message["To"]) or foreign_email in raw.decode(errors="replace"):
        raise AssertionError("Legacy cross-account payer redirected cancellation mail")
    plain = message.get_body(preferencelist=("plain",)).get_content()
    html = message.get_body(preferencelist=("html",)).get_content()
    if "Northstar & Orbit cancellation" not in plain:
        raise AssertionError("Cancellation plain text retained an encoded HTML entity")
    if "Northstar &amp; Orbit cancellation" not in html or "&amp;amp;" in html:
        raise AssertionError("Cancellation HTML double-escaped a stored entity")
    if "Foreign payer leak" in plain or "Foreign payer leak" in html:
        raise AssertionError("Foreign-payer subscription leaked into owner email")
    if "skipped" not in output.lower() or "payer" not in output.lower():
        raise AssertionError("Legacy cross-account payer was not logged as skipped")
    # Restore the original synthetic cancellation for the separate
    # non-email channel probes below.
    db = sqlite3.connect(db_path)
    try:
        db.execute("UPDATE subscriptions SET name='Northstar cancellation' WHERE id=2")
        db.execute("UPDATE subscriptions SET cancellation_date=NULL WHERE id=3")
        db.commit()
    finally:
        db.close()
    return {"owner_only": True, "foreign_payer_skipped": True,
            "entity_decoded_once": True, "data": "synthetic only"}


def exercise_internal_receipts(app: str, sink: str, network: str, image: str,
                               scratch: Path) -> dict:
    receipts = scratch / "receipts"
    receipts.mkdir()
    receipts.chmod(0o777)
    docker("run", "-d", "--name", sink, "--network", network,
           "--network-alias", "receipt",
           "-v", f"{ROOT / 'tests/release/receipt-server.php'}:/fixtures/router.php:ro",
           "-v", f"{receipts}:/receipts", "--entrypoint", "php", image,
           "-S", "0.0.0.0:8080", "/fixtures/router.php")
    for _ in range(40):
        health = docker("exec", app, "curl", "-fsS", "http://receipt:8080/health", check=False)
        if health.returncode == 0:
            break
        time.sleep(0.25)
    else:
        raise AssertionError("Internal-only receipt server did not start")

    db = sqlite3.connect(scratch / "db" / "wallos.db")
    try:
        db.execute("BEGIN IMMEDIATE")
        db.execute("UPDATE email_notifications SET enabled=0 WHERE user_id=1")
        db.execute("UPDATE admin SET local_webhook_notifications_allowlist='mailpit:1025,receipt:8080' WHERE id=1")
        db.execute("""INSERT INTO discord_notifications
                   (enabled, webhook_url, user_id) VALUES (1, 'http://receipt:8080/discord', 1)""")
        db.execute("""INSERT INTO gotify_notifications
                   (enabled, url, token, user_id) VALUES (1, 'http://receipt:8080', 'synthetic', 1)""")
        db.execute("""INSERT INTO mattermost_notifications
                   (enabled, webhook_url, user_id) VALUES (1, 'http://receipt:8080/mattermost', 1)""")
        db.execute("""INSERT INTO ntfy_notifications
                   (enabled, host, topic, user_id)
                   VALUES (1, 'http://receipt:8080', 'ntfy', 1)""")
        db.execute("""INSERT INTO webhook_notifications
                   (enabled, url, request_method, headers, payload, cancelation_payload, user_id)
                   VALUES (1, 'http://receipt:8080/webhook', 'POST',
                           '',
                           '{\"renewal\":\"{{subscription_name}}\"}',
                           '{\"cancellation\":\"{{subscription_name}}\"}', 1)""")
        db.commit()
    finally:
        db.close()

    renewal_log = app_cron(app, "sendnotifications.php")
    cancellation_log = app_cron(app, "sendcancellationnotifications.php")
    receipt_file = receipts / "requests.jsonl"
    for _ in range(40):
        if receipt_file.exists() and len(receipt_file.read_text().splitlines()) >= 9:
            break
        time.sleep(0.25)
    if not receipt_file.exists():
        raise AssertionError("No internal notification receipts were captured")
    requests = [json.loads(line) for line in receipt_file.read_text().splitlines()]
    expected = {"/discord", "/message", "/mattermost", "/ntfy", "/webhook"}
    counts = {path: sum(item["path"] == path for item in requests) for path in expected}
    expected_counts = {path: (1 if path == "/mattermost" else 2) for path in expected}
    if counts != expected_counts:
        raise AssertionError(f"Expected nine receipts from five local channels; got {counts}; logs: {renewal_log[-900:]}, {cancellation_log[-900:]}")
    for path in expected:
        payloads = [item["body"] for item in requests if item["path"] == path]
        if not any("Aster AI renewal" in body for body in payloads):
            raise AssertionError(f"{path}: renewal payload missing")
        if path != "/mattermost" and not any("Northstar cancellation" in body for body in payloads):
            raise AssertionError(f"{path}: cancellation payload missing")
    return {"network": "Docker --internal", "channels": sorted(expected),
            "receipts": counts, "data": "synthetic only"}


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit("Usage: tests/release/mail-capture.py <local-image-tag>")
    image = sys.argv[1]
    token = uuid.uuid4().hex[:10]
    network, app, pit, sink = (f"dial-mail-qa-{token}", f"dial-mail-app-{token}",
                               f"dial-mailpit-{token}", f"dial-receipt-{token}")
    (ROOT / "data").mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix=".dial-mail-qa-", dir=ROOT / "data") as scratch_text:
        scratch = Path(scratch_text)
        db_dir = scratch / "db"
        logos = scratch / "logos"
        db_dir.mkdir()
        logos.mkdir()
        try:
            docker("network", "create", "--internal", network)
            docker("run", "-d", "--name", pit, "--network", network,
                   "--network-alias", "mailpit", MAILPIT_IMAGE,
                   "--disable-version-check", "--smtp-auth-accept-any",
                   "--smtp-auth-allow-insecure", "--smtp-allowed-recipients",
                   r"^.+@example\.(test|invalid)$")
            base_args = ("run", "-d", "--name", app, "--network", network,
                         "-e", "TZ=UTC", "-e", "DEMO_MODE=0",
                         *(("-e", f"PUID={os.geteuid()}", "-e", f"PGID={os.getegid()}")
                           if os.geteuid() != 0 else ()),
                         "-v", f"{db_dir}:/var/www/html/db",
                         "-v", f"{logos}:/var/www/html/images/uploads/logos")
            docker(*base_args, image)
            wait_for_app(app)
            assert_fixture_owner(app)
            docker("rm", "-f", app)
            seed(db_dir / "wallos.db")
            password_hash = docker("run", "--rm", "--entrypoint", "php", image,
                                   "-r", 'echo password_hash("dial-mail-fixture-only", PASSWORD_DEFAULT);').stdout
            db = sqlite3.connect(db_dir / "wallos.db")
            db.execute("UPDATE user SET password=? WHERE id=1", (password_hash,))
            db.commit()
            db.close()
            docker(*base_args, image)
            wait_for_app(app)
            paths = [
                ("renewal", "sendnotifications.php"),
                ("cancellation", "sendcancellationnotifications.php"),
                ("verification", "sendverificationemails.php"),
                ("password reset", "sendresetpasswordemails.php"),
            ]
            ids: dict[str, str] = {}
            logs: dict[str, str] = {}
            seen_message_ids: set[str] = set()
            for index, (path, script) in enumerate(paths, 1):
                logs[path] = app_cron(app, script)[-1000:]
                entries = wait_for_count(pit, index)
                fresh = {entry["ID"] for entry in entries} - seen_message_ids
                if len(fresh) != 1:
                    raise AssertionError(f"{path}: expected one new captured message, found {len(fresh)}")
                ids[path] = fresh.pop()
                seen_message_ids.add(ids[path])
            # The pending verification row correctly prevents login. Simulate
            # completed verification for the authenticated SMTP-test state.
            docker("exec", app, "sqlite3", "/var/www/html/db/wallos.db",
                   "DELETE FROM email_verification WHERE user_id=1; "
                   "UPDATE admin SET require_email_verification=0 WHERE id=1")
            logs["SMTP test"] = smtp_test_endpoint(app)
            entries = wait_for_count(pit, 5)
            fresh = {entry["ID"] for entry in entries} - seen_message_ids
            if len(fresh) != 1:
                raise AssertionError(f"SMTP test: expected one new captured message, found {len(fresh)}")
            ids["SMTP test"] = fresh.pop()
            seen_message_ids.add(ids["SMTP test"])
            result = []
            for path in ("renewal", "cancellation", "verification", "password reset", "SMTP test"):
                raw = docker_bytes("exec", pit, "wget", "-qO-",
                                   f"http://127.0.0.1:8025/api/v1/message/{ids[path]}/raw")
                result.append(inspect_message(raw, path))
            isolation = exercise_cancellation_boundary(app, pit, db_dir / "wallos.db", seen_message_ids)
            non_email = (exercise_internal_receipts(app, sink, network, image, scratch)
                         if LANGUAGE == "zh_cn" else None)
            OUTPUT.mkdir(parents=True, exist_ok=True)
            manifest = {"image": image, "network": "Docker --internal",
                        "mailpit_image": MAILPIT_IMAGE,
                        "mailpit_image_id": docker("image", "inspect", MAILPIT_IMAGE,
                                                   "--format", "{{.Id}}").stdout.strip(),
                        "data": "synthetic only",
                        "messages": result, "cron_outputs": logs,
                        "cancellation_boundary": isolation,
                        "non_email_receipts": non_email}
            output_path = OUTPUT / f"manifest-{LANGUAGE}.json"
            output_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")
            print(f"Five isolated SMTP paths passed in {LANGUAGE}; evidence: {output_path}")
            if non_email:
                print("Five configurable non-email channels produced nine expected renewal/cancellation receipts on the internal network.")
        finally:
            docker("rm", "-f", app, pit, sink, check=False)
            docker("network", "rm", network, check=False)


if __name__ == "__main__":
    main()
