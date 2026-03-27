<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PosBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosBlueprintController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $companyId = $request->user()?->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'El usuario no tiene compañía asociada.'], 422);
        }

        $blueprint = PosBlueprint::where('company_id', $companyId)->first();
        if (!$blueprint) {
            return response()->json(['message' => 'Sin blueprint POS guardado.'], 404);
        }

        return response()->json([
            'message' => 'Blueprint POS cargado.',
            'data' => [
                'analysis_text' => $blueprint->analysis_text,
                'payload' => json_encode($blueprint->payload ?? []),
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $companyId = $request->user()?->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'El usuario no tiene compañía asociada.'], 422);
        }

        $validated = $request->validate([
            'analysis_text' => ['nullable', 'string'],
            'payload' => ['nullable', 'string'],
        ]);

        $decodedPayload = [];
        if (!empty($validated['payload'])) {
            $decodedPayload = json_decode($validated['payload'], true);
            if (!is_array($decodedPayload)) {
                return response()->json(['message' => 'payload debe ser JSON serializado válido.'], 422);
            }
        }

        $blueprint = PosBlueprint::updateOrCreate(
            ['company_id' => $companyId],
            [
                'analysis_text' => $validated['analysis_text'] ?? null,
                'payload' => $decodedPayload,
            ]
        );

        return response()->json([
            'message' => 'Blueprint POS guardado correctamente.',
            'data' => [
                'id' => $blueprint->id,
            ],
        ]);
    }
}
