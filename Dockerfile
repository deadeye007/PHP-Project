# Use the official PHP image with Apache
FROM php:8.2-apache

# Install mysqli extension for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project files to the web root
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80
EXPOSE 80