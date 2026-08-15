@extends('layouts.material')

@section('title', 'Photo Gallery Management')

@section('content')
<div class="space-y-6" x-data="galleryManager()">
    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden border border-slate-800">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-40 -top-10 w-48 h-48 bg-purple-500/10 rounded-full blur-2xl pointer-events-none"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="px-3 py-1 bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 rounded-full text-xs font-bold uppercase tracking-wider">
                        Showcase Media
                    </span>
                    <span class="text-slate-400 text-xs font-semibold">• Live Management</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                    <svg class="w-8 h-8 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Photo Gallery Showcase
                </h1>
                <p class="text-slate-300 text-sm mt-1 max-w-xl font-medium">
                    Upload, manage, tag, and organize salon interior photos, hair styling portfolios, bridal makeup transformations, and treatment media.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" 
                        @click="openUploadModal = true"
                        class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-extrabold text-sm rounded-xl shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/50 transition-all duration-200 flex items-center gap-2 group transform active:scale-95 cursor-pointer">
                    <svg class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Upload New Photos</span>
                </button>

                <button type="button"
                        x-show="selectedIds.length > 0"
                        x-cloak
                        @click="openBulkDeleteModal = true"
                        class="px-4 py-2.5 bg-rose-600/90 hover:bg-rose-600 text-white font-bold text-sm rounded-xl shadow-md transition-all flex items-center gap-2 border border-rose-400/30 animate-fade-in">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    <span>Delete Selected (<span x-text="selectedIds.length"></span>)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Flash Messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 rounded-xl shadow-sm flex items-center justify-between" x-data="{ show: true }" x-show="show">
            <div class="flex items-center gap-3">
                <div class="p-1 bg-emerald-500 text-white rounded-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="font-bold text-sm">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-emerald-600 hover:text-emerald-900 text-xs font-black">✕</button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Total Showcase Photos</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($totalPhotos) }}</h3>
                <p class="text-xs font-semibold text-emerald-600 mt-1 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                    Ready for public showcase
                </p>
            </div>
            <div class="w-12 h-12 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-center text-indigo-600 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Storage Used</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ $totalSizeFormatted }}</h3>
                <p class="text-xs font-semibold text-slate-500 mt-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Optimized storage memory
                </p>
            </div>
            <div class="w-12 h-12 bg-purple-50 border border-purple-100 rounded-xl flex items-center justify-center text-purple-600 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                </svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Media Categories</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ count($allCategories) }}</h3>
                <p class="text-xs font-semibold text-purple-600 mt-1 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500 inline-block"></span>
                    Organized service tags
                </p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M11 7h8M11 11h8M11 15h8M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- Category Filter Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 scrollbar-none">
            <a href="{{ route('manager.galleries.index', array_merge(request()->except('category', 'page'), ['category' => 'all'])) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all whitespace-nowrap {{ request('category', 'all') === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                All Media
            </a>
            @foreach($allCategories as $cat)
                <a href="{{ route('manager.galleries.index', array_merge(request()->except('category', 'page'), ['category' => $cat])) }}"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap {{ request('category') === $cat ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $cat }}
                </a>
            @endforeach
        </div>

        <!-- Search Input Form -->
        <form action="{{ route('manager.galleries.index') }}" method="GET" class="w-full md:w-72 shrink-0">
            @if(request('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
            @endif
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}"
                       placeholder="Search title, category..." 
                       class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all text-slate-800 placeholder-slate-400">
                @if(request('search'))
                    <a href="{{ route('manager.galleries.index', request()->except('search')) }}" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Gallery Grid Section -->
    @if($galleries->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center shadow-sm">
            <div class="w-20 h-20 bg-indigo-50 border border-indigo-100 rounded-full flex items-center justify-center text-indigo-500 mx-auto mb-4">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-extrabold text-slate-800">No Gallery Photos Found</h3>
            <p class="text-slate-500 text-sm mt-1 max-w-sm mx-auto">
                @if(request('search') || request('category'))
                    No images match your current filter query. Try clearing filters or search terms.
                @else
                    Start showcasing your saloon work by uploading your first set of images!
                @endif
            </p>
            <div class="mt-6 flex justify-center gap-3">
                @if(request('search') || request('category'))
                    <a href="{{ route('manager.galleries.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-200 transition-all">
                        Clear All Filters
                    </a>
                @endif
                <button type="button" 
                        @click="openUploadModal = true"
                        class="px-5 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl hover:bg-indigo-700 shadow-md shadow-indigo-100 transition-all">
                    Upload Photos Now
                </button>
            </div>
        </div>
    @else
        <!-- Bulk Select Control Header -->
        <div class="flex items-center justify-between px-2 text-xs text-slate-500 font-semibold">
            <div class="flex items-center gap-2">
                <input type="checkbox" 
                       @change="toggleSelectAll($event)" 
                       :checked="selectedIds.length === {{ $galleries->count() }}"
                       id="select-all-checkbox"
                       class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 cursor-pointer">
                <label for="select-all-checkbox" class="cursor-pointer font-bold text-slate-700">
                    Select All Photos ({{ $galleries->count() }})
                </label>
            </div>
            <span>Showing {{ $galleries->count() }} photo(s)</span>
        </div>

        <!-- Grid Container -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($galleries as $gallery)
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden flex flex-col group relative">
                    
                    <!-- Selection Checkbox -->
                    <div class="absolute top-3 left-3 z-20">
                        <input type="checkbox" 
                               value="{{ $gallery->id }}" 
                               x-model="selectedIds"
                               class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 shadow-sm cursor-pointer">
                    </div>

                    <!-- Category Badge -->
                    <div class="absolute top-3 right-3 z-20">
                        <span class="px-2.5 py-1 bg-slate-900/75 backdrop-blur-md text-white text-[10px] font-extrabold rounded-lg uppercase tracking-wider border border-white/20">
                            {{ $gallery->category ?: 'General' }}
                        </span>
                    </div>

                    <!-- Image & Action Overlay Container -->
                    <div class="relative aspect-4/3 bg-slate-100 overflow-hidden cursor-pointer" @click="openLightbox('{{ asset($gallery->image_path) }}', '{{ $gallery->title }}', '{{ $gallery->category }}')">
                        <img src="{{ asset($gallery->image_path) }}" 
                             alt="{{ $gallery->title }}" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        
                        <!-- Hover Overlay with Quick Action Buttons -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-900/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-2 p-4">
                            <!-- Zoom Lightbox -->
                            <button type="button" 
                                    @click.stop="openLightbox('{{ asset($gallery->image_path) }}', '{{ $gallery->title }}', '{{ $gallery->category }}')"
                                    title="View Fullscreen"
                                    class="w-10 h-10 bg-white/90 hover:bg-white text-slate-800 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path></svg>
                            </button>

                            <!-- Copy URL -->
                            <button type="button" 
                                    @click.stop="copyToClipboard('{{ asset($gallery->image_path) }}')"
                                    title="Copy Image URL"
                                    class="w-10 h-10 bg-white/90 hover:bg-white text-indigo-600 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                            </button>

                            <!-- Edit Details -->
                            <button type="button" 
                                    @click.stop="openEditModal({{ $gallery->id }}, '{{ addslashes($gallery->title) }}', '{{ addslashes($gallery->category) }}')"
                                    title="Edit Details"
                                    class="w-10 h-10 bg-white/90 hover:bg-white text-purple-600 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>

                            <!-- Delete -->
                            <form action="{{ route('manager.galleries.destroy', $gallery->id) }}" method="POST" @submit.prevent="confirmDelete($event)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        title="Delete Photo"
                                        class="w-10 h-10 bg-rose-600 hover:bg-rose-700 text-white rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Card Body Meta -->
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <h4 class="font-extrabold text-sm text-slate-800 group-hover:text-indigo-600 transition-colors line-clamp-1">
                                {{ $gallery->title ?: $gallery->file_name }}
                            </h4>
                            <p class="text-[11px] font-semibold text-slate-400 truncate mt-0.5">
                                {{ $gallery->file_name }}
                            </p>
                        </div>

                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-bold">
                            <span class="flex items-center gap-1 text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $gallery->created_at->format('M d, Y') }}
                            </span>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md font-extrabold">
                                {{ $gallery->formatted_size }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Upload Photos Modal -->
    <div x-show="openUploadModal" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="openUploadModal = false" 
             class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 transform transition-all relative">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-center text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900">Upload Gallery Photos</h3>
                        <p class="text-xs text-slate-500 font-semibold">Support JPEG, PNG, WEBP, GIF (Max 10MB per file)</p>
                    </div>
                </div>
                <button type="button" @click="openUploadModal = false" class="text-slate-400 hover:text-slate-600 p-2 rounded-full hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form action="{{ route('manager.galleries.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <!-- Category & Title Inputs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category / Service Tag</label>
                        <select name="category" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-600 text-slate-800">
                            <option value="">-- Select Category --</option>
                            @foreach($allCategories as $catOption)
                                <option value="{{ $catOption }}" {{ request('category') === $catOption ? 'selected' : '' }}>{{ $catOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Optional Display Title</label>
                        <input type="text" 
                               name="title" 
                               placeholder="e.g. Modern Hair Highlights" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-600 text-slate-800">
                    </div>
                </div>

                <!-- Drag & Drop Upload Zone -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload Image Files</label>
                    <div @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleFileDrop($event)"
                         :class="{ 'border-indigo-500 bg-indigo-50/50 scale-[0.99]': isDragging, 'border-slate-300 bg-slate-50/80': !isDragging }"
                         class="border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer hover:border-indigo-400 group relative">
                        
                        <input type="file" 
                               name="images[]" 
                               id="gallery-file-input" 
                               multiple 
                               accept="image/*"
                               @change="handleFileSelect($event)"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        
                        <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                        </div>
                        <h4 class="font-extrabold text-sm text-slate-800">Drag & Drop photos here, or <span class="text-indigo-600 underline">Browse</span></h4>
                        <p class="text-xs text-slate-400 mt-1">Select multiple images at once (Max 10MB per file)</p>
                    </div>
                </div>

                <!-- Selected Files Preview Grid -->
                <template x-if="filesPreview.length > 0">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                            <span>Selected Files (<span x-text="filesPreview.length"></span>)</span>
                            <button type="button" @click="clearFiles()" class="text-rose-600 hover:underline">Clear All</button>
                        </div>
                        <div class="grid grid-cols-4 sm:grid-cols-6 gap-3 max-h-44 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200/80">
                            <template x-for="(file, index) in filesPreview" :key="index">
                                <div class="relative aspect-square rounded-lg overflow-hidden border border-slate-200 group bg-slate-200">
                                    <img :src="file.url" class="w-full h-full object-cover">
                                    <button type="button" 
                                            @click="removeFile(index)" 
                                            class="absolute top-1 right-1 w-5 h-5 bg-rose-600 text-white rounded-full flex items-center justify-center text-[10px] font-black shadow-md hover:scale-110 transition-transform">
                                        ✕
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" 
                            @click="openUploadModal = false" 
                            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                            :disabled="filesPreview.length === 0"
                            :class="{ 'opacity-50 cursor-not-allowed': filesPreview.length === 0 }"
                            class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
                        Upload Photos Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Details Modal -->
    <div x-show="openEditModalState" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="openEditModalState = false" 
             class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <h3 class="text-lg font-black text-slate-900">Edit Photo Details</h3>
                <button type="button" @click="openEditModalState = false" class="text-slate-400 hover:text-slate-600 p-1">✕</button>
            </div>

            <form :action="editFormUrl" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Photo Title</label>
                    <input type="text" 
                           name="title" 
                           x-model="editItem.title" 
                           required 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-600 text-slate-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category / Tag</label>
                    <select name="category" 
                            x-model="editItem.category" 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-600 text-slate-800">
                        <option value="">-- Select Category --</option>
                        @foreach($allCategories as $catOption)
                            <option value="{{ $catOption }}">{{ $catOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="openEditModalState = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div x-show="openBulkDeleteModal" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="openBulkDeleteModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 text-center shadow-2xl border border-slate-100">
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 class="text-lg font-black text-slate-900">Confirm Bulk Delete</h3>
            <p class="text-xs text-slate-500 mt-2">
                Are you sure you want to permanently delete <span class="font-extrabold text-rose-600" x-text="selectedIds.length"></span> selected photo(s)? This action cannot be undone.
            </p>

            <form action="{{ route('manager.galleries.bulk-delete') }}" method="POST" class="mt-6 flex items-center justify-center gap-3">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="button" @click="openBulkDeleteModal = false" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/30">
                    Yes, Delete Selected
                </button>
            </form>
        </div>
    </div>

    <!-- Fullscreen Lightbox Preview Modal -->
    <div x-show="lightboxOpen" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-between p-4 sm:p-8">
        
        <!-- Header -->
        <div class="w-full flex items-center justify-between text-white z-10">
            <div>
                <h4 class="font-extrabold text-base" x-text="lightboxTitle"></h4>
                <p class="text-xs text-indigo-300 font-bold" x-text="lightboxCategory"></p>
            </div>
            <button type="button" @click="lightboxOpen = false" class="p-2 bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Image Display -->
        <div class="max-w-5xl max-h-[80vh] w-full h-full flex items-center justify-center my-auto p-4">
            <img :src="lightboxSrc" class="max-w-full max-h-[75vh] object-contain rounded-2xl shadow-2xl border border-white/10">
        </div>

        <!-- Footer -->
        <div class="text-slate-400 text-xs font-semibold pb-2">
            Click anywhere outside or press ESC to close.
        </div>
    </div>

    <!-- Toast Notification Component -->
    <div x-show="toastShow" 
         x-cloak 
         x-transition 
         class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl border border-slate-800 flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        <span class="text-xs font-extrabold" x-text="toastMessage"></span>
    </div>
</div>

<script>
    function galleryManager() {
        return {
            openUploadModal: false,
            openEditModalState: false,
            openBulkDeleteModal: false,
            lightboxOpen: false,
            lightboxSrc: '',
            lightboxTitle: '',
            lightboxCategory: '',
            isDragging: false,
            filesPreview: [],
            selectedIds: [],
            editItem: { id: null, title: '', category: '' },
            editFormUrl: '',
            toastShow: false,
            toastMessage: '',

            handleFileSelect(e) {
                const files = e.target.files;
                this.addFiles(files);
            },

            handleFileDrop(e) {
                this.isDragging = false;
                const files = e.dataTransfer.files;
                this.addFiles(files);
            },

            addFiles(files) {
                Array.from(files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const url = URL.createObjectURL(file);
                        this.filesPreview.push({ file, url });
                    }
                });
            },

            removeFile(index) {
                URL.revokeObjectURL(this.filesPreview[index].url);
                this.filesPreview.splice(index, 1);
            },

            clearFiles() {
                this.filesPreview.forEach(item => URL.revokeObjectURL(item.url));
                this.filesPreview = [];
            },

            toggleSelectAll(e) {
                if (e.target.checked) {
                    this.selectedIds = [
                        @foreach($galleries as $g)
                            {{ $g->id }},
                        @endforeach
                    ];
                } else {
                    this.selectedIds = [];
                }
            },

            openLightbox(src, title, category) {
                this.lightboxSrc = src;
                this.lightboxTitle = title || 'Gallery Image';
                this.lightboxCategory = category || 'General Showcase';
                this.lightboxOpen = true;
            },

            openEditModal(id, title, category) {
                this.editItem = { id, title, category };
                this.editFormUrl = `/manager/galleries/${id}`;
                this.openEditModalState = true;
            },

            copyToClipboard(url) {
                navigator.clipboard.writeText(url).then(() => {
                    this.showToast('Image URL copied to clipboard!');
                });
            },

            showToast(msg) {
                this.toastMessage = msg;
                this.toastShow = true;
                setTimeout(() => {
                    this.toastShow = false;
                }, 3000);
            },

            confirmDelete(e) {
                if (!confirm('Are you sure you want to delete this gallery photo?')) {
                    e.preventDefault();
                }
            }
        }
    }
</script>
@endsection
