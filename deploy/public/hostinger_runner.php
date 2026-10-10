<?php
/**
 * Hostinger Web Artisan, Image Sync & Storage Symlink Runner
 * Jugnu Saloon & Spa Web Application
 */

define('LARAVEL_START', microtime(true));

$vendorPath = __DIR__ . '/../vendor/autoload.php';
$bootstrapPath = __DIR__ . '/../bootstrap/app.php';

if (!file_exists($vendorPath) || !file_exists($bootstrapPath)) {
    // If running inside public_html directly
    $vendorPath = __DIR__ . '/vendor/autoload.php';
    $bootstrapPath = __DIR__ . '/bootstrap/app.php';
}

if (!file_exists($vendorPath) || !file_exists($bootstrapPath)) {
    die("<h2 style='color:red;'>Error: Could not locate Laravel vendor or bootstrap directories. Make sure this file is placed in your Laravel public directory.</h2>");
}

require $vendorPath;
$app = require_once $bootstrapPath;
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

$action = $_GET['action'] ?? null;
$outputLog = [];
$statusType = 'info';

$subfolders = ['products', 'services', 'categories', 'branding', 'galleries', 'receipts'];

// Helper function to sync files from storage/app/public to public/storage
function syncPublicStorageDirs($subfolders, &$outputLog) {
    $storageBase = storage_path('app/public');
    $publicBase  = public_path('storage');
    $copiedCount = 0;

    // Ensure base dirs exist
    if (!file_exists($storageBase)) {
        @mkdir($storageBase, 0775, true);
    }
    if (!file_exists($publicBase)) {
        @mkdir($publicBase, 0775, true);
    }

    foreach ($subfolders as $sub) {
        $srcDir = $storageBase . '/' . $sub;
        $dstDir = $publicBase . '/' . $sub;

        if (!file_exists($srcDir)) {
            @mkdir($srcDir, 0775, true);
        }
        if (!file_exists($dstDir)) {
            @mkdir($dstDir, 0775, true);
        }

        // Copy files from storage to public if not already there
        if (is_dir($srcDir)) {
            $files = @scandir($srcDir) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $srcFile = $srcDir . '/' . $f;
                $dstFile = $dstDir . '/' . $f;
                if (is_file($srcFile) && !file_exists($dstFile)) {
                    if (@copy($srcFile, $dstFile)) {
                        $copiedCount++;
                    }
                }
            }
        }

        // Also copy from public to storage if missing
        if (is_dir($dstDir)) {
            $files = @scandir($dstDir) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $dstFile = $dstDir . '/' . $f;
                $srcFile = $srcDir . '/' . $f;
                if (is_file($dstFile) && !file_exists($srcFile)) {
                    @copy($dstFile, $srcFile);
                }
            }
        }
    }

    $outputLog[] = "📁 Verified/created storage directories: <code>" . implode(', ', $subfolders) . "</code>";
    if ($copiedCount > 0) {
        $outputLog[] = "🖼️ Synchronized <strong>{$copiedCount}</strong> image file(s) between <code>storage/app/public</code> and <code>public/storage</code>.";
    } else {
        $outputLog[] = "ℹ️ Storage directories are in sync.";
    }
}

if ($action) {
    switch ($action) {
        case 'storage_link':
            $target = storage_path('app/public');
            $shortcut = public_path('storage');

            // 1. If shortcut is a broken link, remove it first
            if (is_link($shortcut)) {
                $currentTarget = @readlink($shortcut);
                if (!$currentTarget || !file_exists($shortcut)) {
                    $outputLog[] = "⚠️ Removing broken symlink at <code>{$shortcut}</code> (was pointing to: {$currentTarget}).";
                    @unlink($shortcut);
                }
            }

            // 2. Try creating symlink or fall back to directory mirroring
            $symlinkCreated = false;
            if (!file_exists($shortcut)) {
                try {
                    if (function_exists('symlink') && @symlink($target, $shortcut)) {
                        $outputLog[] = "✅ Symbolic link created successfully: <code>public/storage</code> -> <code>storage/app/public</code>";
                        $statusType = 'success';
                        $symlinkCreated = true;
                    } else {
                        Artisan::call('storage:link');
                        $outputLog[] = "✅ Artisan storage:link output: " . Artisan::output();
                        $statusType = 'success';
                        $symlinkCreated = file_exists($shortcut);
                    }
                } catch (\Throwable $e) {
                    $outputLog[] = "⚠️ Symlink creation warning: " . $e->getMessage();
                }
            } else {
                $outputLog[] = "ℹ️ <code>public/storage</code> already exists (Type: " . (is_link($shortcut) ? 'Symbolic Link' : 'Real Directory') . ").";
            }

            // 3. Always ensure dual-directory sync exists
            syncPublicStorageDirs($subfolders, $outputLog);
            $statusType = 'success';
            break;

        case 'sync_images':
            syncPublicStorageDirs($subfolders, $outputLog);
            $statusType = 'success';
            break;

        case 'migrate':
            try {
                Artisan::call('migrate', ['--force' => true]);
                $res = Artisan::output();
                $outputLog[] = "✅ Database migration complete!";
                $outputLog[] = "<pre style='background:#0f172a; color:#38bdf8; padding:15px; border-radius:8px;'>" . htmlspecialchars($res) . "</pre>";
                $statusType = 'success';
            } catch (\Exception $e) {
                $outputLog[] = "❌ Migration Error: " . $e->getMessage();
                $statusType = 'danger';
            }
            break;

        case 'clear_cache':
            try {
                Artisan::call('config:clear');
                $outputLog[] = "✅ Config cache cleared.";
                Artisan::call('cache:clear');
                $outputLog[] = "✅ Application cache cleared.";
                Artisan::call('view:clear');
                $outputLog[] = "✅ Compiled views cleared.";
                Artisan::call('route:clear');
                $outputLog[] = "✅ Route cache cleared.";
                $statusType = 'success';
            } catch (\Exception $e) {
                $outputLog[] = "❌ Cache Clear Error: " . $e->getMessage();
                $statusType = 'danger';
            }
            break;

        case 'all':
            // 1. Storage & Image Sync
            $target = storage_path('app/public');
            $shortcut = public_path('storage');
            if (is_link($shortcut) && (!@readlink($shortcut) || !file_exists($shortcut))) {
                @unlink($shortcut);
            }
            if (!file_exists($shortcut)) {
                if (function_exists('symlink')) {
                    @symlink($target, $shortcut);
                }
                if (!file_exists($shortcut)) {
                    try { Artisan::call('storage:link'); } catch (\Throwable $e) {}
                }
            }
            syncPublicStorageDirs($subfolders, $outputLog);
            $outputLog[] = "✅ Storage folders initialized and synchronized!";

            // 2. Migrate
            try {
                Artisan::call('migrate', ['--force' => true]);
                $outputLog[] = "✅ Database migrations executed!";
                $outputLog[] = "<pre style='background:#0f172a; color:#38bdf8; padding:12px; border-radius:6px;'>" . htmlspecialchars(Artisan::output()) . "</pre>";
            } catch (\Exception $e) {
                $outputLog[] = "❌ Migration Error: " . $e->getMessage();
            }

            // 3. Clear Caches
            try {
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');
                $outputLog[] = "✅ System caches cleared!";
            } catch (\Exception $e) {
                $outputLog[] = "❌ Cache Error: " . $e->getMessage();
            }

            $statusType = 'success';
            break;
    }
}

// Check Diagnostics
$uploadMax = ini_get('upload_max_filesize');
$postMax   = ini_get('post_max_size');
$memory    = ini_get('memory_limit');
$storageWritable = is_writable(storage_path('app/public'));
$publicStorageExists = file_exists(public_path('storage'));
$publicStorageIsLink = is_link(public_path('storage'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostinger Production Web Runner - Jugnu Saloon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; padding-top: 40px; padding-bottom: 60px; }
        .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .btn-custom { border-radius: 8px; font-weight: 700; padding: 12px 20px; transition: all 0.2s; }
        .path-box { background: #0f172a; border: 1px solid #334155; padding: 10px 15px; border-radius: 6px; font-family: monospace; color: #a5f3fc; }
        .diag-pill { background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 8px 12px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container max-width-800">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card p-4 p-md-5">
                    <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom border-secondary">
                        <div>
                            <h2 class="fw-black mb-1 text-white">🚀 Hostinger Production Web Runner</h2>
                            <p class="text-secondary small mb-0">Run Laravel artisan commands, fix image storage & symlinks directly from browser</p>
                        </div>
                        <span class="badge bg-primary px-3 py-2 fs-6">Laravel v<?= app()->version() ?></span>
                    </div>

                    <!-- Server Diagnostics Pill Bar -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-bold small text-uppercase">Environment & Upload Diagnostics:</label>
                        <div class="row g-2">
                            <div class="col-sm-3">
                                <div class="diag-pill">
                                    <div class="text-secondary small">Upload Max:</div>
                                    <strong class="text-warning"><?= htmlspecialchars($uploadMax) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="diag-pill">
                                    <div class="text-secondary small">Post Max:</div>
                                    <strong class="text-warning"><?= htmlspecialchars($postMax) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="diag-pill">
                                    <div class="text-secondary small">Memory Limit:</div>
                                    <strong class="text-info"><?= htmlspecialchars($memory) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="diag-pill">
                                    <div class="text-secondary small">Storage Mode:</div>
                                    <strong class="<?= $publicStorageIsLink ? 'text-success' : 'text-primary' ?>">
                                        <?= $publicStorageIsLink ? 'Symlink Active' : ($publicStorageExists ? 'Direct Directory' : 'Unlinked') ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Paths Info -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-bold small text-uppercase">Detected Server Paths:</label>
                        <div class="mb-2">
                            <small class="text-muted d-block">Storage Public Path:</small>
                            <div class="path-box"><?= storage_path('app/public') ?></div>
                        </div>
                        <div>
                            <small class="text-muted d-block">Public Storage Path:</small>
                            <div class="path-box"><?= public_path('storage') ?></div>
                        </div>
                    </div>

                    <!-- Execution Results Output -->
                    <?php if (!empty($outputLog)): ?>
                        <div class="alert alert-<?= $statusType === 'success' ? 'success' : ($statusType === 'danger' ? 'danger' : 'info') ?> mb-4">
                            <h5 class="fw-bold mb-3">Execution Results:</h5>
                            <?php foreach ($outputLog as $log): ?>
                                <div class="mb-2"><?= $log ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="?action=all" class="btn btn-success w-100 btn-custom fs-5 shadow">
                                ⚡ Run Complete Setup (Link + Migrate + Cache)
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="?action=storage_link" class="btn btn-primary w-100 btn-custom fs-5 shadow">
                                🔗 Fix Storage Symlink & Folders
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="?action=sync_images" class="btn btn-info text-dark w-100 btn-custom fs-5 shadow">
                                🖼️ Sync Images & Fix Folders
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="?action=migrate" class="btn btn-warning text-dark w-100 btn-custom fs-5 shadow">
                                📦 Run Database Migrations (`migrate`)
                            </a>
                        </div>
                        <div class="col-md-12">
                            <a href="?action=clear_cache" class="btn btn-secondary w-100 btn-custom shadow">
                                🧹 Clear All System Caches (Config, View, Route, Cache)
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top border-secondary text-center text-muted small">
                        Jugnu Saloon Web Application &bull; Hostinger Web Hosting Assistant
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
