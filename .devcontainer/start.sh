#!/usr/bin/env bash
cd "$(dirname "$0")/.." || exit 1
if ! command -v apt-get >/dev/null; then
  echo "PROBLEM: this Codespace is in recovery mode. Run: Codespaces: Rebuild Container"; exit 1
fi
PHP=php
if ! command -v php >/dev/null || ! php -m | grep -qi pdo_mysql || ! php -m | grep -qi mbstring; then
  echo "Installing PHP (first time only)..."
  sudo apt-get update -qq
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-cli php-mysql php-curl php-mbstring >/dev/null
  PHP=/usr/bin/php
fi
if ! command -v mysql >/dev/null; then
  echo "Installing MariaDB (first time only, about 1-2 minutes)..."
  sudo apt-get update -qq
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mariadb-server mariadb-client >/dev/null
fi
echo "Starting the database..."
sudo service mariadb start >/dev/null 2>&1 || sudo service mysql start >/dev/null 2>&1 || {
  sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld
  (sudo mysqld_safe >/dev/null 2>&1 &)
}
for i in $(seq 1 30); do sudo mysqladmin ping --silent >/dev/null 2>&1 && break; sleep 1; done
if ! sudo mysql -e "SELECT 1 FROM feedback_system.users LIMIT 1" >/dev/null 2>&1; then
  echo "Creating the database and demo data..."
  sudo mysql < database/schema.sql
fi
sudo mysql -e "CREATE USER IF NOT EXISTS 'feedback_user'@'localhost' IDENTIFIED BY 'devpass';
  CREATE USER IF NOT EXISTS 'feedback_user'@'127.0.0.1' IDENTIFIED BY 'devpass';
  ALTER USER 'feedback_user'@'localhost' IDENTIFIED BY 'devpass';
  ALTER USER 'feedback_user'@'127.0.0.1' IDENTIFIED BY 'devpass';
  GRANT ALL ON feedback_system.* TO 'feedback_user'@'localhost';
  GRANT ALL ON feedback_system.* TO 'feedback_user'@'127.0.0.1'; FLUSH PRIVILEGES;"
cp .env.example .env
sed -i 's/^APP_ENV=.*/APP_ENV=development/; s/^DB_HOST=.*/DB_HOST=127.0.0.1/; s/^DB_USER=.*/DB_USER=feedback_user/; s/^DB_PASS=.*/DB_PASS=devpass/' .env
mkdir -p storage/logs storage/backups
pkill -f "S 0.0.0.0:8000" 2>/dev/null || true
setsid nohup "$PHP" -S 0.0.0.0:8000 deploy/router.php > storage/logs/server.log 2>&1 &
sleep 2
CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/)
if [ "$CODE" = "200" ]; then
  echo "SUCCESS: FeedbackHub is running on port 8000. Open the PORTS tab and click the globe next to 8000."
  echo "To run the tests: $PHP tests/run_tests.php http://localhost:8000"
else
  echo "PROBLEM: website answered HTTP $CODE. Send a photo of this:"; tail -5 storage/logs/server.log; tail -3 storage/logs/app.log 2>/dev/null
fi
