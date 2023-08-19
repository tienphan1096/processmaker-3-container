#!/bin/sh
set -e
service nginx start
service php8.1-fpm start
tail -f /dev/null