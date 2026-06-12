#!/bin/bash

set -e

hosts="188.121.114.24"

echo "are you sure? (type y)";
read -rn 1 accept;
test "$accept" != "y" && exit 1;
echo

echo "uploading"


eval "$(ssh-agent -s)"
ssh-add $HOME/.ssh/phnx-DB-production

for host in $hosts; do
  echo "uploading to $host"

  echo 'prepare directories'
  ssh -p 22 -i $HOME/.ssh/phnx-DB-production ubuntu@$host "
    sudo mkdir -p /var/www/wallbot && \
    sudo mkdir -p /var/www/wallbot/storage /var/www/wallbot/storage/framework/tmp /var/www/wallbot/bootstrap/cache && \
    sudo chown -R ubuntu:www-data /var/www/wallbot
  "

  echo 'copy files'
  rsync -avz \
    --delete \
    --no-perms --no-owner --no-group \
    --omit-dir-times \
    --no-times \
    -e "ssh -i $HOME/.ssh/phnx-DB-production -p 22" \
    --exclude-from=.rsyncignore \
    ./ ubuntu@$host:/var/www/wallbot

  echo 'install dependencies and refresh laravel caches'
  ssh -p 22 -i $HOME/.ssh/phnx-DB-production ubuntu@$host "
    cd /var/www/wallbot && \
    if command -v composer >/dev/null 2>&1; then
      composer install --no-dev --optimize-autoloader --no-interaction
    fi
  "

  echo 'fix permissions'
  ssh -p 22 -i $HOME/.ssh/phnx-DB-production ubuntu@$host "
    sudo chown -R www-data:www-data /var/www/wallbot && \
    sudo chmod -R 775 /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache && \
    sudo chgrp -R www-data /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache && \
    sudo chmod -R ug+rwx /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache
  "

  echo 'discover packages and clear caches'
  ssh -p 22 -i $HOME/.ssh/phnx-DB-production ubuntu@$host "
    sudo -u www-data bash -c '
      cd /var/www/wallbot && \
      php artisan package:discover --ansi && \
      php artisan config:clear --ansi && \
      php artisan route:clear --ansi && \
      php artisan view:clear --ansi
    '
  "

  echo 'restart supervisor workers'
  ssh -p 22 -i $HOME/.ssh/phnx-DB-production ubuntu@$host "
    sudo supervisorctl stop all && \
    sudo supervisorctl reread && \
    sudo supervisorctl update && \
    sudo supervisorctl reload
  "

  echo "done for $host"
done

