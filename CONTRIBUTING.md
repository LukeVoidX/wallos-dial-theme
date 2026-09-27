# Contributing to Wallos Dial

Wallos Dial is an overlay on a pinned Wallos release. Please keep changes small enough to review against upstream behavior and preserve the subscription, calendar, search, settings, and notification flows.

## Local checks

1. Fork or branch from the current repository state. Do not add a personal database, uploaded logos, `.env`, or private icon mappings.
2. Run `./scripts/check-package.sh` and `docker compose -f compose.build.yaml config -q`.
3. Build with `docker build -t wallos-dial:dev .` and run `./scripts/smoke-image.sh wallos-dial:dev`.
4. Use disposable data for browser checks. Verify dashboard, subscriptions, create/edit/details, search/filter/grouping, calendar, statistics, settings, and language switching as relevant to your change.
5. Attach a concise explanation, affected Wallos version, before/after behavior, and redacted screenshots to your pull request.

The Docker build compares overwritten Wallos files with `compat/upstream-files.sha256`. For a new upstream version, review the changed upstream source and runtime behavior before changing that manifest. A successful hash update alone does not establish compatibility. See [the upgrade process](docs/UPGRADING.md).

The public poster screenshots use synthetic data. Do not submit screenshots from a personal account, even if the UI change seems small. The original ImageGen backgrounds and the exact screenshot-composition source are described in [poster provenance](docs/assets/posters/source/README.md).

Wallos and this derivative project are GPLv3. Keep upstream notices and mark new third-party assets with their source and license.
