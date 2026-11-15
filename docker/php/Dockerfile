FROM php:8.5-fpm-trixie

# Webhosting L lets you pick PHP 8.5 in konsoleH, on a Debian trixie base.
# Symfony 8.1 requires PHP >= 8.4, so 8.5 is the closest match to production.

# Reuse the developer's UID/GID for www-data. PHP-FPM drops privileges to this
# user, so cache/log files created in the container remain writable on the host.
ARG UID=1000
ARG GID=1000

RUN groupmod --non-unique --gid "${GID}" www-data \
    && usermod --non-unique --uid "${UID}" www-data \
    # usermod keeps file ownership as uid 33, which is no longer www-data.
    && chown -R www-data:www-data /var/www

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        # GD with WebP: the site makes the smaller copies of the images it
        # publishes, as Hetzner's PHP does with its GD. With JPEG too: the CMS
        # reads the images of external resources, most of which are JPEG.
        libjpeg-dev \
        libpng-dev \
        libwebp-dev \
        ${PHPIZE_DEPS} \
    # OPcache already ships compiled in php:8.5-fpm-trixie (Zend OPcache);
    # only the extensions that are missing from the base image are built.
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        pdo_mysql \
        zip \
    && pecl install apcu redis \
    && docker-php-ext-enable apcu redis \
    # Development only: code coverage driver for PHPUnit and Infection.
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove ${PHPIZE_DEPS} \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Composer is required on the hosting account too (usually run over SSH).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Default CMD from the base image: php-fpm, listening on 9000.
