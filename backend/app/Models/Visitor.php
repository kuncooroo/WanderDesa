<?php

namespace App\Models;

use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['external_ref', 'display_name', 'phone', 'email'])]
class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
