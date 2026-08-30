Absolutely. **NCCAA HRMS** is the right name, and I would treat this as a proper enterprise application—not a collection of PHP pages.

One important clarification: by **PHP**, I recommend **Laravel + REST API + MySQL**, rather than plain PHP. Laravel gives you the structure needed for authentication, authorization, migrations, validation, queues, API resources, logging, testing, and maintainability while still keeping the stack PHP.

# NCCAA HRMS

## Industrial-Grade Requirements & Implementation Plan

### Technology Stack

| Layer             | Technology                            |
| ----------------- | ------------------------------------- |
| Backend           | **PHP 8.3+ / Laravel 12**             |
| API               | **RESTful JSON API**                  |
| Database          | **MySQL 8+**                          |
| Frontend          | **HTML5 + Tailwind CSS + JavaScript** |
| UI Components     | Tailwind-based reusable components    |
| Authentication    | Laravel Sanctum                       |
| Authorization     | RBAC + granular permissions           |
| Validation        | Laravel Form Requests                 |
| Database          | Laravel Migrations + Seeders          |
| API Documentation | OpenAPI / Swagger                     |
| Testing           | PHPUnit / Laravel Feature Tests       |
| Queue             | Laravel Queue                         |
| Cache             | Redis                                 |
| Web Server        | Nginx                                 |
| Deployment        | Linux                                 |
| Version Control   | Git                                   |

The frontend should consume the REST API rather than directly accessing the database.

```text
┌──────────────────────────────────────────┐
│              NCCAA HRMS                  │
├──────────────────────────────────────────┤
│                                          │
│  Admin Web UI        Cadet Web UI        │
│  HTML/Tailwind/JS    HTML/Tailwind/JS    │
│          │                   │            │
│          └─────────┬─────────┘            │
│                    ▼                      │
│             REST API Layer               │
│                    │                      │
│        ┌───────────┴──────────┐           │
│        │                      │           │
│   Auth/RBAC              Business Logic  │
│        │                      │           │
│        └───────────┬──────────┘           │
│                    ▼                      │
│                MySQL 8                   │
│                                          │
└──────────────────────────────────────────┘
```

---

# 1. Core objective

NCCAA HRMS should initially manage:

### People

* Cadets
* Administrators
* Province administrators
* District administrators
* Other authorized users

### Organization

* Provinces
* Districts
* Local levels
* Wards
* Ranks
* Roles
* Permissions

### Talent

* Skills
* Cadet skills
* Experience
* Availability

### Recruitment

* Recruitment campaigns
* Required skills
* Candidate matching
* Applications
* Shortlisting
* Selection
* Assignment

### Communication

* Announcements
* Notifications
* Targeted communication

---

# 2. Critical architectural rule

You specifically said:

> Province and district must not be hardcoded.

Correct.

There should be **ZERO hardcoded province/district lists in frontend JavaScript**.

Instead:

```text
MySQL
  │
  ├── provinces
  │
  ├── districts
  │
  ├── local_levels
  │
  └── wards
```

The frontend obtains them through APIs.

Example:

```http
GET /api/v1/geography/provinces
```

Then:

```http
GET /api/v1/geography/provinces/{province}/districts
```

This means if Nepal's administrative structure changes, you update the database rather than changing application code.

---

# 3. Geographic hierarchy

Use:

```text
Province
   │
   └── District
          │
          └── Local Government
                 │
                 └── Ward
```

Database:

```text
provinces
districts
local_levels
wards
```

Relationships:

```text
province
   1
   │
   └────── N districts

district
   1
   │
   └────── N local_levels

local_level
   1
   │
   └────── N wards
```

---

# 4. User architecture

We should distinguish **authentication users** from **cadet information**.

### users

Responsible for login/security.

```text
users
-----
id
uuid
username
email
phone
password
status
email_verified_at
phone_verified_at
last_login_at
created_at
updated_at
deleted_at
```

### cadets

Responsible for NCC information.

```text
cadets
------
id
uuid
cadet_number
name
rank_id
province_id
district_id
local_level_id
ward_id
email
phone
...
```

And:

```text
users
   │
   │ optional
   ▼
cadets
```

This is important because eventually you may have:

* Admin users
* Cadet users
* System users
* Coordinators
* Recruiters

without forcing everything into the cadet table.

---

# 5. User fields

For an admin-created user, your required fields become:

### Identity

* Full Name
* Username
* Email
* Contact Number
* Password
* Cadet Number
* Rank

### Organization

* Province
* District
* Local Level
* Ward

### Account

* Status
* Role
* Permissions
* Last Login
* Created By
* Created At
* Updated At

---

# 6. Cadet number

This must have a **database-level unique constraint**.

Not merely frontend validation.

Example:

```text
cadet_number VARCHAR(50) UNIQUE
```

So even if two administrators try to create:

```text
NCC-00123
```

simultaneously, MySQL prevents duplication.

---

# 7. Rank management

Do **not** hardcode ranks in PHP or JavaScript.

Create:

```text
ranks
-----
id
name
short_code
description
display_order
status
created_at
updated_at
```

Initial seed/migration data:

| Rank                         | Code |
| ---------------------------- | ---- |
| Senior Under Officer         | SUO  |
| Company Junior Under Officer | CJUO |
| Platoon Junior Under Officer | PJUO |
| Quarter Master Sergeant      | QMS  |
| Sergeant                     | SGT  |
| Corporal                     | CPL  |
| Lance Corporal               | LCPL |
| Cadet                        | CDT  |

You can display:

> Quarter Master Sergeant (हवल्दार मेजर)

while storing a structured value.

For example:

```text
name = Quarter Master Sergeant
short_code = QMS
name_ne = हवल्दार मेजर
```

I'd actually include both English and Nepali names:

```text
ranks
-----
id
name_en
name_ne
short_code
display_order
status
```

This gives you proper bilingual support later.

---

# 8. RBAC architecture

Use proper RBAC:

```text
User
 │
 └── User Roles
        │
        ▼
       Role
        │
        ▼
   Permissions
```

Tables:

```text
users
roles
permissions
user_roles
role_permissions
```

Potential roles:

```text
SUPER_ADMIN
CENTRAL_ADMIN
PROVINCE_ADMIN
DISTRICT_ADMIN
RECRUITMENT_MANAGER
CONTENT_MANAGER
REPORT_MANAGER
CADET
```

These are database records—not hardcoded authorization logic.

---

# 9. Permission system

Permissions should be granular.

For example:

```text
users.view
users.create
users.update
users.delete
users.assign-role
users.reset-password

cadets.view
cadets.create
cadets.update
cadets.delete
cadets.import
cadets.export

skills.view
skills.create
skills.update
skills.delete

recruitments.view
recruitments.create
recruitments.update
recruitments.delete
recruitments.publish

applications.view
applications.review
applications.shortlist
applications.select

notifications.view
notifications.create
notifications.send

reports.view
reports.export

geography.view
geography.manage

roles.view
roles.create
roles.update
roles.delete

permissions.view
```

---

# 10. Administrative scope

RBAC alone isn't enough.

We also need **data scope**.

For example:

### Super Admin

Can access:

```text
All Nepal
```

### Province Admin

Can access:

```text
Their Province
     ↓
All districts under province
```

### District Admin

Can access:

```text
Their District
```

Therefore authorization becomes:

```text
Permission
+
Geographical Scope
```

For example:

```text
cadets.view
+
province_id = 5
```

This is much more secure than simply checking whether someone is an admin.

---

# 11. Admin user creation

Admin dashboard:

```text
Users
 └── Create User
```

Form:

```text
PERSONAL INFORMATION

Full Name *
Contact Number *
Email *
Username *
Password *
Confirm Password *

CADET INFORMATION

Cadet Number *
Rank *

LOCATION

Province *
District *
Local Government
Ward

ACCESS

Role *
Status *
```

When Province is selected:

```http
GET /api/v1/geography/provinces/{id}/districts
```

When District is selected:

```http
GET /api/v1/geography/districts/{id}/local-levels
```

No hardcoded geographic data.

---

# 12. Password requirements

At minimum:

```text
Minimum 8 characters
```

But for production I'd recommend:

```text
Minimum 12 characters
```

with Laravel's password validation.

Passwords must **never** be stored as plain text.

Use:

```text
Argon2id
```

or Laravel's supported secure password hashing configuration.

---

# 13. Authentication

REST authentication:

```text
POST /api/v1/auth/login
```

Response:

```json
{
  "user": {},
  "token": "...",
  "expires_at": "..."
}
```

Other endpoints:

```text
POST /api/v1/auth/logout
GET  /api/v1/auth/me
POST /api/v1/auth/refresh
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
```

Eventually:

```text
2FA
```

should be added.

---

# 14. REST API structure

Use versioning from day one:

```text
/api/v1/
```

Example:

```text
/api/v1/auth
/api/v1/users
/api/v1/cadets
/api/v1/roles
/api/v1/permissions
/api/v1/ranks
/api/v1/skills
/api/v1/geography
/api/v1/recruitments
/api/v1/applications
/api/v1/notifications
/api/v1/reports
```

---

# 15. API response standard

Don't return random JSON from every controller.

Standardize responses.

Success:

```json
{
  "success": true,
  "message": "User created successfully.",
  "data": {}
}
```

Validation:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

Not found:

```json
{
  "success": false,
  "message": "User not found."
}
```

---

# 16. Backend architecture

Do **not** create:

```text
users.php
create-user.php
delete-user.php
```

That is exactly what we want to avoid.

Laravel architecture:

```text
HTTP Request
     ↓
Route
     ↓
Controller
     ↓
Form Request
     ↓
Service
     ↓
Repository / Query Layer
     ↓
Model
     ↓
MySQL
```

For more complex modules:

```text
Controller
    ↓
Application Service
    ↓
Domain/Business Logic
    ↓
Repository
    ↓
Eloquent
    ↓
MySQL
```

Controllers should remain thin.

---

# 17. Database migration strategy

Every structural database change must be a migration.

For example:

```text
2026_08_30_000001_create_users_table.php
2026_08_30_000002_create_roles_table.php
2026_08_30_000003_create_permissions_table.php
2026_08_30_000004_create_provinces_table.php
2026_08_30_000005_create_districts_table.php
2026_08_30_000006_create_local_levels_table.php
2026_08_30_000007_create_wards_table.php
2026_08_30_000008_create_ranks_table.php
2026_08_30_000009_create_cadets_table.php
...
```

Never manually create production tables.

---

# 18. Geography migrations

Example architecture:

```text
provinces
---------
id BIGINT
name_en
name_ne
code
status
timestamps
```

```text
districts
---------
id
province_id FK
name_en
name_ne
code
status
timestamps
```

```text
local_levels
------------
id
district_id FK
name_en
name_ne
type
code
status
timestamps
```

```text
wards
-----
id
local_level_id FK
ward_number
name_en
name_ne
status
timestamps
```

Foreign keys should enforce the hierarchy at database level.

---

# 19. Don't put Nepal geography into migration code unnecessarily

There are two separate things:

### Schema migration

Creates:

```text
provinces
districts
local_levels
wards
```

### Data seeding/import

Loads Nepal's actual administrative data.

This separation is cleaner.

For example:

```text
database/
 ├── migrations/
 └── seeders/
      ├── ProvinceSeeder
      ├── DistrictSeeder
      └── GeographySeeder
```

If you already have authoritative Excel/CSV data containing Nepal's geography, create an **import command** rather than manually typing hundreds/thousands of records.

---

# 20. Initial database modules

I'd structure the database into these groups:

### Authentication

```text
users
sessions
password_reset_tokens
```

### Authorization

```text
roles
permissions
user_roles
role_permissions
```

### Organization

```text
provinces
districts
local_levels
wards
ranks
```

### Cadet

```text
cadets
cadet_profiles
cadet_skills
skills
skill_categories
```

### Recruitment

```text
recruitments
recruitment_skills
recruitment_locations
applications
application_status_history
```

### Communication

```text
notifications
notification_recipients
announcements
```

### System

```text
audit_logs
attachments
system_settings
```

---

# 21. Audit logging

For an industrial system, this is important.

Track:

```text
Who
What
When
Where
```

Example:

```text
Admin Milan
created user
NCC-00123
2026-08-30 23:41
IP address
```

Another:

```text
Province Admin
updated cadet
NCC-00231
changed rank
Cadet → SGT
```

Table:

```text
audit_logs
----------
id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
user_agent
created_at
```

This becomes extremely useful later.

---

# 22. Modern UI/UX direction

The UI should **not look like an old government CRUD system**.

Think:

> Modern ERP + HRMS + enterprise dashboard.

### Design principles

* Clean whitespace
* Strong typography
* Clear hierarchy
* Responsive
* Accessible
* Minimal visual noise
* Consistent spacing
* Reusable components
* Clear status indicators
* Excellent tables
* Search-first interfaces
* Keyboard-friendly forms
* Mobile-friendly cadet portal

---

# 23. Admin layout

```text
┌────────────────────────────────────────────────────────┐
│ NCCAA HRMS                         🔔  Admin ▼         │
├───────────────┬────────────────────────────────────────┤
│               │                                        │
│ Dashboard     │ Dashboard                              │
│               │                                        │
│ Cadets        │ ┌──────┐ ┌──────┐ ┌──────┐            │
│ Recruitment   │ │ 200  │ │ 87   │ │ 12   │            │
│ Skills        │ │Cadets│ │Users │ │Open  │            │
│ Users         │ └──────┘ └──────┘ └──────┘            │
│ Roles         │                                        │
│ Geography     │ ┌──────────────────────────────────┐   │
│ Notifications │ │ Talent Distribution              │   │
│ Reports       │ │                                  │   │
│               │ └──────────────────────────────────┘   │
│ Settings      │                                        │
│               │                                        │
│ Logout        │                                        │
└───────────────┴────────────────────────────────────────┘
```

And, based on your previous UI direction, the sidebar should be **stationary**, with the content area properly offset so it never hides underneath the sidebar.

---

# 24. Dashboard KPIs

Initially:

```text
Total Cadets
Registered Users
Unregistered Cadets
Active Recruitments
Applications
Selected Candidates
Available Talent
```

Then:

### Skills

```text
Content Creator
Graphic Designer
Videographer
Video Editor
Writer
IT / Programming
```

### Geography

```text
Cadets by Province
Cadets by District
```

---

# 25. Cadet Explorer

This is likely to become one of your most-used pages.

```text
Cadet Explorer

Search by name / cadet number / phone

Province       [All ▼]
District       [All ▼]
Rank           [All ▼]
Skill          [All ▼]
Availability   [All ▼]
Registration   [All ▼]

[Search] [Reset]
```

Results should support:

* Pagination
* Sorting
* Filtering
* Column selection
* Export
* Bulk selection

---

# 26. Excel import

Your existing ~200-row Excel should be imported through an administrative workflow.

```text
Import Cadets
       ↓
Upload Excel
       ↓
Read headers
       ↓
Map columns
       ↓
Validate
       ↓
Preview
       ↓
Duplicate detection
       ↓
Error report
       ↓
Confirm
       ↓
Import
```

Example:

```text
200 records

✓ 187 valid
⚠ 8 duplicates
✕ 5 errors
```

Never directly insert the Excel rows without validation.

---

# 27. Recruitment module

The first real business module should be:

## Recruitment Campaign

Example:

**Flood & Disaster Digital Content Team**

Required:

```text
Content Creator
Graphic Designer
Videographer
Video Editor
Writer
```

The system finds matching cadets.

```text
Recruitment
      ↓
Required Skills
      ↓
Cadet Skills
      ↓
Candidate Pool
```

---

# 28. Application workflow

```text
OPEN
 ↓
APPLIED
 ↓
SHORTLISTED
 ↓
SELECTED
 ↓
ASSIGNED
 ↓
COMPLETED
```

Admin should be able to see:

```text
Candidates: 47
Shortlisted: 18
Selected: 10
Assigned: 8
```

---

# 29. Communication

Admin can target:

```text
All Cadets

Province

District

Rank

Skill

Recruitment

Selected Users
```

For example:

```text
Skill = Video Editor
AND
Province = Any
AND
Registered = Yes
```

Then send notification.

This is exactly what your current disaster-content requirement needs.

---

# 30. Security requirements

The application should include:

### Authentication

* Secure password hashing
* Login throttling
* Session/token management
* Password reset
* Account lock/disable
* Optional 2FA

### API security

* Authentication middleware
* Authorization middleware
* Request validation
* Rate limiting
* CORS configuration
* CSRF protection where applicable
* Secure headers

### Database

* Prepared queries / ORM
* Foreign keys
* Unique constraints
* Transactions
* Soft deletes where appropriate

### File upload

* MIME validation
* File-size limits
* Randomized filenames
* Storage outside executable paths
* Virus scanning later if required

---

# 31. API documentation

Every API should be documented.

For example:

```text
POST /api/v1/users
```

Documentation should specify:

```text
Authentication
Request
Validation
Response
Errors
Permissions
Examples
```

Use OpenAPI/Swagger so developers can test endpoints without guessing.

---

# 32. Testing

Do not wait until the end.

### Unit tests

Test:

```text
Services
Business rules
Permission checks
Candidate matching
```

### Feature/API tests

Test:

```text
Login
Create user
Update user
RBAC
Province restriction
District restriction
Cadet import
Recruitment
Applications
Notifications
```

Critical test:

> District Admin must never retrieve cadets belonging to another district.

---

# 33. Development roadmap

## Phase 0 — Architecture

```text
Repository
Environment
Laravel setup
MySQL
Git
Coding standards
API conventions
Environment configuration
```

---

## Phase 1 — Database Foundation

Implement migrations for:

```text
users
roles
permissions
provinces
districts
local_levels
wards
ranks
```

Seed:

```text
roles
permissions
ranks
geography
```

---

## Phase 2 — Authentication + RBAC

Implement:

```text
Login
Logout
Current user
Password reset
Role assignment
Permission checking
Scope checking
```

---

## Phase 3 — User Management

Admin:

```text
List users
Create
View
Edit
Disable
Reset password
Assign role
Assign geography
```

---

## Phase 4 — Cadet Management

```text
Cadet CRUD
Profile
Search
Filtering
Geography
Rank
Status
```

---

## Phase 5 — Excel Import

```text
Upload
Mapping
Validation
Preview
Duplicates
Import
Import logs
```

---

## Phase 6 — Skills / Talent

```text
Skill categories
Skills
Cadet skills
Skill levels
Experience
Availability
```

---

## Phase 7 — Recruitment

```text
Create recruitment
Required skills
Location
Candidate matching
Applications
Shortlisting
Selection
Assignment
```

---

## Phase 8 — Communication

```text
Announcements
Notifications
Targeting
Notification history
Read/unread
```

---

## Phase 9 — Reporting

```text
Cadet reports
Talent reports
Recruitment reports
Province reports
District reports
Exports
```

---

## Phase 10 — Production Hardening

```text
Security audit
API rate limiting
Performance
Caching
Queue workers
Logging
Backups
Monitoring
Error handling
Automated tests
CI/CD
```

---

# 34. Recommended project structure

```text
nccaa-hrms/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Models/
│   ├── Services/
│   ├── Policies/
│   ├── Repositories/
│   └── Actions/
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── api.php
│   └── web.php
│
├── resources/
│   ├── views/
│   ├── css/
│   └── js/
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
├── storage/
│
└── docs/
    ├── architecture/
    ├── api/
    ├── database/
    └── requirements/
```

---

# 35. Important development rule

I would establish these rules before writing code:

### ❌ Don't

```text
Hardcode province
Hardcode district
Hardcode ranks
Hardcode permissions
Hardcode roles
Put SQL everywhere
Put business logic inside controllers
Create PHP pages for every operation
Trust frontend validation
Store plaintext passwords
Allow unrestricted admin access
```

### ✅ Do

```text
Database-driven configuration
REST API
Laravel services
Form Requests
Policies
RBAC
Geographic scope
Migrations
Seeders
Transactions
API Resources
Audit logs
Automated tests
OpenAPI documentation
```

---

# 36. The MVP database relationship

The core relationship should ultimately look like:

```text
                    ┌─────────────┐
                    │    USER     │
                    └──────┬──────┘
                           │
                     User Roles
                           │
                           ▼
                    ┌─────────────┐
                    │    ROLE     │
                    └──────┬──────┘
                           │
                    Role Permissions
                           │
                           ▼
                    ┌─────────────┐
                    │ PERMISSION  │
                    └─────────────┘


┌───────────┐
│ PROVINCE  │
└─────┬─────┘
      │
      ▼
┌───────────┐
│ DISTRICT  │
└─────┬─────┘
      │
      ▼
┌──────────────┐
│ LOCAL LEVEL  │
└──────┬───────┘
       │
       ▼
┌─────────────┐
│    WARD     │
└─────────────┘


                 ┌──────────────┐
                 │    CADET     │
                 └──────┬───────┘
                        │
            ┌───────────┼────────────┐
            ▼           ▼            ▼
          Rank       Location       Skills
                                      │
                                      ▼
                                   Talent
                                      │
                                      ▼
                                Recruitment
                                      │
                                      ▼
                                 Application
```

---

## 37. What I would build first

Rather than jumping directly into UI, the implementation should start with these **five deliverables**:

### Deliverable 1 — SRS

A formal requirements document defining every MVP feature and acceptance criteria.

### Deliverable 2 — Database Design

Complete MySQL ERD + table definitions + foreign keys + indexes + migrations.

### Deliverable 3 — RBAC Specification

Roles, permissions, geographic scopes, and exactly what each role can do.

### Deliverable 4 — REST API Specification

Every endpoint, request, response, validation rule, authentication requirement and permission.

### Deliverable 5 — UI/UX Specification

Admin and Cadet screens, navigation, forms, tables, dashboard, responsive behavior and design system.

Then we implement:

**Foundation → Auth → RBAC → Geography → Users → Cadets → Excel → Skills → Recruitment → Communication.**

That order will prevent us from building UI first and later discovering that the database, permissions, or geographic hierarchy cannot support the requirements.
