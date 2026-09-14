# Payment unknown

Kiosk copy: **“Status belum diketahui — jangan bayar dua kali.”** (`CheckoutPhase.unknownStatus`). Laravel is still the ledger.

## What it means

The tablet lost the network or a poll failed while a payment may already exist as `processing`. The kiosk **must not** show success. It must not start a second visitor payment for the same cart without an operator decision.

## Visitor / attendant

1. Tell the visitor **not** to pay again in the e-wallet app until staff confirm.
2. On the kiosk, use **retry status** (same payment id / poll). Do not invent a new order if the recovery snapshot is still present.
3. If the kiosk offers **retry payment**, that rotates the **payment** idempotency key only. Stop and go to the counter if money may already have left the visitor’s wallet.
4. Assisted fallback: Ticket Officer sells at `/dashboard/assisted-sale` (cash) if the digital attempt is confirmed failed/expired by Laravel.

## Operator / finance

1. Dashboard or API: open the order/payment by number. Trust `payments.status` and `tickets_issued` from Laravel only.
2. If `processing`: wait for sandbox/provider webhook or the `reconcile-open-payments` job (every five minutes). Do not SQL-update to `paid`.
3. If `paid` and tickets exist: reprint or show QR ([reprint.md](reprint.md)). The visitor is done.
4. If `failed` / `expired`: assisted sale if they still want entry. Do not reuse a dead digital payment id.
5. Duplicate webhook `event_id` is safe; conflicting payload on the same id is `409`.

## Never

- Set PAID from Flutter, Blade, or Livewire.
- Refund from Ticket Officer (403). Finance only, and not after `used`.
- Delete `payments` / `tickets` rows to “clean up.”
