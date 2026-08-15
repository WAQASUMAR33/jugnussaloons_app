@extends('layouts.material')

@section('title', 'Expense Categories')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    editCategory: { id: null, title: '' }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-rose-50 text-rose-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Expense Categories</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage expense types and categories for business expenditure tracking.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('manager.expenses.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                &larr; Back to Expenses
            </a>
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-3 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                <span>Add Expense Category</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.expense-categories.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search category title..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600 focus:bg-white transition-all">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Search
            </button>
            @if($search)
                <a href="{{ route('manager.expense-categories.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Category Title</th>
                        <th class="py-4 px-6">Associated Expenses</th>
                        <th class="py-4 px-6">Date Created</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($categories as $category)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-bold text-xs text-slate-400">#{{ $category->id }}</td>
                        <td class="py-4 px-6 font-extrabold text-slate-900">{{ $category->title }}</td>
                        <td class="py-4 px-6">
                            <span class="px-2.5 py-1 bg-rose-50 text-rose-700 font-extrabold text-xs">
                                {{ $category->expenses_count }} {{ Str::plural('expense', $category->expenses_count) }}
                            </span>
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-500">
                            {{ $category->created_at ? $category->created_at->format('M d, Y') : '—' }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="editCategory = { id: {{ $category->id }}, title: '{{ addslashes($category->title) }}' }; editModalOpen = true" 
                                        class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Edit Category">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <form method="POST" action="{{ route('manager.expense-categories.destroy', $category) }}" onsubmit="return confirm('Delete category {{ addslashes($category->title) }}? All linked expenses will be removed.');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Category">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No expense categories registered yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-100">
            {{ $categories->links() }}
        </div>
    </div>

    <!-- MODAL: CREATE CATEGORY -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-md w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900">Add Expense Category</h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('manager.expense-categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category Title</label>
                    <input type="text" name="title" required placeholder="e.g. Utility Bills, Rent, Equipment Maintenance" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                </div>
                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-rose-600 text-white font-bold text-xs shadow-md">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT CATEGORY -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-md w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900">Edit Expense Category</h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form :action="'{{ url('manager/expense-categories') }}/' + editCategory.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category Title</label>
                    <input type="text" name="title" x-model="editCategory.title" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                </div>
                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-rose-600 text-white font-bold text-xs shadow-md">Update Category</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
