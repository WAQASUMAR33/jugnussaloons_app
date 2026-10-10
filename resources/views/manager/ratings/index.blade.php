@extends('layouts.material')

@section('title', 'Staff Feedback & Ratings')

@section('content')
<div x-data="{ 
    selectedStylistId: {{ (int)$preselectedEmployeeId }},
    selectedRating: 4,
    hoverRating: 0,
    comments: '',
    isAnonymous: false,
    customerName: '{{ $preselectedAppointment && $preselectedAppointment->customer ? $preselectedAppointment->customer->name : '' }}',
    customerPhone: '{{ $preselectedAppointment && $preselectedAppointment->customer ? $preselectedAppointment->customer->phone_no1 : '' }}',
    appointmentId: {{ $appointmentId ? (int)$appointmentId : 'null' }},
    isSubmitting: false,

    stylists: @js($stylists),

    getSelectedStylist() {
        return this.stylists.find(s => s.id === this.selectedStylistId) || this.stylists[0] || { id: 0, first_name: 'Stylist', short_name: 'Stylist', full_name: 'Stylist' };
    },

    getRatingPrompt() {
        const stylist = this.getSelectedStylist();
        return 'How was your service with ' + (stylist.first_name || 'our stylist') + '?';
    },

    selectStylist(id) {
        this.selectedStylistId = id;
    },

    setRating(val) {
        this.selectedRating = val;
    }
}" class="space-y-6">

    <!-- 1. Header & Top Action Bar (Consistent with Saloon Services / Appointments Pages) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-amber-50 text-amber-600 rounded-none">
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Staff Feedback & Ratings</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Evaluate employee performance, record customer reviews, and monitor salon stylist satisfaction scores.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Client Kiosk Mode Link -->
            <a href="{{ route('public.feedback') }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 shadow-2xs transition-colors"
               title="Open client tablet feedback screen in new tab">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                <span>Client Kiosk Terminal</span>
            </a>

            <!-- Direct link to Appointments POS -->
            <a href="{{ route('manager.appointments.index') }}" 
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Appointments POS</span>
            </a>
        </div>
    </div>

    <!-- Alert / Flash Messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 text-sm font-bold shadow-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-sm font-bold shadow-xs">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>Please correct the following errors:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-semibold text-rose-800 space-y-0.5 ml-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 2. KPI Metrics Overview (4 Web Summary Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Reviews -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Reviews</span>
                <span class="text-2xl font-black text-slate-900 mt-1 block">{{ $totalReviewsCount }}</span>
                <span class="text-[11px] text-slate-500 font-medium">Recorded across all staff</span>
            </div>
            <div class="p-3 bg-indigo-50 text-indigo-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            </div>
        </div>

        <!-- Card 2: Salon Average Rating -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Salon Average Rating</span>
                <div class="flex items-center gap-1 mt-1">
                    <span class="text-2xl font-black text-amber-600">{{ $avgScore }}</span>
                    <span class="text-xs font-bold text-slate-400">/ 5.0</span>
                </div>
                <div class="flex items-center text-amber-400 text-xs mt-0.5">
                    @for($i = 1; $i <= 5; $i++)
                        <span>★</span>
                    @endfor
                </div>
            </div>
            <div class="p-3 bg-amber-50 text-amber-600">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
            </div>
        </div>

        <!-- Card 3: 5-Star Reviews -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">5-Star Experiences</span>
                <span class="text-2xl font-black text-emerald-600 mt-1 block">{{ $fiveStarReviewsCount }}</span>
                <span class="text-[11px] text-slate-500 font-medium">Top satisfaction rating</span>
            </div>
            <div class="p-3 bg-emerald-50 text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path></svg>
            </div>
        </div>

        <!-- Card 4: Top Stylist -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div class="truncate">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Top Stylist</span>
                <span class="text-base font-black text-slate-900 mt-1 block truncate">
                    {{ $topStylist ? $topStylist['full_name'] : 'N/A' }}
                </span>
                <div class="flex items-center gap-1.5 text-xs text-amber-600 font-bold mt-0.5">
                    <span>⭐ {{ $topStylist ? number_format($topStylist['avg_rating'], 1) : '5.0' }}</span>
                    <span class="text-slate-400 font-normal">({{ $topStylist ? $topStylist['reviews_count'] : 0 }} reviews)</span>
                </div>
            </div>
            <div class="p-3 bg-purple-50 text-purple-600 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
            </div>
        </div>
    </div>

    <!-- 3. Main Web Page Layout: Left Form + Right Stylists & Review Log -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT COLUMN (5 Cols): Interactive Feedback Form -->
        <div class="lg:col-span-5 bg-white border border-slate-200 shadow-sm overflow-hidden sticky top-4">
            
            <!-- Card Header -->
            <div class="bg-slate-50 p-4 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 bg-amber-500 text-white rounded-none">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Record Staff Review</h2>
                        <p class="text-[11px] text-slate-500">Collect client feedback & rating score</p>
                    </div>
                </div>

                @if($preselectedAppointment)
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-900 text-[10px] font-mono font-extrabold border border-indigo-200">
                        #{{ $preselectedAppointment->booking_no }}
                    </span>
                @endif
            </div>

            <!-- Form Body -->
            <form method="POST" action="{{ route('manager.ratings.store') }}" @submit="isSubmitting = true" class="p-5 space-y-4">
                @csrf
                
                <input type="hidden" name="employee_id" :value="selectedStylistId">
                <input type="hidden" name="rating" :value="selectedRating">
                <input type="hidden" name="appointment_id" :value="appointmentId ? appointmentId : ''">
                <input type="hidden" name="is_anonymous" :value="isAnonymous ? 1 : 0">

                <!-- STEP 1: Select Stylist -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                            1. Select Staff Stylist <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400" x-text="stylists.length + ' available'"></span>
                    </div>

                    <!-- Dropdown for Quick Access -->
                    <select x-model="selectedStylistId" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500">
                        <template x-for="stylist in stylists" :key="stylist.id">
                            <option :value="stylist.id" x-text="stylist.full_name + ' (' + stylist.specialty + ')'"></option>
                        </template>
                    </select>

                    <!-- Horizontal Stylist Thumbnails -->
                    <div class="flex items-center gap-2 overflow-x-auto py-2 scrollbar-thin">
                        <template x-for="stylist in stylists" :key="stylist.id">
                            <button type="button" @click="selectStylist(stylist.id)"
                                    class="flex items-center gap-2 p-1.5 border transition-all text-left shrink-0"
                                    :class="selectedStylistId === stylist.id 
                                        ? 'bg-amber-50/80 border-amber-400 ring-1 ring-amber-400 text-amber-900' 
                                        : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'">
                                <img :src="stylist.avatar" :alt="stylist.short_name" class="w-7 h-7 rounded-full object-cover border border-slate-300">
                                <div class="pr-1">
                                    <p class="text-[11px] font-extrabold truncate" x-text="stylist.short_name"></p>
                                    <p class="text-[9px] text-amber-600 font-bold" x-text="'★ ' + Number(stylist.avg_rating).toFixed(1)"></p>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- STEP 2: Service Rating -->
                <div class="space-y-2 p-4 bg-amber-50/50 border border-amber-200">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-black text-slate-800 uppercase tracking-wider">
                            2. Service Rating (1 - 5 Stars) <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-sm font-black text-amber-700" x-text="selectedRating + ' / 5 Stars'"></span>
                    </div>

                    <!-- 5 Big Interactive Stars -->
                    <div class="flex items-center justify-center gap-3 py-2">
                        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                            <button type="button" 
                                    @click="setRating(star)"
                                    @mouseenter="hoverRating = star"
                                    @mouseleave="hoverRating = 0"
                                    class="p-1 focus:outline-hidden transition-transform duration-150 hover:scale-120 active:scale-95"
                                    :title="'Rate ' + star + ' Star'">
                                <svg class="w-9 h-9 transition-colors duration-150"
                                     viewBox="0 0 24 24" 
                                     :fill="(hoverRating ? hoverRating >= star : selectedRating >= star) ? '#F59E0B' : 'none'"
                                     :stroke="(hoverRating ? hoverRating >= star : selectedRating >= star) ? '#F59E0B' : '#CBD5E1'"
                                     stroke-width="1.5">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                            </button>
                        </template>
                    </div>

                    <!-- Dynamic Question Subtext -->
                    <p class="text-xs font-bold text-center text-slate-700" x-text="getRatingPrompt()"></p>
                </div>

                <!-- STEP 3: Review Details & Comments -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            3. Review Comments & Feedback (Optional)
                        </label>
                        <textarea name="comments" x-model="comments" rows="3"
                                  placeholder="Write description here... (Optional, e.g., service details, overall experience, special mentions)"
                                  class="w-full p-2.5 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-indigo-600 focus:bg-white resize-none"></textarea>
                    </div>

                    <!-- Anonymous Toggle -->
                    <div class="flex items-center justify-between py-1">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none" @click="isAnonymous = !isAnonymous">
                            <div class="w-10 h-5 rounded-full transition-colors duration-200 relative flex items-center p-0.5"
                                 :class="isAnonymous ? 'bg-amber-600' : 'bg-slate-300'">
                                <div class="w-4 h-4 bg-white rounded-full shadow-md transform transition-transform duration-200"
                                     :class="isAnonymous ? 'translate-x-5' : 'translate-x-0'"></div>
                            </div>
                            <span class="text-xs font-bold text-slate-700">Submit as Anonymous</span>
                        </label>
                    </div>

                    <!-- Identified Client Inputs (revealed if not anonymous) -->
                    <div x-show="!isAnonymous" x-transition class="grid grid-cols-2 gap-2 pt-1">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase">Client Name</label>
                            <input type="text" name="customer_name" x-model="customerName" placeholder="Client Name"
                                   class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:ring-1 focus:ring-indigo-600">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase">Phone Number</label>
                            <input type="text" name="customer_phone" x-model="customerPhone" placeholder="0300-..."
                                   class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:ring-1 focus:ring-indigo-600">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" 
                            :disabled="isSubmitting"
                            class="w-full py-3 px-4 bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs tracking-wider uppercase shadow-sm transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span x-text="isSubmitting ? 'SUBMITTING...' : 'SUBMIT REVIEW'"></span>
                    </button>
                </div>
            </form>

        </div>

        <!-- RIGHT COLUMN (7 Cols): Stylists Grid + Filter + History Table -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Stylist Overview Cards Grid -->
            <div class="bg-white border border-slate-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>💈 Staff Stylists Leaderboard</span>
                    </h2>
                    <span class="text-xs font-bold text-slate-400">{{ count($stylists) }} Active Staff</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($stylists as $stylist)
                    <div class="p-3.5 border transition-all flex items-center justify-between gap-2"
                         :class="selectedStylistId === {{ $stylist['id'] }} ? 'bg-amber-50/70 border-amber-300 ring-1 ring-amber-300' : 'bg-slate-50/70 border-slate-200 hover:bg-slate-100/80'">
                        <div class="flex items-center gap-3 truncate">
                            <img src="{{ $stylist['avatar'] }}" alt="{{ $stylist['short_name'] }}" class="w-10 h-10 rounded-full object-cover border border-slate-200 shrink-0">
                            <div class="truncate">
                                <p class="text-xs font-black text-slate-900 truncate">{{ $stylist['full_name'] }}</p>
                                <p class="text-[10px] text-slate-500 font-medium truncate">{{ $stylist['specialty'] }}</p>
                                <div class="flex items-center gap-1 text-[11px] font-extrabold text-amber-600 mt-0.5">
                                    <span>⭐ {{ number_format($stylist['avg_rating'], 1) }}</span>
                                    <span class="text-[10px] text-slate-400 font-normal">({{ $stylist['reviews_count'] }})</span>
                                </div>
                            </div>
                        </div>

                        <button type="button" @click="selectStylist({{ $stylist['id'] }})"
                                class="px-2.5 py-1 text-[10px] font-extrabold border transition-colors shrink-0"
                                :class="selectedStylistId === {{ $stylist['id'] }} ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'">
                            <span x-text="selectedStylistId === {{ $stylist['id'] }} ? 'Selected' : 'Rate'"></span>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Filter & Search Bar for Reviews Table -->
            <div class="bg-white p-4 border border-slate-200 shadow-sm">
                <form method="GET" action="{{ route('manager.ratings.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <!-- Search input -->
                    <div class="sm:col-span-2 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search review comments, stylist, client..." 
                               class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white">
                    </div>

                    <!-- Filter by Stylist -->
                    <div>
                        <select name="filter_employee_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                            <option value="">All Stylists</option>
                            @foreach($stylists as $st)
                                <option value="{{ $st['id'] }}" {{ (string)$filterEmployeeId === (string)$st['id'] ? 'selected' : '' }}>
                                    {{ $st['full_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter by Rating & Submit -->
                    <div class="flex items-center gap-2">
                        <select name="filter_rating" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                            <option value="">All Ratings</option>
                            <option value="5" {{ (string)$filterRating === '5' ? 'selected' : '' }}>⭐⭐⭐⭐⭐ 5 Stars</option>
                            <option value="4" {{ (string)$filterRating === '4' ? 'selected' : '' }}>⭐⭐⭐⭐ 4 Stars</option>
                            <option value="3" {{ (string)$filterRating === '3' ? 'selected' : '' }}>⭐⭐⭐ 3 Stars</option>
                            <option value="2" {{ (string)$filterRating === '2' ? 'selected' : '' }}>⭐⭐ 2 Stars</option>
                            <option value="1" {{ (string)$filterRating === '1' ? 'selected' : '' }}>⭐ 1 Star</option>
                        </select>
                        
                        <button type="submit" class="px-3.5 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                            Filter
                        </button>
                        
                        @if($search || $filterEmployeeId || $filterRating)
                            <a href="{{ route('manager.ratings.index') }}" class="px-2.5 py-2 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200">
                                ✕
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Client Reviews Log Table -->
            <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">
                        Client Reviews History Log ({{ $recentReviews->total() }})
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 uppercase font-black tracking-wider border-b border-slate-200 text-[11px]">
                                <th class="py-3 px-4">Date & Time</th>
                                <th class="py-3 px-4">Stylist</th>
                                <th class="py-3 px-4">Rating</th>
                                <th class="py-3 px-4">Review Feedback</th>
                                <th class="py-3 px-4">Client</th>
                                <th class="py-3 px-4">Booking Ref</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @forelse($recentReviews as $review)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                    {{ $review->created_at->format('d M Y, h:i A') }}
                                </td>
                                <td class="py-3 px-4 font-black text-slate-900 whitespace-nowrap">
                                    💇 {{ $review->employee ? $review->employee->name : 'N/A' }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-900 font-black text-[11px]">
                                        <span class="text-amber-500">
                                            @for($i = 1; $i <= $review->rating; $i++)★@endfor
                                        </span>
                                        <span>{{ $review->rating }}.0</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 max-w-xs">
                                    <p class="text-slate-800 line-clamp-2 leading-relaxed">
                                        {{ $review->comments ?: 'No written feedback provided.' }}
                                    </p>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($review->is_anonymous)
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-none text-[10px] font-bold border border-slate-200">
                                            Anonymous
                                        </span>
                                    @else
                                        <span class="font-bold text-slate-900 block">{{ $review->reviewer_display_name }}</span>
                                        @if($review->customer_phone)
                                            <span class="text-[10px] text-slate-400 font-mono block">{{ $review->customer_phone }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($review->appointment)
                                        <a href="{{ route('manager.appointments.index') }}?search={{ $review->appointment->booking_no }}" 
                                           class="font-mono text-indigo-600 font-bold hover:underline">
                                            #{{ $review->appointment->booking_no }}
                                        </a>
                                    @else
                                        <span class="text-slate-400 italic">Direct Rating</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 font-bold">
                                    No reviews found matching the selected criteria.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($recentReviews->hasPages())
                    <div class="p-4 border-t border-slate-200 bg-slate-50">
                        {{ $recentReviews->links() }}
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>
@endsection
