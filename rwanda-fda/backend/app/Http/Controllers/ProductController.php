<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Product::all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'manufacturer_id'     => 'required|exists:manufacturers,id',
            'name'                => 'required|string|max:255',
            'generic_name'        => 'required|string|max:255',
            'dosage_form'         => 'required|string|max:100',
            'strength'            => 'required|string|max:100',
            'registration_number' => 'required|string|max:100|unique:products',
            'internal_cost'       => 'sometimes|numeric|min:0',
        ]);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product);
    }
}
