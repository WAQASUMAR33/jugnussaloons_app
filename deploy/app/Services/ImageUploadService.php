<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    /**
     * Upload an image file with dual-sync (storage/app/public + public/storage).
     * This guarantees accessibility on live shared hosting whether symlinks work or not.
     *
     * @param UploadedFile $file
     * @param string $folder e.g. 'products', 'services', 'categories', 'branding', 'galleries', 'receipts'
     * @param string|null $prefix Optional prefix for the filename
     * @return string Relative URL path e.g. 'storage/products/1725548000_65f8a.jpg'
     */
    public static function upload(UploadedFile $file, string $folder, ?string $prefix = null): string
    {
        $folder = trim($folder, '/\\');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        
        // Sanitize extension to common image formats
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'])) {
            $ext = 'jpg';
        }

        $uniqueId = uniqid();
        $filename = ($prefix ? rtrim($prefix, '_') . '_' : '') . time() . '_' . $uniqueId . '.' . $ext;

        $storageDiskPath = storage_path('app/public/' . $folder);
        $publicDirPath   = public_path('storage/' . $folder);

        // Fix broken symlink if present on shared hosting
        $publicStorageRoot = public_path('storage');
        if (is_link($publicStorageRoot) && !file_exists($publicStorageRoot)) {
            @unlink($publicStorageRoot);
        }

        // Ensure both target directories exist with writable permissions
        try {
            if (!File::isDirectory($storageDiskPath)) {
                File::makeDirectory($storageDiskPath, 0775, true, true);
            }
        } catch (\Throwable $e) {
            Log::warning("Could not create storage disk directory [{$storageDiskPath}]: " . $e->getMessage());
        }

        try {
            if (!File::isDirectory($publicDirPath)) {
                File::makeDirectory($publicDirPath, 0775, true, true);
            }
        } catch (\Throwable $e) {
            Log::warning("Could not create public directory [{$publicDirPath}]: " . $e->getMessage());
        }

        $targetPublicFile = $publicDirPath . DIRECTORY_SEPARATOR . $filename;
        $sourceStorageFile = $storageDiskPath . DIRECTORY_SEPARATOR . $filename;

        // 1. Save to Laravel Public Disk (storage/app/public/...)
        try {
            Storage::disk('public')->putFileAs($folder, $file, $filename);
        } catch (\Throwable $e) {
            Log::error("Storage::disk('public')->putFileAs failed: " . $e->getMessage());
        }

        // 2. Write/copy directly to public/storage/... for shared hosting without working symlinks
        try {
            if (File::exists($sourceStorageFile) && !File::exists($targetPublicFile)) {
                @File::copy($sourceStorageFile, $targetPublicFile);
            } elseif (!File::exists($targetPublicFile)) {
                @copy($file->getRealPath(), $targetPublicFile);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed copying to public storage folder [{$targetPublicFile}]: " . $e->getMessage());
        }

        // 3. Fallback direct file_put_contents if both above failed
        if (!File::exists($sourceStorageFile) && !File::exists($targetPublicFile)) {
            try {
                $contents = file_get_contents($file->getRealPath());
                if ($contents !== false) {
                    @file_put_contents($sourceStorageFile, $contents);
                    @file_put_contents($targetPublicFile, $contents);
                }
            } catch (\Throwable $e) {
                Log::error("Direct file_put_contents fallback failed: " . $e->getMessage());
            }
        }

        // 4. Verify file was saved somewhere
        if (!File::exists($sourceStorageFile) && !File::exists($targetPublicFile)) {
            Log::error("CRITICAL: Image file could not be written to [{$sourceStorageFile}] or [{$targetPublicFile}]. Check directory write permissions.");
            throw new \RuntimeException("Unable to save uploaded image. Please check storage folder permissions on the server.");
        }

        return 'storage/' . $folder . '/' . $filename;
    }

    /**
     * Delete an image from all known storage and public paths.
     *
     * @param string|null $imagePath e.g. 'storage/products/123.jpg'
     * @return bool
     */
    public static function delete(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        $cleaned = ltrim(preg_replace('#^https?://[^/]+/#i', '', $imagePath), '/\\');
        $diskPath = preg_replace('#^storage/#i', '', $cleaned);
        $filename = basename($diskPath);
        $dir = dirname($diskPath);

        // 1. Remove from storage disk
        try {
            if (Storage::disk('public')->exists($diskPath)) {
                Storage::disk('public')->delete($diskPath);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed deleting from Storage disk: " . $e->getMessage());
        }

        // 2. Remove from all potential physical paths
        $candidatePaths = [
            public_path($cleaned),
            public_path('storage/' . $diskPath),
            storage_path('app/public/' . $diskPath),
            base_path('storage/' . $diskPath),
            base_path('public_html/storage/' . $diskPath),
            dirname(base_path()) . '/public_html/storage/' . $diskPath,
        ];

        if ($dir && $dir !== '.' && $dir !== '') {
            $candidatePaths[] = storage_path('app/public/' . $dir . '/' . $filename);
            $candidatePaths[] = public_path('storage/' . $dir . '/' . $filename);
        }

        foreach ($candidatePaths as $path) {
            try {
                if (File::exists($path) && !File::isDirectory($path)) {
                    @File::delete($path);
                }
            } catch (\Throwable $e) {
                Log::warning("Failed deleting from path [{$path}]: " . $e->getMessage());
            }
        }

        return true;
    }
}
