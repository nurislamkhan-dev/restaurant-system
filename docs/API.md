## API documentation

Base URL depends on your `app.baseURL` / web server setup.

### Login

#### Uber Eats sandbox — sign in with OAuth `access_token`

- **POST** `/login/uber-sandbox`
- **CSRF**: include hidden token (use the form on `/` or `/login`)

Behavior:

- **No extra fields**: Uses `UBER_CLIENT_ID` and `UBER_CLIENT_SECRET` from `.env` with `grant_type=client_credentials` and `SCOPE` / `UBER_SANDBOX_SCOPE` against `https://sandbox-login.uber.com/oauth/v2/token`. On success, creates a dashboard session and stores `uber_access_token` (and expiry/scope when returned).
- **`uber_access_token` field** (optional): Paste a token you already obtained; session is created without calling the token URL again (sandbox / dev convenience).

After this login, **Uber Eats sandbox** dashboard actions (`/dashboard/uber-eats/*`) prefer the session token when it is still valid.

### Dashboard (utilities)

#### Get Uber sandbox OAuth token

- **POST** `/dashboard/uber-sandbox-token`

Behavior:

- If you signed in via **Uber sandbox login**, returns the **session** token when still valid (`source: session`).
- Otherwise requests a new token from `https://sandbox-login.uber.com/oauth/v2/token` using `.env` credentials (`source: oauth`).

#### Uber Eats sandbox: get store
- **POST** `/dashboard/uber-eats/store`

Behavior:
- Uses **session** `uber_access_token` when present (Uber sandbox login); otherwise obtains a token with client credentials
- Calls `GET https://test-api.uber.com/v1/delivery/stores/{store_id}`

#### Uber Eats sandbox: accept POS order
- **POST** `/dashboard/uber-eats/accept-pos-order`

Behavior:
- Uses **session** `uber_access_token` when present (Uber sandbox login); otherwise obtains a token with client credentials
- Calls `POST https://test-api.uber.com/v1/delivery/orders/{order_id}/accept_pos_order`

### Website Orders API

#### Create order

- **POST** `/api/orders`

Request JSON:

```json
{
  "customer_name": "John Doe",
  "phone": "+123456789",
  "address": "123 Main St",
  "items": [
    { "name": "Burger", "qty": 1, "price": 5.50 },
    { "name": "Fries", "qty": 2, "price": 2.00 }
  ]
}
```

Behavior:

- Saves into `orders` and `order_items`
- Forces `orders.order_source = website`
- Returns `201 Created` with created order + items

#### List orders

- **GET** `/api/orders`

Returns orders with items and latest delivery (if any).

Delivery object (when present):

- `provider`: `uber_direct`
- `external_delivery_id`: Uber Direct delivery id
- `delivery_status`: last known status (example: `courier_assigned`, `courier_picked_up`, `delivered`, `cancelled`)

#### Update order status

- **PATCH** `/api/orders/{id}/status`

Request JSON:

```json
{ "status": "READY_FOR_PICKUP" }
```

Behavior:

- Updates `orders.status`
- If status becomes `READY_FOR_PICKUP` and the order is a **website** order, requests an Uber Direct delivery (once).

---

### Uber Eats Webhook

- **POST** `/webhook/uber-eats/orders`

Headers:

- `X-Webhook-Secret: <your-secret>` (required if `UBER_EATS_WEBHOOK_SECRET` is set)

Example payload:

```json
{
  "order_id": "UE12345",
  "customer_name": "Alice",
  "address": "45 Broadway",
  "items": [
    { "name": "Pizza", "qty": 1, "price": 12.50 }
  ]
}
```

Behavior:

- Saves into `orders` and `order_items`
- Sets `orders.order_source = uber_eats`
- Idempotent per `(external_order_id, order_source)`

---

### Uber Direct delivery status webhook

- **POST** `/webhook/uber-direct/status`

Headers:

- `X-Webhook-Secret: <your-secret>` (required if `UBER_DIRECT_WEBHOOK_SECRET` is set)

Example payload:

```json
{
  "delivery_id": "DELIVERY_ID_FROM_UBER",
  "status": "courier_assigned"
}
```

Behavior:

- Locates delivery by `deliveries.external_delivery_id` using `delivery_id` (or `id`) from payload
- Handles status/events:
  - `courier_assigned`
  - `courier_picked_up`
  - `delivered`
  - `cancelled`
- Updates `deliveries.delivery_status` and stores raw webhook payload for auditing

---

## Uber Direct retry behavior

When creating an Uber Direct delivery (triggered by setting a **website** order to `READY_FOR_PICKUP`), the delivery is queued into `delivery_jobs` and processed by the worker.

Within a single Uber Direct API call, the server will retry on **transient failures**:

- HTTP `408`, `425`, `429`, `500`, `502`, `503`, `504`
- network/timeout exceptions

Defaults:

- max attempts: `3`
- base delay: `250ms` (exponential backoff)
- max delay: `4000ms`

Config (`.env`):

- `UBER_DIRECT_RETRY_MAX_ATTEMPTS`
- `UBER_DIRECT_RETRY_BASE_DELAY_MS`
- `UBER_DIRECT_RETRY_MAX_DELAY_MS`

De-duplication:

- If a delivery already exists for the order (`deliveries` has a row for `order_id` with `provider=uber_direct`), the system will **not** create another delivery.

## Queue worker (delivery jobs)

When an order is updated to `READY_FOR_PICKUP` (website orders only), a row is inserted into `delivery_jobs` with `status=pending`.

Run the worker:

```bash
php spark deliveries:work
```

Options:

- `--once` process one job and exit
- `--limit <n>` max jobs to process (default 50)

Job retry (requeue) config (`.env`):

- `DELIVERY_JOB_MAX_ATTEMPTS` (default 5)
- `DELIVERY_JOB_RETRY_DELAY_SECONDS` (default 30)

