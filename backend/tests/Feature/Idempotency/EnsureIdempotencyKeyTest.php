<?php

namespace Tests\Feature\Idempotency;

use App\Http\Middleware\EnsureIdempotencyKey;
use App\Support\ApiResponse;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureIdempotencyKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(EnsureIdempotencyKey::class)->post('/__test/idempotency', function () {
            return ApiResponse::success([
                'ok' => true,
                'key' => request()->attributes->get('idempotency_key'),
            ]);
        });
    }

    public function test_missing_header_is_rejected(): void
    {
        $this->postJson('/__test/idempotency', ['note' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('error.details.0.field', IdempotencyManager::HEADER)
            ->assertJsonPath('error.details.0.code', 'validation.required');
    }

    public function test_blank_header_is_rejected(): void
    {
        $this->withHeaders([IdempotencyManager::HEADER => '   '])
            ->postJson('/__test/idempotency')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_header_longer_than_128_is_rejected(): void
    {
        $this->withHeaders([IdempotencyManager::HEADER => str_repeat('a', 129)])
            ->postJson('/__test/idempotency')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('error.details.0.code', 'validation.max');
    }

    public function test_valid_header_passes_through_and_is_stored_on_the_request(): void
    {
        $this->withHeaders([IdempotencyManager::HEADER => '  kiosk-order-1  '])
            ->postJson('/__test/idempotency')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.key', 'kiosk-order-1');
    }

    public function test_alias_is_registered(): void
    {
        $this->assertSame(
            EnsureIdempotencyKey::class,
            app('router')->getMiddleware()['idempotency'] ?? null,
        );
    }
}
