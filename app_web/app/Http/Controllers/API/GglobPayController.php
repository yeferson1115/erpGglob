<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GglobPayController extends Controller
{
    private function isOwner(Request $request): bool
    {
        return strtolower((string) $request->user()->business_role) === 'owner';
    }

    private function normalizePaymentStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'APPROVED', 'DECLINED', 'VOIDED', 'ERROR', 'PENDING' => strtoupper((string) $status),
            default => 'ERROR',
        };
    }

    public function wompiWebhook(Request $request)
    {
        $reference = (string) data_get($request->all(), 'data.transaction.reference', '');
        $transactionId = (string) data_get($request->all(), 'data.transaction.id', '');
        $status = strtoupper((string) data_get($request->all(), 'data.transaction.status', ''));

        if ($reference === '' || $transactionId === '') {
            return response()->json(['message' => 'Webhook recibido sin referencia/transacción.'], 202);
        }

        $updated = DB::table('gglob_pay_payments')
            ->where('reference_code', $reference)
            ->update([
                'status' => $this->normalizePaymentStatus($status === '' ? 'PENDING' : $status),
                'verification_provider' => 'wompi',
                'verification_trace' => 'webhook:' . $transactionId,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => $updated > 0 ? 'Webhook procesado.' : 'Webhook recibido, sin pago asociado.',
        ]);
    }

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
        $user = $request->user();
        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede registrar cuentas destino de Bancolombia.'], 403);
        }

        $validated = $request->validate([
            'bank' => ['required', 'string', 'in:Bancolombia'],
            'holder_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:80'],
            'account_type' => ['required', 'string', 'max:60'],
        ]);

        $id = DB::table('gglob_pay_destination_accounts')->insertGetId([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
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
        $isOwner = $this->isOwner($request);
        $scope = strtolower((string) $request->input('scope', 'assigned'));

        $rows = DB::table('cash_registers as cr')
            ->leftJoin('cash_register_user as cru', function ($join) use ($user) {
                $join->on('cru.cash_register_id', '=', 'cr.id')
                    ->where('cru.user_id', '=', $user->id);
            })
            ->where('cr.company_id', $user->company_id)
            ->when($scope !== 'all' || ! $isOwner, function ($query) {
                $query->whereNotNull('cru.id')->where('cr.status', 'active');
            })
            ->select('cr.id', 'cr.name', 'cr.code', 'cr.status', DB::raw('COALESCE(cru.is_primary, 0) as is_primary'))
            ->orderByDesc('is_primary')
            ->orderBy('cr.name')
            ->get();

        return response()->json(['data' => $rows]);
    }



    public function storeCashRegister(Request $request)
    {
        $user = $request->user();
        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede crear cajas.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $exists = DB::table('cash_registers')
            ->where('company_id', $user->company_id)
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ya existe una caja con ese código en la empresa.'], 409);
        }

        $id = DB::table('cash_registers')->insertGetId([
            'company_id' => $user->company_id,
            'name' => trim($validated['name']),
            'code' => $code,
            'status' => $validated['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cashRegister = DB::table('cash_registers')->where('id', $id)->first();

        return response()->json(['message' => 'Caja creada correctamente.', 'data' => $cashRegister], 201);
    }

    public function updateCashRegister(Request $request, int $cashRegister)
    {
        $user = $request->user();
        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede editar cajas.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'is_primary_for_current_user' => ['nullable', 'boolean'],
        ]);

        $register = DB::table('cash_registers')
            ->where('id', $cashRegister)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$register) {
            return response()->json(['message' => 'Caja no encontrada para la empresa.'], 404);
        }

        $code = strtoupper(trim($validated['code']));
        $exists = DB::table('cash_registers')
            ->where('company_id', $user->company_id)
            ->where('id', '!=', $cashRegister)
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ya existe otra caja con ese código en la empresa.'], 409);
        }

        DB::table('cash_registers')
            ->where('id', $cashRegister)
            ->update([
                'name' => trim($validated['name']),
                'code' => $code,
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

        if (!empty($validated['is_primary_for_current_user'])) {
            DB::table('cash_register_user')
                ->where('user_id', $user->id)
                ->update(['is_primary' => false, 'updated_at' => now()]);

            DB::table('cash_register_user')->updateOrInsert(
                ['cash_register_id' => $cashRegister, 'user_id' => $user->id],
                [
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                    'is_primary' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $updated = DB::table('cash_registers')->where('id', $cashRegister)->first();

        return response()->json(['message' => 'Caja actualizada correctamente.', 'data' => $updated]);
    }

    public function assignCashRegisterToCurrentUser(Request $request, int $cashRegister)
    {
        $user = $request->user();

        if ($this->isOwner($request)) {
            return response()->json(['message' => 'El dueño debe asignar cajas a cajeros desde gestión de cajas.'], 422);
        }

        $validated = $request->validate([
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $register = DB::table('cash_registers')
            ->where('id', $cashRegister)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$register) {
            return response()->json(['message' => 'Caja no encontrada para la empresa.'], 404);
        }

        if (($register->status ?? 'inactive') !== 'active') {
            return response()->json(['message' => 'Solo se pueden asignar cajas activas.'], 422);
        }

        if (!empty($validated['is_primary'])) {
            DB::table('cash_register_user')
                ->where('user_id', $user->id)
                ->update(['is_primary' => false, 'updated_at' => now()]);
        }

        DB::table('cash_register_user')->updateOrInsert(
            ['cash_register_id' => $cashRegister, 'user_id' => $user->id],
            [
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'is_primary' => !empty($validated['is_primary']),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'Caja asignada correctamente al usuario.']);
    }

    public function destroyCashRegister(Request $request, int $cashRegister)
    {
        $user = $request->user();
        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede eliminar cajas.'], 403);
        }

        $register = DB::table('cash_registers')
            ->where('id', $cashRegister)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$register) {
            return response()->json(['message' => 'Caja no encontrada para la empresa.'], 404);
        }

        DB::transaction(function () use ($cashRegister) {
            DB::table('cash_register_user')->where('cash_register_id', $cashRegister)->delete();
            DB::table('cash_registers')->where('id', $cashRegister)->delete();
        });

        return response()->json(['message' => 'Caja eliminada correctamente.']);
    }

    public function cashiers(Request $request)
    {
        $user = $request->user();
        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede gestionar cajas y cajeros.'], 403);
        }

        $rows = DB::table('users')
            ->where('company_id', $user->company_id)
            ->where('business_role', 'cashier')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function assignCashRegisterToCashier(Request $request, int $cashRegister)
    {
        $user = $request->user();
        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede asignar cajas a cajeros.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $register = DB::table('cash_registers')
            ->where('id', $cashRegister)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$register) {
            return response()->json(['message' => 'Caja no encontrada para la empresa.'], 404);
        }

        if (($register->status ?? 'inactive') !== 'active') {
            return response()->json(['message' => 'Solo se pueden asignar cajas activas.'], 422);
        }

        $cashier = DB::table('users')
            ->where('id', $validated['user_id'])
            ->where('company_id', $user->company_id)
            ->where('business_role', 'cashier')
            ->first();

        if (!$cashier) {
            return response()->json(['message' => 'Cajero no válido para la empresa.'], 404);
        }

        if (!empty($validated['is_primary'])) {
            DB::table('cash_register_user')
                ->where('user_id', $validated['user_id'])
                ->update(['is_primary' => false, 'updated_at' => now()]);
        }

        DB::table('cash_register_user')->updateOrInsert(
            ['cash_register_id' => $cashRegister, 'user_id' => $validated['user_id']],
            [
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'is_primary' => !empty($validated['is_primary']),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'Caja asignada al cajero correctamente.']);
    }

    public function providerSettings(Request $request, string $provider)
    {
        $provider = strtolower($provider);
        if (!in_array($provider, ['wompi', 'bancolombia'], true)) {
            return response()->json(['message' => 'Proveedor no soportado.'], 404);
        }

        if (!$this->isOwner($request)) {
            return response()->json(['message' => 'Solo el dueño puede ver configuración de proveedores.'], 403);
        }

        $setting = DB::table('gglob_pay_provider_settings')
            ->where('company_id', $request->user()->company_id)
            ->where('provider', $provider)
            ->first();

        if (!$setting) {
            return response()->json(['configured' => false, 'provider' => $provider]);
        }

        $raw = json_decode(Crypt::decryptString($setting->encrypted_config), true) ?? [];
        $public = [
            'provider' => $provider,
            'configured' => true,
            'updated_at' => $setting->updated_at,
        ];

        if ($provider === 'wompi') {
            $public['public_key'] = $raw['public_key'] ?? null;
            $public['private_key'] = $raw['private_key'] ?? null;
            $public['events_secret'] = $raw['events_secret'] ?? null;
            $public['events_secret_configured'] = !empty($raw['events_secret']);
            $public['private_key_configured'] = !empty($raw['private_key']);
        } else {
            $public['base_url'] = $raw['base_url'] ?? null;
            $public['client_id'] = $raw['client_id'] ?? null;
            $public['client_secret'] = $raw['client_secret'] ?? null;
            $public['client_secret_configured'] = !empty($raw['client_secret']);
        }

        return response()->json($public);
    }

    public function saveProviderSettings(Request $request, string $provider)
    {
        $provider = strtolower($provider);
        if (!in_array($provider, ['wompi', 'bancolombia'], true)) {
            return response()->json(['message' => 'Proveedor no soportado.'], 404);
        }

        $user = $request->user();
        if ($provider === 'wompi') {
            if (!$this->isOwner($request)) {
                return response()->json(['message' => 'Solo el dueño puede configurar llaves de Wompi.'], 403);
            }
            $validated = $request->validate([
                'public_key' => ['required', 'string', 'max:255'],
                'private_key' => ['required', 'string', 'max:255'],
                'events_secret' => ['required', 'string', 'max:255'],
            ]);
        } else {
            if (!$this->isOwner($request)) {
                return response()->json(['message' => 'Solo el dueño puede configurar Bancolombia.'], 403);
            }
            $validated = $request->validate([
                'base_url' => ['required', 'string', 'max:255'],
                'client_id' => ['required', 'string', 'max:255'],
                'client_secret' => ['required', 'string', 'max:255'],
            ]);
        }

        DB::table('gglob_pay_provider_settings')->updateOrInsert(
            ['company_id' => $user->company_id, 'provider' => $provider],
            [
                'updated_by' => $user->id,
                'encrypted_config' => Crypt::encryptString(json_encode($validated, JSON_UNESCAPED_UNICODE)),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'Configuración guardada correctamente.']);
    }

    public function createQrIntent(Request $request)
    {
        $validated = $request->validate([
            'source_channel' => ['required', 'string', 'in:bancolombia_ahorros,wompi_credit_card'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'destination_account_id' => ['nullable', 'integer', 'exists:gglob_pay_destination_accounts,id'],
        ]);

        $companyId = $request->user()->company_id;
        $sourceChannel = $validated['source_channel'];
        $referenceCode = 'GGPAY-' . now()->format('Ymd-His') . '-' . strtoupper(substr((string) Str::uuid(), 0, 4));

        $registerValid = DB::table('cash_register_user')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_register_user.cash_register_id')
            ->where('cash_register_user.cash_register_id', $validated['cash_register_id'])
            ->where('cash_register_user.user_id', $request->user()->id)
            ->where('cash_registers.company_id', $companyId)
            ->where('cash_registers.status', 'active')
            ->exists();
        if (!$registerValid) {
            return response()->json(['message' => 'La caja seleccionada no es válida para el usuario.'], 422);
        }

        if ($sourceChannel === 'wompi_credit_card') {
            $setting = $this->readProviderConfig($companyId, 'wompi');
            if (!$setting || empty($setting['public_key']) || empty($setting['private_key'])) {
                return response()->json(['message' => 'Wompi no está configurado por el dueño.'], 422);
            }
            $amountInCents = (int) round(((float) $validated['amount']) * 100);

            $checkoutUrl = "https://checkout.wompi.co/l/{$setting['public_key']}"
                . "?reference=" . urlencode($referenceCode)
                . "&amount-in-cents={$amountInCents}"
                . "&currency=COP";

            $payload = [
                'provider' => 'wompi',
                'reference' => $referenceCode,
                'amount_in_cents' => (int) round(((float) $validated['amount']) * 100),
                'currency' => 'COP',
                'public_key' => $setting['public_key'],
                'checkout_url' => $checkoutUrl,
            ];
        } else {
            $setting = $this->readProviderConfig($companyId, 'bancolombia');
            if (!$setting || empty($setting['base_url']) || empty($setting['client_id']) || empty($setting['client_secret'])) {
                return response()->json(['message' => 'Bancolombia no está configurado por el administrador.'], 422);
            }

            if (empty($validated['destination_account_id'])) {
                return response()->json(['message' => 'Debes seleccionar una cuenta destino de Bancolombia.'], 422);
            }

            $account = DB::table('gglob_pay_destination_accounts')
                ->where('id', $validated['destination_account_id'])
                ->where('company_id', $companyId)
                ->where('bank', 'Bancolombia')
                ->first();

            if (!$account) {
                return response()->json(['message' => 'La cuenta destino debe ser de Bancolombia y de la empresa.'], 422);
            }

            $payload = [
                'provider' => 'bancolombia',
                'reference' => $referenceCode,
                'amount' => round((float) $validated['amount'], 2),
                'currency' => 'COP',
                'destination_bank' => 'Bancolombia',
                'destination_account' => $account->account_number,
                'destination_type' => $account->account_type,
            ];
        }

        return response()->json([
            'reference_code' => $referenceCode,
            'source_channel' => $sourceChannel,
            'qr_payload' => $payload,
        ]);
    }

    private function readProviderConfig(int $companyId, string $provider): ?array
    {
        $setting = DB::table('gglob_pay_provider_settings')
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->first();

        if (!$setting) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($setting->encrypted_config), true) ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function verifyPendingWompiPayments(Request $request)
    {
        $companyId = $request->user()->company_id;
        $setting = $this->readProviderConfig($companyId, 'wompi');

        if (!$setting || empty($setting['private_key'])) {
            return response()->json(['message' => 'Wompi no está configurado por el dueño.'], 422);
        }

        $pendingPayments = DB::table('gglob_pay_payments')
            ->where('company_id', $companyId)
            ->where('source_channel', 'wompi_credit_card')
            ->where('status', 'PENDING')
            ->orderByDesc('verified_at')
            ->get();

        $updatedCount = 0;
        foreach ($pendingPayments as $payment) {
            $response = Http::withToken((string) $setting['private_key'])
                ->timeout(12)
                ->get('https://production.wompi.co/v1/transactions', [
                    'reference' => $payment->reference_code,
                ]);

            if (!$response->successful()) {
                continue;
            }

            $transaction = data_get($response->json(), 'data.0');
            if (!$transaction) {
                continue;
            }

            $newStatus = $this->normalizePaymentStatus((string) data_get($transaction, 'status', 'ERROR'));
            DB::table('gglob_pay_payments')
                ->where('id', $payment->id)
                ->update([
                    'status' => $newStatus,
                    'verification_provider' => 'wompi',
                    'verification_trace' => 'manual_sync:' . (string) data_get($transaction, 'id', $payment->reference_code),
                    'updated_at' => now(),
                ]);

            $updatedCount++;
        }

        return response()->json([
            'message' => 'Verificación manual completada.',
            'updated' => $updatedCount,
        ]);
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
            'status' => 'PENDING',
            'verification_provider' => strtolower($validated['bank']) === 'wompi' ? 'wompi' : 'bancolombia',
            'verification_trace' => 'created_from_desk',
            'verified_at' => $verifiedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = DB::table('gglob_pay_payments')->where('id', $id)->first();

        return response()->json([
            'message' => 'Pago verificado guardado',
            'data' => [
                'reference_code' => $payment->reference_code,
                'sender_name' => $payment->sender_name,
                'account_number' => $payment->account_number,
                'amount' => (float)$payment->amount,
                'cashier' => $payment->cashier,
                'bank' => $payment->bank,
                'verified_at' => $payment->verified_at,
                'cash_register_id' => $payment->cash_register_id,
                'cashier_user_id' => $payment->cashier_user_id,
                'destination_account_id' => $payment->destination_account_id,
                'source_channel' => $payment->source_channel
            ]
        ], 201);
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
