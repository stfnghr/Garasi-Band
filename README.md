# Garasi Band — Garage Rental Web App

Built with **Laravel 13 (PHP 8.3)** and **MySQL/MariaDB**. The website interface uses Bootstrap 5.

---

## Table of Contents

1. [Features](#features)
2. [Business Rules](#business-rules)
3. [Technologies & Libraries](#technologies--libraries)
4. [Folder Structure](#folder-structure)
5. [How to Run](#how-to-run)
6. [Demo Accounts](#demo-accounts)
7. [Unit Testing](#unit-testing)

---

## Features

The application has only **two roles**: **User** (garage renter) and **Admin**.

### User (Renter)

| Feature | Description |
|---|---|
| Register | NIK (16 digits), name, phone number (08…, 10–15 digits), email (username), and password. Initial status is *Pending*. |
| Login / Logout | Users with *Pending* or *Rejected* status cannot log in. |
| Rent New Garage | Enter vehicle details, select a rental type (Daily or Monthly), and specify the rental duration. The end date is calculated automatically. The system displays garages available today along with the total rental cost, after which the user uploads proof of payment. |
| Active Rentals | Displays garages currently being rented today. |
| Rental History | Displays all rentals, including detailed cost and payment information. |
| Profile | Update name/phone number (email is locked), change password, and delete the account if there are no active rentals. |

### Admin

| Feature | Description |
|---|---|
| Garages | Displays a list of garages with type filters, summary information, pagination (10/25/50 items per page), and a pop-up showing current and historical garage occupants. |
| Create Garages in Batch | Create 1–999 garages per type. Garage IDs are generated automatically, such as `S001`, `C010`, `E999`, and so on. |
| Change Garage Price | Update the prices of multiple garages of the same type simultaneously. **Garages cannot be deleted.** |
| New User Verification | Approve or reject new registrations after reviewing their ID cards (KTP). |
| Rental Extensions | Select an active rental for today, specify a new end date, and let the system calculate the additional cost. The admin then uploads proof of payment. |
| Rental Reports | Generate reports by month, day, garage type, user, and individual garage. Includes charts powered by Chart.js. |

All important actions require confirmation pop-ups, and validation errors are displayed in pop-ups using SweetAlert2.

## Business Rules

- **Vehicle Placement:**
  - Bicycles and motorcycles must be assigned to **S** garages.
  - Cars must be assigned to **C** garages.
  - Electric cars may be assigned to either **C** or **E** garages.

- **Monthly Pricing:**
  - Monthly rental price = 25 × daily rental price.

- **Rental Dates:**
  - The rental start date is always **today**.
  - The rental end date is calculated **inclusively**.

- **Monthly Threshold:**
  - The monthly threshold equals the number of days in the month in which the rental starts (28/29/30/31 days).
  - Any remaining days are charged at the daily rate.
  - Example: A 40-day rental starting on December 1 consists of 1 month + 9 days = Rp 680,000 (daily rate: Rp 20,000).

- **Rental Extensions:**
  - Extensions are always calculated using the daily rate: additional days × the rental's *price snapshot*.

- **Electric Vehicle Charging:**
  - (Battery capacity − remaining battery level) × price per kWh.
  - This applies only to electric cars parked in **E** garages.

- **Price Snapshot:**
  - Changes to garage prices do not affect ongoing rentals.

- **Garage Status:**
  - The status changes to `used` when a garage is rented.
  - The status returns to `available` after the rental end date has passed.
  - The daily scheduler `GarageService::syncStatuses()` handles garage status synchronization.

## Technologies & Libraries

| Library | Version | Purpose in the Application |
| :--- | :--- | :--- |
| Laravel Framework | 13.43.0 | Routing, controllers, middleware, Form Request validation, session-based authentication, password hashing, payment proof file storage (Storage), and daily garage status scheduling. |
| Eloquent ORM | (Laravel) | Model-based database access and relationships (`hasMany`, `belongsTo`), as well as the `availableOn()` and `activeOn()` query scopes. |
| Blade Template | (Laravel) | View templates using layouts and partials. |
| Carbon | 3.15.1 | Date calculations, including the number of days in a month, inclusive rental end dates, and additional extension days. |
| Bootstrap | 5.3.3 | Responsive grid, cards, tables, modals, and buttons with a purple theme. |
| Bootstrap Icons | 1.11.3 | Icons for menus, buttons, and status indicators. |
| SweetAlert2 | 11.14.5 | Validation pop-ups, "Are you sure?" confirmation dialogs, and success notifications. |
| flatpickr | 4.6.13 | Date picker for rental extension end dates and report date ranges. |
| Chart.js | 4.4.6 | Charts for monthly/daily revenue, revenue by garage type, revenue by garage, and revenue composition. |
| Google Fonts (Plus Jakarta Sans) | - | Interface typography. |
| PHPUnit | 12.5.45 | Running unit tests and feature tests. |
| Faker | 1.24.1 | Generating dummy data in factories and seeders. |
| Laravel Pint | 1.32.3 | Formatting code according to PSR-12 standards. |
| Composer | 2.9.5 | Dependency installation and autoloading. |

## Folder Structure

```text
Garasi_Band/
└── app/                          # Laravel application source code
    ├── app/Enums/                # GarageType, VehicleType, PaymentType, UserRole, UserStatus, GarageStatus
    ├── app/Models/               # User, Garage, Vehicle, Rental, Payment
    ├── app/Services/             # RentalPriceCalculator, RentalService, GarageService, ReportService, ...
    ├── app/Http/Controllers/     # Auth, RentalController (user), Admin, Profile
    ├── app/Http/Requests/        # Form validation
    ├── app/Http/Middleware/      # EnsureRole (admin / user roles)
    ├── database/migrations/      # Database schema
    ├── database/seeders/         # Demo data
    ├── resources/views/          # Blade templates
    ├── public/css, public/js     # Purple theme, global validation & pop-ups
    └── tests/                    # Unit & Feature tests (UT-01 to UT-71)
```

## How to Run

### Prerequisites

- PHP 8.3+ with the `pdo_mysql`, `fileinfo`, and `gd` extensions.
- Composer 2.
- MySQL/MariaDB (e.g., XAMPP).

### Steps

1. Start MySQL (XAMPP Control Panel → **Start MySQL**), then create the database:

   ```sql
   CREATE DATABASE db_garasi_sertifikasi;
   ```

2. Navigate to the application folder and install dependencies:

   ```bash
   cd app
   composer install
   ```

3. Prepare the environment file:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Adjust `DB_USERNAME` / `DB_PASSWORD` in `.env` if your MySQL installation uses a password.

4. Create the database tables and seed demo data:

   ```bash
   php artisan migrate:fresh --seed
   ```

5. Link the payment proof upload folder to `public`:

   ```bash
   php artisan storage:link
   ```

6. Start the development server:

   ```bash
   php artisan serve
   ```

   Open **http://127.0.0.1:8000** in your browser.

7. (Optional) Run the scheduler to automatically update garage statuses to `available` every day:

   ```bash
   php artisan schedule:work
   ```

## Demo Accounts

These accounts are created by the seeder (for local demonstration purposes only):

| Role | Email | Password | Status |
|---|---|---|---|
| Admin | admin@garasi.test | password | – |
| User | budi@garasi.test | password | Approved |
| User | sari@garasi.test | password | Approved |
| User | citra@garasi.test | password | Pending (for verification demonstration) |

## Unit Testing

Testing method: **Black-Box Testing** (equivalence partitioning and boundary value analysis).

Tests run using an in-memory SQLite database, so the MySQL database is not affected.

```bash
cd app
php artisan test
```

Current results: **88 tests, 329 assertions — all PASS**.

The complete list of test scenarios (UT-01 to UT-69) is available in Chapter 4 of the report.

| File | Coverage |
|---|---|
| `tests/Unit/RentalPriceCalculatorTest.php` | New rental costs, rental extensions, and electric vehicle charging |
| `tests/Unit/VehicleTypeTest.php` | Vehicle placement and license plates |
| `tests/Unit/GarageCodeGeneratorTest.php` | Garage ID format |
| `tests/Feature/RentalFlowTest.php` | New rental workflow |
| `tests/Feature/RentalExtensionTest.php` | Rental extension workflow |
| `tests/Feature/GarageBatchTest.php` | Batch garage creation |
| `tests/Feature/RevisionFeaturesTest.php` | Registration, profile, price updates, and garage details |
| `tests/Feature/ReportTest.php` | Reports |
| `tests/Feature/AuthAndVerificationTest.php` | Login, user verification, and role-based access control |
