<?php

namespace Database\Factories;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoredFile>
 */
class StoredFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->uuid().'.png';

        return [
            'disk' => 'local',
            'path' => 'uploads/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', Str::random(16)),
            'uploaded_by_user_id' => null,
        ];
    }
}
