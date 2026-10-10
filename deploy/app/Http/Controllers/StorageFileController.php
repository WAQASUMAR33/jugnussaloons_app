<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageFileController extends Controller
{
    /**
     * Fallback controller to serve files under /storage/{path} dynamically.
     * Guarantees images display on live servers (like Hostinger shared hosting)
     * even when Apache symlinks fail, are disabled, or directory roots differ.
     */
    public function show(Request $request, string $path)
    {
        // 1. Guard against directory traversal attacks
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            abort(400, 'Invalid file path.');
        }

        $rawPath = ltrim($path, '/\\');
        // Clean leading storage/ or public/ if duplicated in URL
        $cleanPath = preg_replace('#^(public/|storage/)+#i', '', $rawPath);
        $filename = basename($cleanPath);
        $dir = dirname($cleanPath);
        $subfolder = ($dir !== '.' && $dir !== '') ? $dir : '';

        // 2. Comprehensive candidate paths across all possible Hostinger root structures
        $candidates = array_filter([
            // Standard Laravel storage disk (storage/app/public/...)
            storage_path('app/public/' . $cleanPath),
            // Public storage folder (public/storage/...)
            public_path('storage/' . $cleanPath),
            // Root project storage / public_html configurations
            base_path('storage/' . $cleanPath),
            base_path('storage/app/public/' . $cleanPath),
            base_path('public/' . $cleanPath),
            public_path($cleanPath),
            public_path('uploads/' . $cleanPath),
            // If hosted directly inside public_html
            base_path('public_html/storage/' . $cleanPath),
            base_path('public_html/storage/app/public/' . $cleanPath),
            base_path('public_html/public/storage/' . $cleanPath),
            dirname(base_path()) . '/public_html/storage/' . $cleanPath,
            dirname(base_path()) . '/public_html/storage/app/public/' . $cleanPath,
            dirname(base_path()) . '/public_html/public/storage/' . $cleanPath,
            // Subfolder fallback
            $subfolder ? storage_path('app/public/' . $subfolder . '/' . $filename) : null,
            $subfolder ? public_path('storage/' . $subfolder . '/' . $filename) : null,
            // Direct folder fallbacks by known category
            storage_path('app/public/galleries/' . $filename),
            public_path('storage/galleries/' . $filename),
            storage_path('app/public/products/' . $filename),
            public_path('storage/products/' . $filename),
            storage_path('app/public/services/' . $filename),
            public_path('storage/services/' . $filename),
            storage_path('app/public/receipts/' . $filename),
            public_path('storage/receipts/' . $filename),
            storage_path('app/public/categories/' . $filename),
            public_path('storage/categories/' . $filename),
            storage_path('app/public/branding/' . $filename),
            public_path('storage/branding/' . $filename),
        ]);

        $foundFile = null;
        foreach ($candidates as $candidate) {
            if ($candidate && File::exists($candidate) && !File::isDirectory($candidate)) {
                $foundFile = $candidate;
                break;
            }
        }

        // 3. If file is found, auto-sync to public/storage so web server can serve directly
        if ($foundFile) {
            try {
                $directPublicPath = public_path('storage/' . $cleanPath);
                if (!File::exists($directPublicPath)) {
                    File::ensureDirectoryExists(dirname($directPublicPath), 0775, true);
                    @File::copy($foundFile, $directPublicPath);
                }
            } catch (\Throwable $e) {
                // Silently ignore copy failure; still stream file
            }

            $mime = File::mimeType($foundFile) ?: 'application/octet-stream';
            $headers = [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Access-Control-Allow-Origin' => '*',
            ];

            return response()->file($foundFile, $headers);
        }

        // 4. If image file is not found on disk, gracefully serve placeholder for images
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'])) {
            $placeholder = public_path('images/placeholder-gallery.svg');
            if (File::exists($placeholder)) {
                return response()->file($placeholder, [
                    'Content-Type' => 'image/svg+xml',
                    'Cache-Control' => 'no-cache, private',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }
        }

        abort(404, 'Image not found.');
    }
}
