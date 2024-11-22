ARG PHP_VERSION=8.3

FROM mlocati/php-extension-installer:latest AS ext-installer
FROM php:${PHP_VERSION}-cli-alpine AS app

WORKDIR /var/www/html

ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

COPY --from=ext-installer /usr/bin/install-php-extensions /usr/bin/install-php-extensions

RUN install-php-extensions @composer opcache xdebug intl bcmath
RUN apk add git