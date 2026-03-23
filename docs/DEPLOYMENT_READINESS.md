# Deployment Readiness Checklist

This checklist is for preparing the completed MVP for deployment. It is intentionally practical and keeps UAT/demo seeding separate from production rollout.

## Environment Variables

Confirm these values before deployment:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` points to the real application URL
- `APP_KEY` is generated and not shared across environments
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `FILESYSTEM_DISK` matches the intended deployment storage strategy
- `TEMPLATE_UPLOAD_DISK` is set if template uploads should use a non-default disk
- `SF9_EXPORT_DISK` is set if SF9 exports should use a non-default disk
- `SF10_EXPORT_DISK` is set if SF10 exports should use a non-default disk
- `AUTH_ALLOW_SELF_REGISTRATION=false` unless the product explicitly enables public registration
- `QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` match the target environment

## Database and Cache Preparation

- Run migrations:

```bash
php artisan migrate --force
```

- Seed only the production-safe base data:

```bash
php artisan db:seed --force
```

- Do not run `Database\\Seeders\\UatDemoSeeder` in production.
- Because cache, session, and queue defaults are database-backed in this repo, make sure the Laravel cache/jobs/session tables are migrated in every environment.

## Storage and File Permissions

- Ensure `storage/` and `bootstrap/cache/` are writable by the web server and queue worker user
- Create the public symlink when serving public assets or downloads from the `public` disk:

```bash
php artisan storage:link
```

- Verify the application can write to private/local export directories used by:
  - template uploads
  - SF1 import source files
  - grading-sheet exports
  - SF9 exports
  - SF10 exports

## Template Upload Prerequisites

- Upload real validated template files for:
  - grading sheet
  - SF9
  - SF10
- Confirm the active template per scope is mapped completely before users begin export workflows
- If you are using the UAT dataset locally, note that it seeds real workbook files only for the seeded demo environment

## Queue, Session, and Cache Notes

- The current MVP does not depend on a mandatory queued export pipeline to function
- Even so, if you keep `QUEUE_CONNECTION=database`, be prepared to run a worker for any queued tasks introduced later:

```bash
php artisan queue:work
```

- With database-backed sessions and cache, confirm connection stability and table health under load

## Frontend and Build Steps

- Install frontend dependencies with `npm install` or `npm ci`
- Build production assets:

```bash
npm run build
```

- Confirm Vite build artifacts are deployed with the release

## Production Safety Checks

- Verify role/permission seed data exists before first login
- Keep public self-registration disabled unless formally approved
- Confirm storage disks and download paths do not expose private files directly
- Review uploaded template handling and file permissions on the target server
- Verify backups exist before applying production migrations
- Run a smoke test for:
  - admin login
  - teacher grade entry page access
  - adviser review access
  - registrar finalized-record visibility
  - template upload
  - grading-sheet, SF9, and SF10 export generation

## Recommended Release Order

1. Put the app in maintenance mode if needed
2. Deploy code and install dependencies
3. Run migrations
4. Seed base reference data
5. Build or publish frontend assets
6. Refresh caches if desired:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Upload/verify active templates
8. Run smoke tests
9. Disable maintenance mode

## UAT to Production Handoff Reminder

- UAT accounts, demo records, and demo export history are for local or staging verification only
- Production should start from production-safe seed data plus real user provisioning and real template uploads
