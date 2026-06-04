ARG BASE_IMAGE=mafio69/php-env:8.4-fpm-alpine
FROM ${BASE_IMAGE}

WORKDIR /var/www/html

# Create data directory for SQLite
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

# Configure nginx for PHP
RUN echo 'server { \
    listen 80; \
    server_name localhost; \
    root /var/www/html; \
    index index.php index.html; \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
}' > /etc/nginx/http.d/default.conf

COPY . /var/www/html