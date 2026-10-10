<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageUploadService;

class SettingController extends Controller
{
    /**
     * Display branding settings management form.
     */
    public function index()
    {
        $setting = Setting::getSettings();
        return view('manager.settings.index', compact('setting'));
    }

    /**
     * Update branding settings.
     */
    public function update(Request $request)
    {
        $setting = Setting::getSettings();

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'brand_slogan' => ['nullable', 'string', 'max:255'],
            'brand_address' => ['nullable', 'string', 'max:1000'],
            'brand_phone1' => ['nullable', 'string', 'max:50'],
            'brand_phone2' => ['nullable', 'string', 'max:50'],
            'brand_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:4096'],
            'whatsapp_birthday_message' => ['nullable', 'string', 'max:2000'],
            'whatsapp_anniversary_message' => ['nullable', 'string', 'max:2000'],
            'pos_receipt_note' => ['nullable', 'string', 'max:2000'],
            'appointment_receipt_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $logoPath = $setting->brand_logo;

        if ($request->hasFile('brand_logo')) {
            ImageUploadService::delete($setting->brand_logo);
            $logoPath = ImageUploadService::upload($request->file('brand_logo'), 'branding', 'brand_logo');
        }

        $setting->update([
            'brand_name' => $validated['brand_name'],
            'brand_slogan' => $validated['brand_slogan'] ?? '',
            'brand_address' => $validated['brand_address'] ?? '',
            'brand_phone1' => $validated['brand_phone1'] ?? '',
            'brand_phone2' => $validated['brand_phone2'] ?? '',
            'whatsapp_birthday_message' => $validated['whatsapp_birthday_message'] ?? Setting::defaultBirthdayMessage(),
            'whatsapp_anniversary_message' => $validated['whatsapp_anniversary_message'] ?? Setting::defaultAnniversaryMessage(),
            'pos_receipt_note' => $validated['pos_receipt_note'] ?? Setting::defaultPosReceiptNote(),
            'appointment_receipt_note' => $validated['appointment_receipt_note'] ?? Setting::defaultAppointmentReceiptNote(),
            'brand_logo' => $logoPath,
        ]);

        return redirect()->route('manager.settings.index')
            ->with('success', 'Brand identity, print receipt notes, and system settings updated successfully!');
    }
}
