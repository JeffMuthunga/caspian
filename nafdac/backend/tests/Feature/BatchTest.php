<?php
namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    private function product(): array
    {
        $mfg = Manufacturer::create([
            'company_name' => 'PharmaCo Ltd', 'origin_country' => 'Nigeria',
            'reg_no' => 'NAFDAC-MFG-001', 'authorization_status' => 'authorized',
        ]);
        $product = Product::create([
            'supplier_id' => $mfg->id, 'product_name' => 'Amoxicillin 500mg',
            'inn_name' => 'Amoxicillin', 'formulation' => 'Capsule',
            'potency' => '500mg', 'market_auth_number' => 'NAFDAC-PRD-001',
        ]);
        return ['manufacturer' => $mfg, 'product' => $product];
    }

    public function test_can_create_batch(): void
    {
        ['manufacturer' => $mfg, 'product' => $product] = $this->product();

        $this->postJson('/api/batches', [
            'product_id'         => $product->id,
            'supplier_id'        => $mfg->id,
            'lot_number'         => 'LOT-4421',
            'production_date'    => '2025-01-01',
            'expiry_date'        => '2027-01-01',
            'units_manufactured' => 10000,
        ])->assertStatus(201)
          ->assertJsonPath('lot_number', 'LOT-4421')
          ->assertJsonMissingPath('units_manufactured');
    }
}
