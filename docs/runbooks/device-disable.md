# Device disable and maintenance

Remote control is Laravel device state. The kiosk only renders it.

## Who can do what (`docs/07`)

| Action | Dashboard | Roles |
|---|---|---|
| Maintenance on/off | `/dashboard/kiosks` buttons **Maintenance** / **Keluar maint** | Operator, Admin, Super Admin (`kiosks.maintenance`) |
| Disable | **Nonaktifkan** → confirm **Ya, nonaktifkan** | Admin, Super Admin only (`kiosks.deactivate`) |
| Issue activation code | shown once | Admin / Super Admin (`kiosks.activate`) |

Operator **cannot** disable a device. That is intentional least privilege.

## Maintenance

Use for planned printer/network work. Active devices in maintenance cannot start new payments (`device.maintenance`). In-flight payments still reconcile on the server. Heartbeat may include `enter_maintenance`.

## Disable (compromise, theft, stubborn client)

1. Admin/Super Admin: **Nonaktifkan** on `/dashboard/kiosks`.
2. Sanctum device tokens are revoked. Further commerce is `401` then blocked.
3. The tablet should show **Kiosk dinonaktifkan** and cannot sell (AC-08).
4. Visitors go to assisted sale.
5. Re-enable is a **new activation** after the device is trusted again — rotate the activation code; do not paste old tokens.

## After disable

- Do not delete historical orders/tickets.
- If the tablet still displays an old QR, validation/check-in still hits Laravel (QR HMAC). Stolen paper tickets remain valid until used/expired/refunded per server rules.
