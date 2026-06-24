ARG BASE_IMAGE=mafio69/php-env:8.4-fpm-alpine
FROM ${BASE_IMAGE}

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && mkdir -p /var/log/nginx /run/nginx /var/lib/nginx/logs \
    && chown -R www-data:www-data /var/log/nginx /run/nginx /var/lib/nginx/logs

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-errors.ini /usr/local/etc/php/conf.d/zz-php-errors.ini
COPY docker/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies
RUN composer install --ignore-platform-req=ext-pdo_sqlite --no-dev --optimize-autoloader

# Copy application files
COPY . /var/www/html

CMD ["/usr/local/bin/start.sh"]