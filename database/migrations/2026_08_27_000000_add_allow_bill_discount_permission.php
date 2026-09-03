<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $perm = Permission::firstOrCreate(
            ['slug' => 'allow-bill-discount'],
            ['name' => 'Allow Bill Discount']
        );

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && !$adminRole->permissions()->where('slug', 'allow-bill-discount')->exists()) {
            $adminRole->permissions()->attach($perm->id);
        }

        $managerRole = Role::where('slug', 'manager')->first();
        if ($managerRole && !$managerRole->permissions()->where('slug', 'allow-bill-discount')->exists()) {
            $managerRole->permissions()->attach($perm->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $perm = Permission::where('slug', 'allow-bill-discount')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
