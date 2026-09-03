<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles belonging to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Direct permissions belonging to the user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    protected ?array $cachedRoleSlugs = null;
    protected ?array $cachedPermissionSlugs = null;

    protected function getRoleSlugs(): array
    {
        if ($this->cachedRoleSlugs === null) {
            $this->cachedRoleSlugs = $this->roles()->get()->map(function ($r) {
                return [strtolower($r->slug ?? ''), strtolower($r->name ?? '')];
            })->flatten()->filter()->values()->all();
        }
        return $this->cachedRoleSlugs;
    }

    protected function getPermissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs === null) {
            $direct = $this->permissions()->get()->map(function ($p) {
                return [strtolower($p->slug ?? ''), strtolower($p->name ?? '')];
            })->flatten();

            $viaRoles = $this->roles()->with('permissions')->get()->flatMap(function ($r) {
                return $r->permissions->map(function ($p) {
                    return [strtolower($p->slug ?? ''), strtolower($p->name ?? '')];
                })->flatten();
            });

            $this->cachedPermissionSlugs = $direct->concat($viaRoles)->filter()->unique()->values()->all();
        }
        return $this->cachedPermissionSlugs;
    }

    /**
     * Check if user has a specific role (by slug or name).
     */
    public function hasRole(string $role): bool
    {
        return in_array(strtolower($role), $this->getRoleSlugs(), true);
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        $userRoles = $this->getRoleSlugs();
        foreach ($roles as $role) {
            if (in_array(strtolower($role), $userRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has a specific permission (direct or via role or admin override).
     */
    public function hasPermission(string $permission): bool
    {
        // Admin has all permissions unconditionally
        if ($this->hasRole('admin')) {
            return true;
        }

        return in_array(strtolower($permission), $this->getPermissionSlugs(), true);
    }

    /**
     * Check if user has any of the specified permissions.
     */
    public function hasAnyPermission(array|string $permissions): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $permissionList = (array) $permissions;
        foreach ($permissionList as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }

        return false;
    }
}
