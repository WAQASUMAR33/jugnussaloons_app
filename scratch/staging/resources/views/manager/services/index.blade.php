@extends('layouts.material')

@section('title', 'Saloon Services Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    editService: { id: null, service_category_id: '', title: '', description: '', price: 0, discount: 0, discounted_price: 0, junior_commission: 0, senior_commission: 0, image: null },
    calculateDiscount(price, discount) {
        if (!price || price <= 0) return 0;
        if (!discount || discount <= 0) return price;
        return (price - (price * (discount / 100))).toFixed(2);
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Saloon Services Catalog</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage haircutting, styling, coloring, grooming packages, service categories, and staff commission.</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Add New Service</span>
        </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.services.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search service by title or description..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
            </div>

            <div>
                <select name="service_category_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Service Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>
                            {{ $cat->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                    Search Catalog
                </button>
                @if($search || $categoryId)
                    <a href="{{ route('manager.services.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Services Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Image</th>
                        <th class="py-4 px-6">Category</th>
                        <th class="py-4 px-6">Service Title & Description</th>
                        <th class="py-4 px-6">Original Price</th>
                        <th class="py-4 px-6">Discount</th>
                        <th class="py-4 px-6">Final Price</th>
                        <th class="py-4 px-6">Junior Comm.</th>
                        <th class="py-4 px-6">Senior Comm.</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($services as $service)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-bold text-xs text-slate-400">#{{ $service->id }}</td>
                        <td class="py-4 px-6">
                            @if($service->image)
                                <img src="{{ asset($service->image) }}" alt="{{ $service->title }}" class="w-12 h-12 object-cover border border-slate-200 shadow-sm">
                            @else
                                <div class="w-12 h-12 bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                            @endif
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 font-extrabold text-xs">
                                {{ $service->category->title ?? 'General' }}
                            </span>
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $service->title }}</p>
                            <p class="text-xs text-slate-500 max-w-xs mt-0.5 line-clamp-2">{{ $service->description ?: 'No description' }}</p>
                        </td>
                        <td class="py-4 px-6 font-bold text-slate-800">
                            {{ number_format($service->price, 2) }}
                        </td>
                        <td class="py-4 px-6">
                            @if($service->discount > 0)
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-xs font-bold">
                                    {{ $service->discount }}% OFF
                                </span>
                            @else
                                <span class="text-xs text-slate-400 font-medium">None</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-extrabold text-emerald-600">
                            {{ number_format($service->discounted_price, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold text-sky-700">
                            {{ number_format($service->junior_commission ?? $service->commission ?? 0, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold text-purple-700">
                            {{ number_format($service->senior_commission ?? 0, 2) }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="editService = { 
                                            id: {{ $service->id }}, 
                                            service_category_id: {{ $service->service_category_id ?? 'null' }}, 
                                            title: '{{ addslashes($service->title) }}', 
                                            description: '{{ addslashes($service->description) }}', 
                                            price: {{ $service->price }}, 
                                            discount: {{ $service->discount }}, 
                                            discounted_price: {{ $service->discounted_price }}, 
                                            junior_commission: {{ $service->junior_commission ?? $service->commission ?? 0 }}, 
                                            senior_commission: {{ $service->senior_commission ?? 0 }}, 
                                            image: '{{ $service->image ? asset($service->image) : '' }}' 
                                        }; editModalOpen = true" 
                                        class="p-2 text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit Service">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.services.destroy', $service) }}" onsubmit="return confirm('Delete service {{ addslashes($service->title) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Service">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No saloon services created yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $services->links() }}
        </div>
    </div>

    <!-- MODAL: CREATE SERVICE -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                    Add Saloon Service
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.services.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ newPrice: 0, newDiscount: 0 }">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Service Title</label>
                        <input type="text" name="title" required placeholder="e.g. Executive Haircut & Beard Grooming" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Service Category</label>
                        <select name="service_category_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                            <option value="">General / Uncategorized</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description</label>
                    <textarea name="description" rows="3" placeholder="Service overview and included treatments..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Price</label>
                        <input type="number" step="0.01" min="0" name="price" x-model.number="newPrice" required placeholder="50.00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" x-model.number="newDiscount" placeholder="10" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discounted Price</label>
                        <input type="number" step="0.01" min="0" name="discounted_price" :value="calculateDiscount(newPrice, newDiscount)" 
                               class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 text-sm font-bold text-emerald-600 focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Junior Comm.</label>
                        <input type="number" step="0.01" min="0" name="junior_commission" placeholder="10.00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-sky-200 text-sm font-bold text-sky-700 focus:ring-2 focus:ring-indigo-600" title="Commission for Junior Staff">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Senior Comm.</label>
                        <input type="number" step="0.01" min="0" name="senior_commission" placeholder="20.00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-purple-200 text-sm font-bold text-purple-700 focus:ring-2 focus:ring-indigo-600" title="Commission for Senior Staff">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Service Image (Upload to Server)</label>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Save Service
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT SERVICE -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Saloon Service
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/services') }}/' + editService.id" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Service Title</label>
                        <input type="text" name="title" x-model="editService.title" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Service Category</label>
                        <select name="service_category_id" x-model="editService.service_category_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                            <option value="">General / Uncategorized</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description</label>
                    <textarea name="description" rows="3" x-model="editService.description" 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Price</label>
                        <input type="number" step="0.01" min="0" name="price" x-model.number="editService.price" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" x-model.number="editService.discount" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discounted Price</label>
                        <input type="number" step="0.01" min="0" name="discounted_price" :value="calculateDiscount(editService.price, editService.discount)" 
                               class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 text-sm font-bold text-emerald-600 focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Junior Comm.</label>
                        <input type="number" step="0.01" min="0" name="junior_commission" x-model.number="editService.junior_commission" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-sky-200 text-sm font-bold text-sky-700 focus:ring-2 focus:ring-indigo-600" title="Commission for Junior Staff">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Senior Comm.</label>
                        <input type="number" step="0.01" min="0" name="senior_commission" x-model.number="editService.senior_commission" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-purple-200 text-sm font-bold text-purple-700 focus:ring-2 focus:ring-indigo-600" title="Commission for Senior Staff">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Change Image (Upload New Image)</label>
                    <template x-if="editService.image">
                        <div class="mb-2 flex items-center gap-3 p-2 bg-slate-50 border border-slate-200">
                            <img :src="editService.image" class="w-10 h-10 object-cover border">
                            <span class="text-xs text-slate-500 font-medium">Current Image Saved</span>
                        </div>
                    </template>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Update Service
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
