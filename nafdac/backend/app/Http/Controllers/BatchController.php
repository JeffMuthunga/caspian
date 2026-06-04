<?php
namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(): JsonResponse { return response()->json(Batch::all()); }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'supplier_id'        => 'required|exists:manufacturers,id',
            'lot_number'         => 'required|string|max:100|unique:batches',
            'production_date'    => 'required|date',
            'expiry_date'        => 'required|date|after:production_date',
            'units_manufactured' => 'sometimes|integer|min:1',
        ]);

        return response()->json(Batch::create($data), 201);
    }

    public function show(Batch $batch): JsonResponse { return response()->json($batch); }
}
