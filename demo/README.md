# Wallos Dial live demo operations

This directory prepares the **actual Wallos Dial 0.1.0 image** for an isolated public read-only demo. The private Wallos container, its SQLite database, and its uploaded logos must never be reused.

## Topology

`https://lukevoidx.github.io/wallos-dial-theme/` and `/zh-CN/` (static GitHub Pages product information) link to `https://wallos-demo.mostai.org/` (Caddy) → `127.0.0.1:8296` (separate Wallos Dial container) → separate synthetic SQLite and SVG logo volumes.

GitHub Pages cannot execute PHP; its bilingual pages explain the theme and offer a direct link to the demo. The public Caddy site allows GET/HEAD for the visible pages and assets, selected read-only subscription detail endpoints, and POST only for the Dial language endpoint. Demo Mode makes the language endpoint update only the visitor's Cookie. All other writes and administrative paths return 404. The public account is shared and uses synthetic data only.

## Prepare on a server after approval

1. Build or pull the exact versioned Wallos Dial image. Create an **empty** dedicated root such as `/srv/wallos-dial-public-demo` with `template/`, `db/`, and `logos/` directories and a `.wallos-dial-demo-only` marker. Do not point it at `/srv/wallos`, an existing Wallos mount, or any production database.
2. Start the image once with the empty `template/` directory mounted at `/var/www/html/db`, visit the local registration page to let Wallos initialize and migrate SQLite, then stop that temporary container. Confirm `template/wallos.db` has zero rows in `user` and `subscriptions`.
3. Copy `template/wallos.db` to `db/wallos.db` and run `python3 demo/seed.py /srv/wallos-dial-public-demo/db/wallos.db /srv/wallos-dial-public-demo/logos`. The seeder refuses to run on a populated database and adds 30 fictional subscriptions.
4. Configure an untracked `.env` with absolute demo-only mounts:

   ```dotenv
   WALLOS_DEMO_DB_DIR=/srv/wallos-dial-public-demo/db
   WALLOS_DEMO_LOGOS_DIR=/srv/wallos-dial-public-demo/logos
   WALLOS_DEMO_IMAGE=ghcr.io/lukevoidx/wallos-dial:0.1.0
   TZ=Asia/Taipei
   ```

   Then run `docker compose --env-file .env -f demo/compose.yaml config`, check the mounts, and start the dedicated container. Its HTTP port is bound to loopback only.
5. Append `demo/Caddyfile.site.example` to the reviewed server Caddyfile, validate it, then reload Caddy. The snippet assumes the existing `security_headers_proxy` and `access_log` imports and a Cloudflare-proxied, one-level subdomain. Confirm the origin firewall and HTTPS behavior before opening it publicly.
6. Schedule `demo/reset.sh /srv/wallos-dial-public-demo` every two hours using the host's scheduler. The script verifies the demo-only marker and the container's exact mounts, seeds a fresh database, stops only the dedicated demo container, swaps the SQLite file, restarts, and checks health. Do not advertise timed resets until this job is active and verified.
7. Test anonymously: dashboard, all 30 subscriptions and marks, details, calendar, statistics, English/Simplified Chinese, and `/health.php`; test that a mutation endpoint, `/admin.php`, and database paths are blocked through the public hostname. Verify a reset returns the synthetic row count to 30. Publish the static English and Chinese GitHub Pages product pages only after the direct demo URL works.

## Local preview

A current local review instance is bound to `http://127.0.0.1:4190/` with its own ignored `data/demo-local/` volumes. It uses the actual Wallos Dial container and seed data. This local instance is writable for isolated QA. The public Caddy proxy is read-only.
