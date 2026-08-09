<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\SaloonService;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Employee Category
        $employeeCategory = AccountCategory::firstOrCreate(['title' => 'Employee']);

        // 2. Create Employee Staff Account
        $staffAccount = Account::firstOrCreate(
            ['phone_no1' => '+1 555-0999'],
            [
                'account_category_id' => $employeeCategory->id,
                'name' => 'Alex Rivera (Senior Stylist)',
                'father_name' => 'Michael Rivera',
                'address' => '45 Stylist Lane, Saloon District',
                'phone_no2' => null,
                'card_no' => 'EMP-7001',
                'balance' => 0.00,
            ]
        );

        // 3. Create Sample Appointment if customer & service exist
        $customer = Account::where('account_category_id', '!=', $employeeCategory->id)->first();
        $service = SaloonService::first();

        if ($customer && $service && $staffAccount) {
            $appointment = Appointment::firstOrCreate(
                ['booking_no' => 'APT-202608-0001'],
                [
                    'account_id' => $customer->id,
                    'employee_id' => $staffAccount->id,
                    'appointment_date' => date('Y-m-d'),
                    'start_time' => '10:30:00',
                    'total_amount' => $service->discounted_price,
                    'discount' => 0.00,
                    'net_amount' => $service->discounted_price,
                    'paid_amount' => $service->discounted_price,
                    'balance_due' => 0.00,
                    'total_commission' => $service->commission,
                    'status' => 'confirmed',
                    'notes' => 'Customer requested Senior Stylist Alex Rivera.',
                ]
            );

            AppointmentService::firstOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'saloon_service_id' => $service->id,
                ],
                [
                    'price' => $service->price,
                    'discount' => $service->discount,
                    'discounted_price' => $service->discounted_price,
                    'commission' => $service->commission,
                ]
            );
        }
    }
}
