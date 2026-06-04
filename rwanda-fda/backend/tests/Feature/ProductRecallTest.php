<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductRecallTest extends TestCase
{
    use RefreshDatabase;

    private function seedBatchAndManufacturer(): array
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
        $batch = Batch::create([
            'product_id' => $product->id, 'manufacturer_id' => $mfg->id,
            'batch_number' => 'LOT-4421',
            'manufacture_date' => '2025-01-01', 'expiry_date' => '2027-01-01',
        ]);
        return ['mfg' => $mfg, 'batch' => $batch];
    }

    public function test_can_create_product_recall(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'batch_id'        => $batch->id,
            'manufacturer_id' => $mfg->id,
            'recall_number'   => 'RCL-001',
            'reason'          => 'Substandard API content',
            'classification'  => 'Class II',
            'qc_summary'      => 'Active ingredient at 72% (spec: 95-105%)',
            'status'          => 'active',
            'date_issued'     => '2026-01-15',
            'scope'           => 'National',
            'internal_investigation_notes' => 'Confidential lab report',
            'inspector_id'    => 42,
        ])->assertStatus(201)
          ->assertJsonPath('recall_number', 'RCL-001')
          ->assertJsonPath('classification', 'Class II')
          ->assertJsonMissingPath('internal_investigation_notes')
          ->assertJsonMissingPath('inspector_id');
    }

    public function test_only_active_recalls_returned_when_status_filter_applied(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-001', 'reason' => 'Substandard',
            'classification' => 'Class II', 'qc_summary' => 'API at 72%',
            'status' => 'active', 'date_issued' => '2026-01-15', 'scope' => 'National',
        ]);

        $this->getJson('/api/product-recalls?status=active')
             ->assertStatus(200)
             ->assertJsonCount(1);

        $this->getJson('/api/product-recalls?status=completed')
             ->assertStatus(200)
             ->assertJsonCount(0);
    }

    public function test_recall_index_includes_batch_and_manufacturer_relations(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-REL-001', 'reason' => 'Substandard API content',
            'classification' => 'Class II', 'qc_summary' => 'API at 72%',
            'status' => 'active', 'date_issued' => '2026-01-15',
        ]);

        $this->getJson('/api/product-recalls')
             ->assertStatus(200)
             ->assertJsonPath('0.batch.batch_number', 'LOT-4421')
             ->assertJsonPath('0.manufacturer.name', 'PharmaCo Ltd');
    }

    public function test_recall_show_includes_batch_and_manufacturer_relations(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $recall = $this->postJson('/api/product-recalls', [
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-SHOW-001', 'reason' => 'Substandard API content',
            'classification' => 'Class II', 'qc_summary' => 'API at 72%',
            'status' => 'active', 'date_issued' => '2026-01-15',
        ])->json();

        $this->getJson("/api/product-recalls/{$recall['id']}")
             ->assertStatus(200)
             ->assertJsonPath('batch.id', $batch->id)
             ->assertJsonPath('batch.product.registration_number', 'PRD-112')
             ->assertJsonPath('manufacturer.id', $mfg->id);
    }
}
