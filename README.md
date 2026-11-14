# ISProject1

A secure PHP + MariaDB web application using Ubuntu and Apache.

**Features:** signup and login, Argon2id password hashing, HTML and server-side validation,
prepared statements, CSRF protection, hardened sessions (HttpOnly, SameSite, idle timeout),
login lockout, admin activity log, status page, password strength meter, security headers,
and a daily database backup script.

## Requirements

    sudo apt update
    sudo apt install apache2 php libapache2-mod-php php-mysql php-mbstring mariadb-server git -y


## Install on a server

1. Clone the repository and copy the site into the web root:

       git clone https://github.com/<group>/ISProject1.git
       sudo mkdir -p /var/www/html/isproject1
       sudo cp -r ISProject1/* /var/www/html/isproject1/
       sudo rm -f /var/www/html/isproject1/README.md

2. Create the database and tables:

       sudo mysql < ISProject1/schema.sql

3. Create the database user and choose your own strong password:

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

## Subdomains (for the DNS and Apache work)

All pages live in one folder. Point each subdomain at the same folder and set its start page:

| Subdomain | Start page |
|---|---|
| app.example.com | index.php |
| admin.example.com | admin.php (administrators only) |
| status.example.com | status.php |

Apache: use `DirectoryIndex admin.php` (or `status.php`) inside that VirtualHost.

## Daily backup (as root)

    sudo cp backup.sh /usr/local/bin/isproject1-backup.sh
    sudo chmod +x /usr/local/bin/isproject1-backup.sh
    sudo crontab -e
    # add this line:
    0 2 * * * /usr/local/bin/isproject1-backup.sh

Backups are saved in `/var/backups/isproject1` and the last 7 days are kept.

## Notes

- Lockout rules are set at the top of `lib.php` (5 wrong passwords per username, 20 per IP, 15 minutes).
- Security headers are sent by PHP (`lib.php`). If you also add them in Nginx or Apache, remove one set to avoid duplicates.
