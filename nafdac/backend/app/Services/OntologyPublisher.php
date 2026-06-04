<?php
namespace App\Services;

use App\Models\ProductRecall;
use Illuminate\Support\Facades\Http;

class OntologyPublisher
{
    private string $baseUrl;
    private string $nmraId;

    public function __construct()
    {
        $this->baseUrl = config('services.ontology.url');
        $this->nmraId  = config('services.ontology.nmra_id');
    }

    public function publishRecallGraph(ProductRecall $recall): void
    {
        $batch   = $recall->batch;
        $product = $batch->product;
        $mfg     = $recall->manufacturer;

        // Map NAFDAC column names → canonical ontology field names
        $mfgObj = $this->publish('Manufacturer', (string) $mfg->id, [
            'name'                => $mfg->company_name,         // company_name → name
            'country'             => $mfg->origin_country,       // origin_country → country
            'registration_number' => $mfg->reg_no,               // reg_no → registration_number
            'license_status'      => $mfg->authorization_status, // authorization_status → license_status
        ]);
        $prodObj = $this->publish('Product', (string) $product->id, [
            'name'                => $product->product_name,       // product_name → name
            'generic_name'        => $product->inn_name,           // inn_name → generic_name
            'dosage_form'         => $product->formulation,        // formulation → dosage_form
            'strength'            => $product->potency,            // potency → strength
            'registration_number' => $product->market_auth_number, // market_auth_number → registration_number
        ]);
        $batchObj = $this->publish('Batch', (string) $batch->id, [
            'batch_number'    => $batch->lot_number,      // lot_number → batch_number
            'manufacture_date'=> $batch->production_date, // production_date → manufacture_date
            'expiry_date'     => $batch->expiry_date,
        ]);
        $recallObj = $this->publish('ProductRecall', (string) $recall->id, [
            'recall_number'  => $recall->alert_reference,     // alert_reference → recall_number
            'reason'         => $recall->recall_reason,        // recall_reason → reason
            'classification' => $recall->severity_grade,       // severity_grade → classification
            'qc_summary'     => $recall->laboratory_findings,  // laboratory_findings → qc_summary
            'status'         => $recall->recall_status,        // recall_status → status
            'date_issued'    => $recall->issue_date,           // issue_date → date_issued
            'scope'          => $recall->affected_regions,     // affected_regions → scope
        ]);

        $this->link($mfgObj['id'],    $prodObj['id'],   'manufactures');
        $this->link($batchObj['id'],  $prodObj['id'],   'batch_of');
        $this->link($recallObj['id'], $batchObj['id'],  'affects');
        $this->link($recallObj['id'], $mfgObj['id'],    'issued_against');

        $this->ingest([$mfgObj['id'], $prodObj['id'], $batchObj['id'], $recallObj['id']]);
    }

    private function publish(string $type, string $sourceId, array $properties): array
    {
        return Http::post("{$this->baseUrl}/ontology/publish", [
            'source_nmra' => $this->nmraId,
            'object_type' => $type,
            'source_id'   => $sourceId,
            'properties'  => $properties,
        ])->throw()->json();
    }

    private function link(string $fromId, string $toId, string $linkType): void
    {
        Http::post("{$this->baseUrl}/ontology/link", [
            'from_object_id'  => $fromId,
            'to_object_id'    => $toId,
            'link_type'       => $linkType,
            'created_by_nmra' => $this->nmraId,
        ])->throw();
    }

    private function ingest(array $objectIds): void
    {
        Http::post("{$this->baseUrl}/ai/ingest", ['object_ids' => $objectIds])->throw();
    }
}
