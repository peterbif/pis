# Personnel Information System

University College Hospital, Ibadan · Laravel 12 · Vue 3 · Vuetify · Pinia

The system provides a personnel self-service portal and an HR administration workspace. The interface uses the supplied UCH crest and a green visual identity. The personnel form covers appointment/payroll, personal details, contact and addresses, next of kin, repeatable education, job categories, bank details and private documents. Admin-managed lookup lists supply the selectable values.

## Requirements

- PHP 8.2 or later with the PDO driver for the selected database, OpenSSL, Mbstring, XML, Ctype, Fileinfo, GD, ZIP and BCMath extensions
- Composer 2
- Node.js 20 or later and npm
- MySQL 8+ (SQLite can be used for local development when PHP has `pdo_sqlite` installed)
- An SMTP mail transport or other Laravel mail driver for email verification

## Local setup

1. Copy `.env.example` to `.env`. Set unique administrator passwords and configure database and mail values.
2. Run `composer install` and `php artisan key:generate`.
3. Create the MySQL database and least-privilege account, put those values into `.env`, then run `php artisan migrate --seed`.
4. Run `npm ci && npm run build`.
5. Start with `php artisan serve`. Configure `MAIL_MAILER=log` to inspect local OTP messages in `storage/logs/laravel.log`, or use a development SMTP service.

For a local MySQL instance, create the schema and account with a privileged MySQL user, then use the resulting account in `.env`:

```sql
CREATE DATABASE personnel_information_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pis_app'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-unique-password';
GRANT ALL PRIVILEGES ON personnel_information_system.* TO 'pis_app'@'127.0.0.1';
```

`php artisan migrate --seed` creates the initial Superadmin (`superadmin@personnel.local`) and Admin (`admin@personnel.local`) accounts. The local passwords are `ChangeThisSuperAdmin!2026` and `ChangeThisAdmin!2026`; you can override them with `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in `.env`. Change these local defaults before exposing the app to a network. Production seeding refuses these defaults and requires unique administrator passwords of at least 14 characters.

## Production deployment

- Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, correct `APP_URL`, and HTTPS at the edge. Set `SESSION_SECURE_COOKIE=true` and `SESSION_DOMAIN` appropriately. Add the production hostname to `SANCTUM_STATEFUL_DOMAINS` (host and port only, comma-separated).
- Use a supported production database; configure the database-backed session store and run `php artisan migrate --force`. Back up and test recovery of the database and private file storage.
- Keep `storage/` and `bootstrap/cache/` writable by the PHP service. Never expose `.env`, source files, logs, or the private storage directory from the web root. Serve only `public/`.
- Build static assets with `npm ci && npm run build`; deploy `public/build/manifest.json` and its assets with the release. Run `php artisan optimize` as part of release automation and `php artisan optimize:clear` when diagnosing stale cached configuration.
- Configure private real-time chat using the Reverb variables in `.env.example`. For TLS proxy, allowed-origin, and Supervisor setup, follow [deploy/README-reverb.md](deploy/README-reverb.md); set the production WebSocket endpoint before building the Vite assets.
- Configure a real mail transport and a queue worker if mail delivery is moved to an asynchronous queue. Set an appropriate backup/retention policy for personnel data and uploaded documents.
- Use a dedicated non-root database account, least-privilege filesystem permissions, HTTPS, database encryption/backups, monitoring, and a documented key-rotation/recovery process. Set request size limits at the web server and PHP layers as well as in Laravel.

Documents are stored outside the public web root, encrypted with the Laravel application key, and downloaded through an authenticated owner/admin endpoint. Keep the key protected and backed up: encrypted uploads cannot be recovered without the matching key. Excel exports include profile fields, including flattened education entries, but omit uploaded files. Export is protected by administrator authorization and marked no-store.

## Authentication and authorization

Personnel signup is invitation-only. An admin imports a CSV containing one approved email per row or adds an address individually. Signup checks the email against that unused invitation, requires a confirmed 12-character minimum password, sends a time-limited six-digit email code, and limits verification attempts. A successful code marks the email verified and creates the account; the person then signs in with that email and password before filling the forms. Login uses Laravel's stateful Sanctum session cookies, CSRF protection and throttling; no bearer token is stored in browser local storage. Validation responses include field-level errors in plain language. Admin routes check server-side permissions. Superadmins can create additional admins and grant or change their access to dashboard, personnel records, review, Excel export, invitations and form options.

Personnel can add a missing value directly beside a dropdown (or job-category list). The server checks the field category, adds the option to the shared list, refreshes the choices and selects it for that employee automatically. Admins can also add options one at a time or bulk import a CSV with `category,label,parent` columns.

## Main routes

- `GET /api/lookups` — active form choices
- `POST /api/login`, `POST /api/logout`, `GET /api/user`
- `POST /api/signup/check-email`, `POST /api/signup`, `POST /api/signup/verify`
- `GET/PUT /api/personnel/profile`, `POST /api/personnel/documents`, `GET /api/personnel/documents/{document}`
- `GET /api/admin/dashboard`, `GET /api/admin/personnel` and `GET /api/admin/personnel/export`
- `PATCH /api/admin/personnel/{profile}/status`
- `POST /api/admin/invitations`, `POST /api/admin/import` (CSV, maximum 10,000 rows)
- `GET/POST/PUT/DELETE /api/admin/lookups...`, `POST /api/admin/lookups/import` (`category,label,parent` CSV)
- `GET /api/chat`, `POST /api/chat/messages` — authenticated personnel/admin chat with private Reverb channels
- `POST /api/admin/users` — superadmin only
- `GET /api/admin/users`, `PATCH /api/admin/users/{user}/permissions` — superadmin only
- `POST /api/lookups` — authenticated personnel can add a supported dropdown option

The personnel directory supports name, email, IPPIS and file-number filters, pagination and Excel export. The XLSX export applies the same filters when supplied. Education supports up to 30 repeatable entries. Profile uploads are limited to 5 MB and approved MIME types.

## Operational notes

The app seeds Nigerian states and all 774 state-linked local government areas. LGA data is derived from the [Nigeria Administrative Divisions dataset](https://github.com/open-admin-data/nigeria-administrative-divisions), licensed [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/); attribution is retained here. Selecting a state filters its matching LGA choices, and the API validates the state/LGA relationship. HR should review geographical data and configure the actual institution values before onboarding staff; lookup administration supports adding, renaming, sorting, deactivating, and CSV bulk importing options (`category,label,parent`). The sample HR options are not an authoritative UCH personnel classification. Email domain, geographical lists, production branding/contact details and profile completeness rules should be confirmed with UCH HR before launch.
