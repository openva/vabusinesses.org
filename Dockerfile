FROM php:8.2-apache
RUN a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl gnupg \
    && rm -rf /var/lib/apt/lists/*

# Add the NodeSource repository. This has to be a single RUN, so that
# NODE_MAJOR is still set when the repo URL is assembled.
ENV NODE_MAJOR=20
RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list

# "sqlite" is not a package on Debian 13; the CLI we invoke is "sqlite3".
RUN apt-get update && apt-get install -y --no-install-recommends \
    zip unzip nodejs jq sqlite3 \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug && docker-php-ext-enable xdebug

# Composer runs inside the container, against the same PHP the site runs on.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Copy over the deploy scripts
WORKDIR /var/www/
COPY ./deploy ./deploy

EXPOSE 80

RUN deploy/docker-setup-server.sh

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
