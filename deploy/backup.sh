#!/usr/bin/env bash
# FeedbackHub database backup (Linux / VPS / cPanel cron)
# Schedule nightly at 2am:   crontab -e   ->   0 2 * * * /var/www/feedback-system/deploy/backup.sh
set -euo pipefail
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
set -a; source "$APP_DIR/.env"; set +a        # read DB settings from .env
BACKUP_DIR="$APP_DIR/storage/backups"
FILE="$BACKUP_DIR/${DB_NAME}_$(date +%Y-%m-%d_%H%M).sql.gz"
mkdir -p "$BACKUP_DIR"
MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" \
    --single-transaction --routines --no-tablespaces "$DB_NAME" | gzip > "$FILE"
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +14 -delete   # keep 14 days
echo "[$(date '+%Y-%m-%d %H:%M:%S')] BACKUP: created $(basename "$FILE")" >> "$APP_DIR/storage/logs/app.log"
echo "Backup saved: $FILE"
