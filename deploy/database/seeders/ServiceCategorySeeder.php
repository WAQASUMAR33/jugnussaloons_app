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
        // Create Categories
        $hair = ServiceCategory::firstOrCreate(['title' => 'Hair'], ['description' => 'Haircuts, styling, coloring & keratin treatments']);
        $skin = ServiceCategory::firstOrCreate(['title' => 'Skin'], ['description' => 'Facials, skin care & glowing treatments']);
        $makeup = ServiceCategory::firstOrCreate(['title' => 'Makeup'], ['description' => 'Party makeup, bridal makeup & makeover']);
        $nails = ServiceCategory::firstOrCreate(['title' => 'Nails'], ['description' => 'Manicure, pedicure & nail art']);
        $waxing = ServiceCategory::firstOrCreate(['title' => 'Waxing'], ['description' => 'Full body waxing & organic waxing']);
        $packages = ServiceCategory::firstOrCreate(['title' => 'Packages'], ['description' => 'Exclusive salon combo packages']);

        $servicesData = [
            // Hair
            ['title' => 'Hair Cut', 'service_category_id' => $hair->id, 'price' => 500.00, 'discounted_price' => 500.00, 'commission' => 150.00, 'image' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Blow Dry', 'service_category_id' => $hair->id, 'price' => 800.00, 'discounted_price' => 800.00, 'commission' => 200.00, 'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Keratin Treatment', 'service_category_id' => $hair->id, 'price' => 4000.00, 'discounted_price' => 4000.00, 'commission' => 1000.00, 'image' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Hair Color', 'service_category_id' => $hair->id, 'price' => 2500.00, 'discounted_price' => 2500.00, 'commission' => 600.00, 'image' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Highlights', 'service_category_id' => $hair->id, 'price' => 2800.00, 'discounted_price' => 2800.00, 'commission' => 700.00, 'image' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Hair Spa', 'service_category_id' => $hair->id, 'price' => 1500.00, 'discounted_price' => 1500.00, 'commission' => 400.00, 'image' => 'https://images.unsplash.com/photo-1519699047748-de8e457a634e?auto=format&fit=crop&w=400&q=80'],
            
            // Skin
            ['title' => 'Facial', 'service_category_id' => $skin->id, 'price' => 2000.00, 'discounted_price' => 2000.00, 'commission' => 500.00, 'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Gold Facial', 'service_category_id' => $skin->id, 'price' => 2500.00, 'discounted_price' => 2500.00, 'commission' => 650.00, 'image' => 'https://images.unsplash.com/photo-1512290900673-700204752c00?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Cleanup', 'service_category_id' => $skin->id, 'price' => 1200.00, 'discounted_price' => 1200.00, 'commission' => 300.00, 'image' => 'https://images.unsplash.com/photo-1512290900673-700204752c00?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'D-Tan Facial', 'service_category_id' => $skin->id, 'price' => 2500.00, 'discounted_price' => 2500.00, 'commission' => 600.00, 'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=400&q=80'],
            
            // Nails
            ['title' => 'Manicure', 'service_category_id' => $nails->id, 'price' => 800.00, 'discounted_price' => 800.00, 'commission' => 200.00, 'image' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Pedicure', 'service_category_id' => $nails->id, 'price' => 1200.00, 'discounted_price' => 1200.00, 'commission' => 300.00, 'image' => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?auto=format&fit=crop&w=400&q=80'],
            
            // Waxing
            ['title' => 'Full Arms Wax', 'service_category_id' => $waxing->id, 'price' => 800.00, 'discounted_price' => 800.00, 'commission' => 200.00, 'image' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Full Legs Wax', 'service_category_id' => $waxing->id, 'price' => 1200.00, 'discounted_price' => 1200.00, 'commission' => 300.00, 'image' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Bikini Wax', 'service_category_id' => $waxing->id, 'price' => 1500.00, 'discounted_price' => 1500.00, 'commission' => 400.00, 'image' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=400&q=80'],
            ['title' => 'Brazilian Wax', 'service_category_id' => $waxing->id, 'price' => 2000.00, 'discounted_price' => 2000.00, 'commission' => 500.00, 'image' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=400&q=80'],
        ];

        foreach ($servicesData as $sd) {
            SaloonService::firstOrCreate(['title' => $sd['title']], $sd);
        }
    }
}
