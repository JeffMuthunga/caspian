<?php
// rwanda-fda/backend/tests/Feature/ManufacturerTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_manufacturer(): void
    {
        $response = $this->postJson('/api/manufacturers', [
            'name'                => 'PharmaCo Ltd',
            'country'             => 'Kenya',
            'registration_number' => 'MFG-441',
            'license_status'      => 'active',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'PharmaCo Ltd')
                 ->assertJsonPath('registration_number', 'MFG-441');
    }

    public function test_can_list_manufacturers(): void
    {
        $this->postJson('/api/manufacturers', [
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);

        $this->getJson('/api/manufacturers')
             ->assertStatus(200)
             ->assertJsonCount(1);
    }

    public function test_internal_fields_are_not_returned(): void
    {
        $response = $this->postJson('/api/manufacturers', [
            'name'                   => 'PharmaCo Ltd',
            'country'                => 'Kenya',
            'registration_number'    => 'MFG-441',
            'license_status'         => 'active',
            'internal_vendor_rating' => 3,
            'contract_terms'         => 'NET-30',
        ]);

        $response->assertStatus(201)
                 ->assertJsonMissingPath('internal_vendor_rating')
                 ->assertJsonMissingPath('contract_terms');
    }
}
