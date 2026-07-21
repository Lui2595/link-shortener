# LPshortener

URL shortener built with **Laravel + React (Inertia)** for the Spot2 FullStack challenge.

- Passwordless **OTP login** (email code)
- Short codes of **8 readable characters** (no ambiguous `0/O/1/I/l`)
- REST API + **Swagger UI** at `/api/documentation`
- Feature/unit tests for auth, CRUD and redirect
- **MySQL** as primary database

## Quick start (local)

Requirements: PHP 8.3+, Composer, Node 20+, MySQL, SQLite extension (for tests).

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure MySQL in .env:
# DB_CONNECTION=mysql
# DB_DATABASE=shortener
# DB_USERNAME=root
# DB_PASSWORD=...

php artisan migrate --seed
npm install
npm run build

php artisan serve
# optional: npm run dev
```

App: [http://localhost:8000](http://localhost:8000)  
Swagger: [http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)

OTP emails go to `storage/logs/laravel.log` when `MAIL_MAILER=log`.

### Demo seed

- User: `demo@lpshortener.test`
- Sample short code: `/demosp2t` → `https://example.com`

## User flow

1. **Home** — paste a long URL and click *Acortar*
2. If not logged in → enter email → receive OTP → verify
3. Pending URL is saved and you land on the **panel**
4. Panel: list / visit / edit / delete
5. **`/{code}`** — “Wait a moment…” then redirect (or JSON / `?direct=1` for 302)

## API

Base path: `/api` (session + CSRF, same origin). Send `X-XSRF-TOKEN` / `X-CSRF-TOKEN` on mutating requests from the SPA.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/otp/request` | Create user if needed + send OTP |
| POST | `/api/auth/otp/verify` | Validate OTP and start session |
| POST | `/api/auth/logout` | End session |
| GET | `/api/auth/me` | Current user |
| GET | `/api/urls` | List own short URLs |
| POST | `/api/urls` | Create `{ "original_url": "https://..." }` |
| GET | `/api/urls/{id}` | Show |
| PUT | `/api/urls/{id}` | Update destination |
| DELETE | `/api/urls/{id}` | Delete |

Redirect:

- `GET /{code}` → Inertia wait page
- `GET /{code}?direct=1` → HTTP 302
- `GET /{code}` with `Accept: application/json` → `{ original_url, code }`

```bash
php artisan l5-swagger:generate
php artisan swagger:publish-ui
```

On some hosts, nginx returns 404 for Laravel-served Swagger UI `.js`/`.css` routes. Run `php artisan swagger:publish-ui` to copy assets into `public/vendor/swagger-ui` (never under `public/docs` — that shadows the `/docs` JSON endpoint).
## Tests

Tests use SQLite in-memory (`phpunit.xml`) so they stay fast and isolated from your MySQL data.

```bash
php artisan test
```

## Design decisions

### MySQL

Relational model (user → many urls), unique index on `code`, transactional writes. Good fit for this workload and easy to run on XAMPP / AWS RDS.

### Secure non-sequential codes

`ShortCodeGenerator` uses `random_int` over a 31-char unambiguous alphabet, length 8, retries on collision + unique DB index.

### Cache

Redirect lookups use `Cache::remember("short_url:{code}", 1h)`. Invalidate on update/delete. At scale: Redis + CDN.

### Security

- Eloquent → SQL injection protection
- CSRF on web/Inertia + `X-CSRF-TOKEN` for API fetches
- XSS mitigated by React escaping
- OTP hashed at rest, rate-limited, single-use, 10 min TTL

## Production (AWS free tier sketch)

1. EC2 / Lightsail — PHP-FPM + Nginx  
2. RDS MySQL  
3. SES for OTP mail  
4. Redis for cache/sessions in production  

## CI / CD

### GitHub Actions

`.github/workflows/main.yml` runs tests on push/PR to `main`.

### Deploy webhook (server-side)

`POST /api/deploy` with header `X-Deploy-Secret: <DEPLOY_SECRET>` (CSRF-exempt, throttled):

1. `git pull origin <DEPLOY_BRANCH>`
2. Clear cached config (`config:cache` would keep `APP_ENV=production` and break tests with CSRF 419)
3. Run challenge tests via PHPUnit (SQLite in-memory)
4. `npm ci` → Vite build (`node node_modules/vite/bin/vite.js build`)
5. `php artisan l5-swagger:generate` + `swagger:publish-ui` (assets in `public/vendor/swagger-ui` for nginx)
6. If tests, install, build, or Swagger fail → `git reset --hard` to the previous commit

```bash
# .env
DEPLOY_SECRET=a-long-random-string
DEPLOY_BRANCH=main
# Optional — set if auto-detect fails under php-fpm:
# DEPLOY_PHP_BINARY=/usr/bin/php8.4
# DEPLOY_NODE_BINARY=/usr/bin/node
```

```bash
curl -X POST https://tu-dominio.com/api/deploy \
  -H "X-Deploy-Secret: $DEPLOY_SECRET" \
  -H "Accept: application/json"
```

Server requirements: `git`, PHP CLI (+ SQLite extension), `node`, `npm`, write access to the repo, and PHP/nginx timeouts long enough (pull + tests + `npm ci` + build can take several minutes). Avoid leaving a stale `bootstrap/cache/config.php` from production `config:cache` without re-running deploy (the webhook clears it before tests).

**Note:** this is a simple webhook for demos/small VPS. Prefer GitHub Actions → SSH deploy for production hardening (IP allowlist, deploy keys, zero-downtime).
