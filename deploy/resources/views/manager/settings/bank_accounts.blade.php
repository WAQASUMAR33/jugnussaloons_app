@extends('layouts.material')

@section('title', 'Bank Account Details Management')

@section('content')
<div class="space-y-6" x-data="{ 
    createModalOpen: false, 
    editModalOpen: false,
    editAccount: {
        bankid: '',
        bank_name: '',
        account_title: '',
        account_no: '',
        branch_name: '',
        iban: '',
        is_active: true
    },
    openEditModal(acc) {
        this.editAccount = { ...acc };
        this.editModalOpen = true;
    }
}">
    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
        <a href="{{ route('manager.settings.index') }}" 
           class="px-5 py-2.5 font-extrabold text-xs transition-all flex items-center gap-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            Branding & Store Settings
        </a>
        <a href="{{ route('manager.bank-accounts.index') }}" 
           class="px-5 py-2.5 font-extrabold text-xs transition-all flex items-center gap-2 bg-indigo-600 text-white shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
            Bank Accounts Management
        </a>
    </div>

    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-indigo-50 text-indigo-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Bank Accounts Management</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Manage official bank account titles, account numbers, and branch details for company payments.</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-2 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Bank Account
        </button>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <p class="text-sm font-bold">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 shadow-2xs">
            <h4 class="font-extrabold text-sm mb-1">Please fix the following validation errors:</h4>
            <ul class="list-disc list-inside text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.bank-accounts.index') }}" class="flex-1 flex gap-3">
            <input type="text" 
                   name="search" 
                   value="{{ $search }}" 
                   placeholder="Search bank name, account title, or account number..." 
                   class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-600 flex-1">
            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Search
            </button>
            @if($search)
                <a href="{{ route('manager.bank-accounts.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">Bank ID</th>
                        <th class="py-4 px-6">Bank Name</th>
                        <th class="py-4 px-6">Account Title</th>
                        <th class="py-4 px-6">Account Number</th>
                        <th class="py-4 px-6">Branch / IBAN</th>
                        <th class="py-4 px-6">Status</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($bankAccounts as $acc)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <!-- 1. bankid PK -->
                        <td class="py-4 px-6 font-black text-indigo-600 text-xs">
                            #{{ $acc->bankid }}
                        </td>
                        <!-- 2. bank_name -->
                        <td class="py-4 px-6 font-bold text-slate-900">
                            {{ $acc->bank_name }}
                        </td>
                        <!-- 3. account_title -->
                        <td class="py-4 px-6 font-semibold text-slate-700">
                            {{ $acc->account_title }}
                        </td>
                        <!-- 4. account_no -->
                        <td class="py-4 px-6 font-mono font-bold text-indigo-900">
                            {{ $acc->account_no }}
                        </td>
                        <!-- Branch / IBAN -->
                        <td class="py-4 px-6 text-xs text-slate-600">
                            <p class="font-medium">{{ $acc->branch_name ?: '—' }}</p>
                            @if($acc->iban)
                                <span class="font-mono text-[10px] text-slate-400">IBAN: {{ $acc->iban }}</span>
                            @endif
                        </td>
                        <!-- Status -->
                        <td class="py-4 px-6">
                            @if($acc->is_active)
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[11px] font-extrabold rounded-sm">Active</span>
                            @else
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[11px] font-extrabold rounded-sm">Inactive</span>
                            @endif
                        </td>
                        <!-- Actions (CRUD) -->
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEditModal({{ json_encode($acc) }})" 
                                        class="p-2 text-indigo-600 hover:bg-indigo-50 transition-colors rounded-sm"
                                        title="Edit Bank Account">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <form method="POST" action="{{ route('manager.bank-accounts.destroy', $acc->bankid) }}" 
                                      onsubmit="return confirm('Are you sure you want to delete this bank account?');"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="p-2 text-rose-600 hover:bg-rose-50 transition-colors rounded-sm"
                                            title="Delete Bank Account">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400 font-semibold">
                            No bank account details found. Click "Add New Bank Account" to create your first bank record.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bankAccounts->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $bankAccounts->links() }}
            </div>
        @endif
    </div>

    <!-- Create Modal -->
    <div x-show="createModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="createModalOpen = false" 
             class="bg-white max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6">
            
            <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Add New Bank Account</h3>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Enter official bank details for store accounts.</p>
                </div>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.bank-accounts.store') }}" class="space-y-4">
                @csrf
                
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Bank Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="bank_name" required placeholder="e.g. Meezan Bank, HBL, Allied Bank" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Account Title <span class="text-rose-500">*</span></label>
                    <input type="text" name="account_title" required placeholder="e.g. Jugnu Saloon & Spa Private Limited" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Account Number <span class="text-rose-500">*</span></label>
                    <input type="text" name="account_no" required placeholder="e.g. 0101-0104859301" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-mono font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Branch Name</label>
                        <input type="text" name="branch_name" placeholder="e.g. Main Boulevard Branch" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">IBAN (Optional)</label>
                        <input type="text" name="iban" placeholder="PK36MEZN0099..." 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-mono font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="pt-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-700">Set as Active Bank Account</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" @click="createModalOpen = false" class="px-5 py-2.5 text-slate-600 font-extrabold text-xs hover:bg-slate-100">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md">
                        Save Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="editModalOpen = false" 
             class="bg-white max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6">
            
            <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Edit Bank Account #<span x-text="editAccount.bankid"></span></h3>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Update registered bank title and details.</p>
                </div>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/bank-accounts') }}/' + editAccount.bankid" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Bank Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="bank_name" x-model="editAccount.bank_name" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Account Title <span class="text-rose-500">*</span></label>
                    <input type="text" name="account_title" x-model="editAccount.account_title" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Account Number <span class="text-rose-500">*</span></label>
                    <input type="text" name="account_no" x-model="editAccount.account_no" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-mono font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">Branch Name</label>
                        <input type="text" name="branch_name" x-model="editAccount.branch_name" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-extrabold uppercase text-slate-700 mb-1">IBAN (Optional)</label>
                        <input type="text" name="iban" x-model="editAccount.iban" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-mono font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="pt-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" :checked="editAccount.is_active" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-700">Set as Active Bank Account</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" @click="editModalOpen = false" class="px-5 py-2.5 text-slate-600 font-extrabold text-xs hover:bg-slate-100">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md">
                        Update Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
