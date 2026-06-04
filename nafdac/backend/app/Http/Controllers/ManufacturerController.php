<?php
// app/Http/Controllers/ManufacturerController.php
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
            'company_name'         => 'required|string|max:255',
            'origin_country'       => 'required|string|max:100',
            'reg_no'               => 'required|string|max:100|unique:manufacturers',
            'authorization_status' => 'sometimes|string|in:authorized,suspended,revoked',
        ]);

        return response()->json(Manufacturer::create($data), 201);
    }

    public function show(Manufacturer $manufacturer): JsonResponse
    {
        return response()->json($manufacturer);
    }
}
