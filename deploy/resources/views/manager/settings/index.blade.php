@extends('layouts.material')

@section('title', 'Brand Identity & Store Settings')

@section('content')
<div class="space-y-8" x-data="{ 
    logoPreview: '{{ $setting->brand_logo ? asset($setting->brand_logo) : '' }}',
    handleLogoChange(e) {
        const file = e.target.files[0];
        if (file) {
            this.logoPreview = URL.createObjectURL(file);
        }
    }
}">
    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
        <a href="{{ route('manager.settings.index') }}" 
           class="px-5 py-2.5 font-extrabold text-xs transition-all flex items-center gap-2 bg-indigo-600 text-white shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            Branding & Store Settings
        </a>
        <a href="{{ route('manager.bank-accounts.index') }}" 
           class="px-5 py-2.5 font-extrabold text-xs transition-all flex items-center gap-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
            Bank Accounts Management
        </a>
    </div>

    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-purple-900 text-white p-6 sm:p-8 shadow-md border border-indigo-700/50">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-white/10 backdrop-blur-md border border-white/20 text-white">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Brand Identity & System Settings</h1>
                        <p class="text-xs sm:text-sm text-indigo-200 mt-1 font-medium">Configure store name, logo, contact numbers, address, and tagline for system headers and print receipts.</p>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 bg-white/10 text-xs font-extrabold uppercase tracking-wider text-indigo-100 border border-white/20">
                    ⚙️ Active Configuration
                </span>
            </div>
        </div>
    </div>

    <!-- Alert Banner -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <p class="text-sm font-bold">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 shadow-2xs">
            <h4 class="font-extrabold text-sm mb-1">Please correct the following errors:</h4>
            <ul class="list-disc list-inside text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form Section -->
    <form action="{{ route('manager.settings.update') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 shadow-sm p-6 sm:p-8 space-y-8">
        @csrf

        <!-- Branding Logo & Store Header Info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-8 border-b border-slate-200">
            <div class="lg:col-span-1 space-y-2">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-indigo-600"></span>
                    Brand Logo & Visuals
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">Upload your store logo image. Recommended format: PNG, JPG, or SVG (Max 4MB).</p>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-2">Store Brand Logo</label>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-4 bg-slate-50 border border-slate-200">
                        <div class="w-24 h-24 bg-white border border-slate-200 flex items-center justify-center overflow-hidden shrink-0 shadow-inner relative group">
                            <template x-if="logoPreview">
                                <img :src="logoPreview" class="w-full h-full object-contain p-1" alt="Brand Logo Preview">
                            </template>
                            <template x-if="!logoPreview">
                                <div class="text-center p-2">
                                    <svg class="w-8 h-8 text-slate-300 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span class="text-[10px] font-bold text-slate-400 block">No Logo</span>
                                </div>
                            </template>
                        </div>

                        <div class="space-y-2">
                            <input type="file" name="brand_logo" id="brand_logo" @change="handleLogoChange" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-xs file:font-black file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                            <p class="text-[11px] text-slate-500">Transparent PNG logos display best on sidebar and header backdrops.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Name & Tagline Slogan -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-8 border-b border-slate-200">
            <div class="lg:col-span-1 space-y-2">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-600"></span>
                    Identity & Tagline
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">Enter your main official business name and slogan to appear on customer receipts and header displays.</p>
            </div>

            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label for="brand_name" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-2">
                        Brand Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="brand_name" id="brand_name" value="{{ old('brand_name', $setting->brand_name) }}" required placeholder="e.g. Jugnu Saloon & Spa" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">
                </div>

                <div>
                    <label for="brand_slogan" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-2">
                        Brand Slogan / Tagline
                    </label>
                    <input type="text" name="brand_slogan" id="brand_slogan" value="{{ old('brand_slogan', $setting->brand_slogan) }}" placeholder="e.g. Executive Hair Styling & Grooming" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">
                </div>
            </div>
        </div>

        <!-- Contact Numbers & Business Address -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-8 border-b border-slate-200">
            <div class="lg:col-span-1 space-y-2">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-emerald-600"></span>
                    Contact & Location
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">Phone numbers and physical store address will be printed at the top of sales slips, appointment slips, and supplier invoices.</p>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="brand_phone1" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-2">
                            Primary Phone Number
                        </label>
                        <input type="text" name="brand_phone1" id="brand_phone1" value="{{ old('brand_phone1', $setting->brand_phone1) }}" placeholder="e.g. +92 300 1234567" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">
                    </div>

                    <div>
                        <label for="brand_phone2" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-2">
                            Secondary Phone Number (2nd)
                        </label>
                        <input type="text" name="brand_phone2" id="brand_phone2" value="{{ old('brand_phone2', $setting->brand_phone2) }}" placeholder="e.g. +92 321 7654321" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">
                    </div>
                </div>

                <div>
                    <label for="brand_address" class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-2">
                        Complete Store Address
                    </label>
                    <textarea name="brand_address" id="brand_address" rows="3" placeholder="e.g. Shop #12, Commercial Plaza, Main Boulevard, Saloon District, City" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">{{ old('brand_address', $setting->brand_address) }}</textarea>
                </div>
            </div>
        </div>

        <!-- WhatsApp Celebration Message Templates (Birthdate & Marriage Ceremony) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-8 border-b border-slate-200">
            <div class="lg:col-span-1 space-y-2">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-emerald-500"></span>
                    WhatsApp Wishing Messages
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Personalize default wishing messages sent via WhatsApp for client birthdates and marriage ceremony anniversaries.
                </p>
                <div class="p-3 bg-indigo-50/70 border border-indigo-100 rounded-none space-y-1.5 mt-3">
                    <p class="text-[11px] font-black text-indigo-950 uppercase tracking-wider">Dynamic Placeholders:</p>
                    <div class="flex flex-wrap gap-1 text-[10px] font-mono font-bold text-indigo-700">
                        <span class="bg-white px-1.5 py-0.5 border border-indigo-200">{name}</span>
                        <span class="bg-white px-1.5 py-0.5 border border-indigo-200">{salon_name}</span>
                        <span class="bg-white px-1.5 py-0.5 border border-indigo-200">{date}</span>
                        <span class="bg-white px-1.5 py-0.5 border border-indigo-200">{milestone}</span>
                    </div>
                    <p class="text-[10px] text-indigo-600/90 leading-tight pt-1">Tags are automatically substituted with client data upon sending.</p>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <!-- Birthday Message Template -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="whatsapp_birthday_message" class="block text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span>🎂 Birthday Wishing Message (Birthdate)</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400">1-Click WhatsApp Trigger</span>
                    </div>
                    <textarea name="whatsapp_birthday_message" id="whatsapp_birthday_message" rows="3" 
                              placeholder="e.g. Happy Birthday {name}! 🎂 Wishing you a wonderful day from {salon_name}..." 
                              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">{{ old('whatsapp_birthday_message', $setting->whatsapp_birthday_message ?: \App\Models\Setting::defaultBirthdayMessage()) }}</textarea>
                    <p class="text-[11px] text-slate-500">Sent to clients on their birthdate with invitation and special discount offer.</p>
                </div>

                <!-- Marriage Ceremony / Anniversary Template -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <label for="whatsapp_anniversary_message" class="block text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span>💍 Marriage Ceremony / Wedding Anniversary Message</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400">1-Click WhatsApp Trigger</span>
                    </div>
                    <textarea name="whatsapp_anniversary_message" id="whatsapp_anniversary_message" rows="3" 
                              placeholder="e.g. Happy Marriage Anniversary {name}! 💍 Wishing you both endless happiness from {salon_name}..." 
                              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">{{ old('whatsapp_anniversary_message', $setting->whatsapp_anniversary_message ?: \App\Models\Setting::defaultAnniversaryMessage()) }}</textarea>
                    <p class="text-[11px] text-slate-500">Sent to clients on their marriage ceremony milestone with luxury couple package offer.</p>
                </div>
            </div>
        </div>

        <!-- Print Receipt Invoices Notes (1 - POS & 2 - Appointments) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-8 border-b border-slate-200">
            <div class="lg:col-span-1 space-y-2">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-600"></span>
                    Print Receipt Invoices Notes
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Customize the footer notes, policy disclaimers, terms, or greeting messages printed on customer receipts.
                </p>
                <div class="p-3 bg-blue-50/70 border border-blue-100 space-y-1.5 mt-3">
                    <p class="text-[11px] font-black text-blue-950 uppercase tracking-wider">Receipt Types:</p>
                    <ul class="text-[10px] text-blue-800 space-y-1 font-medium">
                        <li>• <strong class="font-bold">1 - POS Print Receipt:</strong> Product sales checkout slips.</li>
                        <li>• <strong class="font-bold">2 - Appointment Booking Receipt:</strong> Salon service & booking slips.</li>
                    </ul>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <!-- 1 - POS Print Receipt Notes -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="pos_receipt_note" class="block text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-mono font-bold">1</span>
                            <span>POS Print Receipt Note</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400">Products & POS Checkout</span>
                    </div>
                    <textarea name="pos_receipt_note" id="pos_receipt_note" rows="3" 
                              placeholder="e.g. Goods once sold can be exchanged within 7 days with original receipt. No cash refunds. Thank you for shopping with us!" 
                              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">{{ old('pos_receipt_note', $setting->pos_receipt_note ?: \App\Models\Setting::defaultPosReceiptNote()) }}</textarea>
                    <p class="text-[11px] text-slate-500">This note will appear on all printed POS product sales receipts.</p>
                </div>

                <!-- 2 - Appointment Booking Receipt Notes -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <label for="appointment_receipt_note" class="block text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 bg-purple-100 text-purple-800 text-[10px] font-mono font-bold">2</span>
                            <span>Appointment Booking Receipt Note</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400">Appointments & Services</span>
                    </div>
                    <textarea name="appointment_receipt_note" id="appointment_receipt_note" rows="3" 
                              placeholder="e.g. Appointments are subject to slot availability. Please arrive 10 minutes prior to your scheduled time. Thank you for choosing us!" 
                              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">{{ old('appointment_receipt_note', $setting->appointment_receipt_note ?: \App\Models\Setting::defaultAppointmentReceiptNote()) }}</textarea>
                    <p class="text-[11px] text-slate-500">This note will appear on all printed appointment & salon service booking receipts.</p>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end gap-4">
            <button type="submit" class="px-8 py-3.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-black text-sm uppercase tracking-wider shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Save Branding Settings</span>
            </button>
        </div>
    </form>
</div>
@endsection
