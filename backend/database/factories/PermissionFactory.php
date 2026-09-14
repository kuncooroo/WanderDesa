<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->bothify('resource.###');

        return [
            'name' => $name,
            'display_name' => str($name)->replace('.', ' ')->title()->toString(),
        ];
    }
}
