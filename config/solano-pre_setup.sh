#!/bin/bash

# Only need to composer install if the vendor directory has not been restored from Solano CI's cache
if [ ! -d vendor ]; then
  curl -sS https://getcomposer.org/installer | php
  php composer.phar install
fi
