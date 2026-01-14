
# Gunakan PHP 7.4 FPM base
FROM php:7.4-fpm-buster

# Gunakan repositori archive Debian (karena Buster EOL)
RUN printf "deb [trusted=yes] http://archive.debian.org/debian buster main contrib non-free\n" > /etc/apt/sources.list \
 && printf "deb [trusted=yes] http://archive.debian.org/debian-security buster/updates main contrib non-free\n" >> /etc/apt/sources.list \
 && apt-get -o Acquire::Check-Valid-Until=false update -y \
 && apt-get install -y --no-install-recommends \
      zip unzip git curl libonig-dev zlib1g-dev libpng-dev libzip-dev libpq-dev default-mysql-client \
 && docker-php-ext-install zip mbstring gd pdo pdo_mysql pgsql pdo_pgsql mysqli \
 && rm -rf /var/lib/apt/lists/*

# Install Composer secara global
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Buat user aplikasi
RUN useradd -u 1000 -ms /bin/bash -g www-data masbro

# Set working directory
WORKDIR /var/www/html

# Copy hanya file composer dulu untuk caching dependency layer
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install dependency Laravel sebagai www-data
USER www-data
RUN composer install --no-interaction --no-ansi --no-scripts --no-progress --prefer-dist --ignore-platform-reqs

# Copy source code dengan ownership yang benar
USER root
COPY --chown=www-data:www-data . .

# Buat dan set permission folder storage
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Switch ke user aplikasi
USER masbro

# Expose port php-fpm
EXPOSE 9000

# Run PHP-FPM
CMD ["php-fpm"]