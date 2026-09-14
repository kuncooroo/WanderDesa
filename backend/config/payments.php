<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Digital payment gateway
    |--------------------------------------------------------------------------
    |
    | Vendor is TBD. Use `sandbox` for local/kiosk QR display without a real
    | provider, or `null` for no next_action. Never mark PAID from the client.
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Webhook authenticity
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 of the raw body. Fail closed when the secret is empty.
    | Header name is sandbox-default until [PAYMENT_PROVIDER] is chosen.
    |
    */

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', ''),

    'signature_header' => env('PAYMENT_WEBHOOK_SIGNATURE_HEADER', 'X-WanderDesa-Webhook-Signature'),

];
