<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use App\Services\ImageUploadService;

class ServiceCategoryController extends Controller
{
    /**
     * Display a listing of saloon service categories.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = ServiceCategory::withCount('services');

        if ($search) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('title')->paginate(10)->withQueryString();

        return view('manager.service_categories.index', compact('categories', 'search'));
    }

    /**
     * Store a newly created service category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:service_categories,title'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:4096'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageUploadService::upload($request->file('image'), 'categories');
        }

        ServiceCategory::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.service-categories.index')
            ->with('success', 'Saloon service category created successfully!');
    }

    /**
     * Update the specified service category.
     */
    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:service_categories,title,' . $serviceCategory->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:4096'],
        ]);

        $imagePath = $serviceCategory->image;
        if ($request->hasFile('image')) {
            ImageUploadService::delete($serviceCategory->image);
            $imagePath = ImageUploadService::upload($request->file('image'), 'categories');
        }

        $serviceCategory->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.service-categories.index')
            ->with('success', 'Saloon service category updated successfully!');
    }

    /**
     * Remove the specified service category.
     */
    public function destroy(ServiceCategory $serviceCategory)
    {
        ImageUploadService::delete($serviceCategory->image);

        $serviceCategory->delete();

        return redirect()->route('manager.service-categories.index')
            ->with('success', 'Saloon service category deleted successfully!');
    }
}
