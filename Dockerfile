FROM bellamy/wallos:5.8.3@sha256:0006879778f31a962a3873f7682514b92a3c7665dcbc4764fcc241ad968eaccd

RUN apk add --no-cache 'rsvg-convert=2.62.3-r0'
COPY compat/nginx-dial-icon.conf /etc/nginx/dial-icon-location.conf
RUN echo '61156ad5b632a8ea5f8c83230c501c3b5de7c6415608c0b99a0f166a4128b5e1  /etc/nginx/nginx.conf' | sha256sum -c - && \
    sed -i '/location ~ \\.php\$/i\        include /etc/nginx/dial-icon-location.conf;' /etc/nginx/nginx.conf && \
    nginx -t

LABEL org.opencontainers.image.title="Wallos Dial" \
      org.opencontainers.image.description="Desktop theme overlay for Wallos v5.8.3" \
      org.opencontainers.image.version="1.0.0" \
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
RUN chmod 755 /var/www/html/bin/dial-render-svg.sh
