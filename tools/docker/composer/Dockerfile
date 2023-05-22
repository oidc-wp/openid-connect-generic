# Start with the official PHP CLI image.
FROM php:8.0-cli

# Increase the memory limit to allow for large processes, such as PHPStan.
RUN cd /usr/local/etc/php/conf.d/ && \
  echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-ram-limit.ini

# Copy the Composer PHAR from the official Composer image to the PHP CLI image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install additional packages required for Composer
RUN apt-get update \
    && apt-get install -y git zip unzip zlib1g-dev libzip-dev \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN docker-php-ext-install zip \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install bcmath
