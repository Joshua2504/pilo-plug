FROM php:8.2-apache

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Enable necessary PHP extensions
RUN docker-php-ext-install curl

# Enable Apache mod_rewrite (useful for single-file apps)
RUN a2enmod rewrite

# Copy the application files
COPY index.php /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod 644 /var/www/html/index.php

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]