FROM php:8.4-fpm

# Optional extra root CAs (e.g. a corporate proxy or antivirus doing TLS inspection).
# Drop *.crt files into docker/certs/ - the directory is empty by default.
COPY docker/certs/ /usr/local/share/ca-certificates/
RUN update-ca-certificates

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions imagick exif gd pdo_mysql intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Match www-data to the host user so files created in the bind mount stay editable.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data

WORKDIR /var/www/html
