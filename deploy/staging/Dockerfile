# Reproducible build target for a *synthetic* HTTPS staging environment.
# Pin digest and test upgrades before an institutionally authorized rollout.
FROM php:8.2-fpm-bookworm

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       ca-certificates libonig-dev libsodium-dev clamav clamav-freshclam default-mysql-client gosu \
    && docker-php-ext-install -j2 mbstring pdo_mysql sodium \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv/agile
COPY --chown=www-data:www-data . /srv/agile
RUN mkdir -p /srv/agile/storage/private /srv/agile/storage/backups \
    && chown -R www-data:www-data /srv/agile/storage \
    && chmod 0700 /srv/agile/storage/private /srv/agile/storage/backups \
    && php -r 'foreach(["pdo_mysql","mbstring","fileinfo","sodium"] as $e){if(!extension_loaded($e)){fwrite(STDERR,"Missing PHP extension: $e\n");exit(1);}}'

# Root executes only the short-lived secret handoff, then drops permanently
# to the unprivileged PHP user before starting web, scanner or migration code.
COPY --chmod=0755 deploy/staging/entrypoint.sh /usr/local/bin/agile-entrypoint
USER root
ENTRYPOINT ["/usr/local/bin/agile-entrypoint"]
EXPOSE 9000
CMD ["php-fpm"]
