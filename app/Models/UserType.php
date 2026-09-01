<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'permissions',
    ];

    /**
     * Users assigned to this user type.
     */
    protected $casts = ['permissions' => 'array'];

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public function isSystemAdministrator(): bool
    {
        return $this->name === 'System Administrator';
    }

    public function isAdministrator(): bool
    {
        return $this->name === 'Administrator';
    }

    public function isStandardUser(): bool
    {
        return $this->name === 'Standard User';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_type_id');
    }
}