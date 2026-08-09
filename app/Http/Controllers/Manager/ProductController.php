<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = Product::query();

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('manager.products.index', compact('products', 'search'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');
            $imagePath = 'storage/' . $path;
        }

        Product::create([
            'title' => $validated['title'],
            'price' => $price,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
            'stock' => (int) $validated['stock'],
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.products.index')
            ->with('success', 'Product created successfully!');
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            if ($product->image && file_exists(public_path($product->image))) {
                @unlink(public_path($product->image));
            }
            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');
            $imagePath = 'storage/' . $path;
        }

        $product->update([
            'title' => $validated['title'],
            'price' => $price,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
            'stock' => (int) $validated['stock'],
            'image' => $imagePath,
        ]);

        return redirect()->route('manager.products.index')
            ->with('success', 'Product updated successfully!');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        if ($product->image && file_exists(public_path($product->image))) {
            @unlink(public_path($product->image));
        }

        $product->delete();

        return redirect()->route('manager.products.index')
            ->with('success', 'Product deleted successfully!');
    }
}
