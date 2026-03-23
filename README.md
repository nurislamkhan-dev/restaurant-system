# Restaurant System (Website + Uber Eats + Uber Direct)

CodeIgniter 4 backend that:

- accepts **website orders** via `POST /api/orders`
- receives **Uber Eats orders** via `POST /webhook/uber-eats/orders`
- requests **Uber Direct deliveries** when a website order becomes `READY_FOR_PICKUP`
- receives **delivery status updates** via `POST /webhook/uber-direct/status`

## Environment setup (do this first)

1) Copy `env.example` → `.env`

2) Update `.env` values:

- **App**
  - `CI_ENVIRONMENT = development`
  - `app.baseURL = 'http://localhost:8080/'`
- **Database**
  - `database.default.hostname`
  - `database.default.database`
  - `database.default.username`
  - `database.default.password`
  - `database.default.port`
- **Uber (sandbox/prod)**
  - `UBER_CLIENT_ID`
  - `UBER_CLIENT_SECRET`
  - `CUSTOMER_ID` (Uber Direct customer id)
  - `SCOPE`
  - `RESTAURANT_NAME`, `RESTAURANT_ADDRESS`, `RESTAURANT_PHONE`
  - Optional environment switch:
    - `UBER_ENV = sandbox` (uses `sandbox-api.uber.com` + `sandbox-login.uber.com`)
    - `UBER_ENV = production` (default; uses production hosts)
    - Optional overrides: `UBER_API_BASE_URL`, `UBER_OAUTH_TOKEN_URL`
- **Webhook secrets (optional but recommended)**
  - `UBER_EATS_WEBHOOK_SECRET`
  - `UBER_DIRECT_WEBHOOK_SECRET`

## Project structure (requirement mapping)

This project runs on **CodeIgniter 4**, so runtime code is in `app/`:

- `app/Controllers/Orders.php`
- `app/Controllers/UberWebhook.php`
- `app/Models/OrderModel.php`, `app/Models/OrderItemModel.php`
- `app/Models/DeliveryModel.php`
- `app/Services/UberDirectService.php`

For checklists that require CodeIgniter 3 style paths, this repo also includes **wrappers** under:

- `application/controllers/Orders.php`
- `application/controllers/UberWebhook.php`
- `application/models/Order_model.php`
- `application/models/Delivery_model.php`
- `application/services/UberDirectService.php`

These wrappers delegate to the CI4 implementations (see `application/README.md`).

## Setup

### 1) Install dependencies

```bash
composer install
```

### 2) Configure environment

See **Environment setup** above.

### 3) Run migrations

```bash
php spark migrate
```

### 4) Start server

```bash
php -S 127.0.0.1:8080 -t public
```

Open:

- `http://localhost:8080/` (login)
- `http://localhost:8080/dashboard` (dashboard)

## Dashboard login (required)

The login form authenticates against the `users` table:

- `email` must exist
- `password` must be a **hashed** password (`password_hash()` / `password_verify()`)
- `status` must be `active`

### Create your first admin user

#### Option A (recommended): seed via migration

This repo includes a migration that inserts/updates a default admin user (idempotent by email):

- `app/Database/Migrations/2026-03-18-140000_SeedDefaultUser.php`

Run:

```bash
php spark migrate
```

It seeds:

- Email: `nurislamkhan.dev@gmail.com`
- Password hash (already stored in the migration)
- Status: `active`

Note: the seeded user **id may not be 1** (AUTO_INCREMENT can advance over time). Login uses **email + password**, not the numeric id.

If you need to re-run only this seed migration:

```bash
php spark migrate:status
php spark migrate:rollback --batch 5
php spark migrate
```

#### Option B: create manually (SQL)

1) Generate a password hash:

```bash
php -r "echo password_hash('admin1234', PASSWORD_DEFAULT) . PHP_EOL;"
```

2) Insert a user row (replace the hash):

```sql
INSERT INTO users (name, email, password, status, created_at)
VALUES ('Admin', 'admin@example.com', '<PASTE_HASH_HERE>', 'active', NOW());
```

Then log in at `http://localhost:8080/` using:

- Email: `admin@example.com`
- Password: `admin1234`

For a full end-to-end walkthrough (including Uber sandbox), see [`IMPLEMENTATION.md`](./IMPLEMENTATION.md).

## API documentation

See [`docs/API.md`](./docs/API.md).

## Database schema

See [`docs/DatabaseSchema.md`](./docs/DatabaseSchema.md).

## Bonus (optional) notes

- **Retry logic**: implemented for Uber Direct delivery creation (exponential backoff; only retries transient errors). See `docs/API.md`.
- **Logging**: uses `log_message()` throughout controllers/services.
- **Webhook authentication**: implemented for webhook endpoints via `X-Webhook-Secret` (configure `UBER_EATS_WEBHOOK_SECRET` and `UBER_DIRECT_WEBHOOK_SECRET`).
- **Queue worker**: implemented (website orders enqueue delivery jobs; run `php spark deliveries:work`).