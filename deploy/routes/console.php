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
    \App\Models\Role::firstOrCreate(
        ['slug' => 'manager'],
        ['name' => 'Manager', 'description' => 'Branch manager']
    );

    // Assign admin role to first user if exists and has no role
    $firstUser = \App\Models\User::first();
    if ($firstUser && !$firstUser->hasRole('admin')) {
        $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);
        $this->info('✓ Admin role attached to user: ' . $firstUser->email);
    }

    $this->info('=== Database Synchronization Complete! ===');
})->purpose('Synchronize baseline database categories, defaults, and roles');
