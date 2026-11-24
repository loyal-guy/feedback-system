# Deployment guide: FeedbackHub

This guide moves the application from XAMPP (development) to a live website.
Option A uses free hosting, which is enough for a project demonstration. Option B uses a VPS.

---

## Pre-deployment checklist

- [ ] All automated tests pass: `php tests/run_tests.php http://localhost/feedback-system`
- [ ] Database backed up (`deploy/backup.bat` on XAMPP)
- [ ] Admin password will be changed after upload
- [ ] `.env` is ready with production values (it is **not** uploaded from your PC)

---

## Option A: Free or shared hosting (InfinityFree / cPanel)

1. **Create an account and a site.** Choose a free subdomain (e.g. `feedbackhub.infinityfreeapp.com`) or connect your own domain.
2. **Create a MySQL database** in the control panel. Write down the **host**, **database name**, **username** and **password** it shows. On free hosts the host is usually *not* `localhost`.
3. **Import the tables:** open phpMyAdmin from the control panel, select your new database, click **Import** and choose `database/schema_hosting.sql`. This version has no `CREATE DATABASE` line, because shared hosts do not allow it.
4. **Upload the files** into `htdocs/` (or `public_html/`) with the File Manager or an FTP program such as FileZilla. Do not upload `.env`, `storage/logs/*.log` or `storage/backups/*`.
5. **Create `.env`** on the server (File Manager → New file) with these values:
   ```
   APP_ENV=production
   APP_NAME=FeedbackHub
   APP_BASE_URL=
   DB_HOST=sqlXXX.infinityfree.com
   DB_PORT=3306
   DB_NAME=if0_XXXXXXX_feedback
   DB_USER=if0_XXXXXXX
   DB_PASS=your-database-password
   SESSION_TIMEOUT_MINUTES=30
   MAX_LOGIN_ATTEMPTS=5
   LOCKOUT_MINUTES=15
   FORCE_HTTPS=true
   ```
6. **Fix error pages:** in `.htaccess`, change `/feedback-system/errors/...` to `/errors/...` because the app is now at the root of the domain.
7. **Enable SSL:** in the control panel open *SSL/TLS* (cPanel: *AutoSSL*; InfinityFree: *Free SSL Certificates*), issue the certificate and wait until it is active. Then uncomment the two HTTPS lines in `.htaccess`.
8. **Permissions:** `storage/` and its sub-folders must be writable (755 or 775). Everything else stays read-only (644 for files).
9. **Secure the accounts:** log in as `admin@feedbackhub.test`, change the password on the Profile page, and deactivate the demo users on the Users page.
10. **Smoke test:** register, log in, submit feedback, reply as admin, and check:
    - the browser shows the padlock (HTTPS)
    - `https://your-site/.env` shows "Not Found"
    - the Deployment page readiness check (as admin) is green
    - optionally, run `php tests/run_tests.php https://your-site` from your PC. This needs remote database access, which free hosts usually block; if so, test by hand.

---

## Option B: VPS (Ubuntu 24.04, DigitalOcean / AWS Lightsail)

```bash
# 1. Install the server software
sudo apt update && sudo apt install -y apache2 mariadb-server php php-mysql php-mbstring unzip certbot python3-certbot-apache
sudo mysql_secure_installation

# 2. Database and a least-privilege user
sudo mysql < /var/www/feedback-system/database/schema.sql
sudo mysql -e "CREATE USER 'feedback_app'@'localhost' IDENTIFIED BY 'LONG-RANDOM-PASSWORD';
  GRANT SELECT, INSERT, UPDATE, DELETE ON feedback_system.* TO 'feedback_app'@'localhost'; FLUSH PRIVILEGES;"

# 3. Code (git clone or upload the zip) and permissions
sudo chown -R root:www-data /var/www/feedback-system
sudo find /var/www/feedback-system -type d -exec chmod 755 {} \;
sudo find /var/www/feedback-system -type f -exec chmod 644 {} \;
sudo chown -R www-data:www-data /var/www/feedback-system/storage
sudo chmod +x /var/www/feedback-system/deploy/backup.sh

# 4. Configuration
sudo cp .env.example .env && sudo nano .env      # production values
sudo chmod 640 .env && sudo chown root:www-data .env

# 5. Web server + HTTPS
sudo cp deploy/apache-vhost.conf /etc/apache2/sites-available/feedbackhub.conf   # edit the domain first
sudo a2enmod rewrite headers ssl && sudo a2ensite feedbackhub && sudo systemctl reload apache2
sudo certbot --apache -d feedbackhub.yourdomain.com

# 6. Firewall
sudo ufw allow OpenSSH && sudo ufw allow 'Apache Full' && sudo ufw enable

# 7. Nightly backups at 02:00
(crontab -l 2>/dev/null; echo "0 2 * * * /var/www/feedback-system/deploy/backup.sh") | crontab -
```

**Domain:** in your registrar's DNS settings, add an **A record** for `feedbackhub` (or `@`) pointing to the VPS IP address.

**Updating later:** run the tests locally, back up, `git pull` on the server, and check the site.

---

## Backups and restore

| Task | Command |
|---|---|
| Backup (Linux) | `deploy/backup.sh` → `storage/backups/feedback_system_DATE.sql.gz` |
| Backup (XAMPP) | double-click `deploy\backup.bat` |
| Restore | `gunzip < backup.sql.gz \| mysql -u root -p feedback_system` |

Keep one copy off the server (Google Drive or your PC) and practise restoring regularly.

## Monitoring

- `storage/logs/app.log`: PHP errors, database failures, security events, backups
- Admin → **Activity Logs**: logins, failed logins, lockouts, denied access, admin actions
- Admin dashboard: failed logins and blocked requests in the last 24 hours
- UptimeRobot (free): alerts you by email if the site goes down
- Check the security headers at securityheaders.com and the SSL setup at ssllabs.com/ssltest
