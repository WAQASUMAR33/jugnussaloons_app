@extends('layouts.material')

@section('title', 'Admin Control Panel')

@section('content')
<div class="space-y-6">

    <!-- Birthday & Anniversary Celebrations Popup Alert -->
    @include('partials.celebrations-modal')

    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="p-3 rounded-xl bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight heading-font">Admin System Insights</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">System overview, active user accounts, and assigned roles.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all rounded-xl">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span>Manage Users</span>
            </a>
            <a href="{{ route('admin.discount-requests.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-all rounded-xl">
                <span>Discount Approvals</span>
            </a>
        </div>
    </div>

    <!-- Admin Metric Widgets -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Registered System Users</span>
                <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
            <h3 class="text-3xl font-extrabold text-indigo-600">{{ $usersCount }}</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">Total active user accounts</p>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Configured Roles</span>
                <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
            </div>
            <h3 class="text-3xl font-extrabold text-emerald-600">{{ $rolesCount }}</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">Active customer & employee roles</p>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">System Permissions</span>
                <div class="p-2.5 rounded-xl bg-purple-50 text-purple-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                </div>
            </div>
            <h3 class="text-3xl font-extrabold text-purple-600">{{ $permissionsCount ?? 0 }}</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">Secured route access permissions</p>
        </div>

    </div>

    <!-- Recent Registered Users Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-extrabold text-slate-900 heading-font">Recent Users & Role Assignments</h3>
            <a href="{{ route('admin.users.index') }}" class="text-xs font-bold text-indigo-600 hover:underline">View All Users &rarr;</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase">
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Assigned Roles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm font-medium">
                    @foreach($users as $user)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-3.5 px-4 font-bold text-slate-900 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold flex items-center justify-center text-xs shadow-xs">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                            <span>{{ $user->name }}</span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-500">{{ $user->email }}</td>
                        <td class="py-3.5 px-4">
                            @forelse($user->roles as $role)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                                    {{ $role->name }}
                                </span>
                            @empty
                                <span class="text-slate-400 italic text-xs">No role</span>
                            @endforelse
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
