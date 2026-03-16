<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GglobPayController extends Controller
{
    public function destinationAccounts(Request $request)
    {
        $companyId = $request->user()->company_id;

        $accounts = DB::table('gglob_pay_destination_accounts')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $accounts]);
    }

    public function storeDestinationAccount(Request $request)
    {
        $validated = $request->validate([
            'bank' => ['required', 'string', 'max:120'],
            'holder_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:80'],
            'account_type' => ['required', 'string', 'max:60'],
        ]);

        $id = DB::table('gglob_pay_destination_accounts')->insertGetId([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'bank' => $validated['bank'],
            'holder_name' => $validated['holder_name'],
            'account_number' => $validated['account_number'],
            'account_type' => $validated['account_type'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = DB::table('gglob_pay_destination_accounts')->where('id', $id)->first();

        return response()->json(['message' => 'Cuenta guardada', 'data' => $account], 201);
    }

    public function cashRegisters(Request $request)
    {
        $user = $request->user();
        $rows = DB::table('cash_registers as cr')
            ->join('cash_register_user as cru', 'cru.cash_register_id', '=', 'cr.id')
            ->where('cr.company_id', $user->company_id)
            ->where('cru.user_id', $user->id)
            ->where('cr.status', 'active')
            ->select('cr.id', 'cr.name', 'cr.code', 'cr.status', 'cru.is_primary')
            ->orderByDesc('cru.is_primary')
            ->orderBy('cr.name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function payments(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = DB::table('gglob_pay_payments')
            ->where('company_id', $companyId);

        if ($request->filled('from')) {
            $query->whereDate('verified_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('verified_at', '<=', $request->input('to'));
        }

        if ($request->filled('cashier') && $request->input('cashier') !== 'Todos') {
            $query->where('cashier', $request->input('cashier'));
        }

        $payments = $query->orderByDesc('verified_at')->get();

        return response()->json(['data' => $payments]);
    }

    public function storePayment(Request $request)
    {
        $validated = $request->validate([
            'reference_code' => ['required', 'string', 'max:80'],
            'sender_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cashier' => ['required', 'string', 'max:120'],
            'bank' => ['required', 'string', 'max:120'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'cashier_user_id' => ['required', 'integer', 'exists:users,id'],
            'destination_account_id' => ['nullable', 'integer', 'exists:gglob_pay_destination_accounts,id'],
            'source_channel' => ['nullable', 'string', 'max:40'],
            'verified_at' => ['nullable', 'date'],
        ]);

        $companyId = $request->user()->company_id;

        $validCashier = DB::table('users')
            ->where('id', $validated['cashier_user_id'])
            ->where('company_id', $companyId)
            ->exists();
        if (!$validCashier) {
            return response()->json(['message' => 'El cajero no pertenece a la empresa.'], 422);
        }

        $validRegister = DB::table('cash_registers')
            ->where('id', $validated['cash_register_id'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();
        if (!$validRegister) {
            return response()->json(['message' => 'La caja no pertenece a la empresa o no está activa.'], 422);
        }

        $assignmentExists = DB::table('cash_register_user')
            ->where('cash_register_id', $validated['cash_register_id'])
            ->where('user_id', $validated['cashier_user_id'])
            ->exists();
        if (!$assignmentExists) {
            return response()->json(['message' => 'La caja no está asignada al cajero seleccionado.'], 409);
        }

        if (!empty($validated['destination_account_id'])) {
            $validDestination = DB::table('gglob_pay_destination_accounts')
                ->where('id', $validated['destination_account_id'])
                ->where('company_id', $companyId)
                ->exists();
            if (!$validDestination) {
                return response()->json(['message' => 'La cuenta destino no pertenece a la empresa.'], 422);
            }
        }

        $verifiedAt = $validated['verified_at'] ?? now()->toDateTimeString();

        $id = DB::table('gglob_pay_payments')->insertGetId([
            'payment_intent_id' => Str::uuid()->toString(),
            'company_id' => $companyId,
            'user_id' => $request->user()->id,
            'cash_register_id' => $validated['cash_register_id'],
            'cashier_user_id' => $validated['cashier_user_id'],
            'reference_code' => $validated['reference_code'],
            'source_channel' => $validated['source_channel'] ?? 'ahorros',
            'destination_account_id' => $validated['destination_account_id'] ?? null,
            'sender_name' => $validated['sender_name'],
            'account_number' => $validated['account_number'],
            'amount' => $validated['amount'],
            'cashier' => $validated['cashier'],
            'bank' => $validated['bank'],
            'status' => 'VERIFIED',
            'verification_provider' => strtolower($validated['bank']) === 'wompi' ? 'wompi' : 'bancolombia',
            'verification_trace' => 'instant_bank_callback',
            'verified_at' => $verifiedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = DB::table('gglob_pay_payments')->where('id', $id)->first();

        return response()->json(['message' => 'Pago verificado guardado', 'data' => $payment], 201);
    }

    public function report(Request $request)
    {
        $paymentsRequest = $this->payments($request);
        $payload = $paymentsRequest->getData(true);
        $rows = $payload['data'] ?? [];

        $total = collect($rows)->sum(fn ($row) => (float) ($row['amount'] ?? 0));
        $count = count($rows);

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total' => $total,
                'count' => $count,
                'average' => $count > 0 ? $total / $count : 0,
            ],
        ]);
    }
}
