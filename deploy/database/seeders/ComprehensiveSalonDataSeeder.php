<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\BankAccount;
use App\Models\Commission;
use App\Models\DayClosing;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Gallery;
use App\Models\Payroll;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStoreStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaloonService;
use App\Models\StaffAttendance;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ComprehensiveSalonDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Demo Role Users
        $this->seedUsers();

        // 2. Seed Stores
        $this->seedStores();

        // 3. Seed Product Categories & Products
        $this->seedProductsAndCategories();

        // 4. Seed Customer, Staff & Supplier Accounts
        $this->seedAccounts();

        // 5. Seed Supplier Purchases
        $this->seedPurchases();

        // 6. Seed POS Sales & Appointments
        $this->seedSalesAndAppointments();

        // 7. Seed Expense Categories & Expenses
        $this->seedExpenses();

        // 8. Seed Bank Accounts
        $this->seedBankAccounts();

        // 9. Seed General Ledgers
        $this->seedLedgers();

        // 10. Seed Staff Attendance & Payroll
        $this->seedStaffAttendanceAndPayroll();

        // 11. Seed Day Closing Records
        $this->seedDayClosings();

        // 12. Seed Photo Galleries
        $this->seedGalleries();
    }

    protected function seedUsers(): void
    {
        $roleConfigs = [
            [
                'email' => 'admin@example.com',
                'name' => 'System Administrator',
                'role_slug' => 'admin',
                'password' => 'password',
            ],
            [
                'email' => 'manager@jugnu.com',
                'name' => 'Noor Manager',
                'role_slug' => 'manager',
                'password' => 'password',
            ],
            [
                'email' => 'accountant@jugnu.com',
                'name' => 'Farhan Qureshi (Accountant)',
                'role_slug' => 'accountant',
                'password' => 'password',
            ],
            [
                'email' => 'sales@jugnu.com',
                'name' => 'Kashif Mehmood (POS Sales)',
                'role_slug' => 'sales-person',
                'password' => 'password',
            ],
            [
                'email' => 'reception@jugnu.com',
                'name' => 'Sadia Akram (Front Desk)',
                'role_slug' => 'receptionist',
                'password' => 'password',
            ],
            [
                'email' => 'stylist@jugnu.com',
                'name' => 'Hammad Stylist',
                'role_slug' => 'staff',
                'password' => 'password',
            ],
        ];

        foreach ($roleConfigs as $cfg) {
            $user = User::firstOrCreate(
                ['email' => $cfg['email']],
                [
                    'name' => $cfg['name'],
                    'password' => Hash::make($cfg['password']),
                ]
            );

            $role = Role::where('slug', $cfg['role_slug'])->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);

                // Sync role standard permissions as direct user permissions
                if ($role->slug === 'admin') {
                    $allPerms = Permission::pluck('id')->toArray();
                    $user->permissions()->sync($allPerms);
                } else {
                    $rolePerms = $role->permissions->pluck('id')->toArray();
                    if (!empty($rolePerms)) {
                        $user->permissions()->sync($rolePerms);
                    }
                }
            }
        }
    }

    protected function seedStores(): void
    {
        Store::firstOrCreate(
            ['code' => 'MAIN-01'],
            [
                'name' => 'Main Phalia City Branch',
                'address' => 'Main Kutchery Road, Phalia, Mandi Bahauddin',
                'phone' => '+92 300 1234567',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        Store::firstOrCreate(
            ['code' => 'VIP-02'],
            [
                'name' => 'Executive VIP Lounge (Gujrat Rd)',
                'address' => 'Gujrat Road Plaza, 1st Floor, Phalia',
                'phone' => '+92 301 7654321',
                'is_default' => false,
                'is_active' => true,
            ]
        );
    }

    protected function seedProductsAndCategories(): void
    {
        $categories = [
            'Hair Care & Shampoos' => 'Professional salon shampoos, cleansers, and conditioners.',
            'Facial & Skincare Treatments' => 'Organic facial polishers, cleansers, serums, and masks.',
            'Beard & Shaving Grooming' => 'Barber pomades, beard oils, pre-shave balms, and foams.',
            'Hair Colors & Bleaches' => 'Permanent hair colors, developer emulsions, and toners.',
            'Hair Styling & Serums' => 'Matte clays, hair waxes, heat protectant sprays, and shine serums.',
            'Professional Salon Tools' => 'Japanese cutting scissors, ceramic blow dryers, and hair straighteners.',
        ];

        $categoryModels = [];
        foreach ($categories as $title => $desc) {
            $categoryModels[$title] = ProductCategory::firstOrCreate(
                ['title' => $title],
                ['description' => $desc]
            );
        }

        $productsData = [
            [
                'category' => 'Hair Care & Shampoos',
                'title' => 'L\'Oreal Professionnel Mythic Oil Shampoo 250ml',
                'purchase_price' => 1850.00,
                'price' => 2600.00,
                'discount' => 10.00,
                'discounted_price' => 2340.00,
                'stock' => 45,
                'low_stock' => 10,
            ],
            [
                'category' => 'Hair Care & Shampoos',
                'title' => 'Kerastase Nutritive Masquintense Hair Mask 200ml',
                'purchase_price' => 3200.00,
                'price' => 4500.00,
                'discount' => 5.00,
                'discounted_price' => 4275.00,
                'stock' => 28,
                'low_stock' => 5,
            ],
            [
                'category' => 'Hair Styling & Serums',
                'title' => 'Moroccanoil Treatment Argan Oil Serum 100ml',
                'purchase_price' => 3800.00,
                'price' => 5200.00,
                'discount' => 0.00,
                'discounted_price' => 5200.00,
                'stock' => 30,
                'low_stock' => 6,
            ],
            [
                'category' => 'Facial & Skincare Treatments',
                'title' => 'Dermacos Oxygen Facial Polish & Radiance Kit',
                'purchase_price' => 2100.00,
                'price' => 3200.00,
                'discount' => 15.00,
                'discounted_price' => 2720.00,
                'stock' => 38,
                'low_stock' => 8,
            ],
            [
                'category' => 'Facial & Skincare Treatments',
                'title' => 'Janssen Fair Skin Brightening Cleansing Foam 200ml',
                'purchase_price' => 2700.00,
                'price' => 3900.00,
                'discount' => 10.00,
                'discounted_price' => 3510.00,
                'stock' => 22,
                'low_stock' => 5,
            ],
            [
                'category' => 'Hair Colors & Bleaches',
                'title' => 'Keune Tinta Permanent Hair Color 60ml - Ash Blonde',
                'purchase_price' => 950.00,
                'price' => 1450.00,
                'discount' => 0.00,
                'discounted_price' => 1450.00,
                'stock' => 60,
                'low_stock' => 15,
            ],
            [
                'category' => 'Beard & Shaving Grooming',
                'title' => 'Proraso Sandalwood Italian Shaving Foam 300ml',
                'purchase_price' => 1100.00,
                'price' => 1700.00,
                'discount' => 10.00,
                'discounted_price' => 1530.00,
                'stock' => 35,
                'low_stock' => 8,
            ],
            [
                'category' => 'Hair Styling & Serums',
                'title' => 'Dapper Dan Heavy Hold Matte Clay Pomade 100ml',
                'purchase_price' => 1700.00,
                'price' => 2500.00,
                'discount' => 10.00,
                'discounted_price' => 2250.00,
                'stock' => 24,
                'low_stock' => 6,
            ],
            [
                'category' => 'Hair Styling & Serums',
                'title' => 'Schwarzkopf Silhouette Super Hold Hairspray 500ml',
                'purchase_price' => 1400.00,
                'price' => 2100.00,
                'discount' => 5.00,
                'discounted_price' => 1995.00,
                'stock' => 32,
                'low_stock' => 8,
            ],
            [
                'category' => 'Professional Salon Tools',
                'title' => 'BaByliss PRO Titanium Pro Ionic Hair Straightener',
                'purchase_price' => 8500.00,
                'price' => 12500.00,
                'discount' => 10.00,
                'discounted_price' => 11250.00,
                'stock' => 10,
                'low_stock' => 2,
            ],
        ];

        $mainStore = Store::where('is_default', true)->first();
        $vipStore = Store::where('is_default', false)->first();

        foreach ($productsData as $pData) {
            $cat = $categoryModels[$pData['category']];
            $product = Product::firstOrCreate(
                ['title' => $pData['title']],
                [
                    'product_category_id' => $cat->id,
                    'purchase_price' => $pData['purchase_price'],
                    'price' => $pData['price'],
                    'discount' => $pData['discount'],
                    'discounted_price' => $pData['discounted_price'],
                    'stock' => $pData['stock'],
                    'low_stock' => $pData['low_stock'],
                ]
            );

            if ($mainStore) {
                ProductStoreStock::updateOrCreate(
                    ['store_id' => $mainStore->id, 'product_id' => $product->id],
                    ['stock' => (int)($pData['stock'] * 0.7)]
                );
            }
            if ($vipStore) {
                ProductStoreStock::updateOrCreate(
                    ['store_id' => $vipStore->id, 'product_id' => $product->id],
                    ['stock' => (int)($pData['stock'] * 0.3)]
                );
            }
        }

        // Seed sample stock transfer
        if ($mainStore && $vipStore) {
            $firstProduct = Product::first();
            if ($firstProduct) {
                StockTransfer::firstOrCreate(
                    ['transfer_no' => 'TRF-2026-001'],
                    [
                        'source_store_id' => $mainStore->id,
                        'destination_store_id' => $vipStore->id,
                        'product_id' => $firstProduct->id,
                        'quantity' => 5,
                        'notes' => 'Branch stock replenishment for VIP weekend rush.',
                        'transfer_date' => now()->subDay()->format('Y-m-d'),
                    ]
                );
            }
        }
    }

    protected function seedAccounts(): void
    {
        $vipCat = AccountCategory::firstOrCreate(['title' => 'VIP Saloon Member'], ['description' => 'Premium loyalty salon card holders']);
        $regCat = AccountCategory::firstOrCreate(['title' => 'Regular Customer'], ['description' => 'Frequent saloon clients']);
        $walkCat = AccountCategory::firstOrCreate(['title' => 'Walk-in Client'], ['description' => 'New and walk-in customers']);
        $supplierCat = AccountCategory::firstOrCreate(['title' => 'Supplier / Vendor'], ['description' => 'Cosmetic and product suppliers']);
        $staffCat = AccountCategory::firstOrCreate(['title' => 'Staff / Employee'], ['description' => 'Stylists, barbers, and beauticians']);

        // Staff Accounts
        $staffMembers = [
            [
                'name' => 'Hammad Khan',
                'phone' => '+92 300 1112233',
                'salary' => 45000.00,
                'emp_type' => 'Senior',
            ],
            [
                'name' => 'Sana Javed',
                'phone' => '+92 302 2223344',
                'salary' => 50000.00,
                'emp_type' => 'Senior',
            ],
            [
                'name' => 'Asad Rauf',
                'phone' => '+92 303 3334455',
                'salary' => 35000.00,
                'emp_type' => 'Junior',
            ],
            [
                'name' => 'Hina Tariq',
                'phone' => '+92 304 4445566',
                'salary' => 38000.00,
                'emp_type' => 'Junior',
            ],
        ];

        foreach ($staffMembers as $sm) {
            Account::firstOrCreate(
                ['phone_no1' => $sm['phone']],
                [
                    'account_category_id' => $staffCat->id,
                    'name' => $sm['name'],
                    'salary' => $sm['salary'],
                    'emp_type' => $sm['emp_type'],
                ]
            );
        }

        // Customer Accounts
        $customers = [
            [
                'name' => 'Ayesha Noor',
                'phone' => '+92 300 5556677',
                'cat_id' => $vipCat->id,
                'card_type' => 'Gold VIP',
            ],
            [
                'name' => 'Fatima Zahra',
                'phone' => '+92 301 6667788',
                'cat_id' => $vipCat->id,
                'card_type' => 'Platinum VIP',
            ],
            [
                'name' => 'Zoya Malik',
                'phone' => '+92 302 7778899',
                'cat_id' => $regCat->id,
                'card_type' => 'Silver Regular',
            ],
            [
                'name' => 'Usman Tariq',
                'phone' => '+92 305 8889900',
                'cat_id' => $regCat->id,
                'card_type' => 'Silver Regular',
            ],
            [
                'name' => 'Hamza Ali',
                'phone' => '+92 306 9990011',
                'cat_id' => $walkCat->id,
                'card_type' => 'Standard',
            ],
        ];

        foreach ($customers as $c) {
            Account::firstOrCreate(
                ['phone_no1' => $c['phone']],
                [
                    'account_category_id' => $c['cat_id'],
                    'name' => $c['name'],
                    'card_type' => $c['card_type'],
                ]
            );
        }

        // Supplier Accounts
        $suppliers = [
            [
                'name' => 'L\'Oreal Pakistan Official Distributorship',
                'phone' => '+92 42 111567325',
            ],
            [
                'name' => 'Janssen Cosmetics Imports Lahore',
                'phone' => '+92 42 35789012',
            ],
        ];

        foreach ($suppliers as $s) {
            Account::firstOrCreate(
                ['phone_no1' => $s['phone']],
                [
                    'account_category_id' => $supplierCat->id,
                    'name' => $s['name'],
                ]
            );
        }
    }

    protected function seedPurchases(): void
    {
        $supplier = Account::whereHas('category', fn($q) => $q->where('title', 'like', '%Supplier%'))->first();
        $products = Product::take(3)->get();
        $store = Store::first();

        if ($supplier && $products->count() > 0) {
            $purchase = Purchase::firstOrCreate(
                ['invoice_no' => 'PUR-2026-001'],
                [
                    'account_id' => $supplier->id,
                    'store_id' => $store?->id,
                    'purchase_date' => now()->subDays(5)->format('Y-m-d'),
                    'total_amount' => 45000.00,
                    'paid_amount' => 45000.00,
                    'balance_due' => 0.00,
                    'notes' => 'Monthly salon consumable supply inventory delivery.',
                ]
            );

            foreach ($products as $p) {
                PurchaseItem::firstOrCreate(
                    [
                        'purchase_id' => $purchase->id,
                        'product_id' => $p->id,
                    ],
                    [
                        'quantity' => 10,
                        'unit_price' => $p->purchase_price ?: 1500.00,
                        'subtotal' => 10 * ($p->purchase_price ?: 1500.00),
                    ]
                );
            }
        }
    }

    protected function seedSalesAndAppointments(): void
    {
        $customer = Account::whereHas('category', fn($q) => $q->where('title', 'like', '%Customer%')->orWhere('title', 'like', '%VIP%'))->first();
        $staff = Account::whereHas('category', fn($q) => $q->where('title', 'like', '%Staff%'))->first();
        $store = Store::first();
        $services = SaloonService::take(4)->get();
        $products = Product::take(3)->get();

        // 1. Seed POS Sales
        if ($customer && $products->count() > 0) {
            $sale1 = Sale::firstOrCreate(
                ['invoice_no' => 'INV-2026-001'],
                [
                    'account_id' => $customer->id,
                    'store_id' => $store?->id,
                    'sale_date' => now()->format('Y-m-d'),
                    'total_amount' => 4590.00,
                    'received_amount' => 4590.00,
                    'balance_due' => 0.00,
                    'payment_mode' => 'Cash',
                    'notes' => 'Walk-in retail product purchase at front counter.',
                ]
            );

            foreach ($products->take(2) as $prod) {
                SaleItem::firstOrCreate(
                    ['sale_id' => $sale1->id, 'product_id' => $prod->id],
                    [
                        'quantity' => 1,
                        'unit_price' => $prod->discounted_price ?: $prod->price,
                        'subtotal' => $prod->discounted_price ?: $prod->price,
                        'purchase_price' => $prod->purchase_price ?: 1500.00,
                    ]
                );
            }

            $sale2 = Sale::firstOrCreate(
                ['invoice_no' => 'INV-2026-002'],
                [
                    'account_id' => $customer->id,
                    'store_id' => $store?->id,
                    'sale_date' => now()->subDay()->format('Y-m-d'),
                    'total_amount' => 5200.00,
                    'received_amount' => 5200.00,
                    'balance_due' => 0.00,
                    'payment_mode' => 'Credit Card',
                    'notes' => 'Paid via POS card terminal.',
                ]
            );

            if ($products->count() >= 3) {
                SaleItem::firstOrCreate(
                    ['sale_id' => $sale2->id, 'product_id' => $products[2]->id],
                    [
                        'quantity' => 1,
                        'unit_price' => $products[2]->price,
                        'subtotal' => $products[2]->price,
                        'purchase_price' => $products[2]->purchase_price ?: 3800.00,
                    ]
                );
            }
        }

        // 2. Seed Appointments
        if ($customer && $staff && $services->count() > 0) {
            $apt1 = Appointment::firstOrCreate(
                ['booking_no' => 'APT-2026-001'],
                [
                    'account_id' => $customer->id,
                    'employee_id' => $staff->id,
                    'appointment_date' => now()->format('Y-m-d'),
                    'start_time' => '11:00:00',
                    'total_amount' => 6500.00,
                    'discount' => 500.00,
                    'discount_type' => 'Fixed',
                    'net_amount' => 6000.00,
                    'paid_amount' => 6000.00,
                    'balance_due' => 0.00,
                    'payment_mode' => 'Cash',
                    'status' => 'completed',
                    'order_type' => 'Walkin',
                    'notes' => 'Client requested signature styling and hot towel relaxation.',
                ]
            );

            foreach ($services->take(2) as $s) {
                AppointmentService::firstOrCreate(
                    ['appointment_id' => $apt1->id, 'saloon_service_id' => $s->id],
                    [
                        'price' => $s->price,
                        'quantity' => 1,
                        'discount' => 0.00,
                        'discounted_price' => $s->price,
                    ]
                );
            }

            $apt2 = Appointment::firstOrCreate(
                ['booking_no' => 'APT-2026-002'],
                [
                    'account_id' => $customer->id,
                    'employee_id' => $staff->id,
                    'appointment_date' => now()->format('Y-m-d'),
                    'start_time' => '15:00:00',
                    'total_amount' => 3500.00,
                    'discount' => 0.00,
                    'net_amount' => 3500.00,
                    'paid_amount' => 1000.00,
                    'balance_due' => 2500.00,
                    'payment_mode' => 'Online',
                    'status' => 'confirmed',
                    'order_type' => 'Online',
                    'notes' => 'Website online booking request.',
                ]
            );

            if ($services->count() >= 3) {
                AppointmentService::firstOrCreate(
                    ['appointment_id' => $apt2->id, 'saloon_service_id' => $services[2]->id],
                    [
                        'price' => $services[2]->price,
                        'quantity' => 1,
                        'discount' => 0.00,
                        'discounted_price' => $services[2]->price,
                    ]
                );
            }
        }
    }

    protected function seedExpenses(): void
    {
        $expCategories = [
            'Utilities & Electricity',
            'Salon Rent & Maintenance',
            'Staff Tea & Refreshments',
            'Towels & Laundry Supplies',
            'Internet & Communication',
        ];

        $expCatModels = [];
        foreach ($expCategories as $title) {
            $expCatModels[$title] = ExpenseCategory::firstOrCreate(['title' => $title]);
        }

        $admin = User::first();

        $expensesData = [
            [
                'cat' => 'Staff Tea & Refreshments',
                'title' => 'Daily Staff Tea & Fresh Milk Refreshments',
                'amount' => 850.00,
                'description' => 'Morning and evening tea for staff and waiting clients.',
            ],
            [
                'cat' => 'Utilities & Electricity',
                'title' => 'Backup Generator Diesel Fuel (20 Litres)',
                'amount' => 5600.00,
                'description' => 'Diesel purchased for load-shedding generator operations.',
            ],
            [
                'cat' => 'Towels & Laundry Supplies',
                'title' => 'Salon Towels Heavy Wash & Sanitization',
                'amount' => 1200.00,
                'description' => 'Commercial laundry washed 120 white salon towels.',
            ],
            [
                'cat' => 'Internet & Communication',
                'title' => 'PTCL Optical Fiber Broadband Bill (Phalia)',
                'amount' => 3800.00,
                'description' => 'Monthly internet bill for POS terminal and guest Wi-Fi.',
            ],
        ];

        foreach ($expensesData as $e) {
            $cat = $expCatModels[$e['cat']];
            Expense::firstOrCreate(
                [
                    'exp_category_id' => $cat->id,
                    'exp_title' => $e['title'],
                ],
                [
                    'amount' => $e['amount'],
                    'description' => $e['description'],
                    'added_by' => $admin?->id ?: 1,
                ]
            );
        }
    }

    protected function seedBankAccounts(): void
    {
        $accounts = [
            [
                'bank_name' => 'Meezan Bank Limited',
                'account_title' => 'Jugnu Saloon Phalia Operations',
                'account_no' => '02100104882711',
                'branch_name' => 'Phalia Main Branch',
                'iban' => 'PK98MEZN0002100104882711',
                'is_active' => true,
            ],
            [
                'bank_name' => 'Habib Bank Limited (HBL)',
                'account_title' => 'Jugnu Saloon POS Merchant Terminal',
                'account_no' => '10447900128403',
                'branch_name' => 'Kutchery Road Phalia',
                'iban' => 'PK45HABB0010447900128403',
                'is_active' => true,
            ],
            [
                'bank_name' => 'Cash In Hand / Counter Drawer',
                'account_title' => 'Executive Front Desk Vault',
                'account_no' => 'CASH-VAULT-01',
                'branch_name' => 'Main Reception Cash Box',
                'iban' => null,
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            BankAccount::firstOrCreate(
                ['account_no' => $acc['account_no']],
                $acc
            );
        }
    }

    protected function seedLedgers(): void
    {
        $customer = Account::whereHas('category', fn($q) => $q->where('title', 'like', '%Customer%'))->first();
        if ($customer) {
            AccountLedger::firstOrCreate(
                [
                    'account_id' => $customer->id,
                    'reference_no' => 'APT-2026-001',
                ],
                [
                    'date' => now()->format('Y-m-d'),
                    'type' => 'credit',
                    'debit' => 0.00,
                    'credit' => 6000.00,
                    'running_balance' => 6000.00,
                    'description' => 'Payment received for Appointment APT-2026-001 via Cash.',
                ]
            );
        }
    }

    protected function seedStaffAttendanceAndPayroll(): void
    {
        $staffMembers = Account::whereHas('category', fn($q) => $q->where('title', 'like', '%Staff%'))->get();

        foreach ($staffMembers as $staff) {
            // 1. Attendance today
            StaffAttendance::updateOrCreate(
                [
                    'account_id' => $staff->id,
                    'date' => now()->format('Y-m-d'),
                ],
                [
                    'status' => 'present',
                    'check_in' => '09:45:00',
                    'check_out' => '20:30:00',
                    'notes' => 'Punctual attendance.',
                ]
            );

            // 2. Attendance yesterday
            StaffAttendance::updateOrCreate(
                [
                    'account_id' => $staff->id,
                    'date' => now()->subDay()->format('Y-m-d'),
                ],
                [
                    'status' => 'present',
                    'check_in' => '09:50:00',
                    'check_out' => '20:15:00',
                    'notes' => 'Present on full shift.',
                ]
            );

            // 3. Monthly Payroll
            $baseSalary = $staff->salary ?: 35000.00;
            $commissionAmount = 4500.00;
            $netSalary = $baseSalary + $commissionAmount;

            Payroll::firstOrCreate(
                [
                    'account_id' => $staff->id,
                    'month_year' => now()->subMonth()->format('Y-m'),
                ],
                [
                    'base_salary' => $baseSalary,
                    'total_commission' => $commissionAmount,
                    'net_salary' => $netSalary,
                    'status' => 'paid',
                    'payment_date' => now()->subDays(10)->format('Y-m-d'),
                    'notes' => 'Saloon monthly salary disbursed with service bonus.',
                ]
            );
        }
    }

    protected function seedDayClosings(): void
    {
        $admin = User::first();
        $yesterday = now()->subDay()->format('Y-m-d');

        DayClosing::firstOrCreate(
            ['closing_date' => $yesterday],
            [
                'opening_cash' => 15000.00,
                'opening_bank' => 45000.00,
                'total_opening' => 60000.00,
                'cash_received' => 38500.00,
                'bank_received' => 18200.00,
                'total_received' => 56700.00,
                'cash_paid' => 6450.00,
                'bank_paid' => 0.00,
                'total_paid' => 6450.00,
                'net_cash_flow' => 32050.00,
                'net_bank_flow' => 18200.00,
                'total_net_flow' => 50250.00,
                'closing_cash_expected' => 47050.00,
                'closing_bank_expected' => 63200.00,
                'closing_total_expected' => 110250.00,
                'cash_actual_counted' => 47050.00,
                'cash_difference' => 0.00,
                'status' => 'verified',
                'closed_by' => $admin?->id ?: 1,
                'closed_at' => now()->subDay()->setTime(21, 30, 0),
                'notes' => 'Perfect cash drawer reconciliation. No difference recorded.',
            ]
        );
    }

    protected function seedGalleries(): void
    {
        $galleries = [
            [
                'title' => 'Signature Royal Bridal Makeover',
                'category' => 'Bridal',
                'image_path' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'title' => 'Modern Balayage & Ash Blonde Foil Highlights',
                'category' => 'Hair',
                'image_path' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'title' => 'Executive Men\'s Beard Grooming & Razor Fade',
                'category' => 'Grooming',
                'image_path' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'title' => 'Gold Radiance Facial & Skin Hydration Spa',
                'category' => 'Skincare',
                'image_path' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=600&q=80',
            ],
        ];

        foreach ($galleries as $index => $g) {
            Gallery::firstOrCreate(
                ['title' => $g['title']],
                [
                    'category' => $g['category'],
                    'image_path' => $g['image_path'],
                    'file_name' => strtolower(str_replace(' ', '-', $g['category'])) . '-sample.jpg',
                    'file_size' => 245000 + ($index * 15000),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
