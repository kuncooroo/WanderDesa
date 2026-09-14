<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class ApplicationBootsTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_stays_ok_during_maintenance_mode(): void
    {
        $this->artisan('down', ['--retry' => 60])->assertSuccessful();

        try {
            $this->get('/up')->assertOk();
            $this->get('/login')->assertStatus(503);
        } finally {
            $this->artisan('up')->assertSuccessful();
        }
    }
}
