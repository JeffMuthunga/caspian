<?php
// tests/Feature/ManufacturerTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_manufacturer(): void
    {
        $this->postJson('/api/manufacturers', [
            'company_name'         => 'PharmaCo Ltd',
            'origin_country'       => 'Nigeria',
            'reg_no'               => 'NAFDAC-MFG-001',
            'authorization_status' => 'authorized',
        ])->assertStatus(201)
          ->assertJsonPath('company_name', 'PharmaCo Ltd')
          ->assertJsonPath('reg_no', 'NAFDAC-MFG-001');
    }

    public function test_can_list_manufacturers(): void
    {
        $this->postJson('/api/manufacturers', [
            'company_name' => 'PharmaCo Ltd', 'origin_country' => 'Nigeria',
            'reg_no' => 'NAFDAC-MFG-001', 'authorization_status' => 'authorized',
        ]);

        $this->getJson('/api/manufacturers')
             ->assertStatus(200)
             ->assertJsonCount(1);
    }
}
