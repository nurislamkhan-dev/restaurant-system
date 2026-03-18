## API documentation

Base URL depends on your `app.baseURL` / web server setup.

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
    { "name": "Burger", "qty": 1 },
    { "name": "Fries", "qty": 2 }
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

Example payload:

```json
{
  "order_id": "UE12345",
  "customer_name": "Alice",
  "address": "45 Broadway",
  "items": [
    { "name": "Pizza", "qty": 1 }
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

Behavior:

- Locates delivery by `deliveries.external_delivery_id` using `delivery_id` (or `id`) from payload
- Handles status/events:
  - `courier_assigned`
  - `courier_picked_up`
  - `delivered`
  - `cancelled`
- Updates `deliveries.delivery_status` and stores raw webhook payload for auditing

