#!/bin/bash

# Only need to composer install if the vendor directory does not already exist (AKA cached by Solano CI)
if [ ! -d vendor ]; then
  curl -sS https://getcomposer.org/installer | php
  php composer.phar install
fi
