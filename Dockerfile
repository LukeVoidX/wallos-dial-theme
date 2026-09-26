FROM bellamy/wallos:5.8.1@sha256:0f049dbab45b9f8e8d43b84fd1b77ef9e55909bd1a384a0f4fe8597ab68a1d5d

LABEL org.opencontainers.image.title="Wallos Dial" \
      org.opencontainers.image.description="Desktop theme overlay for Wallos v5.8.1" \
      org.opencontainers.image.version="0.1.0" \
      org.opencontainers.image.licenses="GPL-3.0-only"

COPY theme/ /var/www/html/
RUN chmod 644 /var/www/html/styles/dial.css \
    /var/www/html/scripts/dial-interaction.js \
    /var/www/html/scripts/stats-dial.js \
    /var/www/html/scripts/subscriptions.js
