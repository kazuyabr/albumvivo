FROM php:8.3-apache

# Extensões exigidas pelo plano: pdo_mysql, gd, zip, intl, curl (+ mbstring p/ UTF-8)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl mbstring curl \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot -> public/ e AllowOverride para as rewrites do front controller
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && a2enmod rewrite \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Limites pensados tanto no Docker quanto na Hostinger compartilhada
RUN { \
      echo 'upload_max_filesize = 32M'; \
      echo 'post_max_size = 40M'; \
      echo 'memory_limit = 256M'; \
      echo 'max_execution_time = 120'; \
      echo 'date.timezone = America/Sao_Paulo'; \
      echo 'display_errors = Off'; \
    } > /usr/local/etc/php/conf.d/freebuff.ini

COPY docker/entrypoint.sh /usr/local/bin/freebuff-entrypoint
RUN chmod +x /usr/local/bin/freebuff-entrypoint

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["freebuff-entrypoint"]
CMD ["apache2-foreground"]
