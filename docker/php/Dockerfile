FROM php:7.4-fpm-bullseye

# Debian 11 (bullseye) is EOL/archived — point apt at an immutable snapshot
# so mariadb-common/libexpat1/mariadb-client 404s (from drained bullseye-security)
# are gone. Deterministic and idempotent; test-built successfully 2026-09-05.
RUN printf "deb http://snapshot.debian.org/archive/debian/20240801T000000Z/ bullseye main\ndeb http://snapshot.debian.org/archive/debian-security/20240801T000000Z/ bullseye-security main\ndeb http://snapshot.debian.org/archive/debian/20240801T000000Z/ bullseye-updates main\n" > /etc/apt/sources.list \
 && apt-get -o Acquire::Check-Valid-Until=false update \
 && apt-get install -y --no-install-recommends \
    git curl unzip zip libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev libicu-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# NOTE: default-mysql-client removed — the app only needs the pdo_mysql PHP
# extension (compiled above), not the mysql CLI binary. It pulled mariadb-common
# + mariadb-client (2 of the 3 packages that 404 on bullseye-security).

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
CMD ["php-fpm"]
