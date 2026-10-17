#!/usr/bin/env bash
# Static release contract; this test never builds, publishes, or starts a service.
set -euo pipefail
cd "$(dirname "$0")/../.."

require_text() {
  local file=$1 expected=$2
  grep -Fq -- "$expected" "$file" || {
    printf 'Missing release contract in %s: %s\n' "$file" "$expected" >&2
    exit 1
  }
}

gate=scripts/verify-1.0-candidate.sh
[[ -x "$gate" ]] || { echo 'Candidate gate is missing or not executable' >&2; exit 1; }
require_text .github/workflows/verify.yml './scripts/verify-1.0-candidate.sh wallos-dial:ci'
require_text .github/workflows/publish-image.yml './scripts/verify-1.0-candidate.sh wallos-dial:preflight'
require_text .github/workflows/publish-image.yml '[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]'
require_text .github/workflows/publish-image.yml 'test "$GITHUB_REF_NAME" = "v$version"'

python3 - <<'PY'
import os
from pathlib import Path
import re
import subprocess
import tempfile
workflow = Path('.github/workflows/publish-image.yml').read_text()
steps = [
    'name: Match tag and declared version',
    '[[ "$version" =~ ^[0-9]+\\.[0-9]+\\.[0-9]+$ ]] || exit 1',
    'test "$GITHUB_REF_NAME" = "v$version"',
    './scripts/verify-1.0-candidate.sh wallos-dial:preflight',
    'name: Log in to GHCR',
    'push: true',
]
positions = [workflow.index(step) for step in steps]
assert positions == sorted(positions), 'Tag validation and full gate must precede registry login/push'
match = re.search(r'      - name: Match tag and declared version\n        run: \|\n((?:          .*\n)+)', workflow)
assert match, 'Cannot isolate stable-tag validation step'
validation = '\n'.join(line[10:] for line in match.group(1).splitlines()) + '\n'
assert validation.splitlines() == [
    'version=$(cat VERSION)',
    '[[ "$version" =~ ^[0-9]+\\.[0-9]+\\.[0-9]+$ ]] || exit 1',
    'test "$GITHUB_REF_NAME" = "v$version"',
], 'Unexpected command in tag-validation step'
with tempfile.TemporaryDirectory() as folder:
    for version, ref, allowed in [
        ('1.0.0-rc.1', 'v1.0.0-rc.1', False),
        ('1.0.0', 'v1.0.1', False),
        ('1.0.0', 'v1.0.0', True),
    ]:
        Path(folder, 'VERSION').write_text(version + '\n')
        result = subprocess.run(['bash', '-e', '-c', validation], cwd=folder,
                                env={**os.environ, 'GITHUB_REF_NAME': ref}, check=False)
        assert (result.returncode == 0) == allowed, (version, ref, result.returncode)
PY

for check in \
  './scripts/check-package.sh' \
  'bash tests/release/version-contract.sh' \
  'bash tests/release/ci-contract.sh' \
  'python3 tests/release/demo-seed-icons.py' \
  'python3 tests/release/docs-contract.py' \
  'python3 tests/demo-reset-safety.py' \
  'python3 tests/demo-smoke-safety.py' \
  'compose.build.yaml' \
  'docker build -t "$image" .' \
  './scripts/smoke-image.sh "$image"' \
  'python3 tests/release/mail-capture.py "$image"' \
  'tests/mail-theme.php' \
  'tests/mail-mime.php' \
  'tests/category-core.php' \
  'tests/category-http.sh' \
  'tests/category-demo-edge.sh' \
  'tests/icon-catalog.php' \
  'tests/icon-schema.php' \
  'tests/icon-resolver.php' \
  'tests/icon-normalize.php' \
  'tests/icon-atomic.php' \
  'tests/logo-limits.php' \
  'tests/run-icon-http.sh' \
  'tests/icon-render-http.sh' \
  'bash tests/browser/run.sh "$image"' \
  'tests/compatibility/upstream-security.sh' \
  'tests/compatibility/security-http.sh' \
  'tests/compatibility/service-worker.sh' \
  'tests/compatibility/upgrade-rollback.sh' \
  'tests/compatibility/hash-gate.sh' \
  'php -l' \
  'node --check'; do
  require_text "$gate" "$check"
done

if grep -Eq '(^|[[:space:]])(docker[[:space:]]+push|git[[:space:]]+push|gh[[:space:]]+release|ssh[[:space:]])' "$gate"; then
  echo 'Candidate gate contains a publishing or remote-host operation' >&2
  exit 1
fi

echo 'CI, stable-tag, full-gate, and nonpublishing contracts passed.'
