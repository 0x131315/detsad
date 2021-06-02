#https://github.com/mlocati/docker-php-extension-installer
#https://hub.docker.com/_/php
FROM php:8.0.6-fpm-alpine AS build
LABEL autodelete="true"
RUN docker-php-ext-install pdo_mysql opcache
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && sync
RUN install-php-extensions gd xdebug

FROM build
COPY . .