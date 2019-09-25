#!/bin/bash

# Make localhost the name of the host
grep "#ServerName www.example.com" /etc/apache2/sites-enabled/000-default.conf
if [ $? -eq 0 ]; then
    sed -i 's/#ServerName www.example.com/ServerName localhost/g' /etc/apache2/sites-enabled/000-default.conf
fi

# Make /var/www/htdocs the webroot
grep "DocumentRoot /var/www/html" /etc/apache2/sites-enabled/000-default.conf
if [ $? -eq 0 ]; then
    sed -i 's/html/htdocs/g' /etc/apache2/sites-enabled/000-default.conf
fi

rm -Rf /var/www/deploy/
