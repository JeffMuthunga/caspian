<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $manufacturer = Manufacturer::firstOrCreate(
            ['reg_no' => 'REG-PH-441'],
            [
                'company_name'         => 'PharmaCo Ltd',
                'origin_country'       => 'Kenya',
                'authorization_status' => 'approved',
            ]
        );

        $product = Product::firstOrCreate(
            ['market_auth_number' => 'NAFD-2024-AMX-001'],
            [
                'supplier_id'  => $manufacturer->id,
                'product_name' => 'Amoxicillin 500mg Capsules',
                'inn_name'     => 'Amoxicillin',
                'formulation'  => 'Capsule',
                'potency'      => '500mg',
            ]
        );

        $batch = Batch::firstOrCreate(
            ['lot_number' => 'LOT-4421'],
            [
                'product_id'      => $product->id,
                'supplier_id'     => $manufacturer->id,
                'production_date' => '2025-01-01',
                'expiry_date'     => '2027-01-01',
            ]
        );

        // Publish NAFDAC's manufacturer, product, and batch to the shared ontology
        // so the ontology has a complete cross-org picture of this supply chain.
        // Requires the AI ontology service to be running on ONTOLOGY_SERVICE_URL.
        try {
            $baseUrl = config('services.ontology.url');
            $nmraId  = config('services.ontology.nmra_id'); // NAFDAC

            $mfgObj = Http::post("{$baseUrl}/ontology/publish", [
                'source_nmra' => $nmraId,
                'object_type' => 'Manufacturer',
                'source_id'   => (string) $manufacturer->id,
                'properties'  => [
                    'name'                => $manufacturer->company_name,
                    'country'             => $manufacturer->origin_country,
                    'registration_number' => $manufacturer->reg_no,
                    'license_status'      => $manufacturer->authorization_status,
                ],
            ])->throw()->json();

            $prodObj = Http::post("{$baseUrl}/ontology/publish", [
                'source_nmra' => $nmraId,
                'object_type' => 'Product',
                'source_id'   => (string) $product->id,
                'properties'  => [
                    'name'                => $product->product_name,
                    'generic_name'        => $product->inn_name,
                    'dosage_form'         => $product->formulation,
                    'strength'            => $product->potency,
                    'registration_number' => $product->market_auth_number,
                ],
            ])->throw()->json();

            $batchObj = Http::post("{$baseUrl}/ontology/publish", [
                'source_nmra' => $nmraId,
                'object_type' => 'Batch',
                'source_id'   => (string) $batch->id,
                'properties'  => [
                    'batch_number'     => $batch->lot_number,
                    'manufacture_date' => $batch->production_date,
                    'expiry_date'      => $batch->expiry_date,
                ],
            ])->throw()->json();

            // Link manufacturer → product and batch → product
            Http::post("{$baseUrl}/ontology/link", [
                'from_object_id'  => $mfgObj['id'],
                'to_object_id'    => $prodObj['id'],
                'link_type'       => 'manufactures',
                'created_by_nmra' => $nmraId,
            ])->throw();

            Http::post("{$baseUrl}/ontology/link", [
                'from_object_id'  => $batchObj['id'],
                'to_object_id'    => $prodObj['id'],
                'link_type'       => 'batch_of',
                'created_by_nmra' => $nmraId,
            ])->throw();

            // Ingest NAFDAC's objects into pgvector
            Http::post("{$baseUrl}/ai/ingest", [
                'object_ids' => [$mfgObj['id'], $prodObj['id'], $batchObj['id']],
            ])->throw();

            $this->command->info('NAFDAC: manufacturer, product, batch published and ingested into ontology.');
        } catch (\Exception $e) {
            $this->command->warn('NAFDAC: ontology publish failed — is the AI service running?');
            $this->command->warn($e->getMessage());
        }

        $this->command->info('NAFDAC demo data seeded:');
        $this->command->info("  Manufacturer : {$manufacturer->company_name} (ID {$manufacturer->id})");
        $this->command->info("  Product      : {$product->product_name} (ID {$product->id})");
        $this->command->info("  Batch        : {$batch->lot_number} (ID {$batch->id}) — pending import review");
    }
}
