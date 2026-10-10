<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $brandName }} - Staff Feedback</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        .scrollbar-none::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-none {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-0 sm:p-4 text-slate-800 antialiased selection:bg-teal-500 selection:text-white">

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
            return this.stylists.find(s => s.id === this.selectedStylistId) || this.stylists[0] || { first_name: 'our staff', short_name: 'Stylist' };
        },

        getRatingPrompt() {
            const stylist = this.getSelectedStylist();
            return 'How was your service with ' + (stylist.first_name || 'our staff') + '?';
        },

        selectStylist(id) {
            this.selectedStylistId = id;
        },

        setRating(val) {
            this.selectedRating = val;
        }
    }" class="w-full max-w-[420px] sm:my-6">

        <!-- Mobile Phone Container -->
        <div class="bg-white rounded-none sm:rounded-[36px] shadow-2xl overflow-hidden sm:border-[6px] sm:border-slate-800 min-h-screen sm:min-h-0 flex flex-col justify-between">
            
            <div>
                <!-- 1. Mobile Header Bar (Deep Teal Green with Back Arrow) -->
                <div class="bg-[#115E59] text-white px-4 py-4 flex items-center gap-3 shadow-md sticky top-0 z-30">
                    <a href="{{ url('/') }}" class="p-1 -ml-1 text-white/90 hover:text-white transition-colors" title="Back">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <h1 class="text-base font-extrabold tracking-tight truncate text-white">
                        {{ $brandName }} - Staff Feedback
                    </h1>
                </div>

                <!-- Session Flash Messages -->
                @if(session('success'))
                <div class="m-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold rounded-2xl flex items-center gap-2 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>{{ session('success') }}</span>
                </div>
                @endif

                @if($errors->any())
                <div class="m-4 p-4 bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold rounded-2xl shadow-xs">
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>Please correct errors:</span>
                    </div>
                    <ul class="list-disc list-inside text-[11px] font-semibold text-rose-800 space-y-0.5 ml-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- Feedback Content -->
                <div class="p-4 bg-[#F1F5F9]/70 space-y-3.5">
                    <form method="POST" action="{{ route('public.feedback.store') }}" @submit="isSubmitting = true" class="space-y-3.5">
                        @csrf
                        
                        <input type="hidden" name="employee_id" :value="selectedStylistId">
                        <input type="hidden" name="rating" :value="selectedRating">
                        <input type="hidden" name="appointment_id" :value="appointmentId ? appointmentId : ''">
                        <input type="hidden" name="is_anonymous" :value="isAnonymous ? 1 : 0">

                        <!-- CARD 1: Select your Stylist -->
                        <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200/80">
                            <h2 class="text-sm font-black text-slate-900 tracking-tight mb-3">
                                1. Select your Stylist:
                            </h2>

                            <!-- Horizontal Carousel of Stylists -->
                            <div class="flex items-start gap-4 overflow-x-auto pb-2 scrollbar-none select-none snap-x snap-mandatory">
                                <template x-for="stylist in stylists" :key="stylist.id">
                                    <div @click="selectStylist(stylist.id)"
                                         class="flex flex-col items-center cursor-pointer shrink-0 w-20 text-center snap-center transition-all group">
                                        
                                        <!-- Avatar with Golden Double Ring when active -->
                                        <div class="relative w-16 h-16 rounded-full flex items-center justify-center transition-all duration-200"
                                             :class="selectedStylistId === stylist.id 
                                                ? 'p-0.5 ring-3 ring-[#C59B27] ring-offset-2 scale-105 shadow-md' 
                                                : 'border-2 border-slate-200 opacity-80 group-hover:opacity-100'">
                                            
                                            <img :src="stylist.avatar" 
                                                 :alt="stylist.short_name"
                                                 class="w-full h-full rounded-full object-cover bg-slate-100">

                                            <template x-if="selectedStylistId === stylist.id">
                                                <div class="absolute -top-1 -right-1 w-5 h-5 bg-[#C59B27] text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-xs border border-white">
                                                    ✓
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Name badge (Amber pill when selected) -->
                                        <div class="mt-2 w-full truncate">
                                            <span class="text-[11px] font-extrabold transition-colors block truncate"
                                                  :class="selectedStylistId === stylist.id 
                                                    ? 'bg-amber-100 text-amber-900 px-2 py-0.5 rounded-full shadow-2xs' 
                                                    : 'text-slate-800'"
                                                  x-text="stylist.short_name">
                                            </span>
                                        </div>

                                        <!-- 5 Gold Stars Rating -->
                                        <div class="flex items-center justify-center text-[10px] text-amber-400 mt-1">
                                            <span>★★★★★</span>
                                        </div>

                                        <!-- Specialty Subtitle -->
                                        <span class="text-[10px] text-slate-400 font-medium truncate w-full block mt-0.5"
                                              x-text="stylist.specialty">
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- CARD 2: Your Rating (5 Interactive Stars) -->
                        <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200/80">
                            <div class="flex items-center justify-between mb-2">
                                <h2 class="text-sm font-black text-slate-900 tracking-tight">
                                    2. Your Rating:
                                </h2>
                                <div class="text-sm font-black text-[#C59B27]">
                                    <span x-text="selectedRating"></span> / 5
                                </div>
                            </div>

                            <!-- Big 5 Stars Widget -->
                            <div class="flex items-center justify-center gap-2.5 py-3">
                                <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                    <button type="button" 
                                            @click="setRating(star)"
                                            @mouseenter="hoverRating = star"
                                            @mouseleave="hoverRating = 0"
                                            class="p-1 focus:outline-hidden transition-transform duration-150 hover:scale-115 active:scale-95"
                                            :title="'Rate ' + star + ' Star'">
                                        <svg class="w-10 h-10 transition-colors duration-150"
                                             viewBox="0 0 24 24" 
                                             :fill="(hoverRating ? hoverRating >= star : selectedRating >= star) ? '#C59B27' : 'none'"
                                             :stroke="(hoverRating ? hoverRating >= star : selectedRating >= star) ? '#C59B27' : '#CBD5E1'"
                                             stroke-width="1.5"
                                             stroke-linecap="round"
                                             stroke-linejoin="round">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                        </svg>
                                    </button>
                                </template>
                            </div>

                            <!-- Dynamic question prompt referencing stylist name -->
                            <div class="text-center">
                                <p class="text-xs font-bold text-slate-700 tracking-tight" x-text="getRatingPrompt()"></p>
                            </div>
                        </div>

                        <!-- CARD 3: Share your details (Comments + Anonymous toggle) -->
                        <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200/80 space-y-3">
                            <h2 class="text-sm font-black text-slate-900 tracking-tight">
                                3. Share your details:
                            </h2>

                            <!-- Review Textarea -->
                            <div>
                                <textarea name="comments" x-model="comments" rows="3"
                                          placeholder="Write your description here... (Optional, e.g., service details, overall experience, special mentions)"
                                          class="w-full p-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-[#115E59] focus:border-[#115E59] focus:outline-hidden resize-none leading-relaxed transition-all"></textarea>
                            </div>

                            <!-- Anonymous Toggle Switch -->
                            <div class="flex items-center justify-between pt-1">
                                <label class="flex items-center gap-3 cursor-pointer select-none" @click="isAnonymous = !isAnonymous">
                                    <div class="w-11 h-6 rounded-full transition-colors duration-200 ease-in-out relative flex items-center p-0.5"
                                         :class="isAnonymous ? 'bg-[#115E59]' : 'bg-slate-300'">
                                        <div class="w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-200 ease-in-out"
                                             :class="isAnonymous ? 'translate-x-5' : 'translate-x-0'"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-700">Submit as Anonymous</span>
                                </label>
                            </div>

                            <!-- Client Info Fields when not anonymous -->
                            <div x-show="!isAnonymous" x-transition class="space-y-2 pt-2 border-t border-slate-100">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Your Name</label>
                                        <input type="text" name="customer_name" x-model="customerName" placeholder="Client Name"
                                               class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-800 focus:ring-1 focus:ring-[#115E59]">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Phone Number</label>
                                        <input type="text" name="customer_phone" x-model="customerPhone" placeholder="0300-..."
                                               class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-800 focus:ring-1 focus:ring-[#115E59]">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON: Warm Rich Gold -->
                        <div class="pt-1 space-y-2.5">
                            <button type="submit" 
                                    :disabled="isSubmitting"
                                    class="w-full py-3.5 px-4 bg-[#C59B27] hover:bg-[#B48820] active:scale-[0.99] text-white font-black text-sm tracking-wider uppercase rounded-xl shadow-md transition-all flex items-center justify-center gap-2">
                                <span x-text="isSubmitting ? 'SUBMITTING...' : 'SUBMIT REVIEW'"></span>
                            </button>

                            <!-- Skip Link -->
                            <div class="text-center">
                                <a href="{{ url('/') }}" 
                                   class="text-xs font-bold text-[#0D5C56] hover:text-[#115E59] hover:underline transition-colors">
                                    Skip for now
                                </a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>

    </div>

</body>
</html>
