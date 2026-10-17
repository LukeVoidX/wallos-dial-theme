# Wallos Dial live demo operations

This directory operates the **actual Wallos Dial 1.0.0 / Wallos 5.8.3 image** in an isolated public read-only Demo. The public deployment uses fictional data; the private Wallos container, its SQLite database, and its uploaded logos must never be reused. Keep the previous 0.1.1 image and matching synthetic-volume backup for an explicit rollback.

## Topology

`https://lukevoidx.github.io/wallos-dial-theme/` and `/zh-CN/` (static GitHub Pages product information) link to `https://wallos-demo.mostai.org/` (Caddy) → `127.0.0.1:8296` (separate Wallos Dial container) → separate synthetic SQLite and SVG logo volumes.

GitHub Pages cannot execute PHP; its bilingual pages explain the theme and offer a direct link to the demo. The reviewed 1.0 Caddy route list allows GET/HEAD for the visible pages and static catalog art, the 31 generated `demo-*.svg` marks, `/categories.php` as a view-only manager, and selected read-only detail endpoints. The only public POST is the Dial language endpoint; Demo Mode updates only the visitor's Cookie. Subscription, category, icon, account and administration writes stay blocked by the edge and by the application. The public account is shared and uses synthetic data only. The public edge denies both My Icons read endpoints and uploaded Logo filenames outside the synthetic Demo pattern.

## Prepare or rebuild the public Demo

1. Pull the published `ghcr.io/lukevoidx/wallos-dial:1.0.0` image and verify its multiarch digest. Create an **empty** dedicated root such as `/srv/wallos-dial-public-demo` with `template/`, `db/`, and `logos/` directories and a `.wallos-dial-demo-only` marker. Do not point it at `/srv/wallos`, an existing Wallos mount, or any production database.
2. Start the **1.0.0** image once with the empty `template/` directory mounted at `/var/www/html/db`, wait for its `/health.php` to return HTTP 200 / `OK`, then stop that temporary container. There is no need to open or submit the registration page: container startup creates the database, completes Wallos and Dial migrations, waits for PHP-FPM, and only then opens Nginx. The health endpoint checks that the Dial migration is recorded. Use only a newly created, uncustomized template; deleting users from a private database does not make it safe. The seeder rejects nondefault data rows across all tables and verifies the reviewed Wallos 5.8.3 defaults for categories, currencies, payment methods, settings and administrator configuration. A template carried over from 0.1.1 must be migrated on a disposable copy or recreated before expecting 1.0 showcase icons.
3. Copy `template/wallos.db` to `db/wallos.db` and run `python3 demo/seed.py /srv/wallos-dial-public-demo/db/wallos.db /srv/wallos-dial-public-demo/logos --showcase-icons`. The seeder accepts only the reviewed, clean Wallos 5.8.3 template and adds 30 fictional subscriptions. The optional flag assigns nine neutral catalog pictograms; public 1.0 acceptance requires all nine. Older schemas need migration on a disposable copy or a fresh template before the 1.0 Demo switch.
4. Configure an untracked `.env` with absolute demo-only mounts:

   ```dotenv
   WALLOS_DEMO_DB_DIR=/srv/wallos-dial-public-demo/db
   WALLOS_DEMO_LOGOS_DIR=/srv/wallos-dial-public-demo/logos
   WALLOS_DEMO_IMAGE=ghcr.io/lukevoidx/wallos-dial:1.0.0
   TZ=Asia/Taipei
   ```

   Then run `docker compose --env-file .env -f demo/compose.yaml config`, check the mounts, and start the dedicated container. Its HTTP port is bound to loopback only.
5. Append `demo/Caddyfile.site.example` to the reviewed server Caddyfile, validate it, then reload Caddy. The snippet assumes the existing `security_headers_proxy` and `access_log` imports and a Cloudflare-proxied, one-level subdomain. Confirm the origin firewall and HTTPS behavior before opening it publicly.
6. Run the 1.0.0 reset job on the reviewed schedule with explicit image and public target variables, for example `WALLOS_DEMO_IMAGE=ghcr.io/lukevoidx/wallos-dial:1.0.0 WALLOS_DEMO_PUBLIC_URL=https://wallos-demo.mostai.org/ demo/reset.sh /srv/wallos-dial-public-demo`. A scheduler does not automatically load the Compose `.env`. The script verifies the demo-only marker, exact DB/Logo mounts, `DEMO_MODE=1`, and the container's image reference before it seeds or stops anything. Seeding rejects private account data in any table, including orphaned payment methods, tokens, custom styles and icons, as well as modified default rows. After restart, the script checks local health and the real public HTTPS route, including 30 unique subscriptions, read-only pages, blocked private icon reads and writes, and session recovery. Failed checks attempt to restore the previous synthetic database and restart the container; if the restore itself fails, the backup is retained with its recovery path in the error output. Confirm the reset result contains nine catalog icon preferences. Do not advertise timed resets until this job is active and verified.
7. Test anonymously: dashboard, all 30 subscriptions and marks, view-only `/categories.php`, details, calendar, statistics, English/Simplified Chinese, and `/health.php`. Assert category/icon/subscription POST routes, `/admin.php`, My Icons GET routes, non-Demo uploaded Logo filenames, and database paths are blocked through the public HTTPS hostname. Verify a reset returns the synthetic row count to 30. For rollback, stop only the Demo timer/container, restore the **matched pre-switch synthetic SQLite database and Logo snapshot**, restore the old Compose/Caddy/reset configuration and `ghcr.io/lukevoidx/wallos-dial:0.1.1`, then verify health, 30 fictional records and read-only routes before restarting the old timer. Never run the 1.0 reset script with the 0.1.1 image. Refresh the static English and Chinese GitHub Pages pages only after the direct Demo URL works.

## Local preview

For local review, use an ignored `data/demo-local/` or another clearly isolated directory and bind the container to loopback. Local QA can be writable so category and icon flows can be tested; the public Caddy proxy remains read-only. Do not assume port `4190` is currently running—check the active container and mount paths before using or resetting it.
