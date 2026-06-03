<?php

namespace App\Http\Controllers;

use App\Models\ProductRecall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRecallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductRecall::query();
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_id'                     => 'required|exists:batches,id',
            'manufacturer_id'              => 'required|exists:manufacturers,id',
            'recall_number'                => 'required|string|max:100|unique:product_recalls',
            'reason'                       => 'required|string',
            'classification'               => 'required|string|in:Class I,Class II,Class III',
            'qc_summary'                   => 'required|string',
            'status'                       => 'sometimes|string|in:active,completed,closed',
            'date_issued'                  => 'required|date',
            'scope'                        => 'sometimes|string|max:100',
            'internal_investigation_notes' => 'sometimes|string',
            'inspector_id'                 => 'sometimes|integer',
        ]);

        return response()->json(ProductRecall::create($data), 201);
    }

    public function show(ProductRecall $productRecall): JsonResponse
    {
        return response()->json($productRecall);
    }
}
