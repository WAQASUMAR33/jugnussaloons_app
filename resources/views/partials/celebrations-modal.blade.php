@php
    $totalCelebrations = ($todayBirthdays->count() ?? 0) + ($todayAnniversaries->count() ?? 0);
@endphp

@if($totalCelebrations > 0)
<div x-data="{ 
    openCelebrationModal: false, 
    activeCelebrationTab: 'all'
}" x-cloak class="relative z-50">

    <!-- Clean Top Trigger Bar (Click to Show Popup) -->
    <div class="bg-white border border-slate-200 rounded-none p-3 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2.5">
            <span class="text-xl">🎉</span>
            <div>
                <span class="text-xs font-black text-slate-900">Today's Celebrations Alert</span>
                <span class="text-xs text-slate-500 font-medium ml-1">
                    — {{ $todayBirthdays->count() }} Birthday(s), {{ $todayAnniversaries->count() }} Anniversary(s) today
                </span>
            </div>
        </div>

        <button type="button" @click="openCelebrationModal = true" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-none transition-colors shrink-0 flex items-center gap-1.5 shadow-xs">
            <span>🎉 View Celebrations ({{ $totalCelebrations }})</span>
        </button>
    </div>

    <!-- POPUP MODAL (Opens only on click) -->
    <div x-show="openCelebrationModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6">
        
        <div @click.away="openCelebrationModal = false"
             x-show="openCelebrationModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 sm:scale-95"
             class="bg-white rounded-none shadow-2xl border border-slate-200 w-full max-w-xl overflow-hidden flex flex-col max-h-[85vh]">
            
            <!-- Clean Header -->
            <div class="bg-slate-900 p-5 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🎉</span>
                    <div>
                        <h2 class="text-base font-black text-white heading-font">
                            Today's Birthdays & Anniversaries
                        </h2>
                        <p class="text-[11px] text-slate-400 font-medium">
                            {{ now()->format('l, M d, Y') }} &bull; {{ $totalCelebrations }} Celebrants Today
                        </p>
                    </div>
                </div>

                <button type="button" @click="openCelebrationModal = false" 
                        class="w-7 h-7 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-none flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Tabs Switcher -->
            <div class="flex items-center gap-1 p-3 bg-slate-50 border-b border-slate-200 shrink-0">
                <button type="button" @click="activeCelebrationTab = 'all'"
                        :class="activeCelebrationTab === 'all' ? 'bg-white text-slate-900 font-black shadow-xs border-slate-300' : 'text-slate-600 hover:bg-slate-100 font-bold border-transparent'"
                        class="px-3 py-1.5 text-xs rounded-none transition-all border">
                    All ({{ $totalCelebrations }})
                </button>
                @if($todayBirthdays->count() > 0)
                    <button type="button" @click="activeCelebrationTab = 'birthdays'"
                            :class="activeCelebrationTab === 'birthdays' ? 'bg-white text-amber-900 font-black shadow-xs border-slate-300' : 'text-slate-600 hover:bg-slate-100 font-bold border-transparent'"
                            class="px-3 py-1.5 text-xs rounded-none transition-all border">
                        🎂 Birthdays ({{ $todayBirthdays->count() }})
                    </button>
                @endif
                @if($todayAnniversaries->count() > 0)
                    <button type="button" @click="activeCelebrationTab = 'anniversaries'"
                            :class="activeCelebrationTab === 'anniversaries' ? 'bg-white text-rose-900 font-black shadow-xs border-slate-300' : 'text-slate-600 hover:bg-slate-100 font-bold border-transparent'"
                            class="px-3 py-1.5 text-xs rounded-none transition-all border">
                        💍 Anniversaries ({{ $todayAnniversaries->count() }})
                    </button>
                @endif
            </div>

            <!-- Modal Content / Celebrants List -->
            <div class="p-4 overflow-y-auto space-y-3 divide-y divide-slate-100 flex-1">
                
                <!-- BIRTHDAYS LIST -->
                <template x-if="activeCelebrationTab === 'all' || activeCelebrationTab === 'birthdays'">
                    <div class="space-y-2.5">
                        @if($todayBirthdays->count() > 0)
                            <div class="text-[10px] font-black text-amber-800 uppercase tracking-wider">
                                🎂 Birthday Celebrants ({{ $todayBirthdays->count() }})
                            </div>
                            @foreach($todayBirthdays as $user)
                                @php
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $user->phone_no1);
                                    $age = $user->date_of_birth ? now()->year - $user->date_of_birth->year : null;
                                    $bdayMsg = rawurlencode("Happy Birthday " . $user->name . "! 🎉 Wishing you a wonderful day from the team at Jugnu Saloon. Visit us today to enjoy special celebration discounts!");
                                @endphp
                                <div class="p-3 bg-white border border-slate-200 rounded-none flex items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-amber-100 text-amber-800 font-black flex items-center justify-center text-xs rounded-none shrink-0 border border-amber-200">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-xs font-black text-slate-900">{{ $user->name }}</h4>
                                                <span class="px-1.5 py-0.2 bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">
                                                    Birthday
                                                </span>
                                                @if($user->category)
                                                    <span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 text-[10px] font-medium border border-slate-200">
                                                        {{ $user->category->title }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                                                <span>{{ $user->phone_no1 ?: 'No phone' }}</span>
                                                @if($user->date_of_birth)
                                                    <span class="text-slate-400 ml-1.5">• Born {{ $user->date_of_birth->format('M d') }} {{ $age ? "({$age} yrs)" : '' }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Action: Only WhatsApp -->
                                    @if($cleanPhone)
                                        <a href="https://wa.me/{{ $cleanPhone }}?text={{ $bdayMsg }}" target="_blank" 
                                           class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-none transition-colors flex items-center gap-1 shrink-0">
                                            <span>💬 WhatsApp</span>
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </template>

                <!-- ANNIVERSARIES LIST -->
                <template x-if="activeCelebrationTab === 'all' || activeCelebrationTab === 'anniversaries'">
                    <div class="space-y-2.5 pt-3">
                        @if($todayAnniversaries->count() > 0)
                            <div class="text-[10px] font-black text-rose-800 uppercase tracking-wider">
                                💍 Wedding Anniversary Celebrants ({{ $todayAnniversaries->count() }})
                            </div>
                            @foreach($todayAnniversaries as $user)
                                @php
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $user->phone_no1);
                                    $years = $user->date_of_anniversary ? now()->year - $user->date_of_anniversary->year : null;
                                    $annivMsg = rawurlencode("Happy Wedding Anniversary " . $user->name . "! 💍 Wishing you both endless joy and love from Jugnu Saloon. Celebrate your special day with our luxury pampering packages!");
                                @endphp
                                <div class="p-3 bg-white border border-slate-200 rounded-none flex items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-rose-100 text-rose-800 font-black flex items-center justify-center text-xs rounded-none shrink-0 border border-rose-200">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-xs font-black text-slate-900">{{ $user->name }}</h4>
                                                <span class="px-1.5 py-0.2 bg-rose-50 text-rose-800 border border-rose-200 text-[10px] font-bold">
                                                    Anniversary
                                                </span>
                                                @if($user->category)
                                                    <span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 text-[10px] font-medium border border-slate-200">
                                                        {{ $user->category->title }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                                                <span>{{ $user->phone_no1 ?: 'No phone' }}</span>
                                                @if($user->date_of_anniversary)
                                                    <span class="text-slate-400 ml-1.5">• {{ $user->date_of_anniversary->format('M d') }} {{ $years ? "({$years} yrs)" : '' }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Action: Only WhatsApp -->
                                    @if($cleanPhone)
                                        <a href="https://wa.me/{{ $cleanPhone }}?text={{ $annivMsg }}" target="_blank" 
                                           class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-none transition-colors flex items-center gap-1 shrink-0">
                                            <span>💬 WhatsApp</span>
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </template>

            </div>

            <!-- Simple Modal Footer -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end shrink-0">
                <button type="button" @click="openCelebrationModal = false" 
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-none transition-colors">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
@endif
