#!/usr/bin/env python3
"""Fill a fresh, migrated Wallos 5.8.1 database with synthetic demo records.

Refuses to touch a database containing users or subscriptions. Never point this
at a private or production Wallos volume.
"""

import argparse
import html
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


def seed(db_path: Path, logo_dir: Path, base_date: date) -> None:
    if not db_path.is_file():
        raise SystemExit(f"Missing migrated database: {db_path}")
    db = sqlite3.connect(db_path)
    try:
        db.execute("BEGIN IMMEDIATE")
        for table in ("user", "subscriptions"):
            count = db.execute(f"SELECT count(*) FROM {table}").fetchone()[0]
            if count:
                raise SystemExit(f"Refusing to seed: {table} already has {count} records")
        db.execute("INSERT INTO user (id, username, email, password, main_currency, firstname, language, budget) VALUES (1, 'demo', 'demo@example.invalid', '!disabled-demo-login!', 2, 'Demo', 'en', 0)")
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
            db.execute(
                """INSERT INTO subscriptions
                (name, logo, price, currency_id, next_payment, cycle, frequency,
                 category_id, payment_method_id, payer_user_id, notify, inactive, user_id, auto_renew)
                VALUES (?, ?, ?, 2, ?, ?, ?, ?, ?, 1, 0, 0, 1, 1)""",
                (name, filename, price, due, cycle, frequency, categories[category], payment_id),
            )
        if db.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
            raise SystemExit("SQLite integrity check failed")
        db.commit()
        print(f"Seeded {len(SERVICES)} fictional subscriptions into {db_path}")
    finally:
        db.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("database", type=Path)
    parser.add_argument("logos", type=Path)
    parser.add_argument("--timezone", default="Asia/Taipei", help="Timezone used for relative payment dates")
    args = parser.parse_args()
    seed(args.database, args.logos, datetime.now(ZoneInfo(args.timezone)).date())
