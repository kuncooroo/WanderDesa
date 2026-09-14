<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsApiEnvelope
{
    protected function assertSuccessEnvelope(TestResponse $response, ?int $status = null): void
    {
        if ($status !== null) {
            $response->assertStatus($status);
        }

        $response->assertJsonStructure([
            'success',
            'data',
            'request_id',
        ])->assertJsonPath('success', true);

        $this->assertIsString($response->json('request_id'));
        $this->assertNotSame('', $response->json('request_id'));
    }

    protected function assertErrorEnvelope(
        TestResponse $response,
        int $status,
        string $errorCode,
    ): void {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'success',
                'error' => ['code', 'message', 'details'],
                'request_id',
            ])
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', $errorCode);
    }
}
