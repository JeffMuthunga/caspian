<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiProxyController extends Controller
{
    public function query(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:1000']);

        $response = Http::post(
            config('services.ontology.url') . '/ai/query',
            [
                'question' => $data['question'],
                'nmra_id'  => config('services.ontology.nmra_id'),
            ]
        );

        return response()->json($response->json(), $response->status());
    }
}
