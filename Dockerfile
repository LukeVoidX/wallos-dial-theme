FROM bellamy/wallos:5.8.3@sha256:0006879778f31a962a3873f7682514b92a3c7665dcbc4764fcc241ad968eaccd

LABEL org.opencontainers.image.title="Wallos Dial" \
      org.opencontainers.image.description="Desktop theme overlay for Wallos v5.8.3" \
      org.opencontainers.image.version="1.0.0-rc.1" \
      org.opencontainers.image.licenses="GPL-3.0-only" \
      org.opencontainers.image.source="https://github.com/LukeVoidX/wallos-dial-theme"

COPY compat/upstream-files.sha256 /tmp/dial-upstream-files.sha256
COPY theme/ /tmp/dial-theme/
RUN set -eu; \
    cd /var/www/html; \
    sha256sum -c /tmp/dial-upstream-files.sha256; \
    find /tmp/dial-theme -type f | while IFS= read -r file; do \
      path=${file#/tmp/dial-theme/}; \
      if [ -f "/var/www/html/$path" ] && ! grep -Fq "  $path" /tmp/dial-upstream-files.sha256; then \
        echo "Unreviewed upstream file replacement: $path" >&2; exit 1; \
      fi; \
    done; \
    cp -a /tmp/dial-theme/. /var/www/html/; \
    rm -rf /tmp/dial-theme /tmp/dial-upstream-files.sha256
RUN chmod 644 /var/www/html/styles/dial.css \
    /var/www/html/scripts/dial-interaction.js \
    /var/www/html/scripts/dial-language.js \
    /var/www/html/scripts/stats-dial.js \
    /var/www/html/scripts/subscriptions.js
