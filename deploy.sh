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
    sudo mkdir -p /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache && \
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

  echo 'fix permissions'
  ssh -p 22 -i $HOME/.ssh/phnx-DB-production ubuntu@$host "
    sudo chown -R www-data:www-data /var/www/wallbot && \
    sudo chmod -R 775 /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache && \
    sudo chgrp -R www-data /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache && \
    sudo chmod -R ug+rwx /var/www/wallbot/storage /var/www/wallbot/bootstrap/cache
  "

  echo "done for $host"
done

