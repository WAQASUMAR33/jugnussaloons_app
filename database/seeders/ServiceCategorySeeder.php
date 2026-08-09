<?php

namespace Database\Seeders;

use App\Models\SaloonService;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Default Saloon Service Categories
        $hairCategory = ServiceCategory::firstOrCreate(['title' => 'Haircuts & Styling'], ['description' => 'Precision haircuts, blow dry, and hair styling services.']);
        $beardCategory = ServiceCategory::firstOrCreate(['title' => 'Beard & Grooming'], ['description' => 'Beard shaping, razor line up, and hot towel treatments.']);
        $facialCategory = ServiceCategory::firstOrCreate(['title' => 'Facial & Skin Care'], ['description' => 'Deep pore cleansing, facial masks, and glowing skin spa.']);
        $colorCategory = ServiceCategory::firstOrCreate(['title' => 'Hair Coloring & Treatments'], ['description' => 'Ammonia-free hair coloring, foil highlights, and keratin.']);

        // 2. Assign Categories to Existing Services
        SaloonService::where('title', 'like', '%Haircut%')->update(['service_category_id' => $hairCategory->id]);
        SaloonService::where('title', 'like', '%Beard%')->update(['service_category_id' => $beardCategory->id]);
        SaloonService::where('title', 'like', '%Facial%')->update(['service_category_id' => $facialCategory->id]);
        SaloonService::where('title', 'like', '%Color%')->orWhere('title', 'like', '%Highlights%')->update(['service_category_id' => $colorCategory->id]);
    }
}
