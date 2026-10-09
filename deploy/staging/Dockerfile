# Reproducible build target for a *synthetic* HTTPS staging environment.
# Pin digest and test upgrades before an institutionally authorized rollout.
FROM php:8.2-fpm-bookworm

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       ca-certificates libonig-dev libsodium-dev clamav clamav-freshclam default-mysql-client \
    && docker-php-ext-install -j2 mbstring pdo_mysql sodium \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv/agile
COPY --chown=www-data:www-data . /srv/agile
RUN mkdir -p /srv/agile/storage/private /srv/agile/storage/backups \
    && chown -R www-data:www-data /srv/agile/storage \
    && chmod 0700 /srv/agile/storage/private /srv/agile/storage/backups \
    && printf '%s\n' 'clear_env = no' > /usr/local/etc/php-fpm.d/zz-agile-environment.conf \
    && php -r 'foreach(["pdo_mysql","mbstring","fileinfo","sodium"] as $e){if(!extension_loaded($e)){fwrite(STDERR,"Missing PHP extension: $e\n");exit(1);}}'

# PHP-FPM listens on 9000; no PHP or database host port is published.
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
