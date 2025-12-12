FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    unixodbc \
    unixodbc-dev \
    freetds-dev \
    freetds-bin \
    tdsodbc \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install SQL Server drivers for PHP
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Configure FreeTDS for SQL Server
RUN echo "[global]\n\
    tds version = 7.4\n\
    client charset = UTF-8" > /etc/freetds/freetds.conf

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create logs directory and set permissions
RUN mkdir -p logs && chmod 777 logs

# Set timezone
RUN echo "date.timezone=Europe/Warsaw" > /usr/local/etc/php/conf.d/timezone.ini

# Expose port
EXPOSE 80

CMD ["apache2-foreground"]
