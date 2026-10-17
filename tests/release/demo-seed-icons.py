#!/usr/bin/env python3
"""Check synthetic Demo seeding and fail-closed template validation."""

from __future__ import annotations

from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile


ROOT = Path(__file__).resolve().parents[2]
SCHEMA = """
CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT,
  main_currency INTEGER, firstname TEXT, language TEXT, budget INTEGER);
CREATE TABLE login_tokens (user_id INTEGER, token TEXT);
CREATE TABLE household (id INTEGER PRIMARY KEY, name TEXT, email TEXT, user_id INTEGER);
CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, "order" INTEGER, user_id INTEGER);
CREATE TABLE admin (id INTEGER PRIMARY KEY, registrations_open INTEGER, max_users INTEGER,
  login_disabled INTEGER, update_notification INTEGER);
INSERT INTO admin(id,registrations_open,max_users,login_disabled,update_notification)
  VALUES (1,0,0,0,0);
CREATE TABLE settings (user_id INTEGER, monthly_price INTEGER, upcoming_payments_limit INTEGER,
  convert_currency INTEGER);
INSERT INTO settings(user_id,monthly_price,upcoming_payments_limit,convert_currency)
  VALUES (1,0,3,0);
CREATE TABLE custom_css_style (css TEXT, user_id INTEGER);
CREATE TABLE payment_methods (id INTEGER PRIMARY KEY, name TEXT, icon TEXT,
  enabled INTEGER, "order" INTEGER, user_id INTEGER);
CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, name TEXT, logo TEXT, price REAL,
  currency_id INTEGER, next_payment TEXT, cycle INTEGER, frequency INTEGER,
  category_id INTEGER, payment_method_id INTEGER, payer_user_id INTEGER,
  notify INTEGER, inactive INTEGER, user_id INTEGER, auto_renew INTEGER);
"""
ICON_TABLE = """
CREATE TABLE dial_subscription_icons (user_id INTEGER, subscription_id INTEGER,
  source TEXT, catalog_key TEXT, asset_id INTEGER, presentation TEXT,
  treatment TEXT, scale INTEGER, updated_at TEXT,
  PRIMARY KEY(user_id,subscription_id));
"""


def run_case(root: Path, name: str, with_table: bool, showcase: bool) -> None:
    directory = root / name
    directory.mkdir()
    database = directory / "wallos.db"
    logos = directory / "logos"
    with sqlite3.connect(database) as db:
        db.executescript(SCHEMA + (ICON_TABLE if with_table else ""))
    command = [sys.executable, str(ROOT / "demo/seed.py"), str(database), str(logos)]
    if showcase:
        command.append("--showcase-icons")
    first = subprocess.run(command, text=True, capture_output=True, check=True)
    assert "30 fictional subscriptions" in first.stdout, first.stdout
    if showcase:
        assert "Assigned 9 neutral catalog pictograms" in first.stdout
    with sqlite3.connect(database) as db:
        assert db.execute("SELECT count(*) FROM subscriptions").fetchone()[0] == 30
        assert db.execute("PRAGMA integrity_check").fetchone()[0] == "ok"
        if with_table:
            rows = db.execute("SELECT catalog_key,presentation,treatment,scale FROM dial_subscription_icons ORDER BY catalog_key").fetchall()
            assert len(rows) == (9 if showcase else 0), (name, rows)
            if showcase:
                assert len({row[0] for row in rows}) == 9, rows
                assert all(row[1:] == ("plain", "color", 100) for row in rows), rows
                assert db.execute("SELECT count(*) FROM subscriptions WHERE logo LIKE 'demo-%'").fetchone()[0] == 30
                assigned = dict(db.execute(
                    """SELECT s.name,i.catalog_key FROM subscriptions AS s
                       JOIN dial_subscription_icons AS i ON i.subscription_id=s.id
                       WHERE i.user_id=1"""
                ).fetchall())
                assert assigned["Aster AI"] == "chatgpt" and assigned["Harbor Compute"] == "netcup"
                assert assigned["Frame Studio"] == "jianying" and assigned["Forge Server"] == "racknerd"
    assert len(list(logos.glob("demo-*.svg"))) == 31, name  # 30 services plus payment mark
    second = subprocess.run(command, text=True, capture_output=True)
    assert second.returncode != 0 and "Refusing to seed" in second.stderr, second


def rejects_orphaned_payment_method(root: Path) -> None:
    """A user-less template may still contain a visible private payment row."""
    directory = root / "orphan-private-payment"
    directory.mkdir()
    database = directory / "wallos.db"
    logos = directory / "logos"
    with sqlite3.connect(database) as db:
        db.executescript(SCHEMA + ICON_TABLE)
        db.execute(
            'INSERT INTO payment_methods(name,icon,enabled,"order",user_id) VALUES(?,?,?,?,?)',
            ('PRIVATE BANK ACCOUNT', 'private.svg', 1, 0, 1),
        )
    result = subprocess.run(
        [sys.executable, str(ROOT / "demo/seed.py"), str(database), str(logos), "--showcase-icons"],
        text=True, capture_output=True,
    )
    assert result.returncode != 0 and "payment_methods" in result.stderr, result
    with sqlite3.connect(database) as db:
        assert db.execute("SELECT count(*) FROM user").fetchone()[0] == 0
        assert db.execute("SELECT count(*) FROM subscriptions").fetchone()[0] == 0
        assert db.execute("SELECT name FROM payment_methods").fetchone()[0] == "PRIVATE BANK ACCOUNT"
    assert not logos.exists(), "Rejected template must not produce public Logo files"


def rejects_missing_icon_schema(root: Path) -> None:
    directory = root / "missing-icon-schema"
    directory.mkdir()
    database = directory / "wallos.db"
    logos = directory / "logos"
    with sqlite3.connect(database) as db:
        db.executescript(SCHEMA)
    result = subprocess.run(
        [sys.executable, str(ROOT / "demo/seed.py"), str(database), str(logos), "--showcase-icons"],
        text=True, capture_output=True,
    )
    assert result.returncode != 0 and "dial_subscription_icons" in result.stderr, result
    assert not logos.exists(), "Missing icon schema must fail before generating Logo files"


def rejects_other_account_rows(root: Path) -> None:
    spoofed_payment_defaults = ''.join(
        f"INSERT INTO payment_methods(id,name,icon,enabled,\"order\",user_id) "
        f"VALUES({index},'Fake Default {index}','private-{index}.svg',1,{index},1);"
        for index in range(1, 32)
    )
    cases = [
        ("payment_methods", spoofed_payment_defaults),
        ("household", "INSERT INTO household(id,name,email,user_id) VALUES(2,'Private Home','secret@example.invalid',1)"),
        ("login_tokens", "INSERT INTO login_tokens(user_id,token) VALUES(1,'private-token')"),
        ("custom_css_style", "INSERT INTO custom_css_style(css,user_id) VALUES('PRIVATE NOTE',1)"),
        ("settings", "INSERT INTO settings(user_id,monthly_price) VALUES(2,999)"),
        ("categories", 'INSERT INTO categories(id,name,"order",user_id) VALUES(9,"Private",9,2)'),
        ("currencies", "CREATE TABLE currencies(id INTEGER PRIMARY KEY,name TEXT,symbol TEXT,code TEXT,user_id INTEGER);"
                       "INSERT INTO currencies VALUES(99,'Private Currency','SECRET','XXX',1)"),
        ("ai_settings", "CREATE TABLE ai_settings(id INTEGER,user_id INTEGER,api_key TEXT);"
                        "INSERT INTO ai_settings VALUES(1,1,'private-api-key')"),
        ("future_user_data", "CREATE TABLE future_user_data(user_id INTEGER,note TEXT);"
                             "INSERT INTO future_user_data VALUES(1,'private-note')"),
        ("admin", "ALTER TABLE admin ADD COLUMN smtp_password TEXT;"
                  "UPDATE admin SET smtp_password='private-smtp-secret' WHERE id=1"),
        ("admin", "ALTER TABLE admin ADD COLUMN latest_version TEXT;"
                  "UPDATE admin SET latest_version='2026-private-note' WHERE id=1"),
        ("last_update_next_payment_date", "CREATE TABLE last_update_next_payment_date(date TEXT);"
                                          "INSERT INTO last_update_next_payment_date VALUES('2026-private-note')"),
    ]
    for index, (name, contamination) in enumerate(cases):
        directory = root / f"orphan-{index}-{name}"
        directory.mkdir()
        database = directory / "wallos.db"
        logos = directory / "logos"
        with sqlite3.connect(database) as db:
            db.executescript(SCHEMA + ICON_TABLE + contamination + ";")
        result = subprocess.run(
            [sys.executable, str(ROOT / "demo/seed.py"), str(database), str(logos), "--showcase-icons"],
            text=True, capture_output=True,
        )
        assert result.returncode != 0 and name in result.stderr, (name, result)
        with sqlite3.connect(database) as db:
            assert db.execute("SELECT count(*) FROM user").fetchone()[0] == 0, name
            assert db.execute("SELECT count(*) FROM subscriptions").fetchone()[0] == 0, name
        assert not logos.exists(), name


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="dial-demo-seed-") as temp:
        root = Path(temp)
        run_case(root, "new-default", True, False)
        run_case(root, "new-showcase", True, True)
        run_case(root, "minimal-no-icon-default", False, False)
        rejects_missing_icon_schema(root)
        rejects_orphaned_payment_method(root)
        rejects_other_account_rows(root)
    print("Demo seed defaults, showcase, and private-template rejection passed")


if __name__ == "__main__":
    main()
