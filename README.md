# FeedbackHub: Customer Feedback System

**Project topic:** Building Web Applications: Web Application Security, Testing, Deployment and Review
**Student:** Agu Prince
**Stack:** PHP 8 · MySQL/MariaDB · HTML5 · CSS3 · JavaScript · Chart.js (runs on XAMPP)

Customers register, rate their experience and track replies. Administrators manage, answer and analyse all feedback. The app also contains in-app pages for each part of the topic: **Security**, **Testing**, **Deployment** and **Project Review** (open the *Project* menu).

---

## 1. Run it on your computer (XAMPP)

1. Install **XAMPP** (PHP 8.1 or newer) from apachefriends.org.
2. Copy the `feedback-system` folder into `C:\xampp\htdocs\`.
3. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
4. Open `http://localhost/phpmyadmin`, click **Import**, choose `database/schema.sql`, then click **Go**.
5. In the project folder, copy `.env.example` and rename the copy to `.env`. The default values already match XAMPP (user `root`, empty password).
6. Open **http://localhost/feedback-system**

| Role  | Email                   | Password  |
|-------|-------------------------|-----------|
| Admin | admin@feedbackhub.test  | Admin@123 |
| User  | demo@feedbackhub.test   | User@1234 |

> Windows tip: if `.env` is hard to create in Explorer, open Notepad, paste the contents of `.env.example`, choose *Save as*, set the type to *All files* and name the file `.env`.

**No XAMPP?** Any PHP 8.1+ with MySQL works: `php -S localhost:8000 deploy/router.php`

## 2. Run the tests

```bash
php tests/run_tests.php http://localhost/feedback-system
```
This runs 51 automated tests: database, login/registration, form validation, functional, security and error handling. Results appear on the **Testing** page and in `tests/TEST_REPORT.md`.

Optional responsive (mobile) test with screenshots: `python tests/responsive_test.py http://localhost/feedback-system` (needs `pip install playwright` and `playwright install chromium`).

## 3. Folder structure

```
feedback-system/
├── index.php, login.php, register.php, logout.php   Public pages
├── dashboard.php, feedback_new.php, my_feedback.php,
│   feedback_view.php, profile.php                    Customer pages
├── security.php, testing.php, deployment.php,
│   review.php                                        Project documentation pages
├── admin/        Admin dashboard, feedback, users, logs, CSV export
├── includes/     bootstrap, database, security, validation, header/footer
├── config/       config.php (reads .env)
├── assets/       CSS, JavaScript, Chart.js, test screenshots
├── errors/       Friendly 403 / 404 / 500 pages
├── database/     schema.sql (XAMPP) and schema_hosting.sql (shared hosting)
├── tests/        Automated tests and their results
├── deploy/       Backup scripts, Apache/Nginx configs, dev-server router
├── docs/         DEPLOYMENT.md (step-by-step go-live guide)
├── storage/      Logs and backups (must be writable)
├── .env.example  Environment variable template
└── .htaccess     Apache security rules
```

## 4. Deploy online
See **docs/DEPLOYMENT.md** or the in-app **Deployment** page.

## 5. Before going live
Change the admin password, set `APP_ENV=production` and `FORCE_HTTPS=true` in `.env`, and remove the demo users.
