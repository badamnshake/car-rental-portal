FROM php:8-apache

# RUN a2enmod ssl && a2enmod rewrite
# RUN mkdir -p /etc/apache2/ssl
RUN docker-php-ext-install pdo pdo_mysql
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Install Composer
# COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

# Set working directory
# WORKDIR /var/www/html

# Copy composer.json and composer.lock
# COPY composer.json composer.lock ./

# Install Composer dependencies
# RUN composer install --no-dev --optimize-autoloader



# COPY ./ssl/*.pem /etc/apache2/ssl/
COPY ./000-default.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
EXPOSE 443