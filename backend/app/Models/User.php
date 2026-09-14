<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('created_at');
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by_user_id');
    }

    public function collectedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'collected_by_user_id');
    }

    public function cashierShifts(): HasMany
    {
        return $this->hasMany(CashierShift::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'checked_in_by_user_id');
    }

    public function hasRole(RoleName|string $role): bool
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $r) => $r->name === $name);
        }

        return $this->roles()->where('roles.name', $name)->exists();
    }

    public function hasPermission(PermissionName|string $permission): bool
    {
        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        foreach ($this->permissionNames() as $held) {
            if ($held === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Effective permissions from all assigned roles (union).
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}
