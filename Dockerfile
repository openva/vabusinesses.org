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

# Show PHP errors in this container.
#
# .htaccess turns display_errors off, because it is deployed verbatim and a
# stack trace in a page leaks paths to whoever provoked it. Here there is no
# untrusted visitor, and a silent 500 while developing is just wasted time.
#
# .htaccess is merged after all server config, so a php_flag in an Apache conf
# cannot outrank it, and neither can a php.ini setting. ini_set() runs later
# still, at execution time, so an auto-prepended file is what actually wins.
# Both pieces are baked into the image and excluded from the deploy bundle,
# so production keeps the safe value from .htaccess.
RUN printf '<?php ini_set("display_errors", "1");\n' > /usr/local/etc/php/dev-display-errors.php \
    && printf 'auto_prepend_file=/usr/local/etc/php/dev-display-errors.php\n' \
        > /usr/local/etc/php/conf.d/zz-dev-display-errors.ini

# Composer runs inside the container, against the same PHP the site runs on.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Copy over the deploy scripts
WORKDIR /var/www/
COPY ./deploy ./deploy

EXPOSE 80

RUN deploy/docker-setup-server.sh

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
