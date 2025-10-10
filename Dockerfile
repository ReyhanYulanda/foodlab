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

# Set working directory
WORKDIR /var/www/html

# Copy hanya file composer dulu untuk caching dependency layer
COPY composer.json composer.lock ./

# Install dependency Laravel
RUN composer install --no-interaction --no-ansi --no-scripts --no-progress --prefer-dist || true

# Sekarang baru copy semua source code project
COPY . .

# Ubah ownership ke user www-data (default PHP-FPM)
RUN chown -R www-data:www-data /var/www/html

# Copy entrypoint ke container
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port php-fpm
EXPOSE 9000

# Gunakan entrypoint custom
ENTRYPOINT ["entrypoint.sh"]