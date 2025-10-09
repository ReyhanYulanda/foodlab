FROM php:7.4-fpm-buster

# Gunakan repositori archive Debian (karena Buster EOL)
RUN printf "deb [trusted=yes] http://archive.debian.org/debian buster main contrib non-free\n" > /etc/apt/sources.list \
 && printf "deb [trusted=yes] http://archive.debian.org/debian-security buster/updates main contrib non-free\n" >> /etc/apt/sources.list \
 && apt-get -o Acquire::Check-Valid-Until=false update -y \
 && apt-get install -y --no-install-recommends \
      zip unzip git curl libonig-dev zlib1g-dev libpng-dev libzip-dev libpq-dev default-mysql-client \
 && docker-php-ext-install zip mbstring gd pdo pdo_mysql pgsql pdo_pgsql mysqli \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Tambah DNS fallback di container
#RUN echo "nameserver 1.1.1.1" > /etc/resolv.conf

# Set working directory
WORKDIR /var/www/html
COPY . /var/www/html

# Buat user non-root dengan grup bawaan PHP
RUN useradd -u 1000 -ms /bin/bash -g www-data masbro \
 && chown -R www-data:www-data /var/www/html

USER masbro

# Install Laravel dependencies (tanpa interaktif)
RUN composer install --no-interaction --no-ansi --no-scripts --no-progress || true

EXPOSE 9000
CMD ["php-fpm"]