<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use App\Services\ImageUploadService;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product categories.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = ProductCategory::withCount('products');

        if ($search) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('title')->paginate(12)->withQueryString();

        return view('manager.product_categories.index', compact('categories', 'search'));
    }

    /**
     * Store a newly created product category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:product_categories,title'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageUploadService::upload($request->file('image'), 'product_categories');
        }

        ProductCategory::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.product-categories.index')
            ->with('success', "Product category '{$validated['title']}' created successfully!");
    }

    /**
     * Update the specified product category.
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:product_categories,title,' . $productCategory->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        $imagePath = $productCategory->image;
        if ($request->hasFile('image')) {
            ImageUploadService::delete($productCategory->image);
            $imagePath = ImageUploadService::upload($request->file('image'), 'product_categories');
        }

        $productCategory->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.product-categories.index')
            ->with('success', "Product category '{$productCategory->title}' updated successfully!");
    }

    /**
     * Remove the specified product category.
     */
    public function destroy(ProductCategory $productCategory)
    {
        ImageUploadService::delete($productCategory->image);

        $productCategory->delete();

        return redirect()->route('manager.product-categories.index')
            ->with('success', 'Product category deleted successfully!');
    }
}
