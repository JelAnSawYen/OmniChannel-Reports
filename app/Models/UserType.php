<?php

namespace App\Models;

use App\Support\NaturalSort;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class UserType extends Model
{
    use HasFactory;

    public const ADMINISTRATOR = 'Administrator';

    public const STANDARD_USER = 'Standard User';

    public const ASSIGNABLE_NAMES = [self::ADMINISTRATOR, self::STANDARD_USER];

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

    public function isAdministrator(): bool
    {
        return $this->name === self::ADMINISTRATOR;
    }

    public function isStandardUser(): bool
    {
        return $this->name === self::STANDARD_USER;
    }

    public function isAssignable(): bool
    {
        return in_array($this->name, self::ASSIGNABLE_NAMES, true);
    }

    public static function assignable(): Collection
    {
        $query = static::query()
            ->whereIn('name', self::ASSIGNABLE_NAMES);
        NaturalSort::apply($query, 'name');

        return $query->get();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_type_id');
    }
}
