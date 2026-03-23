# School Grading Workflow System

Laravel 13 MVP for a role-based school grading workflow built with Blade, Livewire 4, Tailwind CSS, Spatie Laravel Permission, and Laravel Excel.

## Stack

- Laravel 13
- PHP 8.3+
- Blade
- Livewire 4
- Tailwind CSS
- MySQL 8+ for deployment targets
- PHPUnit 12
- Vite

## Local Setup

1. Install dependencies.

```bash
composer install
npm install
```

2. Prepare the environment.

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure your database and storage-related environment values in `.env`.

4. Run migrations and base reference data.

```bash
php artisan migrate --seed
```

5. Start the app.

```bash
npm run dev
php artisan serve
```

## Test Execution

Run the full PHPUnit suite:

```bash
php vendor/bin/phpunit --do-not-cache-result
```

Run a focused test file:

```bash
php vendor/bin/phpunit --do-not-cache-result --filter UatDemoSeederTest
```

## UAT Demo Data

The UAT/demo package is explicit by design and is not wired into the default production seeding path.

Seed a fresh local UAT database:

```bash
php artisan migrate:fresh --seed --seeder=Database\\Seeders\\UatDemoSeeder
```

Seed UAT data onto an already migrated local database:

```bash
php artisan db:seed --class=Database\\Seeders\\UatDemoSeeder
```

Do not run `UatDemoSeeder` in production.

## UAT Login Accounts

All seeded UAT accounts use the password `password`.

| Role | Name | Email | Notes |
| --- | --- | --- | --- |
| Admin | Ava Administrator | `admin.uat@example.test` | Full admin walkthrough account |
| Teacher | Tomas Teacher | `teacher.uat@example.test` | Primary teacher account for grade entry and grading-sheet export |
| Teacher | Nina Support Teacher | `teacher.support@example.test` | Additional teacher account for returned and locked workflow states |
| Adviser | Alicia Adviser | `adviser.uat@example.test` | Primary adviser account for Narra and Acacia walkthroughs |
| Adviser | Miguel Support Adviser | `adviser.support@example.test` | Additional adviser account for completed-section monitoring data |
| Registrar | Rina Registrar | `registrar.uat@example.test` | Read-only final-records verification account |

## Seeded UAT Highlights

- Current active school year: `2025-2026`
- Open grading period: `Q2`
- Current advisory sections:
  - `Grade 7 - Narra` with draft, submitted, returned, reopened, locked, approved, and missing subject states
  - `Grade 7 - Molave` as a completed section with finalized SF9 records
- Previous-year archive:
  - `Grade 6 - Acacia` with finalized SF10 records
- Learner movement exceptions included:
  - transferred-out learner with an effective date
  - dropped learner with remarks

## Roles

- `admin`: setup, assignments, imports, monitoring, templates, lock/reopen, audit visibility
- `teacher`: own teaching loads, roster views, grade entry, grading-sheet preview/export
- `adviser`: advisory review, consolidation, SF9, year-end learner status, SF10 preparation/finalization
- `registrar`: read-only finalized-record repository and verification

## Docs

- UAT checklist: [docs/UAT_CHECKLIST.md](docs/UAT_CHECKLIST.md)
- Deployment readiness: [docs/DEPLOYMENT_READINESS.md](docs/DEPLOYMENT_READINESS.md)

## Production Safety Notes

- Keep `AUTH_ALLOW_SELF_REGISTRATION=false` unless the product explicitly enables it.
- Upload real grading-sheet, SF9, and SF10 templates before production use.
- Use the default base seeders in production, not the UAT/demo seeder.
