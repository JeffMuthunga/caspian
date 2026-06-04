<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse { return response()->json(Product::all()); }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'        => 'required|exists:manufacturers,id',
            'product_name'       => 'required|string|max:255',
            'inn_name'           => 'required|string|max:255',
            'formulation'        => 'required|string|max:100',
            'potency'            => 'required|string|max:100',
            'market_auth_number' => 'required|string|max:100|unique:products',
        ]);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product): JsonResponse { return response()->json($product); }
}
