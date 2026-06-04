<?php
// app/Services/OntologyPublisher.php
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

        $mfgObj    = $this->publish('Manufacturer', (string) $mfg->id, [
            'name'                => $mfg->name,
            'country'             => $mfg->country,
            'registration_number' => $mfg->registration_number,
            'license_status'      => $mfg->license_status,
        ]);
        $prodObj   = $this->publish('Product', (string) $product->id, [
            'name'                => $product->name,
            'generic_name'        => $product->generic_name,
            'dosage_form'         => $product->dosage_form,
            'strength'            => $product->strength,
            'registration_number' => $product->registration_number,
        ]);
        $batchObj  = $this->publish('Batch', (string) $batch->id, [
            'batch_number'     => $batch->batch_number,
            'manufacture_date' => $batch->manufacture_date,
            'expiry_date'      => $batch->expiry_date,
        ]);
        $recallObj = $this->publish('ProductRecall', (string) $recall->id, [
            'recall_number'  => $recall->recall_number,
            'reason'         => $recall->reason,
            'classification' => $recall->classification,
            'qc_summary'     => $recall->qc_summary,
            'status'         => $recall->status,
            'date_issued'    => $recall->date_issued,
            'scope'          => $recall->scope,
        ]);

        $this->link($mfgObj['id'],    $prodObj['id'],    'manufactures');
        $this->link($batchObj['id'],  $prodObj['id'],    'batch_of');
        $this->link($recallObj['id'], $batchObj['id'],   'affects');
        $this->link($recallObj['id'], $mfgObj['id'],     'issued_against');

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
