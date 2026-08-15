<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Display role management.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    /**
     * Store a newly created role with permissions.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? '',
        ]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('admin.users.index', ['tab' => 'roles'])
            ->with('success', "Role '{$role->name}' created successfully!");
    }

    /**
     * Update the specified role and its permissions.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? '',
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.users.index', ['tab' => 'roles'])
            ->with('success', "Role '{$role->name}' updated successfully!");
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role)
    {
        if (in_array($role->slug, ['admin', 'manager', 'customer', 'user'])) {
            return redirect()->route('admin.users.index', ['tab' => 'roles'])
                ->with('error', "System core role '{$role->name}' cannot be deleted!");
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return redirect()->route('admin.users.index', ['tab' => 'roles'])
            ->with('success', 'Role deleted successfully!');
    }
}
