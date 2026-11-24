#!/usr/bin/env bash
echo "Waiting for the database..."
for i in $(seq 1 60); do
  mysqladmin ping -h db -u feedback_user -pdevpass --silent 2>/dev/null && break
  sleep 2
done
pkill -f "php -S 0.0.0.0:8000" 2>/dev/null || true
setsid nohup php -S 0.0.0.0:8000 deploy/router.php > storage/logs/server.log 2>&1 &
echo "FeedbackHub is running on port 8000 (see the PORTS tab)."
