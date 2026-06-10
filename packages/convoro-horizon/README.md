# Convoro Horizon

Adds the Laravel Horizon queue dashboard to Convoro for VPS deployments.

**Requirements:** Redis, `QUEUE_CONNECTION=redis`, and a running Horizon worker
(`php artisan horizon`, typically via systemd). Horizon ships with Convoro core,
so this extension just authorizes the `/horizon` dashboard for admins and links
to it from the Marketplace.

After enabling, open **Admin → Marketplace → Horizon → Open management page**, or
visit `/horizon` directly (admin-only).
