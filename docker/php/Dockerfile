FROM php:8.2-apache

# PHP
RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y libpng-dev libonig-dev libicu-dev cron
RUN docker-php-ext-install gd intl mysqli
COPY ./config/logging.ini /usr/local/etc/php/conf.d/logging.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN sed -i 's/auto_start\ \=\ 0/auto_start\ \=\ 1/' "$PHP_INI_DIR/php.ini"

# cron
RUN echo "* */2 * * *	root	cd /var/www/html; /usr/local/bin/php -f cron.php > /dev/null" > /etc/cron.d/okovision
COPY cron.sh /cron.sh
RUN chmod +x /cron.sh

# Apache
RUN a2enmod rewrite
RUN service apache2 restart
