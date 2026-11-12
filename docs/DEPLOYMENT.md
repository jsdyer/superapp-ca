# Running Superapp on superapp.ca

Target: Linux, Apache 2.4, MariaDB 10.11+ (tested on 11.8.9), PHP 8.3+ (tested on 8.3 in CI and 8.4.27 locally) with PDO/pdo_mysql. SQLite is provided for local trials and automated tests. No npm or Composer build is necessary.

## Native LAMP

1. Copy this app directory to `/var/www/superapp`. Keep the **document root at `/var/www/superapp/public`**, never the repository root. Install your distribution’s Apache PHP module or PHP-FPM integration and PDO MySQL extension. Enable `rewrite`, `headers`, and `ssl`; use your distribution’s supported PHP package stream.
2. Create a UTF-8 database and a dedicated application account. Give it SELECT, INSERT, UPDATE and DELETE for runtime. Use a deployment account with CREATE/INDEX privileges to run the initial migration; remove those elevated privileges afterwards if using the same account for setup. Do not use the MariaDB root account in the app.
3. Copy `config/config.example.php` to `config/local.php`, fill in the DSN, database user and password, and set `app_url` to `https://superapp.ca`. Make the file readable by your PHP process but not other local users (for example, owner `root`, group `www-data`, mode `0640`). These values are server-side only.
4. Make only `var/` writable by the web process. For Debian-style hosts: `chown -R www-data:www-data /var/www/superapp/var` and `chmod 0750 /var/www/superapp/var`. Session files are created in `var/sessions` with directory mode `0700`. If CLI and web commands use different users, plan ownership accordingly; run app CLI maintenance as the web service user.
5. Run `php bin/console.php migrate`, then `php bin/console.php create-admin YOUR_HANDLE YOUR_EMAIL`. No default admin or password is shipped. Create other pilot members with `create-user` while registration is closed.
6. Configure your DNS A/AAAA records for the chosen server, issue a TLS certificate, and install `deploy/apache-native.conf` with correct paths. Do not copy its TLS virtual host before certificate files exist. Test Apache configuration before reloading. If using PHP-FPM, add your distribution’s PHP handler and ensure `Authorization` reaches PHP (`CGIPassAuth On` is included).
7. Keep `session_secure=true`, `demo=false`, and initially `registration_open=false`. Set a real HTTPS `contact_url`, accurate `hosting_note`, and optional HTTPS `donation_url`. The shipped contact/hosting wording is intentionally incomplete until the operator configures it. After operational review, set registration open if desired.

The app expects installation at an origin root, not in a URL subdirectory. `app_url` must match the external HTTPS origin; feeds and displayed share links use it. Do not infer an origin from an arbitrary Host header.

## Docker Compose

The bundled alternative uses the official PHP Apache and MariaDB images. Docker itself is optional.

```bash
cp .env.example .env
# Edit .env: unique DB_PASSWORD and DB_ROOT_PASSWORD are required.
docker compose up --build -d
docker compose exec --user www-data web php bin/console.php migrate
docker compose exec --user www-data web php bin/console.php create-admin YOUR_HANDLE YOUR_EMAIL
```

The application binds to `127.0.0.1:8080`; the database has no published port. Access locally or put your existing reverse proxy in front of that loopback port. For external HTTPS set `APP_URL=https://superapp.ca` and `SESSION_SECURE=true`, then recreate the web container. The Compose defaults support a local HTTP trial only. Production registration remains closed until you set `REGISTRATION_OPEN=true`. Persist both named volumes. Never run `docker compose down -v` unless intentionally destroying all instance data.

The Docker definition has been reviewed but was not built in the development environment; native PHP/SQLite was exercised locally and both MariaDB and SQLite passed the domain and HTTP suites in CI. Test your image build and reverse-proxy configuration on staging before public launch.

## Operator configuration

Every key in `config/config.example.php` can be overridden with `SUPERAPP_` plus its uppercase name. For example, `SUPERAPP_REGISTRATION_OPEN=false`. Environment settings take precedence over `config/local.php`. The Compose file maps its `.env` names into those variables. Booleans accept `true`/`false`.

`hosting_note` should identify the operator, application/database region, backup region, and relevant third-party processors. A .ca domain is not proof of Canadian data residency. Set `source_url` if this is deployed from a fork.

To accept voluntary support, configure an HTTPS hosted payment page offering donor-chosen **CAD** amounts. Review recurrence and currency in the provider. This app only links to that page: it does not charge cards, receive webhooks, track donations, issue receipts, or promise tax deductibility. Set `donation_url` empty to disable it. No feature gates depend on payments.

## Maintenance, backup and recovery

- Run `php bin/console.php cleanup` daily as the web service user; this deletes expired rate-limit buckets. PHP session garbage collection or your distribution’s session cleanup must handle `var/sessions`. A cron example is `17 3 * * * cd /var/www/superapp && /usr/bin/php bin/console.php cleanup` under that user.
- Back up MariaDB with `mariadb-dump --single-transaction --default-character-set=utf8mb4` using a mode-0600 options file or your backup tool’s secret mechanism. Back up `config/local.php` separately and encrypt it. Do not commit backups. Session backups are not necessary for recovery.
- Restore into a new staging database and verify account login, private records, the directory and exports before changing production. User JSON export is not a full database backup: moderation state and credentials are deliberately omitted, and import restores entries only.
- Publish a retention period for database backups and access logs. Deleting an account removes live rows but cannot erase already-copied backups until rotation. Rotate server logs and keep their access restricted.
- Use `php bin/console.php reset-password HANDLE` for manually verified recovery. It revokes all sessions and API tokens. Verify the requester through an established channel; email verification and self-service reset are not implemented.
- Keep code, PHP, Apache, MariaDB and container bases patched. The migration command creates missing base, community and customization tables; it is not a general schema upgrade framework. Future releases need explicit incremental migrations and backup instructions.

## Before public registration

Publish actual operator contact, service-specific terms, hosting/backup details and retention periods. Exercise account recovery, backups, restoration, directory moderation and abuse reports. Add front-proxy request limits and a trusted proxy configuration suited to your network. PHP only uses `REMOTE_ADDR` for rate limits and does not trust arbitrary forwarding headers. If you use a reverse proxy, configure Apache’s trusted `mod_remoteip` boundary explicitly; otherwise the shared proxy IP can rate-limit all members together.

Current application limits: 10,000 entries per member, 60,000 bytes per full-text entry, 1,000 bytes per status, 2 MB per request/import and 500 imported entries per batch. Garden and directory views show at most 100 records; commons and workspace paginate by 25. This first release is designed for a small community pilot. Add bounded pagination to the remaining moderation/directory/profile views, quotas for all shared resources, anti-spam operations and email verification before scaling up.


## Upgrading from the first Superapp release

1. Close registration and put the instance behind a maintenance page while upgrading. Take a database backup and retain the previous code release.
2. Pull the new code, including `database/community.sql` and `database/customization.sql`.
3. From the repository root, with your normal application database configuration, run `php bin/console.php migrate`. This adds the community tables without modifying or deleting existing rows. It can be rerun safely on SQLite and MariaDB.
4. Verify sign-in, `/admin/features`, `/customize`, `/pocket`, `/start`, `/workbench`, `/rings`, `/funding`, the directory, and an account export. Existing users start with empty workspaces for the new tools. Reopen the instance after verification.
5. For rollback, restore the previous code and retain the additive tables; it ignores them. A database restore is only necessary if you need to revert newly entered data. Back up first to avoid losing member work.

New limits: 100 shortcuts and 100 projects per member; 500 updates per project; 10 owned webrings per member; 500 membership requests/members per ring. Projects/rings discovery and ring memberships are bounded lists, not full pagination. Directory evidence currently adds per-listing queries; tune indexes/batch loading before growing beyond the pilot. Quotas are application checks, not concurrency-tested hard resource ceilings.

Financial reporting is available at `/funding`. Administrators enter aggregate monthly CAD figures manually; no webhook or payment reconciliation is implied. Keep donor names, receipt details and payment identifiers out of the public notes. There is no immutable revision log. Preserve actual accounting records outside the app.

Exports include the new member records. The importer remains entry-only; do not use an account JSON export as your sole backup. See [Community tools](COMMUNITY.md) for privacy and deletion rules.

## Customization upgrade

Back up the database and deploy the new code during maintenance, then run `php bin/console.php migrate` before serving requests. The additive `database/customization.sql` creates instance settings, member preferences and private pocket tasks; existing content is retained. All 23 optional features start enabled. Sign in as an administrator and open `/admin/features` to choose your launch scope, default theme and welcome message. See [Customization](CUSTOMIZATION.md) for configuration precedence, safe core routes, and member controls. Existing members keep their theme; new members receive the instance default and an optional welcome flow. Pocket lists are limited to 200 tasks per member.
