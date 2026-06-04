<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductRecall;
use App\Services\OntologyPublisher;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $manufacturer = Manufacturer::firstOrCreate(
            ['registration_number' => 'MFG-441'],
            [
                'name'           => 'PharmaCo Ltd',
                'country'        => 'Kenya',
                'license_status' => 'active',
            ]
        );

        $product = Product::firstOrCreate(
            ['registration_number' => 'PRD-112'],
            [
                'manufacturer_id' => $manufacturer->id,
                'name'            => 'Amoxicillin 500mg',
                'generic_name'    => 'Amoxicillin',
                'dosage_form'     => 'Capsule',
                'strength'        => '500mg',
            ]
        );

        $batch = Batch::firstOrCreate(
            ['batch_number' => 'LOT-4421'],
            [
                'product_id'       => $product->id,
                'manufacturer_id'  => $manufacturer->id,
                'manufacture_date' => '2025-01-01',
                'expiry_date'      => '2027-01-01',
            ]
        );

        $recall = ProductRecall::firstOrCreate(
            ['recall_number' => 'RCL-2026-001'],
            [
                'batch_id'        => $batch->id,
                'manufacturer_id' => $manufacturer->id,
                'reason'          => 'Active pharmaceutical ingredient below specification. '
                    . 'API content measured at 72% of stated dose. '
                    . 'Specification requires 95–105%.',
                'classification'  => 'Class II',
                'qc_summary'      => 'Laboratory analysis of batch LOT-4421 confirmed API content at 72% '
                    . '(specification: 95–105%). Product poses potential health risk for patients '
                    . 'requiring therapeutic dosing. Batch withdrawn from all distribution channels.',
                'status'          => 'active',
                'date_issued'     => '2026-01-15',
                'scope'           => 'National',
            ]
        );

        // Load relations needed by OntologyPublisher
        $recall->load(['batch.product', 'manufacturer']);

        // Publish to shared ontology and ingest into pgvector
        // Requires the AI ontology service to be running on ONTOLOGY_SERVICE_URL
        try {
            (new OntologyPublisher())->publishRecallGraph($recall);
            $this->command->info('Rwanda FDA: recall graph published and ingested into ontology.');
        } catch (\Exception $e) {
            $this->command->warn('Rwanda FDA: ontology publish failed — is the AI service running?');
            $this->command->warn($e->getMessage());
        }

        $this->command->info('Rwanda FDA demo data seeded:');
        $this->command->info("  Manufacturer : {$manufacturer->name} (ID {$manufacturer->id})");
        $this->command->info("  Product      : {$product->name} (ID {$product->id})");
        $this->command->info("  Batch        : {$batch->batch_number} (ID {$batch->id})");
        $this->command->info("  Recall       : {$recall->recall_number} — {$recall->classification} / {$recall->status}");
    }
}
