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
            'company_name' => 'PharmaCo Ltd', 'origin_country' => 'Nigeria',
            'reg_no' => 'NAFDAC-MFG-001', 'authorization_status' => 'authorized',
        ]);
        $product = Product::create([
            'supplier_id' => $mfg->id, 'product_name' => 'Amoxicillin 500mg',
            'inn_name' => 'Amoxicillin', 'formulation' => 'Capsule',
            'potency' => '500mg', 'market_auth_number' => 'NAFDAC-PRD-001',
        ]);
        $batch = Batch::create([
            'product_id' => $product->id, 'supplier_id' => $mfg->id,
            'lot_number' => 'LOT-4421',
            'production_date' => '2025-01-01', 'expiry_date' => '2027-01-01',
        ]);
        return ['mfg' => $mfg, 'batch' => $batch];
    }

    public function test_can_create_product_recall(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'uuid-1', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'lot_id'              => $batch->id,
            'supplier_id'         => $mfg->id,
            'alert_reference'     => 'NAFDAC-RCL-001',
            'recall_reason'       => 'Substandard API content',
            'severity_grade'      => 'Grade II',
            'laboratory_findings' => 'Active ingredient at 72% (spec: 95-105%)',
            'recall_status'       => 'active',
            'issue_date'          => '2026-01-15',
            'affected_regions'    => 'National',
        ])->assertStatus(201)
          ->assertJsonPath('alert_reference', 'NAFDAC-RCL-001')
          ->assertJsonPath('severity_grade', 'Grade II');
    }

    public function test_status_filter_on_index(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'uuid-1', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();
        $this->postJson('/api/product-recalls', [
            'lot_id' => $batch->id, 'supplier_id' => $mfg->id,
            'alert_reference' => 'NAFDAC-RCL-001', 'recall_reason' => 'Substandard',
            'severity_grade' => 'Grade II', 'laboratory_findings' => 'API at 72%',
            'recall_status' => 'active', 'issue_date' => '2026-01-15',
        ]);

        $this->getJson('/api/product-recalls?status=active')->assertStatus(200)->assertJsonCount(1);
        $this->getJson('/api/product-recalls?status=completed')->assertStatus(200)->assertJsonCount(0);
    }

    public function test_recall_index_includes_batch_and_manufacturer_relations(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'uuid-1', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'lot_id' => $batch->id, 'supplier_id' => $mfg->id,
            'alert_reference' => 'ALERT-REL-001', 'recall_reason' => 'Substandard',
            'severity_grade' => 'Grade II', 'laboratory_findings' => 'API at 72%',
            'recall_status' => 'active', 'issue_date' => '2026-01-15',
        ]);

        $this->getJson('/api/product-recalls')
             ->assertStatus(200)
             ->assertJsonPath('0.batch.lot_number', 'LOT-4421')
             ->assertJsonPath('0.manufacturer.company_name', 'PharmaCo Ltd');
    }

    public function test_recall_show_includes_batch_and_manufacturer_relations(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'uuid-1', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $recall = $this->postJson('/api/product-recalls', [
            'lot_id' => $batch->id, 'supplier_id' => $mfg->id,
            'alert_reference' => 'ALERT-SHOW-001', 'recall_reason' => 'Substandard',
            'severity_grade' => 'Grade II', 'laboratory_findings' => 'API at 72%',
            'recall_status' => 'active', 'issue_date' => '2026-01-15',
        ])->json();

        $this->getJson("/api/product-recalls/{$recall['id']}")
             ->assertStatus(200)
             ->assertJsonPath('batch.id', $batch->id)
             ->assertJsonPath('manufacturer.id', $mfg->id)
             ->assertJsonPath('batch.lot_number', 'LOT-4421');
    }
}
