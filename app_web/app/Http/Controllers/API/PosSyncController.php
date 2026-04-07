<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PosCashMovement;
use App\Models\PosSale;
use App\Models\PosShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PosSyncController extends Controller
{
    public function syncShift(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:open,close'],
            'cashier' => ['nullable', 'string', 'max:120'],
            'cash_register_name' => ['required', 'string', 'max:120'],
            'sales_point_id' => ['required', 'integer', 'exists:sales_points,id'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'cash_register_shift_id' => ['nullable', 'integer', 'exists:cash_register_shifts,id'],
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
        $this->ensurePosContextAccess($user->id, $user->company_id, (int) $validated['sales_point_id'], (int) $validated['cash_register_id']);
        $this->ensureCashRegisterShiftConsistency(
            $validated['event_type'],
            (int) $validated['sales_point_id'],
            (int) $validated['cash_register_id']
        );
        $syncHash = hash('sha256', implode('|', [
            $user->company_id,
            $user->id,
            $validated['event_type'],
            $validated['sales_point_id'] ?? '',
            $validated['cash_register_id'] ?? '',
            $validated['cash_register_shift_id'] ?? '',
            $validated['cash_register_name'],
            $occurredAt->toIso8601String(),
            $validated['biometric_photo_path'],
        ]));

        $shiftEvent = PosShift::query()->firstOrCreate(
            ['sync_hash' => $syncHash],
            [
                'company_id' => $user->company_id,
                'cashier_user_id' => $user->id,
                'sales_point_id' => $validated['sales_point_id'] ?? null,
                'cash_register_id' => $validated['cash_register_id'] ?? null,
                'cash_register_shift_id' => $validated['cash_register_shift_id'] ?? null,
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
            'sales_point_id' => ['required', 'integer', 'exists:sales_points,id'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'cash_register_shift_id' => ['nullable', 'integer', 'exists:cash_register_shifts,id'],
        ]);

        $soldAt = Carbon::parse($validated['sold_at']);
        $this->ensurePosContextAccess($user->id, $user->company_id, (int) $validated['sales_point_id'], (int) $validated['cash_register_id']);
        $this->ensureCashRegisterHasActiveShiftForCashier(
            (int) $validated['sales_point_id'],
            (int) $validated['cash_register_id'],
            $user->id
        );
        $syncHash = hash('sha256', implode('|', [
            $user->company_id,
            $user->id,
            $validated['sales_point_id'] ?? '',
            $validated['cash_register_id'] ?? '',
            $validated['cash_register_shift_id'] ?? '',
            $validated['ticket_code'],
            $soldAt->toIso8601String(),
            $validated['total'],
        ]));

        $sale = PosSale::query()->firstOrCreate(
            ['sync_hash' => $syncHash],
            [
                'company_id' => $user->company_id,
                'cashier_user_id' => $user->id,
                'sales_point_id' => $validated['sales_point_id'] ?? null,
                'cash_register_id' => $validated['cash_register_id'] ?? null,
                'cash_register_shift_id' => $validated['cash_register_shift_id'] ?? null,
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
            'sales_point_id' => ['required', 'integer', 'exists:sales_points,id'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'cash_register_shift_id' => ['nullable', 'integer', 'exists:cash_register_shifts,id'],
        ]);

        $occurredAt = Carbon::parse($validated['at']);
        $this->ensurePosContextAccess($user->id, $user->company_id, (int) $validated['sales_point_id'], (int) $validated['cash_register_id']);
        $this->ensureCashRegisterHasActiveShiftForCashier(
            (int) $validated['sales_point_id'],
            (int) $validated['cash_register_id'],
            $user->id
        );
        $syncHash = hash('sha256', implode('|', [
            $user->company_id,
            $user->id,
            $validated['sales_point_id'] ?? '',
            $validated['cash_register_id'] ?? '',
            $validated['cash_register_shift_id'] ?? '',
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
                'sales_point_id' => $validated['sales_point_id'] ?? null,
                'cash_register_id' => $validated['cash_register_id'] ?? null,
                'cash_register_shift_id' => $validated['cash_register_shift_id'] ?? null,
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

    private function ensurePosContextAccess(int $userId, int $companyId, int $salesPointId, int $cashRegisterId): void
    {
        $register = DB::table('cash_registers')
            ->where('id', $cashRegisterId)
            ->where('company_id', $companyId)
            ->where('sales_point_id', $salesPointId)
            ->first();

        abort_unless($register !== null, 422, 'La caja no pertenece al punto de venta seleccionado.');

        $assignedToRegister = DB::table('cash_register_user')
            ->where('cash_register_id', $cashRegisterId)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($assignedToRegister, 403, 'El cajero no está asignado a esta caja.');

        $assignedToSalesPoint = DB::table('sales_point_user')
            ->where('company_id', $companyId)
            ->where('sales_point_id', $salesPointId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        abort_unless($assignedToSalesPoint || $assignedToRegister, 403, 'El cajero no está asignado al punto de venta.');
    }

    private function ensureCashRegisterShiftConsistency(string $eventType, int $salesPointId, int $cashRegisterId): void
    {
        $latestOpen = PosShift::query()
            ->where('event_type', 'open')
            ->where('sales_point_id', $salesPointId)
            ->where('cash_register_id', $cashRegisterId)
            ->orderByDesc('occurred_at')
            ->first();

        if ($eventType === 'open' && $latestOpen !== null) {
            $latestClose = PosShift::query()
                ->where('event_type', 'close')
                ->where('sales_point_id', $salesPointId)
                ->where('cash_register_id', $cashRegisterId)
                ->where('occurred_at', '>=', $latestOpen->occurred_at)
                ->exists();

            if (!$latestClose) {
                abort(422, 'La caja seleccionada ya tiene un turno activo.');
            }
        }
    }

    private function ensureCashRegisterHasActiveShiftForCashier(int $salesPointId, int $cashRegisterId, int $userId): void
    {
        $latestOpen = PosShift::query()
            ->where('event_type', 'open')
            ->where('sales_point_id', $salesPointId)
            ->where('cash_register_id', $cashRegisterId)
            ->orderByDesc('occurred_at')
            ->first();

        abort_unless($latestOpen !== null, 422, 'Debes abrir turno en la caja seleccionada para registrar ventas o movimientos.');

        $latestClose = PosShift::query()
            ->where('event_type', 'close')
            ->where('sales_point_id', $salesPointId)
            ->where('cash_register_id', $cashRegisterId)
            ->where('occurred_at', '>=', $latestOpen->occurred_at)
            ->exists();

        abort_if($latestClose, 422, 'Debes abrir turno en la caja seleccionada para registrar ventas o movimientos.');
        abort_if((int) $latestOpen->cashier_user_id !== $userId, 422, 'La caja seleccionada tiene un turno activo de otro cajero.');
    }
}
