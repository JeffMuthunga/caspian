<?php
namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Manufacturer::all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                   => 'required|string|max:255',
            'country'                => 'required|string|max:100',
            'registration_number'    => 'required|string|max:100|unique:manufacturers',
            'license_status'         => 'sometimes|string|in:active,suspended,revoked',
            'internal_vendor_rating' => 'sometimes|integer|min:1|max:5',
            'contract_terms'         => 'sometimes|string',
        ]);

        return response()->json(Manufacturer::create($data), 201);
    }

    public function show(Manufacturer $manufacturer): JsonResponse
    {
        return response()->json($manufacturer);
    }
}
