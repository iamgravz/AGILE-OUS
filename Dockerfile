# PHP web runtime for a PHP-capable container hosting provider.
FROM php:8.3-apache-bookworm
RUN docker-php-ext-install pdo_mysql opcache \
 && a2enmod headers rewrite \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/agile-hardening.conf \
 && a2enconf agile-hardening \
 && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/zz-agile.ini
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} + \
 && find /var/www/html -type f -exec chmod 644 {} +
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=4s --start-period=30s CMD curl -fsS http://127.0.0.1/healthz.php || exit 1
