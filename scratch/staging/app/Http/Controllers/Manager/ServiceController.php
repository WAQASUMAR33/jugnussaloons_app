<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\SaloonService;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    /**
     * Display a listing of saloon services.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = $request->input('service_category_id');

        $query = SaloonService::with('category');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('service_category_id', $categoryId);
        }

        $services = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        $categories = ServiceCategory::orderBy('title')->get();

        return view('manager.services.index', compact('services', 'categories', 'search', 'categoryId'));
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'junior_commission' => ['nullable', 'numeric', 'min:0'],
            'senior_commission' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $juniorComm = (float) ($validated['junior_commission'] ?? 0);
        $seniorComm = (float) ($validated['senior_commission'] ?? 0);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('services', $fileName, 'public');
            $imagePath = 'storage/' . $path;
        }

        SaloonService::create([
            'service_category_id' => $validated['service_category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'price' => $price,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
            'commission' => $juniorComm,
            'junior_commission' => $juniorComm,
            'senior_commission' => $seniorComm,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.services.index')
            ->with('success', 'Saloon service created successfully!');
    }

    /**
     * Update the specified service in storage.
     */
    public function update(Request $request, SaloonService $service)
    {
        $validated = $request->validate([
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'junior_commission' => ['nullable', 'numeric', 'min:0'],
            'senior_commission' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $juniorComm = (float) ($validated['junior_commission'] ?? 0);
        $seniorComm = (float) ($validated['senior_commission'] ?? 0);

        $imagePath = $service->image;
        if ($request->hasFile('image')) {
            // Remove old image if exists
            if ($service->image && file_exists(public_path($service->image))) {
                @unlink(public_path($service->image));
            }
            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('services', $fileName, 'public');
            $imagePath = 'storage/' . $path;
        }

        $service->update([
            'service_category_id' => $validated['service_category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'price' => $price,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
            'commission' => $juniorComm,
            'junior_commission' => $juniorComm,
            'senior_commission' => $seniorComm,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.services.index')
            ->with('success', 'Saloon service updated successfully!');
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy(SaloonService $service)
    {
        if ($service->image && file_exists(public_path($service->image))) {
            @unlink(public_path($service->image));
        }

        $service->delete();

        return redirect()->route('manager.services.index')
            ->with('success', 'Saloon service deleted successfully!');
    }
}
