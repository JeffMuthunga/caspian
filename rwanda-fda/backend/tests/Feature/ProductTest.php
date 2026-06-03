<?php
// tests/Feature/ProductTest.php
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
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);
    }

    public function test_can_create_product(): void
    {
        $mfg = $this->manufacturer();

        $this->postJson('/api/products', [
            'manufacturer_id'     => $mfg->id,
            'name'                => 'Amoxicillin 500mg',
            'generic_name'        => 'Amoxicillin',
            'dosage_form'         => 'Capsule',
            'strength'            => '500mg',
            'registration_number' => 'PRD-112',
        ])->assertStatus(201)
          ->assertJsonPath('name', 'Amoxicillin 500mg')
          ->assertJsonMissingPath('internal_cost');
    }

    public function test_can_list_products(): void
    {
        $mfg = $this->manufacturer();
        $this->postJson('/api/products', [
            'manufacturer_id' => $mfg->id, 'name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule',
            'strength' => '500mg', 'registration_number' => 'PRD-112',
        ]);

        $this->getJson('/api/products')->assertStatus(200)->assertJsonCount(1);
    }
}
