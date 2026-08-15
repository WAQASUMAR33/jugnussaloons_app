<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        ]);

        $logoPath = $setting->brand_logo;

        if ($request->hasFile('brand_logo')) {
            // Remove old logo if exists
            if ($setting->brand_logo && file_exists(public_path($setting->brand_logo))) {
                @unlink(public_path($setting->brand_logo));
            }

            $file = $request->file('brand_logo');
            $fileName = 'brand_logo_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('branding', $fileName, 'public');
            $logoPath = 'storage/' . $path;
        }

        $setting->update([
            'brand_name' => $validated['brand_name'],
            'brand_slogan' => $validated['brand_slogan'] ?? '',
            'brand_address' => $validated['brand_address'] ?? '',
            'brand_phone1' => $validated['brand_phone1'] ?? '',
            'brand_phone2' => $validated['brand_phone2'] ?? '',
            'brand_logo' => $logoPath,
        ]);

        return redirect()->route('manager.settings.index')
            ->with('success', 'Brand identity settings updated successfully!');
    }
}
