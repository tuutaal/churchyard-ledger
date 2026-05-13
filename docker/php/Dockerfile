FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
  && a2enmod rewrite

RUN printf '%s\n' \
  '<Directory /var/www/html>' \
  '    AllowOverride All' \
  '    Require all granted' \
  '</Directory>' \
  > /etc/apache2/conf-available/anesti.conf \
  && a2enconf anesti
