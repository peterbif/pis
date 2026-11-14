# Production chat / WebSocket deployment

The chat uses Laravel Reverb for private WebSocket delivery. The message remains in MySQL if Reverb is briefly unavailable; the UI falls back to refreshing the active conversation every ten seconds until the socket reconnects.

## 1. Install and build

Run from the application root on the deployment host:

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
```

The migration creates `personnel_conversations` and `personnel_messages`. Keep the database backup and normal migration process used by the application.

## 2. Set production environment values

Use the public HTTPS hostname for Reverb and the matching allowed origin. Example for a dedicated `ws.example.org` hostname proxied to Reverb on loopback port 8080:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pis.example.org
SANCTUM_STATEFUL_DOMAINS=pis.example.org
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=pis-production
REVERB_APP_KEY=<unique-random-public-key>
REVERB_APP_SECRET=<unique-random-secret>
REVERB_ALLOWED_ORIGINS=pis.example.org

REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=ws.example.org
REVERB_PORT=443
REVERB_SCHEME=https

REVERB_CLIENT_HOST=ws.example.org
REVERB_CLIENT_PORT=443
REVERB_CLIENT_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_CLIENT_HOST}"
VITE_REVERB_PORT="${REVERB_CLIENT_PORT}"
VITE_REVERB_SCHEME="${REVERB_CLIENT_SCHEME}"
SESSION_SECURE_COOKIE=true
```

Generate a separate secret for each installation, keep `REVERB_APP_SECRET` private, and do not commit `.env`. The Reverb app key is visible in the compiled browser assets by design; the secret is never included in those assets. If you change any `VITE_REVERB_*` setting, rebuild the frontend with `npm run build`.

## 3. Reverse proxy and process manager

Configure DNS and a TLS certificate for the WebSocket hostname, then adapt `deploy/nginx/reverb-server.conf.example` to the server's certificate paths and include it in Nginx. It proxies both Reverb's `/app` WebSocket endpoint and `/apps` broadcast endpoint. Keep port 8080 private; expose only the TLS reverse proxy.

Install `deploy/supervisor/personnel-reverb.conf.example` after replacing the application path and PHP executable path. Then reload Supervisor and start `personnel-reverb`. Reverb is a long-running process and should be supervised. Merge the `minfds=10000` setting from `deploy/supervisor/minfds.conf.example` into the existing `[supervisord]` section; do not duplicate that section. Adjust the limit to the host's expected connection count and OS file limits. For larger deployments, size Nginx/open-file limits and add Reverb's Redis scaling configuration before running multiple Reverb instances.

After deployment:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan reverb:restart
```

Confirm the WebSocket hostname reaches Reverb over TLS, the app hostname is listed in `REVERB_ALLOWED_ORIGINS`, and the site CSP allows the configured `REVERB_CLIENT_HOST`/scheme/port. Private channel authorization is handled at `/broadcasting/auth` using the existing Laravel session and CSRF cookie. Only the owning personnel account and admin/superadmin accounts may subscribe to a personnel conversation.

Chat broadcasts use `ShouldBroadcastNow`, so they do not depend on a queue worker. The database write completes before broadcasting; temporary Reverb errors are logged and do not discard the saved message.
