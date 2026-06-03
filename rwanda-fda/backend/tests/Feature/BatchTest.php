<?php
// tests/Feature/BatchTest.php
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
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);
        $product = Product::create([
            'manufacturer_id' => $mfg->id, 'name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule',
            'strength' => '500mg', 'registration_number' => 'PRD-112',
        ]);
        return ['manufacturer' => $mfg, 'product' => $product];
    }

    public function test_can_create_batch(): void
    {
        ['manufacturer' => $mfg, 'product' => $product] = $this->product();

        $this->postJson('/api/batches', [
            'product_id'       => $product->id,
            'manufacturer_id'  => $mfg->id,
            'batch_number'     => 'LOT-4421',
            'manufacture_date' => '2025-01-01',
            'expiry_date'      => '2027-01-01',
            'quantity_produced'=> 5000,
            'internal_lot_code'=> 'INT-001',
        ])->assertStatus(201)
          ->assertJsonPath('batch_number', 'LOT-4421')
          ->assertJsonMissingPath('quantity_produced')
          ->assertJsonMissingPath('internal_lot_code');
    }

    public function test_can_list_batches(): void
    {
        ['manufacturer' => $mfg, 'product' => $product] = $this->product();
        $this->postJson('/api/batches', [
            'product_id' => $product->id, 'manufacturer_id' => $mfg->id,
            'batch_number' => 'LOT-4421', 'manufacture_date' => '2025-01-01',
            'expiry_date' => '2027-01-01',
        ]);

        $this->getJson('/api/batches')->assertStatus(200)->assertJsonCount(1);
    }
}
