#!/usr/bin/env python3
"""Fill a fresh, migrated Wallos Dial database with synthetic demo records.

Refuses to touch a database containing users, subscriptions, or private icon
rows. Never point this at a private or production Wallos volume.
"""

import argparse
import hashlib
import html
import json
import re
import sqlite3
from datetime import date, datetime, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo

SERVICES = [
    ("Aster AI", "AI", 19, 7, "month"),
    ("Orbit Models", "AI", 29, 15, "month"),
    ("Muse Writing", "AI", 12, 24, "month"),
    ("Synthesis Lab", "AI", 96, 58, "year"),
    ("Frame Studio", "AI", 15, 4, "month"),
    ("Vector Voice", "AI", 18, 12, "month"),
    ("Prompt Atlas", "AI", 9, 29, "month"),
    ("Signal Research", "AI", 39, 43, "quarter"),
    ("Northstar VPS", "Infra", 18, 3, "month"),
    ("Harbor Compute", "Infra", 24, 11, "month"),
    ("Relay Node", "Infra", 8, 18, "month"),
    ("Cedar Storage", "Infra", 6, 28, "month"),
    ("Summit Network", "Infra", 42, 66, "quarter"),
    ("Cloud Route", "Infra", 11, 9, "month"),
    ("Forge Server", "Infra", 120, 93, "year"),
    ("Proxy Lane", "Infra", 14, 21, "month"),
    ("aster.example", "Domains", 16, 20, "year"),
    ("northstar.example", "Domains", 14, 38, "year"),
    ("quietworks.example", "Domains", 12, 75, "year"),
    ("archive.example", "Domains", 18, 110, "year"),
    ("signal.example", "Domains", 10, 142, "year"),
    ("lumen.example", "Domains", 13, 180, "year"),
    ("Cinema Club", "Media", 13, 1, "month"),
    ("Sound Room", "Media", 11, 6, "month"),
    ("Readwise Press", "Media", 8, 16, "month"),
    ("Film Library", "Media", 70, 48, "year"),
    ("Studio Music", "Media", 16, 25, "month"),
    ("Daily Journal", "Media", 5, 31, "month"),
    ("Screen Archive", "Media", 30, 78, "quarter"),
    ("Audio Shelf", "Media", 9, 13, "month"),
]

# Generic pictograms attached only to the optional 1.0 showcase. Names and
# amounts remain fictional; the original generated SVG Logo is still present.
SHOWCASE_ICONS = {
    "Aster AI": "chatgpt",       # dialogue
    "Muse Writing": "claude",    # reference book
    "Cinema Club": "apple-one", # grouped tiles
    "Frame Studio": "jianying",  # film frame
    "Harbor Compute": "netcup",  # compute chip
    "Cedar Storage": "nmcloud",  # cloud/data layers
    "Proxy Lane": "proxy-seller",# routed nodes
    "Forge Server": "racknerd",  # server cabinet
    "Summit Network": "vmiss",   # network globe
}

# Verified against a new, zero-user Wallos 5.8.3 / Dial 1.0 database. These
# signatures cover visible default labels and ownership, excluding mutable
# exchange rates and migration timestamps. Recheck them for every upstream
# upgrade; a mismatched template must never be reused for a public Demo.
DEFAULT_TABLES = {
    "categories": (17, 'id,name,"order",user_id', 'ca6d0741e73a1752cff729bc81e9451b61d1dc5977316e223071b92f50600397'),
    "currencies": (34, 'id,name,symbol,code,user_id', 'e0ec61f7ba979d0fc3e6b670bcefc4fa884350e3de71d7e397e8e6b21127529b'),
    "cycles": (5, 'id,days,name', '1a16d719dd37944583731dc0bdc09a2a90949fb955ee61122c6c3deaa65ef02f'),
    "frequencies": (31, 'id,name', '3b335bae3263ac3ca199b71f2ce66cbffe5c781385bea04ab65fba9b02359bd8'),
    "migrations": (59, 'migration', '5befb80b27c89b7018d20b1b74935ddf221dd27df29f56bbff8c7f3a48251083'),
    "payment_methods": (31, 'id,name,icon,enabled,"order",user_id', '45241505bd3026c0c8a920942205b6fa28c4842b332ed68cabde30c0e9a2afb4'),
}
# The public 0.1.1 image starts from Wallos 5.8.1 and has one fewer upstream
# migration. Keep its exact clean-template signature only for disposable
# upgrade/rollback fixtures; the 1.0 public reset always requests showcase
# icons and therefore requires the reviewed 5.8.3 schema below.
LEGACY_011_MIGRATIONS = (58, '376e96f262ac837686733e60d34a742ddd1e289d5fd237e3ecf7e1be37907f61')
ADMIN_DEFAULTS = {
    "id": 1, "registrations_open": 0, "max_users": 0,
    "require_email_verification": 0, "server_url": "", "smtp_address": "",
    "smtp_port": 587, "smtp_username": "", "smtp_password": "",
    "from_email": "", "encryption": "tls", "login_disabled": 0,
    "oidc_oauth_enabled": 0, "local_webhook_notifications_allowlist": "",
    "allow_standard_users_local_webhooks": 0, "vapid_public_key": "",
    "vapid_private_key": "", "update_notification": 0,
    # latest_version is checked by Wallos itself and may change over time.
    "latest_version": None,
}
SETTINGS_DEFAULTS = {
    "dark_theme": 0, "monthly_price": 0, "convert_currency": 0,
    "remove_background": 0, "color_theme": "blue", "hide_disabled": 0,
    "user_id": 1, "disabled_to_bottom": 0, "show_original_price": 0,
    "mobile_nav": 0, "show_subscription_progress": 0,
    "week_starts_sunday": 0, "upcoming_payments_limit": 3,
}


def verify_clean_template(db: sqlite3.Connection, showcase_icons: bool = False) -> bool:
    """Allow only reviewed system defaults; reject every other data row."""
    db.row_factory = sqlite3.Row
    tables = {
        row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type='table'")
    }
    full_schema = "migrations" in tables
    permitted = set(DEFAULT_TABLES) | {
        "admin", "settings", "last_update_next_payment_date", "sqlite_sequence"
    }
    for table in sorted(tables - permitted):
        quoted = '"' + table.replace('"', '""') + '"'
        count = db.execute(f"SELECT count(*) FROM {quoted}").fetchone()[0]
        if count:
            raise SystemExit(f"Refusing to seed: {table} already has {count} records")

    for table, (expected_count, columns, expected_hash) in DEFAULT_TABLES.items():
        if table not in tables:
            if full_schema:
                raise SystemExit(f"Refusing to seed: missing default table {table}")
            continue
        rows = [tuple(row) for row in db.execute(
            f'SELECT {columns} FROM "{table}" ORDER BY id'
        )]
        if not rows and not full_schema:
            continue  # Small synthetic test schemas may omit initial defaults.
        digest = hashlib.sha256(json.dumps(
            rows, ensure_ascii=False, separators=(',', ':')
        ).encode('utf-8')).hexdigest()
        if (table == "migrations" and not showcase_icons and
                (len(rows), digest) == LEGACY_011_MIGRATIONS):
            continue
        if len(rows) != expected_count or digest != expected_hash:
            raise SystemExit(f"Refusing to seed: {table} differs from clean Wallos 5.8.3 defaults")

    for table, expected in (("admin", ADMIN_DEFAULTS), ("settings", SETTINGS_DEFAULTS)):
        if table not in tables:
            raise SystemExit(f"Refusing to seed: missing system table {table}")
        rows = db.execute(f'SELECT * FROM "{table}"').fetchall()
        if len(rows) != 1:
            raise SystemExit(f"Refusing to seed: {table} must contain one clean default row")
        row = dict(rows[0])
        if set(row) - set(expected):
            raise SystemExit(f"Refusing to seed: {table} has unreviewed columns")
        for column, value in row.items():
            if table == "admin" and column == "latest_version":
                if not isinstance(value, str) or re.fullmatch(r'v\d+\.\d+\.\d+', value) is None:
                    raise SystemExit("Refusing to seed: admin.latest_version is not a version")
                continue
            if expected[column] is not None and value != expected[column]:
                raise SystemExit(f"Refusing to seed: {table}.{column} differs from clean default")

    if "last_update_next_payment_date" in tables:
        rows = db.execute('SELECT * FROM last_update_next_payment_date').fetchall()
        def clean_date(row: sqlite3.Row) -> bool:
            if set(row.keys()) != {"date"} or not isinstance(row["date"], str):
                return False
            try:
                return date.fromisoformat(row["date"]).isoformat() == row["date"]
            except ValueError:
                return False
        if len(rows) > 1 or not all(clean_date(row) for row in rows):
            raise SystemExit("Refusing to seed: last_update_next_payment_date is not a clean date row")
    return "dial_subscription_icons" in tables


def make_logo(initials: str, category: str) -> str:
    accent = "#d20b20" if category == "AI" else "#1a1917"
    safe = html.escape(initials)
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80">'
        '<rect width="80" height="80" rx="12" fill="#f5f2e9"/>'
        f'<circle cx="40" cy="40" r="28" fill="none" stroke="{accent}" stroke-width="3"/>'
        f'<text x="40" y="47" fill="{accent}" text-anchor="middle" '
        f'font-family="Arial,sans-serif" font-weight="700" font-size="19">{safe}</text>'
        '</svg>'
    )


def seed(db_path: Path, logo_dir: Path, base_date: date, showcase_icons: bool = False) -> None:
    if not db_path.is_file():
        raise SystemExit(f"Missing migrated database: {db_path}")
    db = sqlite3.connect(db_path)
    try:
        db.execute("BEGIN IMMEDIATE")
        has_icon_table = verify_clean_template(db, showcase_icons)
        if showcase_icons and not has_icon_table:
            raise SystemExit("Refusing to seed: dial_subscription_icons is required for --showcase-icons")
        db.execute("INSERT INTO user (id, username, email, password, main_currency, firstname, language, budget) VALUES (1, 'demo', 'demo@example.invalid', '!disabled-demo-login!', 2, 'Demo', 'en', 0)")
        # Wallos's remember-me recovery still requires a token row when
        # login_disabled=1. The public demo's login.php sets this fixed token.
        db.execute("INSERT INTO login_tokens (user_id, token) VALUES (1, 'abc123ABC')")
        db.execute("INSERT INTO household (id, name, email, user_id) VALUES (1, 'Demo', 'demo@example.invalid', 1)")
        db.execute("DELETE FROM categories WHERE user_id=1")
        db.execute('INSERT INTO categories (id, name, "order", user_id) VALUES (1, "No category", 0, 1)')
        categories = {}
        for order, name in enumerate(("AI", "Infra", "Domains", "Media"), 1):
            categories[name] = db.execute(
                'INSERT INTO categories (name, "order", user_id) VALUES (?, ?, 1)',
                (name, order),
            ).lastrowid
        db.execute("UPDATE admin SET registrations_open=0, max_users=1, login_disabled=1, update_notification=0 WHERE id=1")
        db.execute("UPDATE settings SET monthly_price=1, upcoming_payments_limit=4, convert_currency=0 WHERE user_id=1")
        db.execute(
            'INSERT INTO custom_css_style (css, user_id) VALUES (?, 1)',
            ('.wallos-dial .demo-banner{width:100%;box-sizing:border-box;margin:0 auto 20px;'
             'padding:11px 16px;border:1px solid #c9c5b9;border-left:3px solid #d90b1c;'
             'border-radius:2px;background:transparent;color:#65615a;text-align:left;'
             'font-size:13px;line-height:1.35}'
             '.wallos-dial .demo-banner b{color:#191817}',),
        )
        payment_id = db.execute(
            'INSERT INTO payment_methods (name, icon, enabled, "order", user_id) VALUES (?, ?, 1, 0, 1)',
            ('Demo Card', 'demo-payment.svg'),
        ).lastrowid
        logo_dir.mkdir(parents=True, exist_ok=True)
        (logo_dir / 'demo-payment.svg').write_text(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 64">'
            '<rect x="5" y="8" width="70" height="46" rx="6" fill="none" stroke="#191817" stroke-width="5"/>'
            '<path d="M8 22h64" stroke="#191817" stroke-width="5"/>'
            '<path d="M15 43h18" stroke="#d20b20" stroke-width="5"/></svg>'
        )
        for index, (name, category, price, offset, period) in enumerate(SERVICES, 1):
            initials = "".join(part[0] for part in name.split() if part)[:2].upper()
            if len(initials) < 2:
                initials = name[:2].upper()
            filename = f"demo-{index:02d}.svg"
            (logo_dir / filename).write_text(make_logo(initials, category))
            cycle, frequency = (4, 1) if period == "year" else (3, 3) if period == "quarter" else (3, 1)
            due = (base_date + timedelta(days=offset)).isoformat()
            subscription_id = db.execute(
                """INSERT INTO subscriptions
                (name, logo, price, currency_id, next_payment, cycle, frequency,
                 category_id, payment_method_id, payer_user_id, notify, inactive, user_id, auto_renew)
                VALUES (?, ?, ?, 2, ?, ?, ?, ?, ?, 1, 0, 0, 1, 1)""",
                (name, filename, price, due, cycle, frequency, categories[category], payment_id),
            ).lastrowid
            if showcase_icons and name in SHOWCASE_ICONS:
                db.execute(
                    """INSERT INTO dial_subscription_icons
                    (user_id, subscription_id, source, catalog_key, presentation, treatment, scale)
                    VALUES (1, ?, 'catalog', ?, 'plain', 'color', 100)""",
                    (subscription_id, SHOWCASE_ICONS[name]),
                )
        if db.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
            raise SystemExit("SQLite integrity check failed")
        db.commit()
        print(f"Seeded {len(SERVICES)} fictional subscriptions into {db_path}")
        if showcase_icons and has_icon_table:
            print(f"Assigned {len(SHOWCASE_ICONS)} neutral catalog pictograms")
    finally:
        db.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("database", type=Path)
    parser.add_argument("logos", type=Path)
    parser.add_argument("--timezone", default="Asia/Taipei", help="Timezone used for relative payment dates")
    parser.add_argument("--showcase-icons", action="store_true",
                        help="Require the Dial 1.0 icon table and assign nine neutral catalog pictograms")
    args = parser.parse_args()
    seed(args.database, args.logos, datetime.now(ZoneInfo(args.timezone)).date(), args.showcase_icons)
