FROM ubuntu

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y apache2 \
    php \
    libapache2-mod-php \
    php-mysql \
    php-cli \
    php-bcmath \
    php-curl \
    php-json \
    php-mbstring \
    php-tokenizer \
    php-xml \
    php-zip \
    php-gmp \
    php-mysql

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html 

RUN composer install --no-interaction --no-plugins --no-scripts

RUN a2enmod rewrite

# Update Apache configuration
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html 

RUN php artisan key:generate
RUN php artisan config:cache

EXPOSE 80

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]