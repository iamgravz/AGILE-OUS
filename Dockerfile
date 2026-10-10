FROM php:8.3-apache-bookworm
RUN docker-php-ext-install pdo_mysql opcache \
 && a2enmod headers rewrite \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/agile-hardening.conf \
 && a2enconf agile-hardening \
 && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/zz-agile.ini \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
 && printf 'ServerName localhost\n' > /etc/apache2/conf-available/server-name.conf \
 && a2enconf server-name
WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker/apache-start.sh /usr/local/bin/agile-apache-start
RUN chmod +x /usr/local/bin/agile-apache-start \
 && chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} + \
 && find /var/www/html -type f -exec chmod 644 {} +
ENV PORT=8080
EXPOSE 8080
CMD ["/usr/local/bin/agile-apache-start"]
