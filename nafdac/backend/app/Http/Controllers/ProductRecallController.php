<?php
namespace App\Http\Controllers;

use App\Models\ProductRecall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRecallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductRecall::with(['batch.product', 'manufacturer']);
        if ($request->has('status')) {
            $query->where('recall_status', $request->input('status'));
        }
        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot_id'              => 'required|exists:batches,id',
            'supplier_id'         => 'required|exists:manufacturers,id',
            'alert_reference'     => 'required|string|max:100|unique:product_recalls',
            'recall_reason'       => 'required|string',
            'severity_grade'      => 'required|string|in:Grade I,Grade II,Grade III',
            'laboratory_findings' => 'required|string',
            'recall_status'       => 'sometimes|string|in:active,completed,closed',
            'issue_date'          => 'required|date',
            'affected_regions'    => 'sometimes|string|max:255',
        ]);

        $recall = ProductRecall::create($data);
        $recall->load(['batch.product', 'manufacturer']);

        (new \App\Services\OntologyPublisher())->publishRecallGraph($recall);

        return response()->json($recall, 201);
    }

    public function show(ProductRecall $productRecall): JsonResponse
    {
        $productRecall->load(['batch.product', 'manufacturer']);
        return response()->json($productRecall);
    }
}
