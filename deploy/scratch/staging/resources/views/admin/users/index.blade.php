@extends('layouts.material')

@section('title', 'User & Role Management')

@section('content')
<div x-data="{ 
    activeTab: '{{ request('tab', 'users') }}',
    createModalOpen: false,
    editModalOpen: false,
    editUser: { id: null, name: '', email: '', roles: [] },
    createRoleModalOpen: false,
    editRoleModalOpen: false,
    editRole: { id: null, name: '', description: '', permissions: [] }
}" class="space-y-6">

    <!-- Header & Action Bar - Sharp Corners -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 text-indigo-600">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Users & Access Control</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage saloon customers, managers, staff roles, and system permissions.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span>Add New User</span>
            </button>
            <button @click="createRoleModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <span>Create Role</span>
            </button>
        </div>
    </div>

    <!-- Tabs Bar - Sharp Corners -->
    <div class="flex border-b border-slate-200 bg-white p-1.5 shadow-sm max-w-md">
        <button @click="activeTab = 'users'" 
                :class="activeTab === 'users' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                class="flex-1 py-2.5 px-4 font-bold text-xs flex items-center justify-center gap-2 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <span>All Users ({{ $users->total() }})</span>
        </button>
        <button @click="activeTab = 'roles'" 
                :class="activeTab === 'roles' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                class="flex-1 py-2.5 px-4 font-bold text-xs flex items-center justify-center gap-2 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span>Roles & Permissions ({{ $roles->count() }})</span>
        </button>
    </div>

    <!-- TAB 1: USERS LIST -->
    <div x-show="activeTab === 'users'" class="space-y-4">
        
        <!-- Filter & Search Bar - Sharp Corners -->
        <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <form method="GET" action="{{ route('admin.users.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="tab" value="users">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email..." 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
                </div>

                <select name="role" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->slug }}" {{ $roleFilter == $role->slug ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                    Filter Results
                </button>
                @if($search || $roleFilter)
                    <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <!-- Users Table Card - Sharp Corners -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                            <th class="py-4 px-6">User / Customer</th>
                            <th class="py-4 px-6">Assigned Roles</th>
                            <th class="py-4 px-6">Permissions</th>
                            <th class="py-4 px-6">Joined Date</th>
                            <th class="py-4 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($users as $user)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-indigo-600 text-white font-extrabold text-xs flex items-center justify-center">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 leading-tight">{{ $user->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold 
                                            @if($role->slug === 'admin') bg-rose-100 text-rose-700 border border-rose-200
                                            @elseif($role->slug === 'manager') bg-amber-100 text-amber-800 border border-amber-200
                                            @elseif($role->slug === 'customer') bg-emerald-100 text-emerald-800 border border-emerald-200
                                            @else bg-indigo-100 text-indigo-800 border border-indigo-200 @endif">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400 italic">No role</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-700 text-xs font-bold">
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    {{ $user->roles->flatMap->permissions->unique('id')->count() }} Permissions
                                </span>
                            </td>
                            <td class="py-4 px-6 text-xs font-medium text-slate-500">
                                {{ $user->created_at->format('M d, Y') }}
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="editUser = { id: {{ $user->id }}, name: '{{ addslashes($user->name) }}', email: '{{ addslashes($user->email) }}', roles: [{{ implode(',', $user->roles->pluck('id')->toArray()) }}] }; editModalOpen = true" 
                                            class="p-2 text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit User">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>

                                    @if(auth()->id() !== $user->id)
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete {{ addslashes($user->name) }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete User">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold">No users found matching your criteria.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <!-- TAB 2: ROLES & PERMISSIONS -->
    <div x-show="activeTab === 'roles'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($roles as $role)
            <div class="bg-white border border-slate-200 p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold 
                            @if($role->slug === 'admin') bg-rose-100 text-rose-700
                            @elseif($role->slug === 'manager') bg-amber-100 text-amber-800
                            @elseif($role->slug === 'customer') bg-emerald-100 text-emerald-800
                            @else bg-indigo-100 text-indigo-800 @endif">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            {{ $role->name }}
                        </span>
                        <span class="text-xs font-semibold text-slate-400">
                            {{ $role->users->count() }} Users
                        </span>
                    </div>

                    <p class="text-xs text-slate-500 font-medium mb-4 min-h-[36px]">
                        {{ $role->description ?: 'System role' }}
                    </p>

                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Granted Permissions:</h4>
                    <div class="flex flex-wrap gap-1.5 mb-6">
                        @forelse($role->permissions as $perm)
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-[11px] font-semibold flex items-center gap-1">
                                <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ $perm->name }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-400 italic">No permissions assigned</span>
                        @endforelse
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <button @click="editRole = { id: {{ $role->id }}, name: '{{ addslashes($role->name) }}', description: '{{ addslashes($role->description) }}', permissions: [{{ implode(',', $role->permissions->pluck('id')->toArray()) }}] }; editRoleModalOpen = true" 
                            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center gap-1.5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit Role & Permissions
                    </button>

                    @if(!in_array($role->slug, ['admin', 'manager', 'customer', 'user']))
                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete custom role?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-slate-400 hover:text-red-600 transition-colors" title="Delete Custom Role">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- MODAL: CREATE USER - Sharp Corners -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Create New User / Customer
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Sarah Johnson" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" required placeholder="sarah@example.com" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Assign Roles</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($roles as $role)
                        <label class="flex items-center gap-2.5 p-3 border border-slate-200 bg-slate-50 hover:bg-indigo-50/50 cursor-pointer">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-bold text-slate-800">{{ $role->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
