# Okovision - image Apache + PHP 8.2 (mod_php, requis par les php_value du .htaccess)
# Base bookworm épinglée : mariadb-client y fournit encore le binaire "mysqldump"
# utilisé par administration::newDump().
FROM php:8.2-apache-bookworm

ARG INSTALL_XDEBUG=0

ENV TZ=Europe/Paris \
    OKV_CONFIG_DIR="" \
    OKV_CRON=1

# --- Paquets système + extensions PHP (mysqli, gd, intl, zip ; curl/mbstring/xml sont déjà inclus)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        cron \
        mariadb-client \
        tzdata \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" mysqli gd intl zip \
 && if [ "$INSTALL_XDEBUG" = "1" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug \
        && printf 'xdebug.mode=debug\nxdebug.start_with_request=yes\nxdebug.client_host=host.docker.internal\nxdebug.client_port=9003\n' \
           > /usr/local/etc/php/conf.d/zz-xdebug.ini; \
    fi \
 && rm -rf /var/lib/apt/lists/*

# --- PHP
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-okovision.ini

# --- Apache : vhost durci du projet en site par défaut
COPY install/099-okovision.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# --- Cron (récupération horaire des CSV de la chaudière)
COPY docker/crontab /etc/cron.d/okovision
RUN sed -i 's/\r$//' /etc/cron.d/okovision && chmod 0644 /etc/cron.d/okovision

# --- Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/okv-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/okv-entrypoint.sh && chmod +x /usr/local/bin/okv-entrypoint.sh

# --- Application
WORKDIR /var/www/okovision
COPY . /var/www/okovision
RUN mkdir -p _tmp _logs dumps var \
 && chown -R www-data:www-data /var/www/okovision

VOLUME ["/data"]
EXPOSE 80

ENTRYPOINT ["okv-entrypoint.sh"]
CMD ["apache2-foreground"]
