@extends('layouts.material')

@section('title', 'Accounts & Client Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    transactionModalOpen: false,
    transactionType: 'receiving',
    categories: {{ json_encode($categories->map(fn($c) => ['id' => (int)$c->id, 'title' => $c->title])) }},
    createCategoryId: '',
    activeAccount: { id: null, name: '', balance: 0, category: '' },
    editAccount: { id: null, account_category_id: '', name: '', father_name: '', address: '', date_of_birth: '', date_of_anniversary: '', phone_no1: '', phone_no2: '', card_no: '', card_type: '', username: '', emp_type: 'junior', salary: 0, balance: 0 },
    isCustomerCategory(catId) {
        if (!catId) return false;
        const cat = this.categories.find(c => c.id == catId);
        if (!cat) return false;
        const title = cat.title.toLowerCase();
        return title.includes('customer') || title.includes('client') || title.includes('vip') || title.includes('member');
    },
    isEmployeeCategory(catId) {
        if (!catId) return false;
        const cat = this.categories.find(c => c.id == catId);
        if (!cat) return false;
        const title = cat.title.toLowerCase();
        return title.includes('employee') || title.includes('staff') || title.includes('stylist') || title.includes('worker');
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Accounts & Client Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Register customer profiles, card types, track opening balances, anniversaries, and contact numbers.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('manager.account-categories.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                Manage Categories
            </a>
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span>Add New Account</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.accounts.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, father name, phone, or card no..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
            </div>

            <select name="category_id" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                        {{ $category->title }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Search Accounts
            </button>
            @if($search || $categoryId)
                <a href="{{ route('manager.accounts.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Accounts Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Client / Account Name</th>
                        <th class="py-4 px-6">Category</th>
                        <th class="py-4 px-6">Phone Numbers</th>
                        <th class="py-4 px-6">Card Info / Type</th>
                        <th class="py-4 px-6">DOB / Anniversary</th>
                        <th class="py-4 px-6">Balance</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($accounts as $account)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-bold text-xs text-slate-400">#{{ $account->id }}</td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $account->name }}</p>
                            @if($account->username)
                                <p class="text-xs font-semibold text-indigo-600">@ {{ $account->username }}</p>
                            @endif
                            @if($account->father_name)
                                <p class="text-xs text-slate-500">S/O, D/O: {{ $account->father_name }}</p>
                            @endif
                            @if($account->address)
                                <p class="text-[11px] text-slate-400 truncate max-w-xs mt-0.5">{{ $account->address }}</p>
                            @endif
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-2.5 py-1 bg-indigo-100 text-indigo-800 text-xs font-bold">
                                {{ $account->category->title ?? 'General' }}
                            </span>
                            @if($account->emp_type)
                                <span class="inline-block ml-1 px-2 py-0.5 text-[10px] font-black uppercase rounded tracking-wider {{ strtolower($account->emp_type) === 'senior' ? 'bg-purple-100 text-purple-900 border border-purple-300' : 'bg-sky-100 text-sky-900 border border-sky-300' }}">
                                    {{ ucfirst($account->emp_type) }}
                                </span>
                            @endif
                            @if($account->salary > 0)
                                <p class="text-[11px] text-emerald-700 font-bold mt-0.5">Salary: {{ number_format($account->salary, 2) }}</p>
                            @endif
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-800 text-xs">{{ $account->phone_no1 }}</p>
                            @if($account->phone_no2)
                                <p class="text-[11px] text-slate-500">{{ $account->phone_no2 }}</p>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-xs">
                            @if($account->card_type || $account->card_no)
                                <div class="flex flex-col gap-1">
                                     @if($account->card_type === 'Gold')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-[10px] uppercase rounded w-max">
                                            ⭐ Gold Card
                                        </span>
                                    @elseif($account->card_type === 'Platinum')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-100 text-purple-900 border border-purple-300 font-extrabold text-[10px] uppercase rounded w-max">
                                            💎 Platinum Card
                                        </span>
                                    @elseif($account->card_type === 'Silver')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-800 border border-slate-300 font-extrabold text-[10px] uppercase rounded w-max">
                                            🛡️ Silver Card
                                        </span>
                                    @elseif($account->card_type === 'No Card')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 font-bold text-[10px] uppercase rounded w-max">
                                            🚫 No Card
                                        </span>
                                    @endif
                                    @if($account->card_no)
                                        <span class="font-mono text-slate-700 text-xs font-semibold">#{{ $account->card_no }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600">
                            @if($account->date_of_birth)
                                <div><strong class="text-slate-400">DOB:</strong> {{ $account->date_of_birth->format('M d, Y') }}</div>
                            @endif
                            @if($account->date_of_anniversary)
                                <div><strong class="text-slate-400">Anniv:</strong> {{ $account->date_of_anniversary->format('M d, Y') }}</div>
                            @endif
                            @if(!$account->date_of_birth && !$account->date_of_anniversary)
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-extrabold text-xs {{ $account->balance >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ number_format($account->balance, 2) }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Receive Payment Action -->
                                <button @click="activeAccount = { id: {{ $account->id }}, name: '{{ addslashes($account->name) }}', balance: {{ $account->balance }}, category: '{{ addslashes($account->category->title ?? 'General') }}' }; transactionType = 'receiving'; transactionModalOpen = true" 
                                        class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-extrabold text-[11px] flex items-center gap-1 transition-colors" title="Receive Cash Payment">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    <span>Receive</span>
                                </button>

                                <!-- Make Payment Action -->
                                <button @click="activeAccount = { id: {{ $account->id }}, name: '{{ addslashes($account->name) }}', balance: {{ $account->balance }}, category: '{{ addslashes($account->category->title ?? 'General') }}' }; transactionType = 'payment'; transactionModalOpen = true" 
                                        class="px-2.5 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 font-extrabold text-[11px] flex items-center gap-1 transition-colors" title="Make Cash Payment">
                                    <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                                    <span>Payment</span>
                                </button>

                                <!-- Ledger Link -->
                                <a href="{{ route('manager.ledger.index', ['account_id' => $account->id]) }}" 
                                   class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="View Account Ledger">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </a>

                                <button @click="editAccount = { 
                                            id: {{ $account->id }}, 
                                            account_category_id: {{ $account->account_category_id }}, 
                                            name: '{{ addslashes($account->name) }}', 
                                            father_name: '{{ addslashes($account->father_name ?? '') }}', 
                                            address: '{{ addslashes($account->address ?? '') }}', 
                                            date_of_birth: '{{ $account->date_of_birth ? $account->date_of_birth->format('Y-m-d') : '' }}', 
                                            date_of_anniversary: '{{ $account->date_of_anniversary ? $account->date_of_anniversary->format('Y-m-d') : '' }}', 
                                            phone_no1: '{{ addslashes($account->phone_no1) }}', 
                                            phone_no2: '{{ addslashes($account->phone_no2 ?? '') }}', 
                                            card_no: '{{ addslashes($account->card_no ?? '') }}', 
                                            card_type: '{{ addslashes($account->card_type ?? '') }}', 
                                            username: '{{ addslashes($account->username ?? '') }}', 
                                            emp_type: '{{ addslashes($account->emp_type ?? 'junior') }}',
                                            salary: {{ $account->salary ?? 0 }},
                                            balance: {{ $account->balance }} 
                                        }; editModalOpen = true" 
                                        class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit Account">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.accounts.destroy', $account) }}" onsubmit="return confirm('Delete account {{ addslashes($account->name) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Account">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No accounts registered yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $accounts->links() }}
        </div>
    </div>

    <!-- MODAL: CREATE ACCOUNT -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-2xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Create Account
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.accounts.store') }}" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Account Category</label>
                        <select name="account_category_id" x-model="createCategoryId" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Sarah Johnson" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Father Name (Optional)</label>
                        <input type="text" name="father_name" placeholder="e.g. Robert Johnson" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Opening Balance</label>
                        <input type="number" step="0.01" name="balance" placeholder="0.00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Phone (Phone 1)</label>
                        <input type="text" name="phone_no1" required placeholder="+1 555-0192" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Secondary Phone (Optional)</label>
                        <input type="text" name="phone_no2" placeholder="+1 555-0193" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <!-- CUSTOMER SPECIFIC FIELDS -->
                <div x-show="isCustomerCategory(createCategoryId)" x-transition class="p-4 bg-indigo-50/70 border border-indigo-200 rounded-lg space-y-4">
                    <div class="flex items-center gap-2 text-indigo-900 font-bold text-xs uppercase border-b border-indigo-200 pb-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 00-3 3b2 2 0 002 2h2m4-3a3 3 0 013 3b2 2 0 01-2 2h-2"></path></svg>
                        <span>Customer Membership & Personal Details</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Username (Customer Login)</label>
                            <input type="text" name="username" placeholder="e.g. sarah_j" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password</label>
                            <input type="password" name="password" placeholder="••••••••" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Card Type</label>
                            <select name="card_type" class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                                <option value="">Select Card Type</option>
                                <option value="No Card">No Card</option>
                                <option value="Silver">Silver Card</option>
                                <option value="Gold">Gold Card</option>
                                <option value="Platinum">Platinum Card</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Card Number (Optional)</label>
                            <input type="text" name="card_no" placeholder="e.g. CRD-99823" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Anniversary Date</label>
                            <input type="date" name="date_of_anniversary" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>
                </div>

                <!-- EMPLOYEE SPECIFIC FIELDS -->
                <div x-show="isEmployeeCategory(createCategoryId)" x-transition class="p-4 bg-purple-50/70 border border-purple-200 rounded-lg space-y-4">
                    <div class="flex items-center gap-2 text-purple-900 font-bold text-xs uppercase border-b border-purple-200 pb-2">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span>Employee Designation, Level & Salary</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Employee Type</label>
                            <select name="emp_type" class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                                <option value="junior">Junior</option>
                                <option value="senior">Senior</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Monthly Salary</label>
                            <input type="number" step="0.01" min="0" name="salary" placeholder="e.g. 50000.00" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Address</label>
                    <textarea name="address" rows="2" placeholder="Street address, city, state..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT ACCOUNT -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-2xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Account
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/accounts') }}/' + editAccount.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Account Category</label>
                        <select name="account_category_id" x-model="editAccount.account_category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" x-model="editAccount.name" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Father Name (Optional)</label>
                        <input type="text" name="father_name" x-model="editAccount.father_name" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Balance</label>
                        <input type="number" step="0.01" name="balance" x-model.number="editAccount.balance" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Phone (Phone 1)</label>
                        <input type="text" name="phone_no1" x-model="editAccount.phone_no1" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Secondary Phone (Optional)</label>
                        <input type="text" name="phone_no2" x-model="editAccount.phone_no2" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <!-- CUSTOMER SPECIFIC FIELDS -->
                <div x-show="isCustomerCategory(editAccount.account_category_id)" x-transition class="p-4 bg-indigo-50/70 border border-indigo-200 rounded-lg space-y-4">
                    <div class="flex items-center gap-2 text-indigo-900 font-bold text-xs uppercase border-b border-indigo-200 pb-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 00-3 3b2 2 0 002 2h2m4-3a3 3 0 013 3b2 2 0 01-2 2h-2"></path></svg>
                        <span>Customer Membership & Personal Details</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Username (Customer Login)</label>
                            <input type="text" name="username" x-model="editAccount.username" placeholder="e.g. sarah_j" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">New Password</label>
                            <input type="password" name="password" placeholder="Leave blank to keep unchanged" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Card Type</label>
                            <select name="card_type" x-model="editAccount.card_type" class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                                <option value="">Select Card Type</option>
                                <option value="No Card">No Card</option>
                                <option value="Silver">Silver Card</option>
                                <option value="Gold">Gold Card</option>
                                <option value="Platinum">Platinum Card</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Card Number (Optional)</label>
                            <input type="text" name="card_no" x-model="editAccount.card_no" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth" x-model="editAccount.date_of_birth" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Anniversary Date</label>
                            <input type="date" name="date_of_anniversary" x-model="editAccount.date_of_anniversary" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>
                </div>

                <!-- EMPLOYEE SPECIFIC FIELDS -->
                <div x-show="isEmployeeCategory(editAccount.account_category_id)" x-transition class="p-4 bg-purple-50/70 border border-purple-200 rounded-lg space-y-4">
                    <div class="flex items-center gap-2 text-purple-900 font-bold text-xs uppercase border-b border-purple-200 pb-2">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span>Employee Designation, Level & Salary</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Employee Type</label>
                            <select name="emp_type" x-model="editAccount.emp_type" class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                                <option value="junior">Junior</option>
                                <option value="senior">Senior</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Monthly Salary</label>
                            <input type="number" step="0.01" min="0" name="salary" x-model.number="editAccount.salary" placeholder="e.g. 50000.00" 
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-indigo-600">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Address</label>
                    <textarea name="address" rows="2" x-model="editAccount.address" 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Update Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: RECORD PAYMENT OR RECEIVING -->
    <div x-show="transactionModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="transactionModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <template x-if="transactionType === 'receiving'">
                        <span class="flex items-center gap-2 text-emerald-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Receive Cash Payment
                        </span>
                    </template>
                    <template x-if="transactionType === 'payment'">
                        <span class="flex items-center gap-2 text-purple-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                            Make Cash Payment
                        </span>
                    </template>
                </h3>
                <button @click="transactionModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Account Summary Box -->
            <div class="p-3.5 bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase text-slate-400">Account Name</p>
                    <h4 class="text-sm font-extrabold text-slate-900" x-text="activeAccount.name"></h4>
                    <span class="text-[10px] font-bold text-indigo-600" x-text="activeAccount.category"></span>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold uppercase text-slate-400">Current Balance</p>
                    <h4 class="text-base font-black text-slate-800" x-text="activeAccount.balance"></h4>
                </div>
            </div>

            <form :action="'{{ url('manager/accounts') }}/' + activeAccount.id + '/transaction'" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="transaction_type" :value="transactionType">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        <span x-text="transactionType === 'receiving' ? 'Receiving Amount' : 'Payment Amount'"></span>
                    </label>
                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-base font-black text-slate-900 focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Transaction Date</label>
                        <input type="date" name="date" value="{{ date('Y-m-d') }}" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Reference # (Optional)</label>
                        <input type="text" name="reference_no" placeholder="e.g. REC-1002, Cash Receipt" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description / Notes (Optional)</label>
                    <textarea name="description" rows="2" placeholder="Transaction notes, payment method..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="transactionModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" 
                            :class="transactionType === 'receiving' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-purple-600 hover:bg-purple-700'"
                            class="px-6 py-2.5 text-white font-bold text-xs shadow-md transition-all">
                        <span x-text="transactionType === 'receiving' ? 'Record Receiving & Generate Ledger' : 'Record Payment & Generate Ledger'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
