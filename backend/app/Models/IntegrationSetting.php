<?php

namespace App\Models;

use Database\Factories\IntegrationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['integration', 'provider', 'is_active', 'config_json', 'secrets_encrypted'])]
#[Hidden(['secrets_encrypted'])]
class IntegrationSetting extends Model
{
    /** @use HasFactory<IntegrationSettingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config_json' => 'array',
        ];
    }
}
