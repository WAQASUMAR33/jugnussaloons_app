<div x-data="{ 
    celebrationTab: '{{ $totalTodayCelebrations > 0 ? 'today' : ($totalUpcomingCelebrations > 0 ? 'upcoming' : 'all') }}',
    searchCelebrant: '',
    messageModalOpen: false,
    modalClientName: '',
    modalClientPhone: '',
    modalType: 'birthday',
    modalMessage: '',
    copySuccess: false,
    openMessageModal(name, phone, type, msg) {
        this.modalClientName = name;
        this.modalClientPhone = phone;
        this.modalType = type;
        this.modalMessage = msg;
        this.copySuccess = false;
        this.messageModalOpen = true;
    },
    formatWhatsAppNumber(phone) {
        if (!phone) return '';
        let digits = phone.replace(/[^0-9]/g, '');
        if (!digits) return '';
        if (digits.startsWith('00')) {
            digits = digits.substring(2);
        }
        if (digits.startsWith('0')) {
            digits = '92' + digits.replace(/^0+/, '');
        } else if (digits.length === 10 && digits.startsWith('3')) {
            digits = '92' + digits;
        }
        return digits;
    },
    sendWhatsApp() {
        if (!this.modalClientPhone) return;
        const clean = this.formatWhatsAppNumber(this.modalClientPhone);
        if (!clean) return;
        const encoded = encodeURIComponent(this.modalMessage);
        window.open(`https://wa.me/${clean}?text=${encoded}`, '_blank');
        this.messageModalOpen = false;
    },
    copyToClipboard() {
        navigator.clipboard.writeText(this.modalMessage).then(() => {
            this.copySuccess = true;
            setTimeout(() => { this.copySuccess = false; }, 2000);
        });
    }
}" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">

    <!-- Header -->
    <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-gradient-to-r from-amber-50/50 via-rose-50/30 to-indigo-50/40">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-500 via-rose-500 to-indigo-600 text-white flex items-center justify-center text-2xl shadow-sm shrink-0">
                🎂
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-base font-black text-slate-900 heading-font">Client Birthdays & Marriage Ceremony Hub</h3>
                    @if($totalTodayCelebrations > 0)
                        <span class="px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px] font-black uppercase tracking-wider animate-pulse">
                            🎉 {{ $totalTodayCelebrations }} Today!
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Send personalized WhatsApp wishing messages for client birthdates and marriage ceremony milestones.
                </p>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="flex items-center gap-2.5">
            <a href="{{ route('manager.settings.index') }}" 
               class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200/80 shadow-2xs transition-all flex items-center gap-1.5 shrink-0"
               title="Configure WhatsApp message templates">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                <span>WhatsApp Templates</span>
            </a>
            <a href="{{ route('manager.accounts.index') }}" 
               class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-2xs transition-all flex items-center gap-1.5 shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Client Date</span>
            </a>
        </div>
    </div>

    <!-- Tabs Navigation Bar -->
    <div class="flex items-center gap-1.5 px-5 pt-3 pb-2 border-b border-slate-100 bg-slate-50/60 overflow-x-auto">
        <button type="button" @click="celebrationTab = 'today'"
                :class="celebrationTab === 'today' ? 'bg-white text-indigo-900 font-black shadow-xs border-slate-200' : 'text-slate-600 hover:text-slate-900 font-bold border-transparent hover:bg-white/60'"
                class="px-3.5 py-2 text-xs rounded-xl border transition-all flex items-center gap-2 shrink-0">
            <span>🎉 Today</span>
            <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ $totalTodayCelebrations > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $totalTodayCelebrations }}
            </span>
        </button>

        <button type="button" @click="celebrationTab = 'upcoming'"
                :class="celebrationTab === 'upcoming' ? 'bg-white text-indigo-900 font-black shadow-xs border-slate-200' : 'text-slate-600 hover:text-slate-900 font-bold border-transparent hover:bg-white/60'"
                class="px-3.5 py-2 text-xs rounded-xl border transition-all flex items-center gap-2 shrink-0">
            <span>📅 Upcoming (30 Days)</span>
            <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ $totalUpcomingCelebrations > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $totalUpcomingCelebrations }}
            </span>
        </button>

        <button type="button" @click="celebrationTab = 'month'"
                :class="celebrationTab === 'month' ? 'bg-white text-indigo-900 font-black shadow-xs border-slate-200' : 'text-slate-600 hover:text-slate-900 font-bold border-transparent hover:bg-white/60'"
                class="px-3.5 py-2 text-xs rounded-xl border transition-all flex items-center gap-2 shrink-0">
            <span>🗓️ This Month ({{ now()->format('M') }})</span>
            <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ $totalMonthCelebrations > 0 ? 'bg-purple-100 text-purple-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $totalMonthCelebrations }}
            </span>
        </button>

        <button type="button" @click="celebrationTab = 'all'"
                :class="celebrationTab === 'all' ? 'bg-white text-indigo-900 font-black shadow-xs border-slate-200' : 'text-slate-600 hover:text-slate-900 font-bold border-transparent hover:bg-white/60'"
                class="px-3.5 py-2 text-xs rounded-xl border transition-all flex items-center gap-2 shrink-0">
            <span>👥 All Celebrants</span>
            <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black bg-indigo-100 text-indigo-800">
                {{ $totalRegisteredCelebrants }}
            </span>
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="p-5 sm:p-6">

        <!-- ================= TAB 1: TODAY'S CELEBRATIONS ================= -->
        <div x-show="celebrationTab === 'today'" x-cloak class="space-y-4">
            @if($totalTodayCelebrations > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    {{-- Today's Birthdays --}}
                    @foreach($todayBirthdays as $user)
                        @php
                            $cleanPhone = \App\Services\CelebrationService::formatWhatsAppPhone($user->phone_no1);
                            $age = $user->turning_age ?: ($user->date_of_birth ? now()->year - $user->date_of_birth->year : null);
                            $bdayMsg = $user->birthday_wishing_message ?? "Happy Birthday {$user->name}! 🎂🎉 Wishing you a wonderful day from the team at Jugnu Saloon.";
                            $bdayUrl = $user->birthday_whatsapp_url ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $bdayMsg);
                        @endphp
                        <div class="p-4 rounded-xl border-2 border-amber-300/80 bg-gradient-to-br from-amber-50/80 to-white flex flex-col justify-between gap-3 shadow-xs hover:shadow-md transition-all">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-900 font-black flex items-center justify-center text-sm shrink-0 border border-amber-200 shadow-2xs">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm font-extrabold text-slate-900">{{ $user->name }}</h4>
                                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-black text-[10px] border border-amber-300">
                                                🎂 Birthday Today!
                                            </span>
                                            @if($user->category)
                                                <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-semibold">
                                                    {{ $user->category->title }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5">
                                            <span>{{ $user->phone_no1 ?: 'No phone' }}</span>
                                            @if($age)
                                                <span class="text-amber-700 font-bold ml-1.5">• Turning {{ $age }} Years Old</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- WhatsApp Wishing Message Preview -->
                            <div class="p-2.5 bg-white/90 rounded-lg border border-amber-200/80 text-[11px] text-slate-700 leading-relaxed font-sans italic line-clamp-2">
                                "{{ $bdayMsg }}"
                            </div>

                            <div class="flex items-center gap-2 pt-2 border-t border-amber-200/60">
                                @if($cleanPhone && $bdayUrl)
                                    <a href="{{ $bdayUrl }}" target="_blank" 
                                       class="flex-1 py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-2xs">
                                        <span>💬 Send Birthday Wish</span>
                                    </a>
                                    <button type="button" @click="openMessageModal('{{ addslashes($user->name) }}', '{{ $user->phone_no1 }}', 'birthday', '{{ addslashes($bdayMsg) }}')"
                                            class="px-2.5 py-2 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs transition-colors" title="Edit wishing message before sending">
                                        ✏️ Edit
                                    </button>
                                    <a href="tel:{{ $user->phone_no1 }}" 
                                       class="p-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400 italic">No valid phone number available</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Today's Marriage Anniversaries --}}
                    @foreach($todayAnniversaries as $user)
                        @php
                            $cleanPhone = \App\Services\CelebrationService::formatWhatsAppPhone($user->phone_no1);
                            $years = $user->anniversary_years ?: ($user->date_of_anniversary ? now()->year - $user->date_of_anniversary->year : null);
                            $annivMsg = $user->anniversary_wishing_message ?? "Happy Marriage Anniversary {$user->name}! 💍✨ Wishing you both endless joy and love from Jugnu Saloon.";
                            $annivUrl = $user->anniversary_whatsapp_url ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $annivMsg);
                        @endphp
                        <div class="p-4 rounded-xl border-2 border-rose-300/80 bg-gradient-to-br from-rose-50/80 to-white flex flex-col justify-between gap-3 shadow-xs hover:shadow-md transition-all">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-900 font-black flex items-center justify-center text-sm shrink-0 border border-rose-200 shadow-2xs">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm font-extrabold text-slate-900">{{ $user->name }}</h4>
                                            <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-900 font-black text-[10px] border border-rose-300">
                                                💍 Marriage Ceremony Today!
                                            </span>
                                            @if($user->category)
                                                <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-semibold">
                                                    {{ $user->category->title }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5">
                                            <span>{{ $user->phone_no1 ?: 'No phone' }}</span>
                                            @if($years)
                                                <span class="text-rose-700 font-bold ml-1.5">• {{ $years }} Year Milestone</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- WhatsApp Wishing Message Preview -->
                            <div class="p-2.5 bg-white/90 rounded-lg border border-rose-200/80 text-[11px] text-slate-700 leading-relaxed font-sans italic line-clamp-2">
                                "{{ $annivMsg }}"
                            </div>

                            <div class="flex items-center gap-2 pt-2 border-t border-rose-200/60">
                                @if($cleanPhone && $annivUrl)
                                    <a href="{{ $annivUrl }}" target="_blank" 
                                       class="flex-1 py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-2xs">
                                        <span>💬 Send Marriage Ceremony Wish</span>
                                    </a>
                                    <button type="button" @click="openMessageModal('{{ addslashes($user->name) }}', '{{ $user->phone_no1 }}', 'anniversary', '{{ addslashes($annivMsg) }}')"
                                            class="px-2.5 py-2 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-900 font-bold text-xs transition-colors" title="Edit wishing message before sending">
                                        ✏️ Edit
                                    </button>
                                    <a href="tel:{{ $user->phone_no1 }}" 
                                       class="p-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400 italic">No valid phone number available</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                </div>
            @else
                <div class="py-8 text-center bg-slate-50/60 rounded-xl border border-dashed border-slate-200 p-6 space-y-3">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mx-auto shadow-2xs">
                        🎂
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-800">No client celebrations today ({{ now()->format('M d, Y') }})</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
                            There are no birthdays or wedding anniversaries scheduled for today. Check upcoming celebrations to plan in advance!
                        </p>
                    </div>
                    <div class="pt-2 flex items-center justify-center gap-3">
                        <button type="button" @click="celebrationTab = 'upcoming'" 
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-colors shadow-2xs">
                            View Upcoming Celebrations ({{ $totalUpcomingCelebrations }}) &rarr;
                        </button>
                        <a href="{{ route('manager.accounts.index') }}" 
                           class="px-4 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs border border-slate-200 transition-colors">
                            Manage Client Dates
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- ================= TAB 2: UPCOMING CELEBRATIONS (30 DAYS) ================= -->
        <div x-show="celebrationTab === 'upcoming'" x-cloak class="space-y-4">
            @php
                $upcomingItems = $sortedUpcoming->filter(fn($item) => $item['days_until'] > 0 && $item['days_until'] <= 30);
            @endphp

            @if($upcomingItems->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    @foreach($upcomingItems as $item)
                        @php
                            $user = $item['account'];
                            $cleanPhone = \App\Services\CelebrationService::formatWhatsAppPhone($user->phone_no1);
                            $isBday = $item['type'] === 'birthday';
                            $msgTitle = $isBday ? 'Birthday' : 'Marriage Ceremony';
                            $earlyMsg = $item['wishing_message'] ?? ($isBday ? $user->birthday_wishing_message : $user->anniversary_wishing_message);
                            $earlyUrl = $item['whatsapp_url'] ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $earlyMsg);
                        @endphp
                        <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:shadow-xs transition-all flex flex-col justify-between gap-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl {{ $isBday ? 'bg-amber-100 text-amber-900 border-amber-200' : 'bg-rose-100 text-rose-900 border-rose-200' }} border font-black flex items-center justify-center text-xs shrink-0">
                                        {{ $isBday ? '🎂' : '💍' }}
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-black text-slate-900 truncate max-w-[150px]">{{ $user->name }}</h4>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-extrabold {{ $isBday ? 'bg-amber-50 text-amber-800' : 'bg-rose-50 text-rose-800' }}">
                                                {{ $item['formatted_date'] }}
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-semibold">• {{ $item['milestone'] }}</span>
                                        </div>
                                    </div>
                                </div>

                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $item['days_until'] <= 7 ? 'bg-rose-100 text-rose-800' : 'bg-indigo-50 text-indigo-700' }} border border-slate-200 shrink-0">
                                    In {{ $item['days_until'] }} {{ Str::plural('day', $item['days_until']) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 text-xs">
                                <span class="text-[11px] text-slate-500 truncate">{{ $user->phone_no1 ?: 'No phone' }}</span>
                                @if($cleanPhone && $earlyUrl)
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ $earlyUrl }}" target="_blank" 
                                           class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] flex items-center gap-1 shrink-0 transition-colors">
                                            <span>💬 Send Wish</span>
                                        </a>
                                        <button type="button" @click="openMessageModal('{{ addslashes($user->name) }}', '{{ $user->phone_no1 }}', '{{ $item['type'] }}', '{{ addslashes($earlyMsg) }}')"
                                                class="p-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] transition-colors" title="Customize Message">
                                            ✏️
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center bg-slate-50/60 rounded-xl border border-dashed border-slate-200 p-6 space-y-2">
                    <p class="text-sm font-bold text-slate-700">No celebrations in the next 30 days</p>
                    <p class="text-xs text-slate-400">Check the 'This Month' or 'All Celebrants' tabs to view future celebration dates.</p>
                </div>
            @endif
        </div>

        <!-- ================= TAB 3: THIS MONTH ================= -->
        <div x-show="celebrationTab === 'month'" x-cloak class="space-y-4">
            @php
                $monthBirthdays = $thisMonthBirthdays ?? collect();
                $monthAnniversaries = $thisMonthAnniversaries ?? collect();
            @endphp

            @if($monthBirthdays->count() > 0 || $monthAnniversaries->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                    {{-- Birthdays this month --}}
                    @foreach($monthBirthdays as $user)
                        @php
                            $cleanPhone = \App\Services\CelebrationService::formatWhatsAppPhone($user->phone_no1);
                            $bdayDay = Carbon\Carbon::parse($user->date_of_birth)->format('M d');
                            $bdayMsg = $user->birthday_wishing_message ?? "Happy Birthday {$user->name}! 🎂 Wishing you a wonderful celebration from Jugnu Saloon.";
                            $bdayUrl = $user->birthday_whatsapp_url ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $bdayMsg);
                        @endphp
                        <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/30 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg">🎂</span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900">{{ $user->name }}</h4>
                                    <p class="text-[11px] text-amber-800 font-bold">Birthdate: {{ $bdayDay }}</p>
                                </div>
                            </div>
                            @if($cleanPhone && $bdayUrl)
                                <div class="flex items-center gap-1">
                                    <a href="{{ $bdayUrl }}" target="_blank" 
                                       class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition-colors flex items-center gap-1" title="Send Birthday Wish">
                                        <span>💬 Wish</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Anniversaries this month --}}
                    @foreach($monthAnniversaries as $user)
                        @php
                            $cleanPhone = \App\Services\CelebrationService::formatWhatsAppPhone($user->phone_no1);
                            $annivDay = Carbon\Carbon::parse($user->date_of_anniversary)->format('M d');
                            $annivMsg = $user->anniversary_wishing_message ?? "Happy Marriage Anniversary {$user->name}! 💍 Wishing you both endless joy from Jugnu Saloon.";
                            $annivUrl = $user->anniversary_whatsapp_url ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $annivMsg);
                        @endphp
                        <div class="p-3.5 rounded-xl border border-rose-200 bg-rose-50/30 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg">💍</span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900">{{ $user->name }}</h4>
                                    <p class="text-[11px] text-rose-800 font-bold">Marriage: {{ $annivDay }}</p>
                                </div>
                            </div>
                            @if($cleanPhone && $annivUrl)
                                <div class="flex items-center gap-1">
                                    <a href="{{ $annivUrl }}" target="_blank" 
                                       class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition-colors flex items-center gap-1" title="Send Marriage Ceremony Wish">
                                        <span>💬 Wish</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center bg-slate-50/60 rounded-xl border border-dashed border-slate-200 p-6 space-y-2">
                    <p class="text-sm font-bold text-slate-700">No client celebrations in {{ now()->format('F') }}</p>
                    <p class="text-xs text-slate-400">Add birthdates and marriage ceremony dates to client profiles in Account Management.</p>
                </div>
            @endif
        </div>

        <!-- ================= TAB 4: ALL REGISTERED CELEBRANTS DIRECTORY ================= -->
        <div x-show="celebrationTab === 'all'" x-cloak class="space-y-4">
            
            <!-- Quick Search Input -->
            <div class="flex items-center justify-between gap-3">
                <div class="relative flex-1 max-w-sm">
                    <input type="text" x-model="searchCelebrant" placeholder="Search celebrants by name or phone..." 
                           class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-hidden">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <span class="text-xs font-bold text-slate-500">
                    {{ $allCelebrants->count() }} Total Registered
                </span>
            </div>

            @if($allCelebrants->count() > 0)
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200">
                                <th class="py-2.5 px-4">Client Name</th>
                                <th class="py-2.5 px-4">Category</th>
                                <th class="py-2.5 px-4">Birthdate</th>
                                <th class="py-2.5 px-4">Marriage Ceremony</th>
                                <th class="py-2.5 px-4">Next Event</th>
                                <th class="py-2.5 px-4 text-right">WhatsApp Wishing</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($allCelebrants as $user)
                                @php
                                    $cleanPhone = \App\Services\CelebrationService::formatWhatsAppPhone($user->phone_no1);
                                    $userSearchStr = strtolower($user->name . ' ' . $user->phone_no1 . ' ' . ($user->category->title ?? ''));
                                    $bdayMsg = $user->birthday_wishing_message ?? "Happy Birthday {$user->name}! 🎂 Wishing you a wonderful day from Jugnu Saloon.";
                                    $annivMsg = $user->anniversary_wishing_message ?? "Happy Marriage Anniversary {$user->name}! 💍 Wishing you both endless happiness from Jugnu Saloon.";
                                    $userBdayUrl = $user->birthday_whatsapp_url ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $bdayMsg);
                                    $userAnnivUrl = $user->anniversary_whatsapp_url ?? \App\Services\CelebrationService::buildWhatsAppUrl($user->phone_no1, $annivMsg);
                                @endphp
                                <tr x-show="!searchCelebrant || '{{ addslashes($userSearchStr) }}'.includes(searchCelebrant.toLowerCase())" 
                                    class="hover:bg-slate-50 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 font-extrabold flex items-center justify-center text-[10px] shrink-0">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <span class="font-bold text-slate-900 block">{{ $user->name }}</span>
                                                <span class="text-[11px] text-slate-400 font-mono">{{ $user->phone_no1 ?: 'No phone' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($user->category)
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold">
                                                {{ $user->category->title }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 text-[11px]">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($user->date_of_birth)
                                            <span class="font-bold text-amber-900 bg-amber-50 px-2 py-0.5 rounded text-[11px] border border-amber-200/60 inline-flex items-center gap-1">
                                                🎂 {{ $user->birthday_formatted }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 italic text-[11px]">Not set</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($user->date_of_anniversary)
                                            <span class="font-bold text-rose-900 bg-rose-50 px-2 py-0.5 rounded text-[11px] border border-rose-200/60 inline-flex items-center gap-1">
                                                💍 {{ $user->anniversary_formatted }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 italic text-[11px]">Not set</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $bdayDays = $user->days_until_birthday ?? 999;
                                            $annivDays = $user->days_until_anniversary ?? 999;
                                            $minDays = min($bdayDays, $annivDays);
                                        @endphp
                                        @if($minDays === 0)
                                            <span class="px-2 py-0.5 rounded-full bg-rose-500 text-white font-black text-[10px]">
                                                🎉 TODAY!
                                            </span>
                                        @elseif($minDays < 999)
                                            <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-bold text-[10px] border border-indigo-100">
                                                In {{ $minDays }} {{ Str::plural('day', $minDays) }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            @if($cleanPhone)
                                                {{-- Send Birthday Wish Button --}}
                                                @if($user->date_of_birth && $userBdayUrl)
                                                    <a href="{{ $userBdayUrl }}" target="_blank" 
                                                       class="px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 text-amber-900 text-[11px] font-bold border border-amber-200 transition-colors flex items-center gap-1"
                                                       title="Send Birthday Wishing Message">
                                                        <span>🎂 Birthday Wish</span>
                                                    </a>
                                                @endif

                                                {{-- Send Marriage Ceremony Wish Button --}}
                                                @if($user->date_of_anniversary && $userAnnivUrl)
                                                    <a href="{{ $userAnnivUrl }}" target="_blank" 
                                                       class="px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-900 text-[11px] font-bold border border-rose-200 transition-colors flex items-center gap-1"
                                                       title="Send Marriage Ceremony Wishing Message">
                                                        <span>💍 Marriage Wish</span>
                                                    </a>
                                                @endif
                                            @endif
                                            <a href="{{ route('manager.accounts.index') }}" 
                                               class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors" title="Edit Client Profile">
                                                ✏️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center bg-slate-50/60 rounded-xl border border-dashed border-slate-200 p-6 space-y-3">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mx-auto shadow-2xs">
                        👥
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-800">No client celebration dates recorded yet</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
                            Add client birthdates and marriage ceremony dates in the Account Management module to celebrate milestones and build loyalty!
                        </p>
                    </div>
                    <a href="{{ route('manager.accounts.index') }}" 
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-colors shadow-2xs">
                        <span>+ Open Account Management</span>
                    </a>
                </div>
            @endif
        </div>

    </div>

    <!-- ================= MODAL: PREVIEW & CUSTOMIZE WHATSAPP WISHING MESSAGE ================= -->
    <div x-show="messageModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6" x-cloak>
        
        <div @click.away="messageModalOpen = false"
             x-show="messageModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 sm:scale-95"
             class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col">
            
            <!-- Modal Header -->
            <div class="p-5 text-white flex items-center justify-between shrink-0"
                 :class="modalType === 'birthday' ? 'bg-gradient-to-r from-amber-600 to-amber-700' : 'bg-gradient-to-r from-rose-600 to-rose-700'">
                <div class="flex items-center gap-3">
                    <span class="text-2xl" x-text="modalType === 'birthday' ? '🎂' : '💍'"></span>
                    <div>
                        <h3 class="text-base font-black text-white heading-font" 
                            x-text="modalType === 'birthday' ? 'Send Birthday Wishing Message' : 'Send Marriage Ceremony Wishing Message'">
                        </h3>
                        <p class="text-xs text-white/80 font-medium">
                            Client: <span class="font-bold" x-text="modalClientName"></span> &bull; <span x-text="modalClientPhone"></span>
                        </p>
                    </div>
                </div>

                <button type="button" @click="messageModalOpen = false" 
                        class="w-7 h-7 bg-white/10 hover:bg-white/20 text-white rounded-lg flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1.5 flex items-center justify-between">
                        <span>Personalize Message Before Sending:</span>
                        <span class="text-[10px] text-slate-400 font-normal">Editable</span>
                    </label>
                    <textarea x-model="modalMessage" rows="5" 
                              class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 leading-relaxed"></textarea>
                </div>

                <!-- Tips & Features -->
                <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl flex items-center justify-between text-xs text-emerald-800">
                    <div class="flex items-center gap-2">
                        <span class="text-base">💬</span>
                        <span>Clicking Send will open WhatsApp Web or Desktop automatically.</span>
                    </div>
                    <button type="button" @click="copyToClipboard()" class="px-2.5 py-1 bg-white hover:bg-emerald-100 text-emerald-800 font-bold rounded-lg border border-emerald-200 transition-colors shrink-0">
                        <span x-text="copySuccess ? 'Copied! ✓' : 'Copy 📋'"></span>
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5 shrink-0">
                <button type="button" @click="messageModalOpen = false" 
                        class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="button" @click="sendWhatsApp()" 
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-xs transition-colors flex items-center gap-1.5">
                    <span>Send via WhatsApp 💬</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </div>

        </div>
    </div>

</div>
