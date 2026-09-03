@extends('layouts.material')

@section('title', 'User Page & Functionality Permissions')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editUserModalOpen: false,
    permissionsModalOpen: false,
    
    // User Edit State
    selectedUser: { id: null, name: '', email: '', roles: [], permissions: [] },
    
    // Quick Permission Groups helper for Alpine
    allPermissionIds: {{ json_encode($permissions->pluck('id')) }},
    
    openPermissionsModal(user) {
        this.selectedUser = {
            id: user.id,
            name: user.name,
            email: user.email,
            roles: user.roles || [],
            permissions: user.permissions ? user.permissions.map(p => Number(p)) : []
        };
        this.permissionsModalOpen = true;
    },

    openEditUserModal(user) {
        this.selectedUser = {
            id: user.id,
            name: user.name,
            email: user.email,
            roles: user.roles || [],
            permissions: user.permissions ? user.permissions.map(p => Number(p)) : []
        };
        this.editUserModalOpen = true;
    },

    toggleAllPermissions(enable) {
        if (enable) {
            this.selectedUser.permissions = [...this.allPermissionIds];
        } else {
            this.selectedUser.permissions = [];
        }
    },

    toggleGroupPermissions(groupItemIds, enable) {
        if (enable) {
            groupItemIds.forEach(id => {
                if (!this.selectedUser.permissions.includes(id)) {
                    this.selectedUser.permissions.push(id);
                }
            });
        } else {
            this.selectedUser.permissions = this.selectedUser.permissions.filter(id => !groupItemIds.includes(id));
        }
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 text-indigo-600">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight heading-font">User Access & Page Permissions</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Assign individual user permissions to specific pages, POS terminal, ledgers, discounts, and salon features.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span>Add New User</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search user by name or email..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
            </div>

            <select name="role" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                <option value="">All User Types</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" {{ $roleFilter == $role->slug ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Filter Users
            </button>
            @if($search || $roleFilter)
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center border border-slate-200">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Users & Direct Permissions Matrix Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                        <th class="py-4 px-6">User Account</th>
                        <th class="py-4 px-6">Role / Type</th>
                        <th class="py-4 px-6">Direct Page Access Permissions</th>
                        <th class="py-4 px-6">Discount Authorization</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse($users as $user)
                    @php
                        $userPermissionIds = $user->permissions->pluck('id')->toArray();
                        $hasDiscountPermission = $user->hasRole('admin') || $user->hasPermission('allow-bill-discount');
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-600 text-white font-black text-xs flex items-center justify-center">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 leading-tight text-sm">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-400 font-normal">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-6">
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                    <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold 
                                        @if($role->slug === 'admin') bg-rose-100 text-rose-700 border border-rose-200
                                        @elseif($role->slug === 'manager') bg-amber-100 text-amber-800 border border-amber-200
                                        @elseif($role->slug === 'customer') bg-emerald-100 text-emerald-800 border border-emerald-200
                                        @else bg-indigo-100 text-indigo-800 border border-indigo-200 @endif">
                                        {{ $role->name }}
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400 italic">User</span>
                                @endforelse
                            </div>
                        </td>

                        <td class="py-4 px-6">
                            <div class="space-y-1.5 max-w-md">
                                <div class="flex items-center gap-2">
                                    @if($user->hasRole('admin'))
                                        <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 font-black text-[11px] border border-indigo-200 inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                            Full Super Admin Access (All Pages)
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 font-black text-[11px] border border-slate-200 inline-flex items-center gap-1">
                                            <span>{{ $user->permissions->count() }} Page Permissions Granted</span>
                                        </span>
                                    @endif
                                </div>

                                @if(!$user->hasRole('admin'))
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($user->permissions->take(4) as $perm)
                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold">
                                                {{ $perm->name }}
                                            </span>
                                        @empty
                                            <span class="text-[11px] text-slate-400 italic">No direct permissions assigned</span>
                                        @endforelse
                                        @if($user->permissions->count() > 4)
                                            <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-600 text-[10px] font-black">
                                                +{{ $user->permissions->count() - 4 }} more
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </td>

                        <td class="py-4 px-6">
                            @if($hasDiscountPermission)
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-black text-[11px] border border-emerald-200 inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    ⭐ Full (> 10% Allowed)
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 inline-flex items-center gap-1">
                                    Standard (Up to 10%)
                                </span>
                            @endif
                        </td>

                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="openPermissionsModal(@js([
                                            'id' => $user->id,
                                            'name' => $user->name,
                                            'email' => $user->email,
                                            'roles' => $user->roles->pluck('id')->toArray(),
                                            'permissions' => $user->permissions->pluck('id')->toArray()
                                        ]))" 
                                        class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-black text-[11px] border border-indigo-200 transition-colors inline-flex items-center gap-1.5"
                                        title="Configure User Direct Permissions">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                                    <span>Permissions</span>
                                </button>

                                <button type="button" @click="openEditUserModal(@js([
                                            'id' => $user->id,
                                            'name' => $user->name,
                                            'email' => $user->email,
                                            'roles' => $user->roles->pluck('id')->toArray(),
                                            'permissions' => $user->permissions->pluck('id')->toArray()
                                        ]))" 
                                        class="p-2 text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" 
                                        title="Edit User Credentials">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                @if(auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete user {{ addslashes($user->name) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete User">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400 font-semibold">
                            No user accounts found matching your criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Links -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
            {{ $users->links() }}
        </div>
    </div>

    <!-- MODAL 1: CONFIGURE USER DIRECT PERMISSIONS -->
    <div x-show="permissionsModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto"
         x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="permissionsModalOpen = false"></div>
            <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-5 max-h-[92vh] flex flex-col">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-indigo-600 rounded-full"></span>
                            <h3 class="text-lg font-black text-slate-900 heading-font">
                                Page & Feature Permissions: <span class="text-indigo-600" x-text="selectedUser.name"></span>
                            </h3>
                        </div>
                        <p class="text-xs font-semibold text-slate-400 mt-0.5" x-text="'Account: ' + selectedUser.email"></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="toggleAllPermissions(true)" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-black text-xs border border-indigo-200 transition-colors">
                            ✓ Grant All Pages
                        </button>
                        <button type="button" @click="toggleAllPermissions(false)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-200 transition-colors">
                            ✕ Revoke All
                        </button>
                        <button type="button" @click="permissionsModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Form Body: Organized into Functional Modules -->
                <form :action="'{{ url('admin/users') }}/' + selectedUser.id" method="POST" class="space-y-6 overflow-y-auto pr-1 flex-1">
                    @csrf
                    @method('PUT')
                    
                    <input type="hidden" name="name" :value="selectedUser.name">
                    <input type="hidden" name="email" :value="selectedUser.email">

                    @php
                        $permissionGroups = [
                            [
                                'title' => '📅 Appointments & POS Billing',
                                'slugs' => ['manage-appointments', 'book-appointment', 'allow-bill-discount', 'approve-discounts'],
                                'color' => 'indigo'
                            ],
                            [
                                'title' => '💇‍♀️ Saloon Treatments & Showcase',
                                'slugs' => ['manage-services', 'manage-gallery'],
                                'color' => 'purple'
                            ],
                            [
                                'title' => '🛍️ Products & Inventory',
                                'slugs' => ['manage-sales', 'manage-products', 'manage-purchases'],
                                'color' => 'emerald'
                            ],
                            [
                                'title' => '💳 Accounts, Ledgers & Finance',
                                'slugs' => ['manage-accounts', 'manage-ledger', 'manage-bank-accounts', 'manage-expenses', 'manage-payroll', 'manage-attendance'],
                                'color' => 'amber'
                            ],
                            [
                                'title' => '📊 Business Reports & Intelligence',
                                'slugs' => ['view-reports'],
                                'color' => 'rose'
                            ],
                            [
                                'title' => '⚙️ System & Administration',
                                'slugs' => ['manage-settings', 'manage-users', 'manage-roles'],
                                'color' => 'slate'
                            ]
                        ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($permissionGroups as $grp)
                        @php
                            $groupPerms = $permissions->whereIn('slug', $grp['slugs']);
                            $groupPermIds = $groupPerms->pluck('id')->toArray();
                        @endphp
                        <div class="bg-slate-50 border border-slate-200 p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                                <h4 class="font-black text-xs text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>{{ $grp['title'] }}</span>
                                </h4>
                                <div class="flex items-center gap-2">
                                    <button type="button" 
                                            @click="toggleGroupPermissions(@js($groupPermIds), true)"
                                            class="text-[10px] font-black text-indigo-600 hover:text-indigo-800">
                                        All
                                    </button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button" 
                                            @click="toggleGroupPermissions(@js($groupPermIds), false)"
                                            class="text-[10px] font-bold text-slate-500 hover:text-slate-800">
                                        None
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                @foreach($groupPerms as $perm)
                                <label class="flex items-start gap-2.5 p-2.5 bg-white border cursor-pointer transition-all
                                    {{ $perm->slug === 'allow-bill-discount' ? 'border-amber-300 bg-amber-50/40 hover:bg-amber-100/40' : 'border-slate-200 hover:border-indigo-300' }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" 
                                           :checked="selectedUser.permissions.includes({{ $perm->id }})"
                                           @change="if($event.target.checked){ if(!selectedUser.permissions.includes({{ $perm->id }})) selectedUser.permissions.push({{ $perm->id }}); } else { selectedUser.permissions = selectedUser.permissions.filter(x => x !== {{ $perm->id }}); }"
                                           class="text-indigo-600 focus:ring-indigo-500 mt-0.5">
                                    <div class="flex-1">
                                        <p class="text-xs font-bold text-slate-800 flex items-center justify-between">
                                            <span>
                                                @if($perm->slug === 'allow-bill-discount')
                                                    ⭐ Allow High Discount (> 10%)
                                                @else
                                                    {{ $perm->name }}
                                                @endif
                                            </span>
                                            @if($perm->slug === 'allow-bill-discount')
                                                <span class="px-1.5 py-0.2 bg-amber-100 text-amber-900 text-[9px] font-black uppercase">> 10% Limit</span>
                                            @endif
                                        </p>
                                        <p class="text-[10px] text-slate-400 font-mono">{{ $perm->slug }}</p>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="pt-4 flex items-center justify-between border-t border-slate-100 shrink-0">
                        <span class="text-xs font-bold text-slate-500">
                            Selected: <strong class="text-indigo-600 font-black" x-text="selectedUser.permissions.length"></strong> permissions
                        </span>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="permissionsModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs bg-slate-100 hover:bg-slate-200 border border-slate-200">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs shadow-md">
                                Save User Permissions
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- MODAL 2: CREATE USER -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto"
         x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="createModalOpen = false"></div>
            <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-5 max-h-[92vh] flex flex-col">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 shrink-0">
                    <h3 class="text-lg font-black text-slate-900 flex items-center gap-2 heading-font">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        Create New User & Assign Page Permissions
                    </h3>
                    <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 overflow-y-auto pr-1 flex-1">
                    @csrf

                    <!-- Account Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 bg-slate-50 border border-slate-200">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                            <input type="text" name="name" required placeholder="e.g. Ayesha Khan" 
                                   class="w-full px-3.5 py-2 bg-white border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
                            <input type="email" name="email" required placeholder="ayesha@example.com" 
                                   class="w-full px-3.5 py-2 bg-white border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password</label>
                            <input type="password" name="password" required placeholder="••••••••" 
                                   class="w-full px-3.5 py-2 bg-white border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>

                    <!-- Direct Permissions Matrix -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-black text-slate-900 uppercase tracking-wider">Assign Page & Functionality Permissions</label>
                            <span class="text-[11px] text-indigo-600 font-bold">Check all pages this user should access</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($permissionGroups as $grp)
                            @php
                                $groupPerms = $permissions->whereIn('slug', $grp['slugs']);
                            @endphp
                            <div class="bg-slate-50 border border-slate-200 p-4 space-y-3">
                                <h4 class="font-black text-xs text-slate-900 uppercase tracking-wider border-b border-slate-200/80 pb-2">
                                    {{ $grp['title'] }}
                                </h4>

                                <div class="space-y-2">
                                    @foreach($groupPerms as $perm)
                                    <label class="flex items-start gap-2.5 p-2.5 bg-white border cursor-pointer transition-all
                                        {{ $perm->slug === 'allow-bill-discount' ? 'border-amber-300 bg-amber-50/40 hover:bg-amber-100/40' : 'border-slate-200 hover:border-indigo-300' }}">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" 
                                               class="text-indigo-600 focus:ring-indigo-500 mt-0.5">
                                        <div class="flex-1">
                                            <p class="text-xs font-bold text-slate-800 flex items-center justify-between">
                                                <span>
                                                    @if($perm->slug === 'allow-bill-discount')
                                                        ⭐ {{ $perm->name }}
                                                    @else
                                                        {{ $perm->name }}
                                                    @endif
                                                </span>
                                                @if($perm->slug === 'allow-bill-discount')
                                                    <span class="px-1.5 py-0.2 bg-amber-100 text-amber-900 text-[9px] font-black uppercase">POS Discount</span>
                                                @endif
                                            </p>
                                            <p class="text-[10px] text-slate-400 font-mono">{{ $perm->slug }}</p>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end gap-3 border-t border-slate-100 shrink-0">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs bg-slate-100 hover:bg-slate-200 border border-slate-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs shadow-md">
                            Create User & Assign Access
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: EDIT USER ACCOUNT DETAILS -->
    <div x-show="editUserModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editUserModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit User Profile & Credentials
                </h3>
                <button @click="editUserModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('admin/users') }}/' + selectedUser.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                    <input type="text" name="name" x-model="selectedUser.name" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" x-model="selectedUser.email" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">New Password (Optional)</label>
                    <input type="password" name="password" placeholder="Leave blank to retain existing password" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-indigo-600">
                </div>

                <!-- Preserve Existing User Permissions When Editing Name/Password -->
                <template x-for="permId in selectedUser.permissions" :key="permId">
                    <input type="hidden" name="permissions[]" :value="permId">
                </template>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editUserModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs bg-slate-100 hover:bg-slate-200 border border-slate-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs shadow-md">
                        Update Account
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
