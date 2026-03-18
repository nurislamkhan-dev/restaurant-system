## Compatibility directory (`application/`)

This project is built on **CodeIgniter 4**, so the real source lives under `app/`:

- `app/Controllers/Orders.php`
- `app/Controllers/UberWebhook.php`
- `app/Models/OrderModel.php` / `app/Models/OrderItemModel.php`
- `app/Models/DeliveryModel.php`
- `app/Services/UberDirectService.php`

Some checklists/specs expect a CodeIgniter 3 style layout (`application/controllers`, `application/models`, etc.).
To match those requirements **without changing the CI4 runtime**, this repository includes thin wrapper classes
under `application/` that delegate to the CI4 implementations.

If you are running the app, always use the `app/` classes (CI4).
