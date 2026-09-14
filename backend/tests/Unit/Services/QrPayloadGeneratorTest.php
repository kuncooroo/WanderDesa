<?php

namespace Tests\Unit\Services;

use App\Services\Qr\QrPayloadGenerator;
use Tests\TestCase;

class QrPayloadGeneratorTest extends TestCase
{
    public function test_payload_is_opaque_and_hashed(): void
    {
        config(['tickets.qr_secret' => 'test-ticket-qr-secret', 'tickets.qr_kid' => 'v1']);

        $generator = new QrPayloadGenerator;
        $first = $generator->generate('TCK-TEST1');
        $second = $generator->generate('TCK-TEST1');

        $this->assertStringStartsWith('WD1.v1.', $first->payload);
        $this->assertSame(64, strlen($first->hash));
        $this->assertSame(hash('sha256', $first->payload), $first->hash);
        $this->assertNotSame($first->payload, $second->payload);
        $this->assertSame(1, $first->version);
        $this->assertSame('v1', $first->secretHint);
        $this->assertLessThanOrEqual(512, strlen($first->payload));
        $this->assertTrue($generator->verifyMac($first->payload, 'TCK-TEST1'));
        $this->assertFalse($generator->verifyMac($first->payload, 'TCK-OTHER'));
        $this->assertNull($generator->parse('not-a-qr'));
    }
}
