<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageUploadService;

class GalleryController extends Controller
{
    /**
     * Predefined gallery categories for easy selection.
     */
    protected array $defaultCategories = [
        'Hair Styling & Cut',
        'Facials & Skincare',
        'Makeup & Bridal',
        'Nail Art & Care',
        'Massage & Spa',
        'Saloon Ambience',
    ];

    /**
     * Display a listing of gallery images with stats and filter options.
     */
    public function index(Request $request)
    {
        $query = Gallery::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        $galleries = $query->orderBy('id', 'desc')->get();

        // Statistics
        $totalPhotos = Gallery::count();
        $totalSizeBytes = Gallery::sum('file_size');
        $totalSizeFormatted = $this->formatBytes($totalSizeBytes);

        // Get distinct existing categories from DB merged with defaults
        $dbCategories = Gallery::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->toArray();

        $allCategories = array_values(array_unique(array_merge($this->defaultCategories, $dbCategories)));

        return view('manager.galleries.index', compact(
            'galleries',
            'totalPhotos',
            'totalSizeFormatted',
            'allCategories'
        ));
    }

    /**
     * Store new uploaded gallery images.
     */
    public function store(Request $request)
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,gif,svg', 'max:10240'], // Max 10MB per file
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $uploadedCount = 0;
        $category = $request->input('category');
        $baseTitle = $request->input('title');

        foreach ($request->file('images') as $file) {
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            $imagePath = ImageUploadService::upload($file, 'galleries', 'gallery');

            $title = $baseTitle ?: pathinfo($originalName, PATHINFO_FILENAME);

            Gallery::create([
                'title' => $title,
                'category' => $category,
                'image_path' => $imagePath,
                'file_name' => $originalName,
                'file_size' => $fileSize,
                'is_active' => true,
            ]);

            $uploadedCount++;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully uploaded {$uploadedCount} photo(s).",
            ]);
        }

        return redirect()->route('manager.galleries.index')
            ->with('success', "Successfully uploaded {$uploadedCount} gallery photo(s)!");
    }

    /**
     * Update title/category for a gallery image.
     */
    public function update(Request $request, Gallery $gallery)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $gallery->update([
            'title' => $validated['title'] ?? $gallery->title,
            'category' => $validated['category'] ?? $gallery->category,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $gallery->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Gallery image updated successfully.',
                'gallery' => $gallery,
            ]);
        }

        return redirect()->route('manager.galleries.index')
            ->with('success', 'Gallery photo details updated successfully!');
    }

    /**
     * Remove the specified gallery photo from disk and storage.
     */
    public function destroy(Request $request, Gallery $gallery)
    {
        $this->deletePhysicalFile($gallery->image_path);
        $gallery->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully.',
            ]);
        }

        return redirect()->route('manager.galleries.index')
            ->with('success', 'Gallery photo deleted successfully!');
    }

    /**
     * Bulk delete selected gallery photos.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:galleries,id'],
        ]);

        $galleries = Gallery::whereIn('id', $request->input('ids'))->get();
        $count = 0;

        foreach ($galleries as $gallery) {
            $this->deletePhysicalFile($gallery->image_path);
            $gallery->delete();
            $count++;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} photo(s) successfully.",
            ]);
        }

        return redirect()->route('manager.galleries.index')
            ->with('success', "Bulk deleted {$count} gallery photo(s) successfully!");
    }

    /**
     * Delete all gallery photos and their physical files.
     */
    public function deleteAll(Request $request)
    {
        $galleries = Gallery::all();
        $count = $galleries->count();

        foreach ($galleries as $gallery) {
            $this->deletePhysicalFile($gallery->image_path);
            $gallery->delete();
        }

        // Clean up any remaining files in storage and public galleries directories
        $directories = [
            storage_path('app/public/galleries'),
            public_path('storage/galleries'),
        ];

        foreach ($directories as $dir) {
            if (\Illuminate\Support\Facades\File::isDirectory($dir)) {
                $files = \Illuminate\Support\Facades\File::files($dir);
                foreach ($files as $file) {
                    if ($file->getFilename() !== '.gitignore') {
                        @\Illuminate\Support\Facades\File::delete($file->getPathname());
                    }
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "All {$count} photo(s) deleted successfully.",
            ]);
        }

        return redirect()->route('manager.galleries.index')
            ->with('success', "All {$count} gallery photo(s) have been deleted successfully!");
    }


    /**
     * Delete physical file from storage or public path.
     */
    protected function deletePhysicalFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        ImageUploadService::delete($relativePath);
    }

    /**
     * Format raw byte count into human readable size string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        } elseif ($bytes > 0) {
            return $bytes . ' Bytes';
        }
        return '0 KB';
    }
}
