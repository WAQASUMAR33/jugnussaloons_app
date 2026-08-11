<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\SaloonService;
use Illuminate\Database\Seeder;

class ServiceAndProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Saloon Services
        SaloonService::firstOrCreate(
            ['title' => 'Executive Haircut & Styling'],
            [
                'description' => 'Precision haircut, hair wash, scalp massage, and professional blowdry styling.',
                'price' => 45.00,
                'discount' => 10.00,
                'discounted_price' => 40.50,
                'commission' => 12.00,
                'image' => null,
            ]
        );

        SaloonService::firstOrCreate(
            ['title' => 'Beard Grooming & Hot Towel Treatment'],
            [
                'description' => 'Beard shaping, razor line up, organic beard oil treatment, and hot towel facial massage.',
                'price' => 30.00,
                'discount' => 15.00,
                'discounted_price' => 25.50,
                'commission' => 8.00,
                'image' => null,
            ]
        );

        SaloonService::firstOrCreate(
            ['title' => 'Organic Hair Color & Highlights'],
            [
                'description' => 'Ammonia-free premium hair coloring, foil highlights, and deep conditioning treatment.',
                'price' => 95.00,
                'discount' => 20.00,
                'discounted_price' => 76.00,
                'commission' => 25.00,
                'image' => null,
            ]
        );

        SaloonService::firstOrCreate(
            ['title' => 'Keratin Smooth Facial Spa'],
            [
                'description' => 'Deep pore cleansing, facial exfoliation, hydration mask, and relaxing neck massage.',
                'price' => 65.00,
                'discount' => 0.00,
                'discounted_price' => 65.00,
                'commission' => 18.00,
                'image' => null,
            ]
        );

        // 2. Seed Saloon Products
        Product::firstOrCreate(
            ['title' => 'Matte Clay Styling Pomade 100g'],
            [
                'price' => 22.50,
                'discount' => 10.00,
                'discounted_price' => 20.25,
                'stock' => 45,
                'image' => null,
            ]
        );

        Product::firstOrCreate(
            ['title' => 'Argan Oil Hydrating Shampoo 250ml'],
            [
                'price' => 18.00,
                'discount' => 0.00,
                'discounted_price' => 18.00,
                'stock' => 30,
                'image' => null,
            ]
        );

        Product::firstOrCreate(
            ['title' => 'Organic Cedarwood Beard Oil 50ml'],
            [
                'price' => 26.00,
                'discount' => 15.00,
                'discounted_price' => 22.10,
                'stock' => 5,
                'image' => null,
            ]
        );

        Product::firstOrCreate(
            ['title' => 'Keratin Repair Hair Conditioner 200ml'],
            [
                'price' => 21.00,
                'discount' => 5.00,
                'discounted_price' => 19.95,
                'stock' => 0,
                'image' => null,
            ]
        );
    }
}
