<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QR authenticity (opaque token)
    |--------------------------------------------------------------------------
    |
    | Crypto details are TBD (docs/03 SRS-TKT-07). Issuance stores a server-
    | verifiable payload; TASK-013 validates it. kid/hint is non-secret.
    |
    */

    'qr_secret' => env('TICKET_QR_SECRET', env('APP_KEY')),

    'qr_kid' => env('TICKET_QR_KID', 'v1'),

];
