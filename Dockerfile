ARG BASE_IMAGE=mafio69/php-env:8.4-apache
FROM ${BASE_IMAGE}

WORKDIR /var/www/html

COPY . /var/www/html/