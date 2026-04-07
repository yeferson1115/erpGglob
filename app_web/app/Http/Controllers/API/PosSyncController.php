<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PosCashMovement;
use App\Models\PosSale;
use App\Models\PosShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PosSyncController extends Controller
{
    public function syncShift(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:open,close'],
            'cashier' => ['nullable', 'string', 'max:120'],
            'cash_register_name' => ['required', 'string', 'max:120'],
            'at' => ['required', 'date'],
            'opening_fund' => ['nullable', 'numeric'],
            'counted_cash' => ['nullable', 'numeric'],
            'total_sales' => ['nullable', 'numeric'],
            'difference' => ['nullable', 'numeric'],
            'biometric_method' => ['required', 'string', 'max:60'],
            'biometric_evidence' => ['required', 'string', 'max:250'],
            'biometric_photo_path' => ['required', 'string', 'max:255'],
        ]);

        $occurredAt = Carbon::parse($validated['at']);
        $syncHash = hash('sha256', implode('|', [
            $user->company_id,
            $user->id,
            $validated['event_type'],
            $validated['cash_register_name'],
            $occurredAt->toIso8601String(),
            $validated['biometric_photo_path'],
        ]));

        $shiftEvent = PosShift::query()->firstOrCreate(
            ['sync_hash' => $syncHash],
            [
                'company_id' => $user->company_id,
                'cashier_user_id' => $user->id,
                'cashier' => $validated['cashier'] ?? ($user->name ?? 'Cajero'),
                'cash_register_name' => $validated['cash_register_name'],
                'event_type' => $validated['event_type'],
                'occurred_at' => $occurredAt,
                'opening_fund' => $validated['opening_fund'] ?? 0,
                'counted_cash' => $validated['counted_cash'] ?? null,
                'total_sales' => $validated['total_sales'] ?? 0,
                'difference' => $validated['difference'] ?? 0,
                'biometric_method' => $validated['biometric_method'],
                'biometric_evidence' => $validated['biometric_evidence'],
                'biometric_photo_path' => $validated['biometric_photo_path'],
                'synced_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Turno sincronizado correctamente.',
            'data' => $shiftEvent,
        ]);
    }

    public function syncSale(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'ticket_code' => ['required', 'string', 'max:80'],
            'sold_at' => ['required', 'date'],
            'payment_type' => ['required', 'string', 'max:60'],
            'total' => ['required', 'numeric', 'min:0.01'],
        ]);

        $soldAt = Carbon::parse($validated['sold_at']);
        $syncHash = hash('sha256', implode('|', [
            $user->company_id,
            $user->id,
            $validated['ticket_code'],
            $soldAt->toIso8601String(),
            $validated['total'],
        ]));

        $sale = PosSale::query()->firstOrCreate(
            ['sync_hash' => $syncHash],
            [
                'company_id' => $user->company_id,
                'cashier_user_id' => $user->id,
                'ticket_code' => $validated['ticket_code'],
                'payment_type' => $validated['payment_type'],
                'total' => $validated['total'],
                'sold_at' => $soldAt,
                'synced_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Venta sincronizada correctamente.',
            'data' => $sale,
        ]);
    }

    public function syncCashMovement(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:80'],
            'detail' => ['required', 'string', 'max:250'],
            'amount' => ['required', 'numeric'],
            'cashier' => ['nullable', 'string', 'max:120'],
            'at' => ['required', 'date'],
        ]);

        $occurredAt = Carbon::parse($validated['at']);
        $syncHash = hash('sha256', implode('|', [
            $user->company_id,
            $user->id,
            $validated['type'],
            $validated['detail'],
            $occurredAt->toIso8601String(),
            $validated['amount'],
        ]));

        $movement = PosCashMovement::query()->firstOrCreate(
            ['sync_hash' => $syncHash],
            [
                'company_id' => $user->company_id,
                'cashier_user_id' => $user->id,
                'cashier' => $validated['cashier'] ?? ($user->name ?? 'Cajero'),
                'type' => $validated['type'],
                'detail' => $validated['detail'],
                'amount' => $validated['amount'],
                'occurred_at' => $occurredAt,
                'synced_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Movimiento de caja sincronizado correctamente.',
            'data' => $movement,
        ]);
    }
}
