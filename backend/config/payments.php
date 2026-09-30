<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Digital payment gateway
    |--------------------------------------------------------------------------
    |
    | `sandbox` — local QR without a real provider (dev/tests).
    | `midtrans` — Midtrans Core API QRIS (sandbox or production via keys).
    | `null` — no next_action.
    | Never mark PAID from the client; only webhooks / reconcile.
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox webhook authenticity
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 of the raw body. Fail closed when the secret is empty.
    | Midtrans uses signature_key in the JSON body instead (see midtrans.*).
    |
    */

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', ''),

    'signature_header' => env('PAYMENT_WEBHOOK_SIGNATURE_HEADER', 'X-WanderDesa-Webhook-Signature'),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Core API (QRIS)
    |--------------------------------------------------------------------------
    */

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
        'is_production' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
        'qris_acquirer' => env('MIDTRANS_QRIS_ACQUIRER', 'gopay'),
        'connect_timeout' => (int) env('MIDTRANS_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 15),
    ],

];
