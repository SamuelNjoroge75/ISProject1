# ISProject1: company feedback portal

A secure PHP + MariaDB web app for a business and IT company. Every department has its own
short feedback form (Ubuntu, Apache or Nginx).

## How it works

1. **Sign up** and pick your department (Software Development, IT Support & Infrastructure,
   Cybersecurity, Data & Analytics, Sales & Marketing, Human Resources, Finance & Admin).
2. **Log in** and choose your department again. It must match the one on your account.
3. Your department's **feedback form slides up** on the dashboard.
4. Answer **two quick questions** (overall rating 1-5, would you recommend working here) and tell
   us **what to improve**. You can send it anonymously.
5. Administrators see a summary per department and the latest feedback on the Admin page.

A person can only open the form of their own department. The department is read from the
account, never from the web address.

## See how the feedback is stored

    sudo mysql ISProject1 -e "SELECT f.id, d.name AS department, f.rating, f.recommend, f.improvement,
      IF(f.anonymous = 1, 'Anonymous', u.username) AS sent_by, f.submitted_at
      FROM feedback f JOIN departments d ON d.id = f.department_id
      LEFT JOIN users u ON u.id = f.user_id ORDER BY f.id;"

    sudo mysql ISProject1 -e "SELECT * FROM feedback;"

Tables: `departments`, `users` (each user has a `department_id`), `feedback`, `login_attempts`.

## Security

Argon2id password hashing, HTML and server-side validation, prepared statements,
CSRF protection, hardened sessions (HttpOnly, SameSite, 30-minute idle timeout), login lockout
(5 wrong passwords per username, 20 per IP, 15 minutes), security headers (including a strict
Content-Security-Policy), admin activity log, and a daily database backup script.

## Friendly extras

A bright multi-colour design with a colour for every department, a light/dark switch (remembered
in the browser), show/hide password, a live password-strength meter, confetti on signup,
first login and after sending feedback, a lockout countdown, a time-of-day greeting, a daily
security tip, a status page, and a Konami-code easter egg.

Note: the Content-Security-Policy blocks inline scripts and styles, so all JavaScript lives in
`theme.js`, `ui.js` and `strength.js`, and all styling lives in `style.css`.

## Upgrading from the first version (before departments)

The database layout changed (departments and feedback tables, and a department for each user).
If you only have test accounts, start the database again:

    sudo mysql -e "DROP DATABASE ISProject1;"
    sudo mysql < schema.sql
    sudo mysql -e "GRANT SELECT, INSERT, UPDATE ON ISProject1.* TO 'isproject'@'localhost'; FLUSH PRIVILEGES;"

Then sign up again and make your admin account (see below).

## Requirements

    sudo apt update
    sudo apt install apache2 php libapache2-mod-php php-mysql php-mbstring mariadb-server git -y

(Using Nginx instead? Install `nginx php-fpm php-mysql mariadb-server git` and add a PHP location block.)

## Install on a server

1. Clone the repository and copy the site into the web root:

       git clone https://github.com/<group>/ISProject1.git
       sudo mkdir -p /var/www/html/isproject1
       sudo cp -r ISProject1/* /var/www/html/isproject1/
       sudo rm -f /var/www/html/isproject1/README.md

2. Create the database and tables:

       sudo mysql < ISProject1/schema.sql

3. Create the database user (choose your own strong password):

       sudo mysql -e "CREATE USER 'isproject'@'localhost' IDENTIFIED BY 'YOUR-STRONG-PASSWORD';"
       sudo mysql -e "GRANT SELECT, INSERT, UPDATE ON ISProject1.* TO 'isproject'@'localhost'; FLUSH PRIVILEGES;"

4. Create the config file and put the same password in it:

       cd /var/www/html/isproject1
       sudo cp config.example.php config.php
       sudo nano config.php

5. Set ownership and permissions:

       sudo chown -R www-data:www-data /var/www/html/isproject1
       sudo chmod 640 /var/www/html/isproject1/config.php

6. Open `http://<server>/isproject1/` and sign up.

## Make a user an administrator

Sign up through the website first, then:

    sudo mysql -e "UPDATE ISProject1.users SET role='admin' WHERE username='YOUR-USERNAME';"

## Subdomains (for the DNS and Nginx/Apache work)

All pages live in one folder. Point each subdomain at the same folder and set its start page:

| Subdomain | Start page |
|---|---|
| app.example.com | index.php |
| admin.example.com | admin.php (administrators only) |
| status.example.com | status.php |

Nginx: use `index admin.php;` (or `status.php`) inside that server block.
Apache: use `DirectoryIndex admin.php` (or `status.php`) inside that VirtualHost.

## Daily backup (as root)

    sudo cp backup.sh /usr/local/bin/isproject1-backup.sh
    sudo chmod +x /usr/local/bin/isproject1-backup.sh
    sudo crontab -e
    # add this line:
    0 2 * * * /usr/local/bin/isproject1-backup.sh

Backups are saved in `/var/backups/isproject1` and the last 7 days are kept.

## Notes

- Never commit `config.php` (it is in `.gitignore`).
- Lockout rules are set at the top of `lib.php` (5 wrong passwords per username, 20 per IP, 15 minutes).
- Security headers are sent by PHP (`lib.php`). If you also add them in Nginx or Apache, remove one set to avoid duplicates.
