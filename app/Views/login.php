<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Restaurant System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f7f8f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #212529;
        }

        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            padding: 2rem 2.5rem;
            width: 100%;
            max-width: 440px;
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }

        p.subtitle {
            margin: 0 0 1.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        label {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0.85rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }

        button {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 4px;
            border: none;
            background-color: #dd4814;
            color: #fff;
            font-size: 0.95rem;
            cursor: pointer;
        }

        button:hover {
            background-color: #c13e10;
        }

        button.secondary {
            background-color: #fff;
            color: #333;
            border: 1px solid #ced4da;
        }

        button.secondary:hover {
            background-color: #f1f3f5;
        }

        textarea {
            width: 100%;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0.85rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.85rem;
            font-family: Menlo, Monaco, Consolas, monospace;
            min-height: 4.5rem;
            resize: vertical;
            box-sizing: border-box;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.35rem 0 1rem;
            color: #999;
            font-size: 0.8rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e5e5;
        }

        .divider span {
            padding: 0 0.75rem;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0 0 0.75rem;
            color: #444;
        }

        .hint {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #777;
        }

        .error {
            margin-bottom: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            background-color: #f8d7da;
            color: #842029;
            font-size: 0.85rem;
        }

        .session-active {
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 8px;
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
            font-size: 0.88rem;
        }

        .session-active strong {
            display: block;
            margin-bottom: 0.5rem;
        }

        .session-active .row-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.65rem;
        }

        .session-active a {
            color: #0a3622;
            font-weight: 600;
        }

        .field-error {
            color: #842029;
            font-size: 0.75rem;
            margin-top: -0.4rem;
            margin-bottom: 0.6rem;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Restaurant System</h1>
        <p class="subtitle"><?= ! empty($already_logged_in)
            ? 'You are signed in.'
            : 'Sign in to access the dashboard.' ?></p>

        <?php if (! empty($already_logged_in)): ?>
            <div class="session-active">
                <strong>Signed in as</strong>
                <?php if (! empty($logged_in_name) || ! empty($logged_in_email)): ?>
                    <?= esc($logged_in_name ?? '') ?>
                    <?php if (! empty($logged_in_email)): ?>
                        <span style="color:#0a3622;opacity:0.9;"> — <?= esc($logged_in_email) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (! empty($auth_method)): ?>
                    <div style="margin-top:0.35rem;font-size:0.8rem;opacity:0.9;">Method: <?= esc($auth_method) ?></div>
                <?php endif; ?>
                <div class="row-links">
                    <a href="<?= base_url('dashboard') ?>">Open dashboard</a>
                    <span>·</span>
                    <a href="<?= base_url('logout') ?>">Sign out</a>
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($error) && $error): ?>
                <div class="error"><?= esc($error) ?></div>
            <?php endif; ?>

            <!-- Post to the same host/path the user is on (avoids localhost vs 127.0.0.1 cookie mismatch) -->
            <form method="post" action="">
                <?= csrf_field() ?>
                <label for="email">Email</label>
                <input type="text" id="email" name="email" placeholder="admin@example.com" value="<?= esc(old('email')) ?>">
                <?php if (isset($validation) && $validation->hasError('email')): ?>
                    <div class="field-error"><?= esc($validation->getError('email')) ?></div>
                <?php endif; ?>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••">
                <?php if (isset($validation) && $validation->hasError('password')): ?>
                    <div class="field-error"><?= esc($validation->getError('password')) ?></div>
                <?php endif; ?>

                <button type="submit">Login</button>
            </form>

            <p class="hint">
                Use the same URL host as in <code>app.baseURL</code> (e.g. always <code>http://localhost:8080</code> or always <code>http://127.0.0.1:8080</code>).
            </p>

            <div class="divider"><span>or</span></div>

            <p class="section-title">Uber Eats sandbox (OAuth)</p>
            <p class="hint" style="margin-top:-0.25rem; margin-bottom:0.85rem;">
                Uses <code>UBER_CLIENT_ID</code> / <code>UBER_CLIENT_SECRET</code> from <code>.env</code> to obtain an
                <code>access_token</code> from
                <code>sandbox-login.uber.com/oauth/v2/token</code>, then opens the dashboard.
            </p>

            <form method="post" action="<?= esc(site_url('login/uber-sandbox')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="secondary">Sign in with Uber sandbox token</button>
            </form>

            <p class="hint" style="margin-top:1rem;">
                Or paste an access token you already received (optional):
            </p>
            <form method="post" action="<?= esc(site_url('login/uber-sandbox')) ?>">
                <?= csrf_field() ?>
                <label for="uber_access_token">Access token</label>
                <textarea id="uber_access_token" name="uber_access_token" placeholder="Bearer token from OAuth response…"></textarea>
                <button type="submit" class="secondary">Sign in with pasted token</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>