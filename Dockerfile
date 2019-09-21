FROM php:5.6.39-apache
RUN a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -
RUN apt-get update
RUN apt-get install -y zip nodejs
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'a5c698ffe4b8e849a443b120cd5ba38043260d5c4023dbf93e1558871f1f07f58274fc6f4c93bcfd858c6bd0775cd8d1') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy over the deploy scripts
WORKDIR /var/www/
COPY ./deploy ./deploy

EXPOSE 80

RUN deploy/docker-setup-server.sh

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
