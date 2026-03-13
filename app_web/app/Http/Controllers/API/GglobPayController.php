<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'verified_at' => ['nullable', 'date'],
        ]);

        $verifiedAt = $validated['verified_at'] ?? now()->toDateTimeString();

        $id = DB::table('gglob_pay_payments')->insertGetId([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'reference_code' => $validated['reference_code'],
            'sender_name' => $validated['sender_name'],
            'account_number' => $validated['account_number'],
            'amount' => $validated['amount'],
            'cashier' => $validated['cashier'],
            'bank' => $validated['bank'],
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
