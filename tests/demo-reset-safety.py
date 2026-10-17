#!/usr/bin/env python3
"""Reject an unsafe Demo reset before stopping or swapping its container."""

from __future__ import annotations

import os
from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile


ROOT = Path(__file__).resolve().parents[1]
IMAGE = "ghcr.io/lukevoidx/wallos-dial:1.0.0"


def check_case(base: Path, name: str, mode: str, actual_image: str,
               icon_asset: bool, expected_error: str) -> None:
    root = base / name
    for directory in ("template", "db", "logos"):
        (root / directory).mkdir(parents=True)
    (root / ".wallos-dial-demo-only").touch()
    database = root / "template/wallos.db"
    with sqlite3.connect(database) as db:
        db.executescript(
            "CREATE TABLE user(id INTEGER);"
            "CREATE TABLE subscriptions(id INTEGER);"
            "CREATE TABLE dial_icon_assets(id INTEGER, bytes BLOB);"
            "CREATE TABLE dial_subscription_icons(subscription_id INTEGER);"
        )
        if icon_asset:
            db.execute("INSERT INTO dial_icon_assets VALUES (1, X'89504E47')")
    (root / "db/wallos.db").write_bytes(b"synthetic-runtime-marker")

    fake_bin = root / "bin"
    fake_bin.mkdir()
    fake_docker = fake_bin / "docker"
    fake_docker.write_text(
        f"#!{sys.executable}\n"
        "import os, pathlib, sys\n"
        "root = pathlib.Path(os.environ['FAKE_DEMO_ROOT'])\n"
        "with (root / 'docker-calls').open('a') as log: log.write(' '.join(sys.argv[1:]) + '\\n')\n"
        "if sys.argv[1] != 'inspect': sys.exit(0)\n"
        "template = sys.argv[sys.argv.index('--format') + 1]\n"
        "if '.Mounts' in template:\n"
        "    print(f'{root}/db:/var/www/html/db')\n"
        "    print(f'{root}/logos:/var/www/html/images/uploads/logos')\n"
        "elif '.Config.Env' in template: print('DEMO_MODE=' + os.environ['FAKE_DEMO_MODE'])\n"
        "elif '.Config.Image' in template: print(os.environ['FAKE_DEMO_IMAGE'])\n"
    )
    fake_docker.chmod(0o755)
    env = {**os.environ, "PATH": f"{fake_bin}:{os.environ['PATH']}",
           "FAKE_DEMO_ROOT": str(root), "FAKE_DEMO_MODE": mode,
           "FAKE_DEMO_IMAGE": actual_image, "WALLOS_DEMO_IMAGE": IMAGE,
           "WALLOS_DEMO_PUBLIC_URL": "https://demo.example.invalid/"}
    result = subprocess.run(["bash", str(ROOT / "demo/reset.sh"), str(root)],
                            text=True, capture_output=True, env=env, timeout=20)
    assert result.returncode != 0, (name, result.stdout)
    assert expected_error in result.stderr, (name, result.stderr)
    assert (root / "db/wallos.db").read_bytes() == b"synthetic-runtime-marker", name
    assert "stop " not in (root / "docker-calls").read_text(), name


def check_swap_failure_restores_runtime(base: Path) -> None:
    """A failed second rename must restart the container on the old database."""
    root = base / "swap-failure"
    for directory in ("template", "db", "logos", "bin"):
        (root / directory).mkdir(parents=True)
    (root / ".wallos-dial-demo-only").touch()
    (root / "template/wallos.db").write_bytes(b"blank-template")
    (root / "db/wallos.db").write_bytes(b"original-runtime-database")
    fake_bin = root / "bin"
    fake_docker = fake_bin / "docker"
    fake_docker.write_text(
        f"#!{sys.executable}\n"
        "import os, pathlib, sys\n"
        "root = pathlib.Path(os.environ['FAKE_DEMO_ROOT'])\n"
        "with (root / 'docker-calls').open('a') as log: log.write(' '.join(sys.argv[1:]) + '\\n')\n"
        "if sys.argv[1] != 'inspect': sys.exit(0)\n"
        "template = sys.argv[sys.argv.index('--format') + 1]\n"
        "if '.Mounts' in template:\n"
        "    print(f'{root}/db:/var/www/html/db')\n"
        "    print(f'{root}/logos:/var/www/html/images/uploads/logos')\n"
        "elif '.Config.Env' in template: print('DEMO_MODE=1')\n"
        f"elif '.Config.Image' in template: print({IMAGE!r})\n"
    )
    fake_docker.chmod(0o755)
    fake_seed = fake_bin / "python3"
    fake_seed.write_text(
        f"#!{sys.executable}\n"
        "from pathlib import Path\n"
        "import sys\n"
        "Path(sys.argv[2]).write_bytes(b'new-synthetic-database')\n"
        "logos = Path(sys.argv[3]); logos.mkdir(parents=True, exist_ok=True)\n"
        "(logos / 'demo-01.svg').write_text('<svg/>')\n"
    )
    fake_seed.chmod(0o755)
    fake_mv = fake_bin / "mv"
    fake_mv.write_text(
        "#!/bin/sh\n"
        "case \"$1\" in */.reset.*/wallos.db)\n"
        "  case \"$2\" in */db/wallos.db) exit 57;; esac;;\n"
        "esac\n"
        "exec /bin/mv \"$@\"\n"
    )
    fake_mv.chmod(0o755)
    env = {**os.environ, "PATH": f"{fake_bin}:{os.environ['PATH']}",
           "FAKE_DEMO_ROOT": str(root), "WALLOS_DEMO_IMAGE": IMAGE,
           "WALLOS_DEMO_PUBLIC_URL": "https://demo.example.invalid/"}
    result = subprocess.run(["bash", str(ROOT / "demo/reset.sh"), str(root)],
                            text=True, capture_output=True, env=env, timeout=20)
    assert result.returncode != 0, result.stdout
    assert (root / "db/wallos.db").read_bytes() == b"original-runtime-database", result.stderr
    calls = (root / "docker-calls").read_text()
    assert "stop " in calls and "start " in calls, (calls, result.stderr)


def check_logo_failure_restores_runtime(base: Path, failure: str) -> None:
    """Failed Logo copy or post-restart smoke must restore both Demo volumes."""
    assert failure in ("logo-copy-failure", "public-smoke-failure")
    root = base / failure
    for directory in ("template", "db", "logos", "bin"):
        (root / directory).mkdir(parents=True)
    (root / ".wallos-dial-demo-only").touch()
    (root / "template/wallos.db").write_bytes(b"blank-template")
    (root / "db/wallos.db").write_bytes(b"original-runtime-database")
    (root / "logos/demo-01.svg").write_bytes(b"original-first-logo")
    (root / "logos/demo-02.svg").write_bytes(b"original-second-logo")
    fake_bin = root / "bin"
    fake_docker = fake_bin / "docker"
    fake_docker.write_text(
        f"#!{sys.executable}\n"
        "import os, pathlib, sys\n"
        "root = pathlib.Path(os.environ['FAKE_DEMO_ROOT'])\n"
        "with (root / 'docker-calls').open('a') as log: log.write(' '.join(sys.argv[1:]) + '\\n')\n"
        "if sys.argv[1] != 'inspect': sys.exit(0)\n"
        "template = sys.argv[sys.argv.index('--format') + 1]\n"
        "if '.Mounts' in template:\n"
        "    print(f'{root}/db:/var/www/html/db')\n"
        "    print(f'{root}/logos:/var/www/html/images/uploads/logos')\n"
        "elif '.Config.Env' in template: print('DEMO_MODE=1')\n"
        f"elif '.Config.Image' in template: print({IMAGE!r})\n"
    )
    fake_docker.chmod(0o755)
    fake_seed = fake_bin / "python3"
    fake_seed.write_text(
        f"#!{sys.executable}\n"
        "from pathlib import Path\n"
        "import sys\n"
        "Path(sys.argv[2]).write_bytes(b'new-synthetic-database')\n"
        "logos = Path(sys.argv[3]); logos.mkdir(parents=True, exist_ok=True)\n"
        "(logos / 'demo-01.svg').write_text('<svg>new</svg>')\n"
    )
    fake_seed.chmod(0o755)
    fake_cp = fake_bin / "cp"
    fake_cp.write_text(
        f"#!{sys.executable}\n"
        "import os, pathlib, subprocess, sys\n"
        "args = sys.argv[1:]\n"
        "root = pathlib.Path(os.environ['FAKE_DEMO_ROOT'])\n"
        "if os.environ['FAKE_DEMO_FAIL_COPY'] == '1' and len(args) == 2 and args[0].endswith('/logos/demo-01.svg') and pathlib.Path(args[1]) == root / 'logos/':\n"
        "    (root / 'logos/demo-01.svg').write_bytes(b'corrupt')\n"
        "    sys.exit(57)\n"
        "sys.exit(subprocess.run(['/bin/cp', *args]).returncode)\n"
    )
    fake_cp.chmod(0o755)
    if failure == "public-smoke-failure":
        for command in ("curl", "sleep"):
            stub = fake_bin / command
            stub.write_text("#!/bin/sh\n" + ("exit 1\n" if command == "curl" else "exit 0\n"))
            stub.chmod(0o755)
    env = {**os.environ, "PATH": f"{fake_bin}:{os.environ['PATH']}",
           "FAKE_DEMO_ROOT": str(root), "WALLOS_DEMO_IMAGE": IMAGE,
           "WALLOS_DEMO_PUBLIC_URL": "https://demo.example.invalid/",
           "FAKE_DEMO_FAIL_COPY": "1" if failure == "logo-copy-failure" else "0"}
    result = subprocess.run(["bash", str(ROOT / "demo/reset.sh"), str(root)],
                            text=True, capture_output=True, env=env, timeout=20)
    assert result.returncode == (57 if failure == "logo-copy-failure" else 1), (
        result.returncode, result.stdout, result.stderr)
    assert (root / "db/wallos.db").read_bytes() == b"original-runtime-database", result.stderr
    assert (root / "logos/demo-01.svg").read_bytes() == b"original-first-logo", result.stderr
    assert (root / "logos/demo-02.svg").read_bytes() == b"original-second-logo", result.stderr
    calls = (root / "docker-calls").read_text()
    assert "stop " in calls and "start " in calls, (calls, result.stderr)


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="dial-demo-reset-safety-") as temporary:
        base = Path(temporary).resolve()
        check_case(base, "not-demo", "0", IMAGE, False, "DEMO_MODE=1")
        check_case(base, "wrong-image", "1", "ghcr.io/lukevoidx/wallos-dial:9.9.9",
                   False, "image mismatch")
        check_case(base, "orphan-private-icon", "1", IMAGE, True,
                   "dial_icon_assets already has 1 records")
        check_swap_failure_restores_runtime(base)
        check_logo_failure_restores_runtime(base, "logo-copy-failure")
        check_logo_failure_restores_runtime(base, "public-smoke-failure")
    print("Demo reset preflight, DB swap, Logo copy and smoke rollback passed")


if __name__ == "__main__":
    main()
