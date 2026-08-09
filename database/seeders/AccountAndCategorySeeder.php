<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountCategory;
use Illuminate\Database\Seeder;

class AccountAndCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Account Categories
        $vipCategory = AccountCategory::firstOrCreate(['title' => 'VIP Saloon Member']);
        $regularCategory = AccountCategory::firstOrCreate(['title' => 'Regular Customer']);
        $walkinCategory = AccountCategory::firstOrCreate(['title' => 'Walk-in Client']);
        $vendorCategory = AccountCategory::firstOrCreate(['title' => 'Supplier / Vendor']);

        // 2. Create Accounts
        Account::firstOrCreate(
            ['phone_no1' => '+1 555-0192'],
            [
                'account_category_id' => $vipCategory->id,
                'name' => 'Sarah Johnson',
                'father_name' => 'Robert Johnson',
                'address' => '742 Evergreen Terrace, Springfield',
                'date_of_birth' => '1992-05-14',
                'date_of_anniversary' => '2018-09-20',
                'phone_no2' => '+1 555-0193',
                'card_no' => 'VIP-8823',
                'balance' => 150.00,
            ]
        );

        Account::firstOrCreate(
            ['phone_no1' => '+1 555-0244'],
            [
                'account_category_id' => $regularCategory->id,
                'name' => 'John Doe',
                'father_name' => 'Richard Doe',
                'address' => '123 Main Street, Suite 4B',
                'date_of_birth' => '1988-11-03',
                'date_of_anniversary' => null,
                'phone_no2' => null,
                'card_no' => 'REG-1049',
                'balance' => 0.00,
            ]
        );

        Account::firstOrCreate(
            ['phone_no1' => '+1 555-0381'],
            [
                'account_category_id' => $vendorCategory->id,
                'name' => 'L\'Oréal Professional Supplies',
                'father_name' => null,
                'address' => '900 Beauty Blvd, Industrial Zone',
                'date_of_birth' => null,
                'date_of_anniversary' => null,
                'phone_no2' => '+1 555-0382',
                'card_no' => 'SUP-5011',
                'balance' => -450.00,
            ]
        );
    }
}
