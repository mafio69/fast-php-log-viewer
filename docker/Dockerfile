ARG BASE_IMAGE=mafio69/php-env:8.4-fpm-alpine
FROM ${BASE_IMAGE}

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

COPY . /var/www/html

CMD ["/usr/local/bin/start.sh"]