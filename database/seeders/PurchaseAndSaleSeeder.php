<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountLedger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;

class PurchaseAndSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Supplier and Customer Categories & Accounts exist
        $supplierCategory = AccountCategory::firstOrCreate(['title' => 'Supplier / Vendor']);
        $customerCategory = AccountCategory::firstOrCreate(['title' => 'VIP Customer']);

        $supplier = Account::firstOrCreate(
            ['phone_no1' => '+1 555-0381'],
            [
                'account_category_id' => $supplierCategory->id,
                'name' => 'L\'Oréal Professional Supplies',
                'balance' => 0.00,
            ]
        );

        $customer = Account::firstOrCreate(
            ['phone_no1' => '+1 555-0192'],
            [
                'account_category_id' => $customerCategory->id,
                'name' => 'Sarah Johnson',
                'balance' => 0.00,
            ]
        );

        $product1 = Product::firstOrCreate(
            ['title' => 'Matte Clay Styling Pomade 100g'],
            [
                'price' => 22.50,
                'discount' => 10.00,
                'discounted_price' => 20.25,
                'stock' => 45,
            ]
        );

        $product2 = Product::firstOrCreate(
            ['title' => 'Argan Oil Hydrating Shampoo 250ml'],
            [
                'price' => 18.00,
                'discount' => 0.00,
                'discounted_price' => 18.00,
                'stock' => 30,
            ]
        );

        // 2. Seed Sample Purchase
        $purchaseInvoice = 'PUR-202608-0001';
        $purchaseDate = now()->subDays(3)->format('Y-m-d');
        
        $purchase = Purchase::firstOrCreate(
            ['invoice_no' => $purchaseInvoice],
            [
                'account_id' => $supplier->id,
                'total_amount' => 375.00,
                'paid_amount' => 200.00,
                'balance_due' => 175.00,
                'purchase_date' => $purchaseDate,
                'notes' => 'Bulk stock acquisition for hair products.',
            ]
        );

        if ($purchase->wasRecentlyCreated) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product1->id,
                'quantity' => 10,
                'unit_price' => 15.00,
                'subtotal' => 150.00,
            ]);

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product2->id,
                'quantity' => 15,
                'unit_price' => 15.00,
                'subtotal' => 225.00,
            ]);

            // Ledger Entry for Purchase
            AccountLedger::create([
                'account_id' => $supplier->id,
                'date' => $purchaseDate,
                'type' => 'purchase',
                'reference_no' => $purchaseInvoice,
                'description' => "Purchase Bill #{$purchaseInvoice} (2 items)",
                'debit' => 375.00,
                'credit' => 0.00,
                'running_balance' => 375.00,
            ]);

            // Ledger Entry for Payment
            AccountLedger::create([
                'account_id' => $supplier->id,
                'date' => $purchaseDate,
                'type' => 'payment',
                'reference_no' => $purchaseInvoice . '-PAY',
                'description' => "Payment made for Purchase Bill #{$purchaseInvoice}",
                'debit' => 0.00,
                'credit' => 200.00,
                'running_balance' => 175.00,
            ]);

            $supplier->update(['balance' => 175.00]);
        }

        // 3. Seed Sample Sale
        $saleInvoice = 'INV-202608-0001';
        $saleDate = now()->subDays(1)->format('Y-m-d');

        $sale = Sale::firstOrCreate(
            ['invoice_no' => $saleInvoice],
            [
                'account_id' => $customer->id,
                'total_amount' => 58.50,
                'received_amount' => 58.50,
                'balance_due' => 0.00,
                'sale_date' => $saleDate,
                'notes' => 'Regular customer walk-in purchase.',
            ]
        );

        if ($sale->wasRecentlyCreated) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product1->id,
                'quantity' => 2,
                'unit_price' => 20.25,
                'subtotal' => 40.50,
            ]);

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product2->id,
                'quantity' => 1,
                'unit_price' => 18.00,
                'subtotal' => 18.00,
            ]);

            // Ledger Entry for Sale
            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $saleDate,
                'type' => 'sale',
                'reference_no' => $saleInvoice,
                'description' => "Product Sale Invoice #{$saleInvoice} (2 items)",
                'debit' => 58.50,
                'credit' => 0.00,
                'running_balance' => 58.50,
            ]);

            // Ledger Entry for Receiving
            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $saleDate,
                'type' => 'receiving',
                'reference_no' => $saleInvoice . '-REC',
                'description' => "Payment received for Sale Invoice #{$saleInvoice}",
                'debit' => 0.00,
                'credit' => 58.50,
                'running_balance' => 0.00,
            ]);

            $customer->update(['balance' => 0.00]);
        }
    }
}
