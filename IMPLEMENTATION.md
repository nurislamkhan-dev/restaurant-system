# IMPLEMENTATION (Step-by-step)

This file is a **hands-on implementation guide** for running the full project locally and testing the **Uber sandbox** flow end-to-end.

## 0) What you will have when finished

- **Website orders API** working:
  - `POST /api/orders`
  - `GET /api/orders`
  - `PATCH /api/orders/{id}/status`
- **Uber Eats webhook** receiving and storing orders:
  - `POST /webhook/uber-eats/orders`
- **Uber Direct delivery creation** when a website order becomes `READY_FOR_PICKUP`
  - background worker `php spark deliveries:work`
- **Uber Direct status webhook** updating delivery + order status:
  - `POST /webhook/uber-direct/status`

Routes are defined in `app/Config/Routes.php`.

---

## 1) Requirements (local machine)

- PHP 8.x (CodeIgniter 4)
- Composer
- MySQL (or MariaDB)

---

## 2) Install dependencies

From the project root:

```bash
composer install
```

---

## 3) Create the database

Create a database (example name from your `.env`: `ubarDB`):

```sql
CREATE DATABASE ubarDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 4) Configure `.env`

You already have a `.env`. Verify/update these keys:

### App

- `CI_ENVIRONMENT = development`
- `app.baseURL = 'http://localhost:8080/'`

### Database

- `database.default.hostname`
- `database.default.database`
- `database.default.username`
- `database.default.password`
- `database.default.port`

### Uber (Sandbox)

This project supports sandbox switching for Uber Direct via:

- `UBER_ENV = sandbox`

And optional explicit overrides:

- `UBER_API_BASE_URL` (example: `https://sandbox-api.uber.com/v1`)
- `UBER_OAUTH_TOKEN_URL` (example: `https://sandbox-login.uber.com/oauth/v2/token`)

Minimum required credentials:

- `UBER_CLIENT_ID`
- `UBER_CLIENT_SECRET`
- `CUSTOMER_ID` (a.k.a. Uber Direct customer id)
- `SCOPE` (example in this repo: `"eats.store eats.order"`)

Pickup (restaurant) details:

- `RESTAURANT_NAME`
- `RESTAURANT_ADDRESS`
- `RESTAURANT_PHONE`

### Webhook secrets (recommended)

If you set these, the webhook endpoints require header `X-Webhook-Secret`:

- `UBER_EATS_WEBHOOK_SECRET`
- `UBER_DIRECT_WEBHOOK_SECRET`

---

## 5) Run migrations

```bash
php spark migrate
```

This creates tables such as:

- `orders`, `order_items`
- `deliveries`, `delivery_jobs`
- `users`

Schema reference: `docs/DatabaseSchema.md`.

---

## 6) Create a dashboard user (required to log in)

Login checks `users.email`, `users.password` (hashed), and `users.status='active'`.

Create a user row (example):

1) Generate a password hash:

```bash
php -r "echo password_hash('admin1234', PASSWORD_DEFAULT) . PHP_EOL;"
```

2) Insert into DB (replace hash value):

```sql
INSERT INTO users (name, email, password, status, created_at)
VALUES ('Admin', 'admin@example.com', '<PASTE_HASH_HERE>', 'active', NOW());
```

---

## 7) Start the server

From the project root:

```bash
php -S 127.0.0.1:8080 -t public
```

Open:

- `http://localhost:8080/` (login)
- `http://localhost:8080/dashboard` (dashboard)

The dashboard includes a button that calls:

- `POST /dashboard/uber-sandbox-token`

This requests an OAuth token from `https://sandbox-login.uber.com/oauth/v2/token` (server-side) and displays it.

---

## 8) Run the delivery worker (Uber Direct job processor)

Uber Direct deliveries are created asynchronously via `delivery_jobs`.

Start a worker in another terminal:

```bash
php spark deliveries:work
```

Helpful options:

```bash
php spark deliveries:work --once
php spark deliveries:work --limit 10
```

---

## 9) Test the Website Orders flow (local)

### 9.1 Create a website order

```bash
curl -sS -X POST "http://localhost:8080/api/orders" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name":"John Doe",
    "phone":"+123456789",
    "address":"123 Main St",
    "items":[
      {"name":"Burger","qty":1,"price":5.5},
      {"name":"Fries","qty":2,"price":2.0}
    ]
  }'
```

Result example (response `201`):

```json
{
  "order": {
    "id": 1,
    "external_order_id": null,
    "order_source": "website",
    "customer_name": "John Doe",
    "phone": "+123456789",
    "address": "123 Main St",
    "status": "pending",
    "total_amount": "9.50",
    "notes": null,
    "source_raw_payload": "{\"customer_name\":\"John Doe\"...}",
    "created_at": "2026-03-18 14:10:11",
    "updated_at": null
  },
  "items": [
    { "name": "Burger", "qty": 1, "price": "5.50" },
    { "name": "Fries", "qty": 2, "price": "2.00" }
  ]
}
```

### 9.2 List orders

```bash
curl -sS "http://localhost:8080/api/orders"
```

Result example (response `200`):

```json
[
  {
    "id": 1,
    "external_order_id": null,
    "order_source": "website",
    "customer_name": "John Doe",
    "phone": "+123456789",
    "address": "123 Main St",
    "status": "pending",
    "total_amount": "9.50",
    "created_at": "2026-03-18 14:10:11",
    "delivery": null,
    "items": [
      { "name": "Burger", "qty": 1, "price": "5.50" },
      { "name": "Fries", "qty": 2, "price": "2.00" }
    ]
  }
]
```

### 9.3 Mark order `READY_FOR_PICKUP` (this enqueues a delivery job)

Replace `ORDER_ID` with the created order id:

```bash
curl -sS -X PATCH "http://localhost:8080/api/orders/ORDER_ID/status" \
  -H "Content-Type: application/json" \
  -d '{"status":"READY_FOR_PICKUP"}'
```

Result example (response `200`):

```json
{ "message": "Order status updated" }
```

Now the worker (`php spark deliveries:work`) will pick up the job and call Uber Direct delivery creation.

Result example (worker terminal output):

```text
Job #1 requeued (attempt 1)
Processed 1 job(s).
```

If Uber Direct succeeds, you’ll see:

```text
Job #1 succeeded
Processed 1 job(s).
```

---

## 10) Uber Sandbox: expose webhooks (ngrok)

Uber needs to reach your machine. Use **ngrok** (or any tunnel).

Example:

```bash
ngrok http 8080
```

You’ll get a public HTTPS URL like:

- `https://YOUR_SUBDOMAIN.ngrok-free.app`

Your webhook URLs become:

- Uber Eats webhook: `https://YOUR_SUBDOMAIN.ngrok-free.app/webhook/uber-eats/orders`
- Uber Direct status: `https://YOUR_SUBDOMAIN.ngrok-free.app/webhook/uber-direct/status`

If you set webhook secrets in `.env`, Uber (or your test client) must send:

- `X-Webhook-Secret: <value>`

---

## 11) Test Uber Eats webhook (manual sandbox simulation)

This endpoint stores an order with:

- `orders.order_source = uber_eats`
- `orders.external_order_id = payload.order_id`

Example:

```bash
curl -sS -X POST "http://localhost:8080/webhook/uber-eats/orders" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: ${UBER_EATS_WEBHOOK_SECRET}" \
  -d '{
    "order_id":"UE12345",
    "customer_name":"Alice",
    "phone":"+1555000111",
    "address":"45 Broadway",
    "items":[{"name":"Pizza","qty":1,"price":12.5}]
  }'
```

Result example (response `200`):

```json
{ "message": "Uber Eats order received" }
```

If you POST the same `order_id` again, result example:

```json
{ "message": "Uber Eats order already received" }
```

Then confirm it exists:

```bash
curl -sS "http://localhost:8080/api/orders"
```

---

## 12) Test Uber Direct status webhook (manual sandbox simulation)

When your system creates a delivery, it stores `deliveries.external_delivery_id`.

To simulate status updates, POST to:

- `/webhook/uber-direct/status`

Example payloads:

```bash
curl -sS -X POST "http://localhost:8080/webhook/uber-direct/status" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: ${UBER_DIRECT_WEBHOOK_SECRET}" \
  -d '{"delivery_id":"DELIVERY_ID_FROM_DB","status":"courier_assigned"}'
```

Result example (response `200`):

```json
{ "message": "Delivery status updated" }
```

```bash
curl -sS -X POST "http://localhost:8080/webhook/uber-direct/status" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: ${UBER_DIRECT_WEBHOOK_SECRET}" \
  -d '{"delivery_id":"DELIVERY_ID_FROM_DB","status":"courier_picked_up"}'
```

```bash
curl -sS -X POST "http://localhost:8080/webhook/uber-direct/status" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: ${UBER_DIRECT_WEBHOOK_SECRET}" \
  -d '{"delivery_id":"DELIVERY_ID_FROM_DB","status":"delivered"}'
```

Result example in `GET /api/orders` after `delivered`:

```json
[
  {
    "id": 1,
    "order_source": "website",
    "status": "delivered",
    "delivery": {
      "provider": "uber_direct",
      "external_delivery_id": "DELIVERY_ID_FROM_DB",
      "delivery_status": "delivered"
    },
    "items": [
      { "name": "Burger", "qty": 1, "price": "5.50" }
    ]
  }
]
```

Behavior:

- Updates `deliveries.delivery_status`
- If `status=delivered` → sets `orders.status=delivered`
- If `status=cancelled` → sets `orders.status=cancelled`

---

## 13) Uber Direct sandbox settings checklist (what to set in Uber portal)

In the Uber developer dashboard / sandbox (names can vary by program):

- **OAuth**:
  - Client ID + Client Secret → put into `.env`
  - Scopes → ensure they match your app + `.env` `SCOPE`
- **Customer / Merchant / Org**:
  - Find your Uber Direct **Customer ID** → set `.env` `CUSTOMER_ID`
- **Webhooks**:
  - Uber Eats orders webhook URL → `/webhook/uber-eats/orders`
  - Uber Direct status webhook URL → `/webhook/uber-direct/status`
  - (Optional) configure shared secret and set it in `.env`

If you use ngrok, remember: changing the ngrok URL means you must update Uber’s webhook URLs.

---

## 14) Troubleshooting quick checks

### Delivery not created

- Ensure you updated an order to `READY_FOR_PICKUP`
- Ensure the worker is running: `php spark deliveries:work`
- Check `delivery_jobs` table:
  - `status=pending` means worker hasn’t picked it up yet
  - `status=failed` means the Uber call failed repeatedly

### Webhook returns 401 Unauthorized

- Either remove webhook secret env vars, or send the correct header:
  - `X-Webhook-Secret: <exact value>`

### Uber sandbox token works but delivery creation fails

- Ensure `.env` contains:
  - `UBER_ENV = sandbox`
  - `CUSTOMER_ID`
  - correct `SCOPE`
- If Uber requires a different API base path for deliveries in your program, set:
  - `UBER_API_BASE_URL`
  - `UBER_OAUTH_TOKEN_URL`

Reference behavior: `docs/API.md`.

