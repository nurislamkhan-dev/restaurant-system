<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Restaurant System Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" type="image/png" href="/favicon.ico">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f7f8f9;
      color: #212529;
    }

    header {
      background-color: #ffffff;
      border-bottom: 1px solid #e5e5e5;
      padding: 0.5rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo {
      font-weight: bold;
      font-size: 1.2rem;
    }

    .menu {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.35rem 0;
    }

    .menu a {
      margin-left: 1rem;
      text-decoration: none;
      color: #555;
      font-size: 0.92rem;
      white-space: nowrap;
    }

    .menu a:hover {
      color: #dd4814;
    }

    .menu .sep {
      margin-left: 0.75rem;
      color: #ccc;
      user-select: none;
    }

    section.anchor {
      scroll-margin-top: 1rem;
    }

    label.dl {
      display: block;
      font-size: 0.85rem;
      color: #555;
      margin-bottom: 0.35rem;
    }

    textarea.codebox {
      width: 100%;
      max-width: 100%;
      min-height: 120px;
      padding: 0.65rem 0.75rem;
      border-radius: 8px;
      border: 1px solid #e5e5e5;
      font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
      font-size: 0.82rem;
      line-height: 1.45;
      box-sizing: border-box;
    }

    textarea.codebox.tall {
      min-height: 220px;
    }

    main {
      max-width: 1100px;
      margin: 2rem auto;
      padding: 0 1.75rem 3rem;
    }

    h1 {
      margin-bottom: 1rem;
    }

    h2 {
      margin-top: 2rem;
      margin-bottom: 0.75rem;
      font-size: 1.1rem;
    }

    pre {
      background-color: #f7f8f9;
      border: 1px solid #f2f2f2;
      padding: 1rem 1.5rem;
      white-space: pre-wrap;
      word-break: break-all;
      font-size: 0.9rem;
    }

    code {
      font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
    }

    .card {
      background: #ffffff;
      border: 1px solid #e5e5e5;
      border-radius: 10px;
      padding: 1rem 1.25rem;
      margin-top: 1rem;
    }

    .row {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .btn {
      appearance: none;
      border: 1px solid #dd4814;
      background: #dd4814;
      color: #ffffff;
      padding: 0.6rem 0.9rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }

    .muted {
      color: #666;
      font-size: 0.92rem;
    }

    .status {
      font-size: 0.92rem;
    }
  </style>
</head>

<body>

  <header>
    <div class="logo">Restaurant System</div>
    <nav class="menu" aria-label="Primary">
      <a href="<?= base_url('/dashboard') ?>">Dashboard</a>
      <span class="sep" aria-hidden="true">|</span>
      <a href="#uber-sandbox-token">Sandbox token</a>
      <a href="#uber-eats-marketplace">Uber Eats</a>
      <a href="#uber-direct">Uber Direct</a>
      <a href="#website-orders">Website orders</a>
      <a href="#configuration">Configuration</a>
      <span class="sep" aria-hidden="true">|</span>
      <a href="<?= base_url('/api/orders') ?>">Orders API</a>
      <a href="<?= base_url('/logout') ?>">Logout</a>
    </nav>
  </header>

  <main>
    <h1>Restaurant System Backend</h1>
    <p>Welcome, <?= esc($logged_in_name !== '' ? $logged_in_name : 'there') ?>!</p>

    <!-- <p class="muted"><?= esc($logged_in_email) ?></p>  -->
    <p>Manage orders from your website and Uber Eats, and dispatch deliveries using Uber Direct.</p>

    <section class="anchor" id="uber-sandbox-token">
    <h2>Uber Sandbox OAuth Token</h2>
    <div class="card">
      <div class="row">
        <div class="col-12">
        <p> Uber access token<br />
          <code><?= esc($uber_access_token) ?></code>
        </p>
        <p>Expires at: <strong><?= esc($uber_token_expires_at_formatted ?? '—') ?></strong>
          <?php if (! empty($uber_token_expires_relative)): ?>
            <span class="muted" style="margin-left:0.35rem;">(<?= esc($uber_token_expires_relative) ?>)</span>
          <?php elseif (! empty($uber_token_expires_at) && is_numeric($uber_token_expires_at) && (int) $uber_token_expires_at <= time()): ?>
            <span class="muted" style="margin-left:0.35rem;color:#842029;">(expired)</span>
          <?php endif; ?>
        </p>
        <p> Scope: <?= esc($uber_token_scope) ?></p>
        <p> Token type: <?= esc($uber_token_type) ?></p><br />
        </div>
      </div>
      <div class="row">
        <button class="btn" id="btnToken" type="button">Create again sandbox token</button>
        <span class="status" id="tokenStatus"></span>
      </div>
      <p class="muted">Calls <code>https://sandbox-login.uber.com/oauth/v2/token</code> from the server using your <code>.env</code> credentials. The token is displayed once and not stored.</p>
      <pre id="tokenOut" style="display:none;"></pre>
    </div>
    </section>

    <section class="anchor" id="uber-eats-marketplace">
    <h2>Uber Eats Sandbox API (Marketplace)</h2>
    <div class="card">
      <div class="row">
        <div style="display:flex; flex-direction:column; gap:0.25rem;">
          <span class="muted">Store ID</span>
          <input id="eatsStoreId" type="text" placeholder="YOUR_TEST_STORE_ID"
            style="padding:0.6rem 0.75rem; border-radius:8px; border:1px solid #e5e5e5; min-width: 260px;" />
        </div>
        <button class="btn" id="btnGetEatsStore" type="button">Get store</button>
      </div>
      <p class="muted" style="margin-top:0.75rem;">Calls <code>GET https://test-api.uber.com/v1/delivery/stores/{store_id}</code></p>
      <pre id="eatsStoreOut" style="display:none;"></pre>
    </div>

    <div class="card">
      <div class="row">
        <div style="display:flex; flex-direction:column; gap:0.25rem;">
          <span class="muted">POS Order ID</span>
          <input id="eatsPosOrderId" type="text" placeholder="YOUR_TEST_POS_ORDER_ID"
            style="padding:0.6rem 0.75rem; border-radius:8px; border:1px solid #e5e5e5; min-width: 260px;" />
        </div>
        <button class="btn" id="btnAcceptPosOrder" type="button">Accept POS order</button>
      </div>
      <p class="muted" style="margin-top:0.75rem;">Calls <code>POST https://test-api.uber.com/v1/delivery/orders/{order_id}/accept_pos_order</code></p>
      <pre id="eatsAcceptOut" style="display:none;"></pre>
    </div>
    </section>

    <section class="anchor" id="uber-direct">
    <h2>Uber Direct (DAAS)</h2>
    <p class="muted">Uses <code>UBER_DIRECT_*</code> from <code>.env</code> — quote first, then create delivery with the returned <code>quote_id</code>. Addresses in the API are JSON <strong>strings</strong> inside the create-delivery body.</p>

    <div class="card">
      <h3 style="margin-top:0;font-size:1rem;">1. Delivery quote</h3>
      <p class="muted"><code>POST …/customers/{customer_id}/delivery_quotes</code></p>
      <label class="dl" for="directPickupJson">Pickup address (JSON object)</label>
      <textarea class="codebox" id="directPickupJson" autocomplete="off"><?= esc(json_encode([
        'street_address' => ['20 W 34th St', 'Floor 2'],
        'state' => 'NY',
        'city' => 'New York',
        'zip_code' => '10001',
        'country' => 'US',
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
      <label class="dl" for="directDropoffJson" style="margin-top:0.75rem;">Dropoff address (JSON object)</label>
      <textarea class="codebox" id="directDropoffJson" autocomplete="off"><?= esc(json_encode([
        'street_address' => ['285 Fulton St', ''],
        'state' => 'NY',
        'city' => 'New York',
        'zip_code' => '10006',
        'country' => 'US',
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
      <div class="row" style="margin-top:0.75rem;">
        <button class="btn" id="btnDirectQuote" type="button">Get quote</button>
        <span class="status" id="directQuoteStatus"></span>
      </div>
      <pre id="directQuoteOut" style="display:none;margin-top:0.75rem;"></pre>
    </div>

    <div class="card">
      <h3 style="margin-top:0;font-size:1rem;">2. Create delivery</h3>
      <p class="muted"><code>POST …/customers/{customer_id}/deliveries</code> — set <code>quote_id</code> from the quote response (filled automatically after a successful quote).</p>
      <label class="dl" for="directDeliveryBody">Request body (JSON)</label>
      <textarea class="codebox tall" id="directDeliveryBody" autocomplete="off">{
  "quote_id": "REPLACE_WITH_QUOTE_ID",
  "pickup_address": "{\"street_address\":[\"20 W 34th St\",\"Floor 2\"],\"state\":\"NY\",\"city\":\"New York\",\"zip_code\":\"10001\",\"country\":\"US\"}",
  "pickup_name": "My Store",
  "pickup_phone_number": "4444444444",
  "pickup_latitude": 40.74868,
  "pickup_longitude": -73.98561,
  "dropoff_address": "{\"street_address\":[\"285 Fulton St\",\"\"],\"state\":\"NY\",\"city\":\"New York\",\"zip_code\":\"10006\",\"country\":\"US\"}",
  "dropoff_name": "Customer",
  "dropoff_phone_number": "5555555555",
  "dropoff_latitude": 40.71301,
  "dropoff_longitude": -74.01317,
  "manifest_items": [
    {
      "name": "Order items",
      "quantity": 1,
      "weight": 30,
      "dimensions": { "length": 40, "height": 40, "depth": 40 }
    }
  ]
}</textarea>
      <div class="row" style="margin-top:0.75rem;">
        <button class="btn" id="btnDirectDelivery" type="button">Create delivery</button>
        <span class="status" id="directDeliveryStatus"></span>
      </div>
      <pre id="directDeliveryOut" style="display:none;margin-top:0.75rem;"></pre>
    </div>
    </section>

    <section class="anchor" id="website-orders">
    <h2>Website Orders</h2>
    <p><strong>POST</strong> <code>/api/orders</code> – Receive orders from the restaurant website.</p>
    <pre><code>{
  "customer_name": "John Doe",
  "phone": "+123456789",
  "address": "123 Main St",
  "items": [
    { "name": "Burger", "qty": 1 },
    { "name": "Fries", "qty": 2 }
  ]
}</code></pre>
    </section>

    <h2>Orders Dashboard API</h2>
    <p><strong>GET</strong> <code>/api/orders</code> – List orders with source, status, items, and delivery status.</p>

    <h2>Update Order Status</h2>
    <p><strong>PATCH</strong> <code>/api/orders/{id}/status</code> – When set to <code>READY_FOR_PICKUP</code>, triggers an Uber Direct delivery request.</p>

    <h2>Uber Eats Webhook</h2>
    <p><strong>POST</strong> <code>/webhook/uber-eats/orders</code> – Receive orders from the Uber Eats marketplace.</p>

    <h2>Uber Direct Status Webhook</h2>
    <p><strong>POST</strong> <code>/webhook/uber-direct/status</code> – Receive delivery status updates from Uber Direct.</p>



    <section class="anchor" id="configuration">
    <h2>Configuration</h2>
    <p>Configure Uber and restaurant settings in <code>.env</code>:</p>
    <pre><code>UBER_CLIENT_ID
UBER_CLIENT_SECRET
RESTAURANT_NAME
RESTAURANT_ADDRESS
RESTAURANT_PHONE</code></pre>
    <p class="muted" style="margin-top:0.75rem;">Uber Direct also uses <code>UBER_DIRECT_CLIENT_ID</code>, <code>UBER_DIRECT_CLIENT_SECRET</code>, <code>UBER_DIRECT_CUSTOMER_ID</code>, <code>UBER_DIRECT_SCOPE</code> (e.g. <code>eats.deliveries</code>).</p>
    </section>
  </main>

  <script>
    (() => {
      const btn = document.getElementById('btnToken');
      const status = document.getElementById('tokenStatus');
      const out = document.getElementById('tokenOut');

      if (!btn) return;

      const set = (msg) => {
        status.textContent = msg || '';
      };

      btn.addEventListener('click', async () => {
        btn.disabled = true;
        out.style.display = 'none';
        out.textContent = '';
        set('Requesting token...');

        try {
          const res = await fetch('<?= base_url('/dashboard/uber-sandbox-token') ?>', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({}),
          });

          const data = await res.json().catch(() => ({}));
          if (!res.ok) {
            set(data?.messages?.error || data?.message || 'Failed');
            return;
          }

          set('Token created.');
          out.style.display = 'block';
          out.textContent = JSON.stringify(data, null, 2);
        } catch (e) {
          set('Request failed.');
        } finally {
          btn.disabled = false;
        }
      });
    })();
  </script>

  <script>
    (() => {
      const storeBtn = document.getElementById('btnGetEatsStore');
      const storeInput = document.getElementById('eatsStoreId');
      const storeOut = document.getElementById('eatsStoreOut');

      if (storeBtn) {
        storeBtn.addEventListener('click', async () => {
          storeOut.style.display = 'block';
          storeOut.textContent = '';

          const storeId = storeInput?.value?.trim();
          if (!storeId) {
            storeOut.textContent = JSON.stringify({
              error: 'store_id is required'
            }, null, 2);
            return;
          }

          try {
            storeOut.textContent = 'Requesting Uber Eats store...';
            const res = await fetch('<?= base_url('/dashboard/uber-eats/store') ?>', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                store_id: storeId
              }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
              storeOut.textContent = JSON.stringify(data, null, 2);
              return;
            }
            storeOut.textContent = JSON.stringify(data, null, 2);
          } catch (e) {
            storeOut.textContent = `Request failed: ${e}`;
          }
        });
      }

      const acceptBtn = document.getElementById('btnAcceptPosOrder');
      const acceptInput = document.getElementById('eatsPosOrderId');
      const acceptOut = document.getElementById('eatsAcceptOut');

      if (acceptBtn) {
        acceptBtn.addEventListener('click', async () => {
          acceptOut.style.display = 'block';
          acceptOut.textContent = '';

          const orderId = acceptInput?.value?.trim();
          if (!orderId) {
            acceptOut.textContent = JSON.stringify({
              error: 'order_id is required'
            }, null, 2);
            return;
          }

          try {
            acceptOut.textContent = 'Accepting POS order...';
            const res = await fetch('<?= base_url('/dashboard/uber-eats/accept-pos-order') ?>', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                order_id: orderId
              }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
              acceptOut.textContent = JSON.stringify(data, null, 2);
              return;
            }
            acceptOut.textContent = JSON.stringify(data, null, 2);
          } catch (e) {
            acceptOut.textContent = `Request failed: ${e}`;
          }
        });
      }
    })();
  </script>

  <script>
    (() => {
      const qBtn = document.getElementById('btnDirectQuote');
      const qStatus = document.getElementById('directQuoteStatus');
      const qOut = document.getElementById('directQuoteOut');
      const pickupEl = document.getElementById('directPickupJson');
      const dropoffEl = document.getElementById('directDropoffJson');
      const deliveryBody = document.getElementById('directDeliveryBody');

      if (qBtn) {
        qBtn.addEventListener('click', async () => {
          qBtn.disabled = true;
          qOut.style.display = 'none';
          qOut.textContent = '';
          qStatus.textContent = 'Requesting quote...';

          let pickup;
          let dropoff;
          try {
            pickup = JSON.parse(pickupEl.value);
            dropoff = JSON.parse(dropoffEl.value);
          } catch (e) {
            qStatus.textContent = 'Invalid JSON in address fields.';
            qBtn.disabled = false;
            return;
          }

          try {
            const res = await fetch('<?= base_url('/dashboard/uber-direct/quote') ?>', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ pickup_address: pickup, dropoff_address: dropoff }),
            });
            const data = await res.json().catch(() => ({}));
            qOut.style.display = 'block';
            qOut.textContent = JSON.stringify(data, null, 2);
            if (!res.ok) {
              qStatus.textContent = 'Quote failed.';
              return;
            }
            qStatus.textContent = 'Quote OK.';
            if (data.id && deliveryBody) {
              let t = deliveryBody.value;
              t = t.replace(/"quote_id"\s*:\s*"[^"]*"/, `"quote_id": "${data.id}"`);
              deliveryBody.value = t;
            }
          } catch (e) {
            qStatus.textContent = 'Request failed.';
            qOut.style.display = 'block';
            qOut.textContent = String(e);
          } finally {
            qBtn.disabled = false;
          }
        });
      }

      const dBtn = document.getElementById('btnDirectDelivery');
      const dStatus = document.getElementById('directDeliveryStatus');
      const dOut = document.getElementById('directDeliveryOut');

      if (dBtn) {
        dBtn.addEventListener('click', async () => {
          dBtn.disabled = true;
          dOut.style.display = 'none';
          dOut.textContent = '';
          dStatus.textContent = 'Creating delivery...';

          let payload;
          try {
            payload = JSON.parse(deliveryBody.value);
          } catch (e) {
            dStatus.textContent = 'Invalid JSON in delivery body.';
            dBtn.disabled = false;
            return;
          }

          try {
            const res = await fetch('<?= base_url('/dashboard/uber-direct/delivery') ?>', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            dOut.style.display = 'block';
            dOut.textContent = JSON.stringify(data, null, 2);
            dStatus.textContent = res.ok ? 'Delivery created.' : 'Request failed.';
          } catch (e) {
            dStatus.textContent = 'Request failed.';
            dOut.style.display = 'block';
            dOut.textContent = String(e);
          } finally {
            dBtn.disabled = false;
          }
        });
      }
    })();
  </script>

</body>

</html>