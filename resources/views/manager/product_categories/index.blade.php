@extends('layouts.material')

@section('title', 'Product Categories Management')

@section('content')
<div x-data="{ 
    createModalOpen: {{ $errors->any() && !old('_method') ? 'true' : 'false' }},
    editModalOpen: {{ $errors->any() && old('_method') === 'PUT' ? 'true' : 'false' }},
    isSubmitting: false,
    createImagePreview: null,
    createImageName: '',
    editImagePreview: null,
    editImageName: '',
    editCategory: { 
        id: {{ old('edit_id', 'null') }}, 
        title: {{ json_encode(old('title', '')) }}, 
        description: {{ json_encode(old('description', '')) }}, 
        image: null 
    },

    openCreate() {
        this.isSubmitting = false;
        this.clearCreateImage();
        this.createModalOpen = true;
    },

    handleCreateImage(event) {
        const file = event.target.files[0];
        if (!file) {
            this.clearCreateImage();
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('Image file size exceeds 10MB limit. Please choose a smaller photo.');
            this.clearCreateImage();
            return;
        }
        this.createImageName = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            this.createImagePreview = e.target.result;
        };
        reader.readAsDataURL(file);
    },

    clearCreateImage() {
        this.createImagePreview = null;
        this.createImageName = '';
        const input = document.getElementById('createCategoryImageInput');
        if (input) input.value = '';
    },

    openEdit(cat) {
        this.isSubmitting = false;
        this.clearEditImage();
        this.editCategory = {
            id: cat.id,
            title: cat.title,
            description: cat.description || '',
            image: cat.image_url || (cat.image ? ('/' + cat.image.replace(/^\/+/, '')) : '')
        };
        this.editModalOpen = true;
    },

    handleEditImage(event) {
        const file = event.target.files[0];
        if (!file) {
            this.clearEditImage();
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('Image file size exceeds 10MB limit. Please choose a smaller photo.');
            this.clearEditImage();
            return;
        }
        this.editImageName = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            this.editImagePreview = e.target.result;
        };
        reader.readAsDataURL(file);
    },

    clearEditImage() {
        this.editImagePreview = null;
        this.editImageName = '';
        const input = document.getElementById('editCategoryImageInput');
        if (input) input.value = '';
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-emerald-50 text-emerald-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Product Categories</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Organize inventory into retail categories (Hair Care, Skin Care, Cosmetics, Beard Products, etc.).</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                <span>Products Inventory</span>
            </a>
            <button type="button" 
                    @click="openCreate()" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Product Category</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
    </div>
    @endif

    <!-- Validation Error Alert Banner -->
    @if($errors->any())
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs shadow-2xs">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 font-black text-rose-900">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>Please correct the errors below:</span>
            </div>
            <button type="button" @click="$el.closest('div').remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
        </div>
        <ul class="list-disc list-inside space-y-1 text-rose-700 pl-1 font-semibold">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Search & Filter -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.product-categories.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search product category title or description..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
            </div>

            <button type="submit" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors shrink-0 cursor-pointer">
                Search Categories
            </button>
            @if($search)
                <a href="{{ route('manager.product-categories.index') }}" class="px-3.5 py-2 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center shrink-0">
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
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[10px] font-extrabold uppercase tracking-wider">
                        <th class="py-3.5 px-5">ID</th>
                        <th class="py-3.5 px-5">Image</th>
                        <th class="py-3.5 px-5">Category Title</th>
                        <th class="py-3.5 px-5">Description</th>
                        <th class="py-3.5 px-5 text-center">Assigned Products</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($categories as $cat)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3.5 px-5 font-mono font-bold text-slate-400">#{{ $cat->id }}</td>
                        
                        <!-- Image with fallback -->
                        <td class="py-3.5 px-5">
                            @if($cat->image)
                                <img src="{{ asset($cat->image) }}" 
                                     alt="{{ $cat->title }}" 
                                     class="w-11 h-11 object-cover border border-slate-200 rounded-xs shadow-2xs"
                                     onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                                <div class="hidden w-11 h-11 bg-emerald-50 border border-slate-200 flex items-center justify-center text-emerald-500" title="Image unavailable">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                </div>
                            @else
                                <div class="w-11 h-11 bg-emerald-50 border border-slate-200 flex items-center justify-center text-emerald-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                </div>
                            @endif
                        </td>

                        <!-- Title -->
                        <td class="py-3.5 px-5">
                            <p class="font-extrabold text-slate-900 text-sm leading-tight">{{ $cat->title }}</p>
                        </td>

                        <!-- Description -->
                        <td class="py-3.5 px-5 text-slate-500 max-w-sm truncate">
                            {{ $cat->description ?: 'No description provided.' }}
                        </td>

                        <!-- Assigned Products Count -->
                        <td class="py-3.5 px-5 text-center">
                            <a href="{{ route('manager.products.index', ['category_id' => $cat->id]) }}" 
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-extrabold text-xs rounded-full transition-colors"
                               title="View products in this category">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                <span>{{ $cat->products_count }} {{ Str::plural('Product', $cat->products_count) }}</span>
                            </a>
                        </td>

                        <!-- Actions -->
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" 
                                        @click="openEdit({{ json_encode($cat) }})" 
                                        class="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-xs transition-colors cursor-pointer" 
                                        title="Edit Category">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.product-categories.destroy', $cat) }}" 
                                      onsubmit="return confirm('Are you sure you want to delete category \'{{ addslashes($cat->title) }}\'? Products assigned to it will remain but become uncategorized.');" 
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xs transition-colors cursor-pointer" 
                                            title="Delete Category">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <div class="max-w-xs mx-auto space-y-3">
                                <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                </div>
                                <p class="text-sm font-bold text-slate-700">No product categories found</p>
                                <p class="text-xs text-slate-400">Get started by creating your first product category for cosmetics, shampoos, or skin care.</p>
                                <button type="button" @click="openCreate()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors">
                                    + Add Category
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
        <div class="p-4 border-t border-slate-200 bg-slate-50">
            {{ $categories->links() }}
        </div>
        @endif
    </div>

    <!-- MODAL: ADD PRODUCT CATEGORY -->
    <template x-teleport="body">
        <div x-show="createModalOpen" 
             @click.self="if(!isSubmitting) createModalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
             x-cloak>
            <div class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-5 max-h-[92vh] overflow-y-auto border border-slate-300 relative">
                
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Add Product Category</h3>
                        <p class="text-xs text-slate-500">Group and classify products for inventory and POS sales.</p>
                    </div>
                    <button type="button" 
                            @click="if(!isSubmitting) createModalOpen = false" 
                            :disabled="isSubmitting"
                            :class="isSubmitting ? 'opacity-40 cursor-not-allowed' : 'text-slate-400 hover:text-slate-600'"
                            class="text-xl font-bold p-1 leading-none cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('manager.product-categories.store') }}" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;" 
                      class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Category Title <span class="text-rose-600">*</span></label>
                        <input type="text" 
                               name="title" 
                               value="{{ old('title') }}" 
                               required 
                               placeholder="e.g. Hair Care, Skin Care, Styling Wax" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        @error('title')
                            <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Description <span class="text-[10px] text-slate-400 font-normal lowercase">(optional)</span></label>
                        <textarea name="description" 
                                  rows="3" 
                                  placeholder="Short summary of products in this category..."
                                  class="w-full text-xs font-medium p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Category Image Upload -->
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase text-slate-700">
                            Category Icon / Photo <span class="text-[10px] text-slate-400 font-normal lowercase">(optional — jpg, png, webp, svg, max 10MB)</span>
                        </label>

                        <template x-if="createImagePreview">
                            <div class="p-3 bg-emerald-50/70 border border-emerald-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img :src="createImagePreview" class="w-12 h-12 object-cover border border-emerald-300 rounded-xs bg-white shadow-2xs">
                                    <p class="text-xs font-bold text-slate-900 truncate max-w-[200px]" x-text="createImageName"></p>
                                </div>
                                <button type="button" 
                                        @click="clearCreateImage()" 
                                        class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer">
                                    Remove
                                </button>
                            </div>
                        </template>

                        <div class="relative">
                            <input type="file" 
                                   id="createCategoryImageInput" 
                                   name="image" 
                                   accept="image/*" 
                                   @change="handleCreateImage($event)"
                                   class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 file:cursor-pointer cursor-pointer border border-slate-300 bg-slate-50">
                        </div>
                        @error('image')
                            <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" 
                                @click="createModalOpen = false" 
                                :disabled="isSubmitting"
                                class="px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                            <span x-show="!isSubmitting">Save Category</span>
                            <span x-show="isSubmitting" class="flex items-center gap-1.5" x-cloak>
                                <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>Saving...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- MODAL: EDIT PRODUCT CATEGORY -->
    <template x-teleport="body">
        <div x-show="editModalOpen" 
             @click.self="if(!isSubmitting) editModalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
             x-cloak>
            <div class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-5 max-h-[92vh] overflow-y-auto border border-slate-300 relative">
                
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900" x-text="'Edit Category: ' + editCategory.title"></h3>
                        <p class="text-xs text-slate-500">Update category title, description, and thumbnail image.</p>
                    </div>
                    <button type="button" 
                            @click="if(!isSubmitting) editModalOpen = false" 
                            :disabled="isSubmitting"
                            :class="isSubmitting ? 'opacity-40 cursor-not-allowed' : 'text-slate-400 hover:text-slate-600'"
                            class="text-xl font-bold p-1 leading-none cursor-pointer">&times;</button>
                </div>

                <form :action="'{{ url('manager/product-categories') }}/' + editCategory.id" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;" 
                      class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Category Title <span class="text-rose-600">*</span></label>
                        <input type="text" 
                               name="title" 
                               x-model="editCategory.title" 
                               required 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Description <span class="text-[10px] text-slate-400 font-normal lowercase">(optional)</span></label>
                        <textarea name="description" 
                                  x-model="editCategory.description" 
                                  rows="3" 
                                  class="w-full text-xs font-medium p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0"></textarea>
                    </div>

                    <!-- Category Image Upload -->
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase text-slate-700">
                            Category Icon / Photo <span class="text-[10px] text-slate-400 font-normal lowercase">(optional — leave blank to keep current)</span>
                        </label>

                        <!-- Existing image preview -->
                        <div x-show="editCategory.image && !editImagePreview" class="flex items-center gap-3 p-2.5 bg-slate-50 border border-slate-200">
                            <img :src="editCategory.image" class="w-12 h-12 object-cover border border-slate-300 rounded-xs bg-white">
                            <span class="text-xs text-slate-600 font-semibold">Current Category Image</span>
                        </div>

                        <!-- New image preview -->
                        <template x-if="editImagePreview">
                            <div class="p-3 bg-emerald-50/70 border border-emerald-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img :src="editImagePreview" class="w-12 h-12 object-cover border border-emerald-300 rounded-xs bg-white shadow-2xs">
                                    <p class="text-xs font-bold text-slate-900 truncate max-w-[200px]" x-text="editImageName"></p>
                                </div>
                                <button type="button" 
                                        @click="clearEditImage()" 
                                        class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer">
                                    Remove
                                </button>
                            </div>
                        </template>

                        <div class="relative">
                            <input type="file" 
                                   id="editCategoryImageInput" 
                                   name="image" 
                                   accept="image/*" 
                                   @change="handleEditImage($event)"
                                   class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 file:cursor-pointer cursor-pointer border border-slate-300 bg-slate-50">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" 
                                @click="editModalOpen = false" 
                                :disabled="isSubmitting"
                                class="px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                            <span x-show="!isSubmitting">Update Category</span>
                            <span x-show="isSubmitting" class="flex items-center gap-1.5" x-cloak>
                                <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>Updating...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection
