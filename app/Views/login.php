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
            max-width: 380px;
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
        <p class="subtitle">Sign in to access the dashboard.</p>

        <?php if (isset($error) && $error): ?>
            <div class="error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('/') ?>">
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
            Demo only: this form does not validate credentials yet; it just redirects to the dashboard.
        </p>
    </div>
</body>

</html>