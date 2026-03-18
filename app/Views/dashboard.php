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
        .menu a {
            margin-left: 1rem;
            text-decoration: none;
            color: #555;
        }
        .menu a:hover {
            color: #dd4814;
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
    <nav class="menu">
        <a href="<?= base_url('/dashboard') ?>">Dashboard</a>
        <a href="<?= base_url('/api/orders') ?>">Orders API</a>
        <a href="<?= base_url('/logout') ?>">Logout</a>
    </nav>
</header>

<main>
    <h1>Restaurant System Backend</h1>
    <p>Manage orders from your website and Uber Eats, and dispatch deliveries using Uber Direct.</p>
    
    <h2>Uber Sandbox OAuth Token</h2>
    <div class="card">
        <div class="row">
            <button class="btn" id="btnToken" type="button">Create sandbox token</button>
            <span class="status" id="tokenStatus"></span>
        </div>
        <p class="muted">Calls <code>https://sandbox-login.uber.com/oauth/v2/token</code> from the server using your <code>.env</code> credentials. The token is displayed once and not stored.</p>
        <pre id="tokenOut" style="display:none;"></pre>
    </div>
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

    <h2>Orders Dashboard API</h2>
    <p><strong>GET</strong> <code>/api/orders</code> – List orders with source, status, items, and delivery status.</p>

    <h2>Update Order Status</h2>
    <p><strong>PATCH</strong> <code>/api/orders/{id}/status</code> – When set to <code>READY_FOR_PICKUP</code>, triggers an Uber Direct delivery request.</p>

    <h2>Uber Eats Webhook</h2>
    <p><strong>POST</strong> <code>/webhook/uber-eats/orders</code> – Receive orders from the Uber Eats marketplace.</p>

    <h2>Uber Direct Status Webhook</h2>
    <p><strong>POST</strong> <code>/webhook/uber-direct/status</code> – Receive delivery status updates from Uber Direct.</p>

    

    <h2>Configuration</h2>
    <p>Configure Uber and restaurant settings in <code>.env</code>:</p>
    <pre><code>UBER_CLIENT_ID
UBER_CLIENT_SECRET
RESTAURANT_NAME
RESTAURANT_ADDRESS
RESTAURANT_PHONE</code></pre>
</main>

<script>
(() => {
  const btn = document.getElementById('btnToken');
  const status = document.getElementById('tokenStatus');
  const out = document.getElementById('tokenOut');

  if (!btn) return;

  const set = (msg) => { status.textContent = msg || ''; };

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    out.style.display = 'none';
    out.textContent = '';
    set('Requesting token...');

    try {
      const res = await fetch('<?= base_url('/dashboard/uber-sandbox-token') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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

</body>
</html>

