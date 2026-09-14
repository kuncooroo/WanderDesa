<?php

namespace App\Services\Qr;

use RuntimeException;

/**
 * Opaque, server-verifiable QR material. Signing algorithm may be swapped
 * later without changing IssueTickets (docs/03 SRS-TKT-07).
 */
class QrPayloadGenerator
{
    public const VERSION = 1;

    public function generate(string $ticketCode): GeneratedQrPayload
    {
        $secret = (string) config('tickets.qr_secret', '');

        if ($secret === '') {
            throw new RuntimeException('Ticket QR secret is not configured.');
        }

        $kid = substr((string) config('tickets.qr_kid', 'v1'), 0, 32);
        $nonce = bin2hex(random_bytes(16));
        $mac = hash_hmac('sha256', $ticketCode."\n".$nonce, $secret);
        $payload = 'WD1.'.$kid.'.'.$nonce.'.'.substr($mac, 0, 32);

        if (strlen($payload) > 512) {
            throw new RuntimeException('Generated QR payload exceeds storage limit.');
        }

        return new GeneratedQrPayload(
            payload: $payload,
            hash: hash('sha256', $payload),
            version: self::VERSION,
            secretHint: $kid,
        );
    }

    public function hash(string $payload): string
    {
        return hash('sha256', $payload);
    }

    /**
     * @return array{kid: string, nonce: string, mac: string}|null
     */
    public function parse(string $payload): ?array
    {
        if (preg_match('/^WD1\.([A-Za-z0-9_-]{1,32})\.([a-f0-9]{32})\.([a-f0-9]{32})$/', $payload, $matches) !== 1) {
            return null;
        }

        return [
            'kid' => $matches[1],
            'nonce' => $matches[2],
            'mac' => $matches[3],
        ];
    }

    public function verifyMac(string $payload, string $ticketCode): bool
    {
        $parsed = $this->parse($payload);

        if ($parsed === null) {
            return false;
        }

        $secret = (string) config('tickets.qr_secret', '');

        if ($secret === '') {
            return false;
        }

        $expected = substr(hash_hmac('sha256', $ticketCode."\n".$parsed['nonce'], $secret), 0, 32);

        return hash_equals($expected, $parsed['mac']);
    }
}
