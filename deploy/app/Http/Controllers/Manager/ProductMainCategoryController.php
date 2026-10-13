<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ProductMainCategory;
use Illuminate\Http\Request;
use App\Services\ImageUploadService;

class ProductMainCategoryController extends Controller
{
    /**
     * Store a newly created main category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:product_main_categories,title'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageUploadService::upload($request->file('image'), 'product_main_categories');
        }

        ProductMainCategory::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
            'is_active' => true,
        ]);

        return redirect()->route('manager.product-categories.index', ['tab' => 'main'])
            ->with('success', "Main Category '{$validated['title']}' created successfully!");
    }

    /**
     * Update the specified main category.
     */
    public function update(Request $request, ProductMainCategory $productMainCategory)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:product_main_categories,title,' . $productMainCategory->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        $imagePath = $productMainCategory->image;
        if ($request->hasFile('image')) {
            ImageUploadService::delete($productMainCategory->image);
            $imagePath = ImageUploadService::upload($request->file('image'), 'product_main_categories');
        }

        $productMainCategory->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.product-categories.index', ['tab' => 'main'])
            ->with('success', "Main Category '{$productMainCategory->title}' updated successfully!");
    }

    /**
     * Remove the specified main category.
     */
    public function destroy(ProductMainCategory $productMainCategory)
    {
        if ($productMainCategory->products()->exists()) {
            return redirect()->route('manager.product-categories.index', ['tab' => 'main'])
                ->with('error', "Cannot delete Main Category '{$productMainCategory->title}' because it has active products.");
        }

        ImageUploadService::delete($productMainCategory->image);
        $productMainCategory->delete();

        return redirect()->route('manager.product-categories.index', ['tab' => 'main'])
            ->with('success', 'Main category deleted successfully!');
    }
}
