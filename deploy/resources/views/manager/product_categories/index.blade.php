@extends('layouts.material')

@section('title', 'Product Categories & Main Categories Management')

@section('content')
<div x-data="{ 
    activeTab: '{{ request('tab', $activeTab ?? 'sub') }}', // 'sub' or 'main'
    createSubModalOpen: false,
    editSubModalOpen: false,
    createMainModalOpen: false,
    editMainModalOpen: false,
    isSubmitting: false,
    
    // Sub Category State
    editSubCategory: { id: null, product_main_category_id: '', title: '', description: '', image: null },
    subImagePreview: null,
    subImageName: '',

    // Main Category State
    editMainCategory: { id: null, title: '', description: '', image: null },
    mainImagePreview: null,
    mainImageName: '',

    // Sub Modal Helpers
    openCreateSub() {
        this.isSubmitting = false;
        this.subImagePreview = null;
        this.subImageName = '';
        this.createSubModalOpen = true;
    },
    openEditSub(cat) {
        this.isSubmitting = false;
        this.subImagePreview = null;
        this.subImageName = '';
        this.editSubCategory = {
            id: cat.id,
            product_main_category_id: cat.product_main_category_id || '',
            title: cat.title,
            description: cat.description || '',
            image: cat.image_url || (cat.image ? ('/' + cat.image.replace(/^\/+/, '')) : '')
        };
        this.editSubModalOpen = true;
    },

    // Main Modal Helpers
    openCreateMain() {
        this.isSubmitting = false;
        this.mainImagePreview = null;
        this.mainImageName = '';
        this.createMainModalOpen = true;
    },
    openEditMain(mainCat) {
        this.isSubmitting = false;
        this.mainImagePreview = null;
        this.mainImageName = '';
        this.editMainCategory = {
            id: mainCat.id,
            title: mainCat.title,
            description: mainCat.description || '',
            image: mainCat.image_url || (mainCat.image ? ('/' + mainCat.image.replace(/^\/+/, '')) : '')
        };
        this.editMainModalOpen = true;
    },

    handleImage(event, target) {
        const file = event.target.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            alert('Image size exceeds 10MB limit. Please choose a smaller file.');
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            if (target === 'sub') {
                this.subImagePreview = e.target.result;
                this.subImageName = file.name;
            } else {
                this.mainImagePreview = e.target.result;
                this.mainImageName = file.name;
            }
        };
        reader.readAsDataURL(file);
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
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Product Categories & Taxonomy</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage dynamic Main Categories and their linked Sub Categories for products & POS checkout.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                <span>Products Inventory</span>
            </a>
            <button type="button" @click="openCreateMain()" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>+ New Main Category</span>
            </button>
            <button type="button" @click="openCreateSub()" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>+ New Sub Category</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
    </div>
    @endif

    <!-- TAB SWITCHER NAVIGATION -->
    <div class="flex border-b border-slate-200 bg-white px-4 pt-3 gap-4">
        <button type="button" 
                @click="activeTab = 'sub'" 
                :class="activeTab === 'sub' ? 'border-emerald-600 text-emerald-700 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 font-bold'"
                class="py-3 px-3 text-xs border-b-2 transition-all flex items-center gap-2">
            <span>🏷️ Sub Categories</span>
            <span class="px-2 py-0.5 text-[10px] rounded-full" 
                  :class="activeTab === 'sub' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                {{ $categories->total() }}
            </span>
        </button>

        <button type="button" 
                @click="activeTab = 'main'" 
                :class="activeTab === 'main' ? 'border-indigo-600 text-indigo-700 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 font-bold'"
                class="py-3 px-3 text-xs border-b-2 transition-all flex items-center gap-2">
            <span>🏛️ Main Categories (Dynamic)</span>
            <span class="px-2 py-0.5 text-[10px] rounded-full" 
                  :class="activeTab === 'main' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600'">
                {{ $mainCategories->count() }}
            </span>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: SUB CATEGORIES LIST & FILTERING     -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'sub'" class="space-y-4">
        <!-- Filter & Search Bar -->
        <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <form method="GET" action="{{ route('manager.product-categories.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3 w-full">
                <input type="hidden" name="tab" value="sub">
                
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search sub categories..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 text-xs font-semibold focus:bg-white focus:border-emerald-600 focus:ring-0">
                </div>

                <!-- Main Category Filter -->
                <div class="w-full sm:w-64">
                    <select name="main_category_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 text-xs font-bold focus:bg-white focus:border-emerald-600 focus:ring-0">
                        <option value="">All Main Categories</option>
                        @foreach($mainCategories as $mc)
                            <option value="{{ $mc->id }}" {{ (string)$mainCategoryId === (string)$mc->id ? 'selected' : '' }}>
                                🏛️ {{ $mc->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="px-4 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors shrink-0">
                    Filter
                </button>
                @if($search || $mainCategoryId)
                    <a href="{{ route('manager.product-categories.index', ['tab' => 'sub']) }}" class="px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 flex items-center justify-center shrink-0">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <!-- Sub Categories Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @forelse($categories as $cat)
            <div class="bg-white border border-slate-200 shadow-sm p-4 flex flex-col justify-between hover:border-emerald-500 transition-all group">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="w-12 h-12 bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 overflow-hidden">
                            @if($cat->image_url)
                                <img src="{{ $cat->image_url }}" alt="{{ $cat->title }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-xl">🏷️</span>
                            @endif
                        </div>
                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-black uppercase border border-indigo-200">
                            {{ $cat->mainCategory ? $cat->mainCategory->title : 'Unassigned' }}
                        </span>
                    </div>

                    <div>
                        <h3 class="font-extrabold text-sm text-slate-900 group-hover:text-emerald-700 transition-colors">{{ $cat->title }}</h3>
                        <p class="text-xs text-slate-500 line-clamp-2 mt-0.5">{{ $cat->description ?: 'No description provided.' }}</p>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="{{ route('manager.products.index', ['category_id' => $cat->id]) }}" 
                       class="font-mono font-bold text-emerald-700 hover:underline">
                        {{ $cat->products_count }} Products
                    </a>

                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="openEditSub({{ json_encode($cat) }})" 
                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px]">
                            Edit
                        </button>
                        <form action="{{ route('manager.product-categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Delete this sub category?');" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[11px]">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full bg-white p-10 border border-slate-200 text-center text-slate-400 font-bold text-xs">
                No sub categories found. Click "+ New Sub Category" to create one.
            </div>
            @endforelse
        </div>

        @if($categories->hasPages())
        <div class="bg-white p-4 border border-slate-200">
            {{ $categories->links() }}
        </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: MAIN CATEGORIES (DYNAMIC)          -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'main'" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            @forelse($mainCategories as $mc)
            <div class="bg-white border-2 border-slate-200 p-5 shadow-sm flex flex-col justify-between hover:border-indigo-500 transition-all">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 bg-indigo-50 text-indigo-600 font-black text-lg flex items-center justify-center">🏛️</span>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase">Active</span>
                    </div>

                    <div>
                        <h3 class="text-base font-black text-slate-900">{{ $mc->title }}</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ $mc->description ?: 'Top-level product category division.' }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 text-center text-xs">
                        <div class="p-2 bg-slate-50 border border-slate-200">
                            <span class="block text-[10px] font-black uppercase text-slate-400">Sub Categories</span>
                            <span class="text-sm font-mono font-black text-indigo-700">{{ $mc->categories_count }}</span>
                        </div>
                        <div class="p-2 bg-slate-50 border border-slate-200">
                            <span class="block text-[10px] font-black uppercase text-slate-400">Products</span>
                            <span class="text-sm font-mono font-black text-emerald-700">{{ $mc->products_count }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('manager.product-categories.index', ['tab' => 'sub', 'main_category_id' => $mc->id]) }}" 
                       class="text-xs font-bold text-indigo-600 hover:underline">
                        View Sub Categories &rarr;
                    </a>

                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="openEditMain({{ json_encode($mc) }})" 
                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                            Edit
                        </button>
                        <form action="{{ route('manager.product-main-categories.destroy', $mc) }}" method="POST" onsubmit="return confirm('Delete this Main Category? (Only possible if no products exist inside it)');" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full bg-white p-10 border border-slate-200 text-center text-slate-400 font-bold text-xs">
                No main categories found. Click "+ New Main Category" to create one.
            </div>
            @endforelse
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: CREATE SUB CATEGORY               -->
    <!-- ========================================== -->
    <div x-show="createSubModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="createSubModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-black text-slate-900">Add New Sub Category</h3>
                    <button type="button" @click="createSubModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form action="{{ route('manager.product-categories.store') }}" method="POST" enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Parent Main Category <span class="text-rose-600">*</span></label>
                        <select name="product_main_category_id" required 
                                class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                            <option value="">-- Choose Main Category --</option>
                            @foreach($mainCategories as $mc)
                                <option value="{{ $mc->id }}" {{ (string)old('product_main_category_id', $mainCategoryId) === (string)$mc->id ? 'selected' : '' }}>
                                    🏛️ {{ $mc->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Sub Category Title <span class="text-rose-600">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. Keratin Shampoos, Skin Serums..." 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Description (Optional)</label>
                        <textarea name="description" rows="2" placeholder="Brief explanation of products in this sub category..." 
                                  class="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Category Image (Optional)</label>
                        <input type="file" name="image" accept="image/*" @change="handleImage($event, 'sub')"
                               class="w-full text-xs p-2 bg-slate-50 border border-slate-300 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700">
                        <template x-if="subImagePreview">
                            <img :src="subImagePreview" class="w-16 h-16 object-cover border mt-2">
                        </template>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="createSubModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black">
                            <span x-text="isSubmitting ? 'Saving...' : 'Create Sub Category'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: EDIT SUB CATEGORY                 -->
    <!-- ========================================== -->
    <div x-show="editSubModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="editSubModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-black text-slate-900">Edit Sub Category</h3>
                    <button type="button" @click="editSubModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form :action="'{{ url('manager/product-categories') }}/' + editSubCategory.id" method="POST" enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Parent Main Category <span class="text-rose-600">*</span></label>
                        <select name="product_main_category_id" x-model="editSubCategory.product_main_category_id" required 
                                class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                            <option value="">-- Choose Main Category --</option>
                            @foreach($mainCategories as $mc)
                                <option value="{{ $mc->id }}">🏛️ {{ $mc->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Sub Category Title <span class="text-rose-600">*</span></label>
                        <input type="text" name="title" x-model="editSubCategory.title" required 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Description (Optional)</label>
                        <textarea name="description" x-model="editSubCategory.description" rows="2" 
                                  class="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Change Image (Optional)</label>
                        <input type="file" name="image" accept="image/*" @change="handleImage($event, 'sub')"
                               class="w-full text-xs p-2 bg-slate-50 border border-slate-300 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700">
                        <div class="mt-2">
                            <template x-if="subImagePreview">
                                <img :src="subImagePreview" class="w-16 h-16 object-cover border">
                            </template>
                            <template x-if="!subImagePreview && editSubCategory.image">
                                <img :src="editSubCategory.image" class="w-16 h-16 object-cover border">
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="editSubModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black">
                            <span x-text="isSubmitting ? 'Updating...' : 'Update Sub Category'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: CREATE MAIN CATEGORY              -->
    <!-- ========================================== -->
    <div x-show="createMainModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="createMainModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-black text-slate-900">Add New Main Category</h3>
                    <button type="button" @click="createMainModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form action="{{ route('manager.product-main-categories.store') }}" method="POST" enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Main Category Title <span class="text-rose-600">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. Hair Care, Skin & Facial, Salon Use..." 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Description (Optional)</label>
                        <textarea name="description" rows="2" placeholder="Brief summary of this main category division..." 
                                  class="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Image / Banner (Optional)</label>
                        <input type="file" name="image" accept="image/*" @change="handleImage($event, 'main')"
                               class="w-full text-xs p-2 bg-slate-50 border border-slate-300 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700">
                        <template x-if="mainImagePreview">
                            <img :src="mainImagePreview" class="w-16 h-16 object-cover border mt-2">
                        </template>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="createMainModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black">
                            <span x-text="isSubmitting ? 'Saving...' : 'Create Main Category'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 4: EDIT MAIN CATEGORY                -->
    <!-- ========================================== -->
    <div x-show="editMainModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="editMainModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-black text-slate-900">Edit Main Category</h3>
                    <button type="button" @click="editMainModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form :action="'{{ url('manager/product-main-categories') }}/' + editMainCategory.id" method="POST" enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Main Category Title <span class="text-rose-600">*</span></label>
                        <input type="text" name="title" x-model="editMainCategory.title" required 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Description (Optional)</label>
                        <textarea name="description" x-model="editMainCategory.description" rows="2" 
                                  class="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Change Image (Optional)</label>
                        <input type="file" name="image" accept="image/*" @change="handleImage($event, 'main')"
                               class="w-full text-xs p-2 bg-slate-50 border border-slate-300 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700">
                        <div class="mt-2">
                            <template x-if="mainImagePreview">
                                <img :src="mainImagePreview" class="w-16 h-16 object-cover border">
                            </template>
                            <template x-if="!mainImagePreview && editMainCategory.image">
                                <img :src="editMainCategory.image" class="w-16 h-16 object-cover border">
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="editMainModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black">
                            <span x-text="isSubmitting ? 'Updating...' : 'Update Main Category'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
