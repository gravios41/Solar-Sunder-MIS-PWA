FROM php:8.3-apache

RUN a2enmod rewrite headers env

# Tesseract OCR — used by api/bill-upload-ocr.php to read kWh/billing period
# off an uploaded electric bill. Without this the binary simply doesn't
# exist in the container and every OCR request fails, forcing manual entry.
RUN apt-get update \
    && apt-get install -y --no-install-recommends tesseract-ocr libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY ["sunder-solar-mis/", "/var/www/html/"]

RUN chown -R www-data:www-data /var/www/html \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
