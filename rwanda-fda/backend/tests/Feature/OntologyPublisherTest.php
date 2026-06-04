<?php
// tests/Feature/OntologyPublisherTest.php
namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductRecall;
use App\Services\OntologyPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OntologyPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_recall_graph_sends_four_publishes_and_four_links(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

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
        $recall = ProductRecall::create([
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-001', 'reason' => 'Substandard API content',
            'classification' => 'Class II', 'qc_summary' => 'API at 72%',
            'status' => 'active', 'date_issued' => '2026-01-15', 'scope' => 'National',
        ]);

        $publisher = new OntologyPublisher();
        $publisher->publishRecallGraph($recall->load(['batch.product', 'manufacturer']));

        // 4 publish calls + 4 link calls + 1 ingest call = 9 total
        Http::assertSentCount(9);
    }

    public function test_creating_recall_via_api_triggers_publisher(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

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

        $this->postJson('/api/product-recalls', [
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-001', 'reason' => 'Substandard API content',
            'classification' => 'Class II', 'qc_summary' => 'API at 72%',
            'status' => 'active', 'date_issued' => '2026-01-15', 'scope' => 'National',
        ])->assertStatus(201);

        Http::assertSentCount(9);
    }
}
