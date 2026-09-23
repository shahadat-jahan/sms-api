# School Management System — API

Laravel REST API for the Akaar IT LTD interview task: **multi-auth (Management Admin + Student)** with
Sanctum personal access tokens, student CRUD with search/filter/pagination, CSV import/export, and a
student email notice.

The React frontend for this task lives in its own project (`../sms-frontend`) and consumes this API
over HTTP; this repository serves JSON only and ships no JavaScript build step.

## Requirements

- PHP 8.3+ (built and verified on PHP 8.5)
- Composer
- MySQL or SQLite

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Point `.env` at your database:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sms
DB_USERNAME=root
DB_PASSWORD=
```

Keep `MAIL_MAILER=log` to demonstrate the notice feature without SMTP — sent messages are written to
`storage/logs/laravel.log`.

```bash

php artisan migrate --seed
php artisan serve
```

API base URL: `http://127.0.0.1:8000/api`

## Seeded credentials

| Role | Email | Password |
| --- | --- | --- |
| Management Admin | admin@school.test | Admin@12345 |
| Student (profile `STD-0001`) | student1@school.test | password123 |

`php artisan db:seed` is idempotent, so these accounts can be recreated at any time.

## Authentication

`POST /api/login` returns a Sanctum personal access token. Send it with every other request:

```
Authorization: Bearer <token>
Accept: application/json
```

One Sanctum guard serves both actors; the `users.role` column decides which endpoints a token may reach
(admin routes manage students, a student only reaches their own profile).

## Endpoints

| Method | Endpoint | Access | Description |
| --- | --- | --- | --- |
| POST | `/api/login` | public | Log in as admin or student; returns `{"user": {...}, "token": "..."}`. Throttled to 5 attempts/minute per email + IP |
| POST | `/api/logout` | any token | Revoke the token that authenticated the request |
| GET | `/api/me` | any token | The authenticated user as `{"user": {...}}` |
| GET | `/api/my-profile` | student | The student profile owned by the token as `{"data": {...}}` — proves multi-auth end to end |
| GET | `/api/students` | admin | Paginated list with `search` (name, email or roll), `class`, `section` and `per_page` (max 100) |
| POST | `/api/students` | admin | Create the user account and the student profile in one transaction |
| GET | `/api/students/{student}` | admin | Single student |
| PUT/PATCH | `/api/students/{student}` | admin | Update the profile and/or the linked user account |
| DELETE | `/api/students/{student}` | admin | Delete the account; the profile row cascades |
| POST | `/api/students/import` | admin | Import students from a CSV upload (`file`) |
| GET | `/api/students/export` | admin | Streamed CSV download of every student |
| POST | `/api/notices/send` | admin | Email `subject` and `message` to every student |

Missing or invalid token → `401`, wrong role → `403`, validation failure → `422`.

### Examples

```bash
# log in and keep the token
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@school.test","password":"Admin@12345"}' | jq -r .token)

# list with search, class filter and pagination
curl -s "http://127.0.0.1:8000/api/students?search=ayesha&class=Class%2010&per_page=15" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'

# create a student (creates the user account too)
curl -s -X POST http://127.0.0.1:8000/api/students \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"name":"New Student","email":"new.student@school.test","password":"password123","roll":"STD-0002","class":"Class 10","section":"A"}'

# CSV import and export
curl -s -X POST http://127.0.0.1:8000/api/students/import \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -F "file=@students.csv"
curl -s -o students.csv "http://127.0.0.1:8000/api/students/export" -H "Authorization: Bearer $TOKEN"

# send a notice to all students (with MAIL_MAILER=log it is written to storage/logs/laravel.log)
curl -s -X POST http://127.0.0.1:8000/api/notices/send \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"subject":"Sports day","message":"The sports day moves to Friday."}'
```

### CSV format

```
name,email,password,roll,class,section,phone,address
Ayesha Siddiqua,ayesha@school.test,password123,STD-0003,Class 10,A,01700000003,Dhaka
Rakib Hasan,rakib@school.test,,STD-0004,Class 9,B,,
```

`name`, `email`, `roll` and `class` are required; a missing `password` falls back to `password123`.
Every row is validated before anything is written, so an invalid file imports nothing and the response
lists the failing line numbers. Blank lines are skipped and duplicate emails or roll numbers inside one
file are rejected.

## Response shape

```json
{
  "data": [
    {
      "id": 4,
      "roll": "STD-0001",
      "class": "Class 10",
      "section": "A",
      "phone": "01700000001",
      "address": "12 Demo Road, Dhaka",
      "user": { "id": 4, "name": "Demo Student", "email": "student1@school.test", "role": "student" },
      "created_at": "2026-09-23T06:00:00.000000Z",
      "updated_at": "2026-09-23T06:00:00.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

## Tests and static analysis

```bash
php artisan test --compact   # 56 tests, 160 assertions
vendor/bin/phpstan analyse   # Larastan level 7 - no errors
vendor/bin/pint              # code style
```

Covered behaviour: login success/failure/throttling, token revocation, `me`, role authorization on every
admin route, student CRUD (persisted state asserted), search by name/email/roll, class and section filters,
pagination cap, CSV import validation and rollback, CSV export content, and notice delivery with `Mail::fake()`.

## Added for this task

```
app/Enums/UserRole.php                       admin | student backed enum
app/Models/User.php                          HasApiTokens, role cast, student() relationship
app/Models/Student.php                       profile model
app/Http/Controllers/AuthController.php      login, logout, me
app/Http/Controllers/StudentController.php   index, store, show, update, destroy, myProfile
app/Http/Controllers/ImportExportController.php
app/Http/Controllers/NoticeController.php
app/Http/Middleware/EnsureRole.php           registered as the `role` alias in bootstrap/app.php
app/Http/Requests/                           Login, Store/UpdateStudent, ImportStudents, SendNotice
app/Http/Resources/                          UserResource, StudentResource
app/Mail/StudentNotice.php                   + resources/views/mail/notice.blade.php
routes/api.php                               the endpoint table above
database/seeders/                            AdminSeeder, StudentSeeder
database/factories/                          StudentFactory, role states on UserFactory
tests/Feature/Http, tests/Feature/Mail       Pest suite
```

## Notes and trade-offs

- The API is unversioned (`/api/...`) to match the task's endpoint contract; prefix a version when the surface changes.
- Students are hard deleted together with their `users` row (`cascadeOnDelete` on the foreign key); no soft deletes.
- Notices are sent synchronously so no queue worker is required for the demo. For a large cohort, add `ShouldQueue` to `StudentNotice` and run a worker.
- The frontend can be a separate origin: `config/cors.php` allows `api/*` from any origin with `supports_credentials`
  false, which is the correct combination for bearer-token authentication (no cookies, so no CSRF or credentialed
  pre-flight setup is needed). List explicit origins and enable credentials only if you switch the frontend to
  Sanctum cookie authentication.
- CSV reading and writing pass the delimiter, the enclosure and an empty escape character explicitly, because PHP 8.4
  deprecated relying on the default backslash escape of `fgetcsv()` and `fputcsv()`.
- `.env` is not committed (see `.gitignore`); only `.env.example` is tracked.
