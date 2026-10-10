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

    /**
     * Cached slug arrays — populated once per request, reused for all checks.
     */
    protected ?array $cachedRoleSlugs = null;
    protected ?array $cachedPermissionSlugs = null;

    /**
     * Eager-load all auth relationships in ONE query batch.
     * Call this once per request (in middleware) to avoid N+1 queries
     * from repeated hasRole() / hasPermission() calls in Blade views.
     */
    public function eagerLoadForAuthChecks(): void
    {
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles.permissions', 'permissions']);
        }
        // Pre-build the cached slug arrays so subsequent calls are instant
        $this->getRoleSlugs();
        $this->getPermissionSlugs();
    }

    protected function getRoleSlugs(): array
    {
        if ($this->cachedRoleSlugs === null) {
            // Use already-loaded relation if available, otherwise load it
            $roles = $this->relationLoaded('roles')
                ? $this->roles
                : $this->roles()->get();

            $this->cachedRoleSlugs = $roles->map(function ($r) {
                return [strtolower($r->slug ?? ''), strtolower($r->name ?? '')];
            })->flatten()->filter()->values()->all();
        }
        return $this->cachedRoleSlugs;
    }

    protected function getPermissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs === null) {
            // Use already-loaded relations if available
            $directPerms = $this->relationLoaded('permissions')
                ? $this->permissions
                : $this->permissions()->get();

            $direct = $directPerms->map(function ($p) {
                return [strtolower($p->slug ?? ''), strtolower($p->name ?? '')];
            })->flatten();

            $roles = $this->relationLoaded('roles')
                ? $this->roles
                : $this->roles()->with('permissions')->get();

            $viaRoles = $roles->flatMap(function ($r) {
                $perms = $r->relationLoaded('permissions')
                    ? $r->permissions
                    : $r->permissions()->get();
                return $perms->map(function ($p) {
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

    /**
     * Check if user is authorized to give discounts (Admin or direct permission assigned).
     */
    public function canGiveDiscount(): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains(function ($p) {
                return strtolower($p->slug ?? '') === 'allow-bill-discount';
            });
        }

        return $this->permissions()->where('slug', 'allow-bill-discount')->exists();
    }
}


