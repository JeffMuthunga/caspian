<?php
namespace Tests\Feature;

use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function manufacturer(): Manufacturer
    {
        return Manufacturer::create([
            'company_name' => 'PharmaCo Ltd', 'origin_country' => 'Nigeria',
            'reg_no' => 'NAFDAC-MFG-001', 'authorization_status' => 'authorized',
        ]);
    }

    public function test_can_create_product(): void
    {
        $mfg = $this->manufacturer();

        $this->postJson('/api/products', [
            'supplier_id'        => $mfg->id,
            'product_name'       => 'Amoxicillin 500mg',
            'inn_name'           => 'Amoxicillin',
            'formulation'        => 'Capsule',
            'potency'            => '500mg',
            'market_auth_number' => 'NAFDAC-PRD-001',
        ])->assertStatus(201)
          ->assertJsonPath('product_name', 'Amoxicillin 500mg');
    }

    public function test_can_list_products(): void
    {
        $mfg = $this->manufacturer();
        $this->postJson('/api/products', [
            'supplier_id' => $mfg->id, 'product_name' => 'Amoxicillin 500mg',
            'inn_name' => 'Amoxicillin', 'formulation' => 'Capsule',
            'potency' => '500mg', 'market_auth_number' => 'NAFDAC-PRD-001',
        ]);

        $this->getJson('/api/products')->assertStatus(200)->assertJsonCount(1);
    }
}
