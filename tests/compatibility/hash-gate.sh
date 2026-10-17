#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

scratch=$(mktemp -d)
trap 'rm -r "$scratch"' EXIT
mkdir -p "$scratch/compat"
cp Dockerfile "$scratch/Dockerfile"
cp -R theme "$scratch/theme"
cp compat/upstream-files.sha256 "$scratch/compat/upstream-files.sha256"
python3 - "$scratch/compat/upstream-files.sha256" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
s = p.read_text()
p.write_text(("0" if s[0] != "0" else "1") + s[1:])
PY
if docker build -q -f "$scratch/Dockerfile" "$scratch" >"$scratch/build.log" 2>&1; then
  echo 'Bad upstream hash was accepted' >&2
  exit 1
fi
grep -Eiq 'FAILED|checksum|did NOT match' "$scratch/build.log"
echo 'Tampered upstream hash rejected.'
