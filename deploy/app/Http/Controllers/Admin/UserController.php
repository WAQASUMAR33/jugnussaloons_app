<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Display a listing of users and direct page permissions.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $roleFilter = $request->input('role');

        $usersQuery = User::with(['roles', 'permissions']);

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $usersQuery->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('slug', $roleFilter);
            });
        }

        $users = $usersQuery->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return view('admin.users.index', compact('users', 'roles', 'permissions', 'search', 'roleFilter'));
    }

    /**
     * Store a newly created user with direct page permissions.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Sync direct user permissions
        if (isset($validated['permissions'])) {
            $user->permissions()->sync($validated['permissions']);
        }

        // Sync roles if provided, or default to customer role
        if (!empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        } else {
            $defaultRole = Role::where('slug', 'customer')->first();
            if ($defaultRole) {
                $user->roles()->sync([$defaultRole->id]);
            }
        }

        return redirect()->route('admin.users.index', ['tab' => 'users'])
            ->with('success', "User '{$user->name}' successfully created with assigned page permissions!");
    }

    /**
     * Update the specified user and their direct page permissions.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', Rules\Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        // Sync direct user page permissions
        $user->permissions()->sync($validated['permissions'] ?? []);

        // Sync roles if provided
        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        return redirect()->route('admin.users.index', ['tab' => 'users'])
            ->with('success', "User '{$user->name}' permissions and account details updated successfully!");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own active account!');
        }

        $user->permissions()->detach();
        $user->roles()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User account deleted successfully!');
    }
}
