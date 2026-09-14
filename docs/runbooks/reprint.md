# Reprint (printer degraded)

**Rule:** Print failure after PAID does not void the payment or mint a second active ticket. Payload always comes from Laravel.

## Kiosk

The tablet shows QR + staff reprint copy when the printer adapter fails (`printFailed`). Tickets on the server stay `issued`/`active`.

## Counter — Ticket Officer

1. Open `/dashboard/tickets`.
2. Search the **ticket code** (from the kiosk screen or payment/order).
3. Load print payload (server QR). Print at the desk printer.
4. Record success or failure in the desk UI so audit writes `ticket.printed` / `ticket.reprinted` / `ticket.print_failed`.
5. Confirm ticket status did **not** change because of reprint.

Auditor can view the desk but cannot reprint.

## If the code is unknown

Finance/Manager traces the order in reports by time/channel. Do not recreate tickets with a new payment.
