# FeedbackHub automated test report

Run: 2026-11-19 10:02:02  
Target: http://127.0.0.1:8081/feedback-system  
Result: **51 / 51 passed**

| ID | Category | Test | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| D1 | Database | Database connection | PDO connects using .env settings | Connected to feedback_system | Pass |
| D2 | Database | All required tables exist | 6 tables: users, categories, feedback, replies, login_attempts, activity_logs | All 6 tables found | Pass |
| D3 | Database | Unique email constraint | Inserting a duplicate email is rejected by the database | Rejected (SQLSTATE 23000) | Pass |
| D4 | Database | Foreign key constraint | Feedback for a non-existent user is rejected | Rejected (SQLSTATE 23000) | Pass |
| D5 | Database | Rating CHECK constraint | A rating of 9 is rejected by the database | Rejected (SQLSTATE 23000) | Pass |
| D6 | Database | Transaction rollback | Changes inside a rolled-back transaction are not saved | Rows before: 6, after rollback: 6 | Pass |
| L1 | Login/Registration | Register with valid details | Account created, redirected to login with success message | Redirected to login, success message shown | Pass |
| L2 | Login/Registration | Register with an existing email | "An account with this email already exists" | Duplicate email error displayed | Pass |
| L3 | Login/Registration | Valid login | User is taken to the dashboard | Dashboard displayed with user name | Pass |
| L4 | Login/Registration | Login with wrong password | "Invalid email or password" shown, no login | Error message displayed, stayed on login page | Pass |
| L5 | Login/Registration | Login with unregistered email | Same generic error as wrong password (no account enumeration) | Generic "Invalid email or password" displayed | Pass |
| L6 | Login/Registration | Admin login | Admin is taken to the admin dashboard | Admin dashboard displayed | Pass |
| L7 | Login/Registration | Logout | Session ends; dashboard then redirects to login | After logout, dashboard redirects to login.php | Pass |
| V1 | Form validation | Empty registration fields | Required-field messages displayed | Messages shown for name, email and password | Pass |
| V2 | Form validation | Invalid email format | "Enter a valid email address" | Email format error displayed | Pass |
| V3 | Form validation | Weak password | Password rule message; account not created | Strong-password rule message displayed | Pass |
| V4 | Form validation | Passwords do not match | "Passwords do not match" | Mismatch message displayed | Pass |
| V5 | Form validation | Invalid name and phone | Name and phone format errors | Both format errors displayed | Pass |
| V6 | Form validation | Feedback rating outside 1-5 | Rating error; nothing saved | Rejected with rating error | Pass |
| V7 | Form validation | Feedback with tampered category id | Category error; nothing saved | Invalid category rejected | Pass |
| V8 | Form validation | Feedback message too short | Length error shown | Length error displayed | Pass |
| F1 | Functional | Home page loads | HTTP 200 with statistics | HTTP 200, statistics shown | Pass |
| F2 | Functional | Submit valid feedback | Saved and the detail page is shown | Feedback saved with status Pending | Pass |
| F3 | Functional | Feedback appears in "My feedback" | New feedback listed | Listed in the table | Pass |
| F4 | Functional | Search and filter | Matching item shown; non-matching hidden | Keyword search correct; rating filter correct | Pass |
| F5 | Functional | Admin replies and resolves feedback | Customer sees the reply and status "Resolved" | Reply visible to customer, status Resolved | Pass |
| F6 | Functional | Update profile | New name saved and shown | Profile updated message and new name shown | Pass |
| F7 | Functional | Change password | Old password stops working, new one works | Changed; login with new password works | Pass |
| F8 | Functional | Admin dashboard, charts and CSV export | Dashboard renders charts; export returns a CSV file | 4 charts rendered; CSV downloaded | Pass |
| S1 | Security | SQL injection in login form | Input  ' OR '1'='1' --  does not log in | Login refused; treated as a normal (wrong) email | Pass |
| S2 | Security | SQL injection in search box | No database error; payload treated as text | HTTP 200, no hashes or SQL errors leaked, zero results | Pass |
| S3 | Security | Stored XSS in feedback | <script> saved but displayed as harmless text | Output escaped as &lt;script&gt; - script does not run | Pass |
| S4 | Security | Reflected XSS in search parameter | Search term escaped in the page | Escaped in the search box value | Pass |
| S5 | Security | CSRF: form submitted without token | Request rejected with HTTP 403 | HTTP 403 | Pass |
| S6 | Security | CSRF: forged token | Request rejected with HTTP 403 | HTTP 403 | Pass |
| S7 | Security | Unauthorised admin page (logged in as user) | Access denied (HTTP 403) | HTTP 403, 403, 403, 403 for 4 admin pages | Pass |
| S8 | Security | Protected page without login | Redirected to login page | Both redirected to login.php | Pass |
| S9 | Security | IDOR: opening another user's feedback | Access denied (HTTP 403) | User B got HTTP 403 for User A's feedback #15 | Pass |
| S10 | Security | Passwords stored as hashes | Database holds a bcrypt hash, not the password | Stored: $2y$10$CfLqJqb9OMy9q… (bcrypt) | Pass |
| S11 | Security | Role cannot be chosen at registration | Extra field role=admin is ignored; account is "user" | Account created with role: user | Pass |
| S12 | Security | Security headers sent | CSP, X-Frame-Options, nosniff, Referrer-Policy present; no X-Powered-By | All headers present, PHP version hidden | Pass |
| S13 | Security | Session cookie flags | Cookie is HttpOnly and SameSite=Lax | HttpOnly; SameSite=Lax | Pass |
| S14 | Security | Session fixation prevented | Session ID changes after login | ID before and after login are different | Pass |
| S15 | Security | Sensitive files blocked from the web | .env, database/schema.sql and logs return 403/404 | Responses: 403, 403, 403, 403, 403 | Pass |
| S16 | Security | Deactivated account cannot log in | Login refused with "deactivated" message | Login refused, message displayed | Pass |
| S17 | Security | Brute-force lockout | After 5 wrong passwords even the correct one is blocked | Blocked after 5 failures | Pass |
| S18 | Security | Security events are logged | Failed logins and denied access appear in activity_logs | 4 of 4 security event types recorded in the last 10 minutes | Pass |
| E1 | Error handling | Feedback that does not exist | Friendly 404 page | HTTP 404, friendly page shown | Pass |
| E2 | Error handling | Invalid id value (text instead of number) | Friendly 404 page, no PHP error | HTTP 404, no PHP errors | Pass |
| E3 | Error handling | Array injection in parameters | Handled without PHP errors | HTTP 200, input safely ignored | Pass |
| E4 | Error handling | Delete resolved feedback (business rule) | "Only pending feedback can be deleted" | Rule enforced, message shown | Pass |

## Response times

| Page | Time (ms) |
|---|---|
| Home page | 1.2 |
| My feedback list | 1.8 |
| Admin dashboard | 3 |
| Admin feedback search | 2 |
