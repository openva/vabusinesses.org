#!/bin/bash

cd htdocs/

# Set the include path.
if [ $(grep include_path .htaccess |grep -v "#" |wc -l |xargs) -eq 0 ]; then
	echo 'php_value include_path ".:includes/"' >> .htaccess
fi

# Have PHP report errors.
if [ $(grep 2039 .htaccess |grep -v "#" |wc -l |xargs) -eq 0 ]; then
	echo 'php_value error_reporting 2039' >> .htaccess
fi

npm install && npm run build

cd ..
