FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
      tesseract-ocr tesseract-ocr-eng libzip-dev libpng-dev libjpeg-dev \
      libfreetype6-dev libonig-dev libxml2-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN { echo "upload_max_filesize=6M"; echo "post_max_size=20M"; \
      echo "memory_limit=256M"; echo "max_execution_time=300"; } \
    > /usr/local/etc/php/conf.d/app.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
# one Tesseract thread: OpenMP threads fight over a small shared CPU and make OCR several times slower
ENV OMP_THREAD_LIMIT=1
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev --no-scripts \
 && chmod +x docker/start.sh

EXPOSE 10000
CMD ["docker/start.sh"]
