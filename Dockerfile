FROM bellamy/wallos:5.8.1@sha256:0f049dbab45b9f8e8d43b84fd1b77ef9e55909bd1a384a0f4fe8597ab68a1d5d

LABEL org.opencontainers.image.title="Wallos Dial" \
      org.opencontainers.image.description="Desktop theme overlay for Wallos v5.8.1" \
      org.opencontainers.image.version="0.1.0" \
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
