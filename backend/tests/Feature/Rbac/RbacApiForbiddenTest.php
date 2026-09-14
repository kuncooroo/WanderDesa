<?php

namespace Tests\Feature\Rbac;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Order;
use App\Support\ApiResponse;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class RbacApiForbiddenTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        Route::middleware(['api', 'auth:sanctum', 'active'])
            ->prefix('api/v1')
            ->get('/__rbac/orders-create', function () {
                Authorizer::authorize(auth()->user(), PermissionName::OrdersCreate);

                return ApiResponse::success(['ok' => true]);
            });
    }

    public function test_missing_permission_returns_auth_forbidden_envelope(): void
    {
        $user = $this->userWithRole(RoleName::Auditor);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/__rbac/orders-create')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_permitted_user_passes_authorizer_on_api_route(): void
    {
        $user = $this->userWithRole(RoleName::TicketOfficer);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/__rbac/orders-create')
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertTrue($user->can('create', Order::class));
    }
}
