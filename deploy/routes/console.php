<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('db:sync-defaults', function () {
    $this->info('Synchronizing default database records...');

    // 1. Ensure Account Categories
    $categories = [
        'VIP Saloon Member',
        'Regular Customer',
        'Walk-in Client',
        'Supplier / Vendor',
        'Staff / Employee',
    ];
    foreach ($categories as $catTitle) {
        \App\Models\AccountCategory::firstOrCreate(['title' => $catTitle]);
    }
    $this->info('✓ Account Categories verified.');

    // 2. Ensure Walk-in Customer Account
    $walkinCat = \App\Models\AccountCategory::where('title', 'like', '%Walk-in%')
        ->orWhere('title', 'like', '%Customer%')
        ->first();
    
    $walkin = \App\Models\Account::firstOrCreate(
        ['name' => 'Walk-in Customer'],
        [
            'account_category_id' => $walkinCat ? $walkinCat->id : 1,
            'phone_no1' => '0300-0000000',
            'balance' => 0.00,
        ]
    );
    $this->info('✓ Walk-in Customer Account verified (ID: ' . $walkin->id . ').');

    // 3. Ensure Default Setting
    \App\Models\Setting::getSettings();
    $this->info('✓ Default Brand Settings verified.');

    // 4. Ensure Roles & Permissions
    $adminRole = \App\Models\Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Administrator', 'description' => 'Full system administrator']
    );
    $managerRole = \App\Models\Role::firstOrCreate(
        ['slug' => 'manager'],
        ['name' => 'Manager', 'description' => 'Branch manager']
    );

    // Ensure allow-bill-discount is granted per user directly, not inherited automatically by manager role
    $discountPerm = \App\Models\Permission::where('slug', 'allow-bill-discount')->first();
    if ($managerRole && $discountPerm) {
        $managerRole->permissions()->detach($discountPerm->id);
    }

    // Assign admin role to first user if exists and has no role
    $firstUser = \App\Models\User::first();
    if ($firstUser && !$firstUser->hasRole('admin')) {
        $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);
        $this->info('✓ Admin role attached to user: ' . $firstUser->email);
    }

    $this->info('=== Database Synchronization Complete! ===');})->purpose('Synchronize baseline database categories, defaults, and roles');

Artisan::command('gallery:clear', function () {
    $this->info('Deleting all images from gallery...');
    $galleries = \App\Models\Gallery::all();
    $count = $galleries->count();

    foreach ($galleries as $gallery) {
        \App\Services\ImageUploadService::delete($gallery->image_path);
        $gallery->delete();
    }

    // Clean up any remaining files in storage and public galleries directories
    $directories = [
        storage_path('app/public/galleries'),
        public_path('storage/galleries'),
    ];

    $filesRemoved = 0;
    foreach ($directories as $dir) {
        if (\Illuminate\Support\Facades\File::isDirectory($dir)) {
            $files = \Illuminate\Support\Facades\File::files($dir);
            foreach ($files as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    @\Illuminate\Support\Facades\File::delete($file->getPathname());
                    $filesRemoved++;
                }
            }
        }
    }

    $this->info("✓ Successfully deleted all {$count} gallery record(s) and cleared {$filesRemoved} stored image file(s).");
})->purpose('Delete all gallery photos and their physical files');

