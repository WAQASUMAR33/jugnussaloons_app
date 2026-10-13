<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\ProductMainCategory;
use Illuminate\Http\Request;
use App\Services\ImageUploadService;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product main categories and sub categories.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $mainCategoryId = $request->input('main_category_id');
        $activeTab = $request->input('tab', 'sub'); // 'sub' or 'main'

        $mainCategories = ProductMainCategory::withCount(['categories', 'products'])
            ->orderBy('title')
            ->get();

        $query = ProductCategory::with(['mainCategory'])->withCount('products');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($mainCategoryId) {
            $query->where('product_main_category_id', $mainCategoryId);
        }

        $categories = $query->orderBy('title')->paginate(15)->withQueryString();

        return view('manager.product_categories.index', compact('categories', 'mainCategories', 'search', 'mainCategoryId', 'activeTab'));
    }

    /**
     * Return JSON subcategories for a given main category (for dynamic dropdowns).
     */
    public function getSubCategories(Request $request)
    {
        $mainCatId = $request->input('main_category_id');
        $query = ProductCategory::query();

        if ($mainCatId) {
            $query->where('product_main_category_id', $mainCatId);
        }

        $subCategories = $query->orderBy('title')->get(['id', 'title', 'product_main_category_id']);

        return response()->json($subCategories);
    }

    /**
     * Store a newly created product sub category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_main_category_id' => ['required', 'exists:product_main_categories,id'],
            'title' => ['required', 'string', 'max:255', 'unique:product_categories,title'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageUploadService::upload($request->file('image'), 'product_categories');
        }

        ProductCategory::create([
            'product_main_category_id' => $validated['product_main_category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.product-categories.index', ['tab' => 'sub', 'main_category_id' => $validated['product_main_category_id']])
            ->with('success', "Sub category '{$validated['title']}' created successfully!");
    }

    /**
     * Update the specified product sub category.
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $request->validate([
            'product_main_category_id' => ['required', 'exists:product_main_categories,id'],
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
            'product_main_category_id' => $validated['product_main_category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.product-categories.index', ['tab' => 'sub'])
            ->with('success', "Sub category '{$productCategory->title}' updated successfully!");
    }

    /**
     * Remove the specified product sub category.
     */
    public function destroy(ProductCategory $productCategory)
    {
        if ($productCategory->products()->exists()) {
            return redirect()->route('manager.product-categories.index', ['tab' => 'sub'])
                ->with('error', "Cannot delete Sub Category '{$productCategory->title}' because it has active products.");
        }

        ImageUploadService::delete($productCategory->image);
        $productCategory->delete();

        return redirect()->route('manager.product-categories.index', ['tab' => 'sub'])
            ->with('success', 'Sub category deleted successfully!');
    }
}
