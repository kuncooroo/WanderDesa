<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests reuse one app instance per method. Clear the Sanctum request
     * guard cache so bearer tokens are re-validated on every HTTP call, without
     * wiping web-guard actingAs() state used by dashboard tests.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        if (isset($this->app['auth']) && array_key_exists('sanctum', config('auth.guards', []))) {
            $this->app['auth']->guard('sanctum')->forgetUser();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
