# ERP Base — PHP Port

A plain-PHP (no framework) ERP base with a vanilla JS + HTML + CSS frontend — no Node.js, no build step, no frontend libraries.

| Layer | Technology |
|---|---|
| Router | `nikic/fast-route` |
| Database | PDO + plain SQL migrations (SQLite default) |
| Auth | `firebase/php-jwt` (HS256) + PHP `password_hash(ARGON2ID)` |
| API keys | HMAC-SHA256 (`hash_hmac`) — timing-safe verify |
| Crypto | AES-256-GCM (`openssl_encrypt`) |
| Email | `phpmailer/phpmailer` |
| Push | `minishlink/web-push` (VAPID) + FCM legacy fallback |
| PDF | `dompdf/dompdf` |
| Payments | `stripe/stripe-php` (stubbed if key not set) |
| Frontend | Vanilla HTML + CSS + JS (served by the same PHP server) |

---

## Quick start

```bash
cd erp_base_php

# 1. Install PHP dependencies (requires PHP ≥ 8.2 + Composer)
composer install

# 2. Copy and edit environment variables
cp .env.example .env
# Edit .env — fill in JWT_SECRET, API_KEY_PEPPER, ENCRYPTION_KEY at minimum

# 3. Run migrations (creates all 11 tables)
php database/migrate.php

# 4. Seed admin user
php database/seed.php
# Default: admin@example.com / admin123456

# 5. Start the built-in PHP development server
php -S localhost:8000 -t public/
# Open http://localhost:8000
```

That's it — no `npm install`, no separate dev server, no proxy.

---

## Project structure

```
erp_base_php/
├── public/
│   ├── index.php              # Front controller + SPA fallback
│   ├── index.html             # SPA shell
│   ├── app.js                 # Vanilla JS SPA (router, API client, all pages)
│   └── styles.css             # Vanilla CSS
├── src/
│   ├── Config.php             # Typed env reader
│   ├── Db/
│   │   └── Database.php       # PDO singleton
│   ├── Routes/
│   │   └── api.php            # All route registrations
│   ├── Middleware/
│   │   ├── AuthenticateUser.php
│   │   ├── AuthenticateProjectAccess.php
│   │   └── RequireScope.php
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── ProjectController.php
│   │   ├── ApiKeyController.php
│   │   ├── TreeDataController.php
│   │   ├── AdminController.php
│   │   ├── ContactController.php
│   │   ├── PaymentController.php
│   │   ├── PdfJobController.php
│   │   ├── PushController.php
│   │   ├── DataStoreController.php
│   │   └── EmailConfigController.php
│   ├── Models/                # Thin PDO wrappers (no ORM)
│   └── Services/              # JwtService, ApiKeyService, …
├── database/
│   ├── migrations/            # 001_users.sql … 011_audit_logs.sql
│   ├── migrate.php            # Migration runner
│   └── seed.php               # Admin user seeder
├── uploads/                   # Generated PDFs
├── .env.example
├── .htaccess                  # Apache rewrite rule
└── composer.json
```

---

## API endpoints (36 total)

All routes are prefix-free. The vanilla JS client calls them at the same origin (e.g. `fetch('/auth/login', …)`).

| Method | Path | Auth |
|--------|------|------|
| POST | `/auth/register` | Public |
| POST | `/auth/login` | Public |
| GET | `/me` | JWT |
| PUT | `/me/password` | JWT |
| GET | `/projects` | JWT |
| POST | `/projects` | JWT |
| GET | `/projects/:id` | JWT |
| PATCH | `/projects/:id` | JWT |
| DELETE | `/projects/:id` | JWT |
| GET | `/projects/:id/api-keys` | JWT |
| POST | `/projects/:id/api-keys` | JWT |
| POST | `/projects/:id/api-keys/:kid/revoke` | JWT |
| GET | `/projects/:id/data` | JWT or API key |
| PUT | `/projects/:id/data` | JWT or API key (write scope) |
| PATCH | `/projects/:id/data` | JWT or API key (write scope) |
| DELETE | `/projects/:id/data` | JWT or API key (write scope) |
| GET | `/admin/stats` | Admin JWT |
| GET | `/admin/users` | Admin JWT |
| GET | `/admin/projects` | Admin JWT |
| GET | `/admin/projects/:id/key-stats` | Admin JWT |
| GET | `/projects/:id/contacts` | JWT or API key |
| POST | `/projects/:id/contacts` | JWT or API key (write) |
| DELETE | `/projects/:id/contacts/:cid` | JWT or API key (write) |
| GET | `/projects/:id/payments` | JWT or API key |
| POST | `/projects/:id/payments/intent` | JWT or API key (write) |
| GET | `/projects/:id/pdf/jobs` | JWT or API key |
| POST | `/projects/:id/pdf/jobs` | JWT or API key (write) |
| GET | `/projects/:id/push/config` | JWT or API key |
| PUT | `/projects/:id/push/config` | JWT or API key (write) |
| GET | `/projects/:id/push/subscriptions` | JWT or API key |
| POST | `/projects/:id/push/subscribe` | JWT or API key (write) |
| DELETE | `/projects/:id/push/subscribe` | JWT or API key (write) |
| POST | `/projects/:id/push/test` | JWT or API key |
| GET | `/projects/:id/db/config` | JWT or API key |
| PUT | `/projects/:id/db/config` | JWT or API key (write) |
| POST | `/projects/:id/db/test` | JWT or API key |
| GET | `/projects/:id/email/config` | JWT or API key |
| PUT | `/projects/:id/email/config` | JWT or API key (write) |
| POST | `/projects/:id/email/test` | JWT or API key |
| POST | `/projects/:id/email/send` | JWT or API key (write) |

---

## Production / Apache deployment

### Option A — project folder dropped into `htdocs/` (XAMPP / WAMP / any shared host)

Just copy the project folder into your `htdocs` (or `www`) directory. No extra config needed.

```
htdocs/
└── erp_base_php/   ← put the project here
    ├── public/
    ├── src/
    ├── .htaccess
    └── ...
```

- Enable `mod_rewrite` and set `AllowOverride All` for the directory.
- Open `http://localhost/erp_base_php/` — the app auto-detects the sub-folder.

### Option B — dedicated virtual host / DocumentRoot

Point `DocumentRoot` to the project root:

```apache
<VirtualHost *:80>
    DocumentRoot /path/to/erp_base_php
    <Directory /path/to/erp_base_php>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Open `http://localhost/`.

### nginx

```nginx
root /path/to/erp_base_php;

location / {
    try_files $uri $uri/ /public/index.php$is_args$args;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### Switch to PostgreSQL
Change `DATABASE_URL` in `.env`:
```
DATABASE_URL=pgsql:host=localhost;port=5432;dbname=erp_base;user=erp;password=secret
```
Then re-run `php database/migrate.php`.

---

## Differences from the Node.js version

| Feature | Node.js | PHP port |
|---|---|---|
| ORM | Prisma (schema-first) | Plain SQL migrations + PDO |
| WebSocket / Socket.IO | Supported | Not included (Phase 2) |
| Tree store backends | 6 drivers | `sql_json` only (Phase 2 for others) |
| Test suite | Vitest integration tests | Not included (Phase 2) |
