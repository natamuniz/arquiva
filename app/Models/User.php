<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Enums\Secrecy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property int|null $unit_id
 * @property Secrecy $clearance
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role', 'unit_id', 'clearance'];

    protected $hidden = ['password'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'clearance' => Secrecy::class,
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function belongsToUnit(?int $unitId): bool
    {
        return $this->unit_id !== null && $this->unit_id === $unitId;
    }
}
