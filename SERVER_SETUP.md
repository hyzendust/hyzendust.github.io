# Server Setup: PostgreSQL + PHP Auth for freedoms4

## Overview

```
/var/www/freedoms4/          ← Hugo's published docs/ folder (static files)
/var/www/freedoms4/api/      ← PHP backend (auth.php lives here)
```

Nginx serves the static Hugo site and passes `/api/` requests to PHP-FPM.

---

## 1 · Install PostgreSQL

```bash
sudo apt update
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable --now postgresql
```

---

## 2 · Create the database and user

```bash
sudo -u postgres psql
```

Inside the psql shell:

```sql
CREATE USER freedoms4_user WITH PASSWORD 'CHANGE_THIS_PASSWORD';
CREATE DATABASE freedoms4 OWNER freedoms4_user;
\c freedoms4

CREATE TABLE users (
    id            BIGSERIAL     PRIMARY KEY,
    username      VARCHAR(32)   NOT NULL UNIQUE,
    email         VARCHAR(254)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    created_at    TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_users_username ON users (username);
CREATE INDEX idx_users_email    ON users (email);

-- Optional: allow only this user to access the table
REVOKE ALL ON TABLE users FROM PUBLIC;
GRANT SELECT, INSERT ON TABLE users TO freedoms4_user;
GRANT USAGE, SELECT ON SEQUENCE users_id_seq TO freedoms4_user;

\q
```

> **Important:** use the same password you set in `DB_PASS` inside `auth.php`.

---

## 3 · Install the PHP PostgreSQL extension

```bash
# Find your PHP version first:
php -v

# Install the pgsql extension (replace 8.x with your version, e.g. 8.3):
sudo apt install -y php8.3-pgsql

# Restart PHP-FPM (replace 8.3 with your version):
sudo systemctl restart php8.3-fpm
```

Verify it loaded:

```bash
php -m | grep pgsql   # should print: pgsql
```

---

## 4 · Deploy the PHP file

```bash
sudo mkdir -p /var/www/freedoms4/api
sudo cp /path/to/auth.php /var/www/freedoms4/api/auth.php
sudo chown -R www-data:www-data /var/www/freedoms4/api
sudo chmod 640 /var/www/freedoms4/api/auth.php
```

Edit the config constants at the top of `auth.php`:

```php
define('DB_PASS', 'CHANGE_THIS_PASSWORD');  // ← your actual password
```

---

## 5 · Configure Nginx

Open your site config (e.g. `/etc/nginx/sites-available/freedoms4`):

```nginx
server {
    listen 443 ssl http2;
    server_name freedoms4.org www.freedoms4.org;

    root /var/www/freedoms4;
    index index.html;

    # ── Static Hugo files ───────────────────────────────────────────────
    location / {
        try_files $uri $uri/ $uri/index.html =404;
    }

    # ── PHP API ─────────────────────────────────────────────────────────
    location /api/ {
        # Only allow POST (OPTIONS for CORS preflight)
        limit_except POST OPTIONS {
            deny all;
        }

        # Pass to PHP-FPM (adjust socket path to match your PHP version)
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO       $fastcgi_path_info;
    }

    # ── Block direct access to .php files outside /api/ ─────────────────
    location ~* \.php$ {
        deny all;
    }

    # SSL certs (already configured, adjust paths if needed)
    ssl_certificate     /etc/letsencrypt/live/freedoms4.org/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/freedoms4.org/privkey.pem;
}
```

Test and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6 · Deploy the Hugo frontend changes

In your local Hugo project, apply the three file changes from the delivery package, then rebuild and sync:

```bash
# From inside the freedoms4 project directory:
hugo --minify

# Sync to server (adjust user/host):
rsync -avz --delete docs/ user@your-vps:/var/www/freedoms4/
```

Or if you use git+CI, commit and push; your pipeline handles the rest.

---

## 7 · Test

```bash
# Sign up
curl -s -X POST https://freedoms4.org/api/auth.php \
  -H 'Content-Type: application/json' \
  -d '{"action":"signup","username":"testuser","email":"test@example.com","password":"hunter2hunter2"}' | jq .

# Log in
curl -s -X POST https://freedoms4.org/api/auth.php \
  -H 'Content-Type: application/json' \
  -d '{"action":"login","username":"testuser","password":"hunter2hunter2"}' | jq .
```

Both should return `{"success":true, ...}`.

---

## Security notes

- All passwords are stored as bcrypt hashes (cost 12). Plain-text passwords are never written to disk or logs.
- Session cookies are `HttpOnly`, `Secure`, and `SameSite=Strict`.
- A simple per-IP rate limit (20 requests per 15 min) is enforced server-side via PHP sessions.
- For production, consider adding `fail2ban` rules on your Nginx access log to block repeated 429s at the firewall level.
- Keep `SESSION_SECURE = true` (requires HTTPS, which you already have).
