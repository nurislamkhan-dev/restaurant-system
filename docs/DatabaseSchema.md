## Database schema

This app uses MySQL (CI4 `MySQLi` driver).

### `orders`

Columns (from migrations):

- `id` (INT, PK, auto_increment)
- `external_order_id` (VARCHAR(100), nullable) — e.g. Uber Eats order id
- `order_source` (ENUM: `website`, `uber_eats`) — defaults to `website`
- `customer_name` (VARCHAR(191))
- `phone` (VARCHAR(50))
- `address` (TEXT)
- `status` (VARCHAR(50)) — defaults to `pending`
- `total_amount` (DECIMAL(10,2), nullable)
- `notes` (TEXT, nullable)
- `source_raw_payload` (LONGTEXT, nullable) — raw JSON payload for auditing
- `created_at` (DATETIME, nullable)
- `updated_at` (DATETIME, nullable)

Indexes:

- `id` (PK)
- `external_order_id`
- `order_source`
- `status`

### `order_items`

Columns:

- `id` (INT, PK, auto_increment)
- `order_id` (INT, FK → `orders.id`, cascade on delete/update)
- `item_name` (VARCHAR(191))
- `quantity` (INT, default 1)
- `price` (DECIMAL(10,2), nullable)
- `created_at` (DATETIME, nullable)
- `updated_at` (DATETIME, nullable)

### `deliveries`

Columns:

- `id` (INT, PK, auto_increment)
- `order_id` (INT, FK → `orders.id`, cascade on delete/update)
- `provider` (VARCHAR(100), default `uber_direct`)
- `external_delivery_id` (VARCHAR(191)) — Uber delivery id
- `delivery_status` (VARCHAR(50), default `requested`)
- `pickup_address` (TEXT, nullable)
- `dropoff_address` (TEXT, nullable)
- `fee` (DECIMAL(10,2), nullable)
- `last_webhook_event` (VARCHAR(100), nullable)
- `last_webhook_at` (DATETIME, nullable)
- `raw_request` (LONGTEXT, nullable)
- `raw_response` (LONGTEXT, nullable)
- `created_at` (DATETIME, nullable)
- `updated_at` (DATETIME, nullable)

