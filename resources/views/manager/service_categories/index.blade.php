@extends('layouts.material')

@section('title', 'Service Categories Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    editCategory: { id: null, title: '', description: '', image: null }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Saloon Service Categories</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage service grouping categories (Hair Styling, Facial Spa, Beard Grooming, Hair Coloring, etc.).</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Add Service Category</span>
        </button>
    </div>

    <!-- Search & Filter -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.service-categories.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search category title or description..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
            </div>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Search Categories
            </button>
            @if($search)
                <a href="{{ route('manager.service-categories.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Categories Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Image</th>
                        <th class="py-4 px-6">Category Title</th>
                        <th class="py-4 px-6">Description</th>
                        <th class="py-4 px-6">Assigned Services</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($categories as $cat)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-mono font-bold text-xs text-slate-400">
                            #{{ $cat->id }}
                        </td>
                        <td class="py-4 px-6">
                            @if($cat->image)
                                <img src="{{ asset($cat->image) }}" alt="{{ $cat->title }}" class="w-12 h-12 rounded object-cover border border-slate-200 shadow-sm">
                            @else
                                <div class="w-12 h-12 rounded bg-indigo-50 border border-slate-200 flex items-center justify-center text-indigo-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-bold text-slate-900">
                            {{ $cat->title }}
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-500 max-w-xs truncate">
                            {{ $cat->description ?: 'No description provided.' }}
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 font-extrabold text-xs">
                                {{ $cat->services_count }} Treatments
                            </span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="editCategory = { id: {{ $cat->id }}, title: '{{ addslashes($cat->title) }}', description: '{{ addslashes($cat->description ?? '') }}', image: '{{ $cat->image ? asset($cat->image) : '' }}' }; editModalOpen = true" 
                                        class="p-2 text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit Category">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.service-categories.destroy', $cat) }}" onsubmit="return confirm('Delete service category {{ addslashes($cat->title) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Category">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No service categories created yet.</p>
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

    <!-- MODAL: ADD SERVICE CATEGORY -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-md w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    New Service Category
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.service-categories.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category Title</label>
                    <input type="text" name="title" required placeholder="e.g. Haircuts & Styling, Facial Spa" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Category details and treatment guidelines..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category Image (Optional)</label>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs text-slate-600 file:mr-4 file:py-1.5 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-1">Recommended format: JPG, PNG, WEBP, or SVG (Max 4MB).</p>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Save Service Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT SERVICE CATEGORY -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-md w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 113.536 3.536L12 20.293H8v-4.000l9.586-9.586z"></path></svg>
                    Edit Service Category
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/service-categories') }}/' + editCategory.id" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category Title</label>
                    <input type="text" name="title" x-model="editCategory.title" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description (Optional)</label>
                    <textarea name="description" rows="3" x-model="editCategory.description" 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category Image (Optional)</label>
                    <template x-if="editCategory.image">
                        <div class="mb-2 flex items-center gap-3 p-2 bg-slate-50 border border-slate-200 rounded">
                            <img :src="editCategory.image" class="w-12 h-12 object-cover rounded border border-slate-200 shadow-sm">
                            <span class="text-xs text-slate-500 font-semibold">Current Image</span>
                        </div>
                    </template>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs text-slate-600 file:mr-4 file:py-1.5 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-1">Select a new image to replace the current one.</p>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
