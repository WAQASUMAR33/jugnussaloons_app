<?php

namespace Database\Seeders;

use App\Models\Gallery;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Gallery::count() === 0) {
            Gallery::create([
                'title' => 'Luxury Saloon Interior & Styling Stations',
                'category' => 'Saloon Ambience',
                'image_path' => 'storage/galleries/saloon_hair_style.png',
                'file_name' => 'saloon_hair_style.png',
                'file_size' => 1250000,
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }
    }
}
