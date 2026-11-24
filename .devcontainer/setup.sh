#!/usr/bin/env bash
set -e
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i 's/^APP_ENV=.*/APP_ENV=development/; s/^DB_HOST=.*/DB_HOST=db/; s/^DB_USER=.*/DB_USER=feedback_user/; s/^DB_PASS=.*/DB_PASS=devpass/' .env
fi
mkdir -p storage/logs storage/backups
