<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Existing installation: company onboarding

The company-ownership migrations intentionally leave existing records unassigned. Login does not create a company or infer ownership. Do not use the free-text `organization` field to decide which tenant owns a record.

### Create the first platform super-admin

Only use this procedure on the verified local development database. Confirm `APP_ENV=local` and that the configured database is a local SQLite database or loopback MySQL/MariaDB database. The command refuses other environments and targets. It creates a new account only after an operator supplies a change/ticket identifier, enters the account details, chooses and confirms a unique password at the hidden prompt, and types the exact authorization phrase. It refuses to run if any super-admin already exists.

```bash
php artisan app:bootstrap-super-admin --operator=CHANGE-TICKET
```

Do not pass passwords as command-line arguments. The command does not promote an existing admin or use default credentials. The operator must independently verify that the new account belongs to the person authorized to administer the platform.

Sign in with that account and create the verified company through the super-admin-protected `POST /platform/companies` route, providing its approved `name` and unique `slug`. The endpoint returns its company ID. Company-management endpoints require an active authenticated `super_admin`.

### Prepare and apply a verified legacy mapping

First inspect each legacy row in an approved local-only review and obtain an explicit owner decision. Keep the mapping file outside version control and restrict its permissions. `records` explicitly assigns IDs to the selected company; `deferred` explicitly lists all other currently unassigned IDs reviewed but not assigned yet. Every currently unassigned record must appear in exactly one of these lists for its table, so an omitted ID fails validation rather than being silently ignored. Deferred records stay unassigned and cannot use tenant-only features. Attendance IDs are mapped explicitly, then validated against their users. Leave records are validated against a staff member and require any non-null `recorded_by` to be a target-company admin or super-admin.

Use this exact JSON shape (replace the sample values with the independently verified company ID, IDs, and operator/ticket; never infer IDs from `organization`). The empty deferred arrays are valid only if there are no other unassigned records of those types; otherwise list every reviewed, still-unassigned ID there:

```json
{
  "company_id": 123,
  "operator": "CHANGE-TICKET",
  "records": {
    "users": [11, 12],
    "office_locations": [3],
    "attendance_settings": [2],
    "attendance_holidays": [],
    "staff_invitations": [],
    "staff_leave_records": [8],
    "attendances": [21, 22]
  },
  "deferred": {
    "users": [],
    "office_locations": [],
    "attendance_settings": [],
    "attendance_holidays": [],
    "staff_invitations": [],
    "staff_leave_records": [],
    "attendances": []
  }
}
```

Before using the assignment command, apply its audit-table migration explicitly on the verified local database:

```bash
php artisan migrate --path=database/migrations/2026_10_24_120000_create_company_ownership_audits_table.php
```

Validate the mapping without changing records:

```bash
php artisan app:assign-legacy-company /secure/local/path/company-ownership-map.json
```

Review the dry-run counts and the mapping with the data owner. Only after approval, apply it with both explicit flags:

```bash
php artisan app:assign-legacy-company /secure/local/path/company-ownership-map.json \
  --apply \
  --confirm="ASSIGN LEGACY OWNERSHIP TO COMPANY 123"
```

Assignment only fills null `company_id` fields, runs transactionally, rejects missing, omitted, ambiguous, or conflicting IDs, and writes a minimal audit row (company, table, record ID, operator identifier, timestamp) for each changed row. Re-running an identical mapping is a no-op; a mapping cannot move records already assigned to another company. The command is local-only and does not run automatically during application startup or login. Existing passwords, roles, active status, record IDs, attendance fields, and leave fields are not rewritten.

After an approved assignment, verify the intended active company users can sign in, suspended companies remain blocked, and the attendance check-in/check-out flows work. Do not verify by creating production records or by changing an unreviewed mapping.

## Field operations

Field sessions and client visits are stored in `field_assignments` and
`client_visits`; they do not write to the office `attendances` table. Deploy the
additive field-operations migration through the normal reviewed migration
process. It has not been applied to a production database as part of this work.

The mobile app submits queued field events through `POST /api/v1/sync/challenge`
and the signed `POST /api/v1/sync/batch` endpoint. Employee views are available
at `GET /api/v1/field/active` and `GET /api/v1/dashboard/employee`. Company
administrators can use `GET /api/v1/admin/dashboard`,
`GET /api/v1/admin/field/on-field`, `GET /api/v1/admin/field/flagged`,
`POST /api/v1/admin/field/{fieldAssignment}/review`, and
`GET /api/v1/admin/attendance/missing`.

Expense claims are stored in `field_expenses` and belong to both the
submitting user and an existing field assignment. Authenticated staff submit
multipart form data to `POST /api/v1/field/expenses` with
`field_assignment_id`, `category` (`transport_fuel`, `meals_refreshments`,
`client_entertainment`, or `other_emergency`), and a positive `amount`.
`currency` defaults to and currently accepts `TZS`; `description`,
`client_uuid`, `is_offline`, and `receipt` are optional. Receipts must be JPEG,
PNG, or WEBP images no larger than 5 MB.

Staff can use `GET /api/v1/field/expenses` for their paginated history,
`GET /api/v1/field/expenses/{expense}` for an authorized claim, and
`GET /api/v1/field/expenses/{expense}/receipt` to download its receipt.
Only the claim owner or an admin/manager authorized by the existing
`admin-oversight` gate in the same company can view a claim or receipt.
Receipts are stored on the non-public `field-expenses-private` disk; the API
returns an authenticated endpoint path, never a public storage URL. Duplicate
`client_uuid` submissions by the owner return the existing claim. Claims
remain pending until a separate review workflow is implemented. Closed
assignments accept a delayed expense only when the assignment is marked
offline and the request includes both `is_offline` and `client_uuid`.

Sync requires the existing approved Android device key and its per-request
signature. Device binding/login middleware is not part of this feature. The
role enum currently persists `super_admin`, `admin`, and `staff`; the existing
`admin-oversight` gate also recognizes `manager` where that role is available.
This feature does not change the role schema or add roles.
If the optional `missed_punch_requests` table is absent, its pending count is
reported as zero.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
