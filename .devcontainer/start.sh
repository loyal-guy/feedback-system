#!/usr/bin/env bash
cd "$(dirname "$0")/.." || exit 1
if ! command -v mysql >/dev/null; then
  echo "Installing MariaDB (first time only, about 1-2 minutes)..."
  sudo apt-get update -qq 2>/dev/null
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
docker rm -f fb-web >/dev/null 2>&1 || true
PHP=""
for c in /usr/local/php/current/bin/php "$(command -v php)"; do
  [ -n "$c" ] && [ -x "$c" ] || continue
  if "$c" -r 'exit(PHP_VERSION_ID >= 80100 && extension_loaded("pdo_mysql") && extension_loaded("mbstring") ? 0 : 1);' 2>/dev/null; then
    PHP="$c"; break
  fi
done
if [ -n "$PHP" ]; then
  setsid nohup "$PHP" -S 0.0.0.0:8000 deploy/router.php > storage/logs/server.log 2>&1 &
  RUN="$PHP"
else
  if ! docker image inspect fbphp >/dev/null 2>&1; then
    echo "Preparing PHP 8.3 (first time only, about 1-2 minutes)..."
    printf 'FROM php:8.3-cli\nRUN docker-php-ext-install pdo_mysql\n' | docker build -q -t fbphp - >/dev/null \
      || { echo "PROBLEM: could not prepare PHP 8.3. Send a photo of this."; exit 1; }
  fi
  docker run -d --name fb-web --network host --user "$(id -u):$(id -g)" \
    -v "$PWD":/app -w /app fbphp php -S 0.0.0.0:8000 deploy/router.php >/dev/null
  RUN="docker exec fb-web php"
fi
sleep 3
CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/)
if [ "$CODE" = "200" ]; then
  echo "SUCCESS: FeedbackHub is running on port 8000. Open the PORTS tab and click the globe next to 8000."
  echo "To run the tests: $RUN tests/run_tests.php http://localhost:8000"
else
  echo "PROBLEM: website answered HTTP $CODE. Send a photo of this:"
  tail -5 storage/logs/server.log 2>/dev/null; docker logs --tail 5 fb-web 2>/dev/null; tail -3 storage/logs/app.log 2>/dev/null
fi
