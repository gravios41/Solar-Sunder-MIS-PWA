FROM php:8.3-apache

RUN a2enmod rewrite headers

COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY ["sunder-solar-mis/", "/var/www/html/"]

RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/__PORT__/${PORT:-10000}/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
