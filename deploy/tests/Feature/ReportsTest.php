<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    protected function getManagerUser()
    {
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create();
        }
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        if (!$user->roles->contains('id', $managerRole->id)) {
            $user->roles()->attach($managerRole);
        }
        return $user;
    }

    public function test_sales_report_page_loads_successfully()
    {
        $user = $this->getManagerUser();

        $response = $this->actingAs($user)->get(route('manager.reports.sales'));
        $response->assertStatus(200);

        $responseCat = $this->actingAs($user)->get(route('manager.reports.sales', ['report_type' => 'categorywise']));
        $responseCat->assertStatus(200);

        $responseDet = $this->actingAs($user)->get(route('manager.reports.sales', ['report_type' => 'detailed']));
        $responseDet->assertStatus(200);
    }

    public function test_stock_report_page_loads_successfully()
    {
        $user = $this->getManagerUser();

        $response = $this->actingAs($user)->get(route('manager.reports.stock'));
        $response->assertStatus(200);

        $responseLow = $this->actingAs($user)->get(route('manager.reports.stock', ['status' => 'low']));
        $responseLow->assertStatus(200);

        $responseOut = $this->actingAs($user)->get(route('manager.reports.stock', ['status' => 'out']));
        $responseOut->assertStatus(200);
    }

    public function test_services_report_page_loads_successfully()
    {
        $user = $this->getManagerUser();

        $response = $this->actingAs($user)->get(route('manager.reports.services'));
        $response->assertStatus(200);

        $responseCat = $this->actingAs($user)->get(route('manager.reports.services', ['report_type' => 'categorywise']));
        $responseCat->assertStatus(200);

        $responseDet = $this->actingAs($user)->get(route('manager.reports.services', ['report_type' => 'detailed']));
        $responseDet->assertStatus(200);
    }

    public function test_ledger_report_page_loads_successfully()
    {
        $user = $this->getManagerUser();

        $response = $this->actingAs($user)->get(route('manager.reports.ledger'));
        $response->assertStatus(200);
    }

    public function test_purchases_report_page_loads_successfully()
    {
        $user = $this->getManagerUser();

        $response = $this->actingAs($user)->get(route('manager.reports.purchases'));
        $response->assertStatus(200);

        $responseAcc = $this->actingAs($user)->get(route('manager.reports.purchases', ['report_type' => 'accountwise']));
        $responseAcc->assertStatus(200);

        $responseDet = $this->actingAs($user)->get(route('manager.reports.purchases', ['report_type' => 'detailed']));
        $responseDet->assertStatus(200);
    }
}
