<?php

namespace App\Services\Qr;

final class GeneratedQrPayload
{
    public function __construct(
        public readonly string $payload,
        public readonly string $hash,
        public readonly int $version,
        public readonly string $secretHint,
    ) {}
}
