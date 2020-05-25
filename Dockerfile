FROM php:7.2-apache
RUN a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -
RUN apt-get update
RUN apt-get install -y zip nodejs jq sqlite

# Copy over the deploy scripts
WORKDIR /var/www/
COPY ./deploy ./deploy

EXPOSE 80

RUN deploy/docker-setup-server.sh

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
