<?php

namespace App\Http\Controllers;

use App\Models\CreditApplication;
use App\Models\CreditPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicCreditPortalController extends Controller
{
    public function home(): View
    {
        return view('welcome-public');
    }

    public function index(Request $request): View
    {
        $documentNumber = preg_replace('/\D+/', '', (string) $request->query('document_number'));

        $applications = collect();
        $payments = collect();

        if ($documentNumber) {
            $applications = CreditApplication::query()
                ->where('status', 'approved')
                ->where('document_number', $documentNumber)
                ->withCount(['payments as approved_payments_count' => fn ($query) => $query->where('status', 'approved')])
                ->latest('updated_at')
                ->get();

            $payments = CreditPayment::query()
                ->where('document_number', $documentNumber)
                ->latest('created_at')
                ->get();
        }

        return view('credit-portal.index', [
            'documentNumber' => $documentNumber,
            'applications' => $applications,
            'payments' => $payments,
        ]);
    }

    public function startPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'credit_application_id' => ['required', 'integer', 'exists:credit_applications,id'],
            'document_number' => ['required', 'string', 'max:60'],
        ]);

        $application = CreditApplication::where('status', 'approved')->findOrFail($data['credit_application_id']);

        abort_unless($application->document_number === $data['document_number'], 403);

        $reference = 'BYB-' . strtoupper(Str::random(10));
        $amount = (float) ($application->installment_value ?? 0);

        $payment = CreditPayment::create([
            'credit_application_id' => $application->id,
            'document_number' => $application->document_number,
            'payer_name' => $application->full_name,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => 'COP',
            'status' => 'pending',
        ]);

        return redirect()->route('credit-portal.checkout', $payment);
    }

    public function checkout(CreditPayment $payment): View
    {
        $publicKey = (string) config('services.wompi.public_key');
        $integrityKey = (string) config('services.wompi.integrity_key');
        $amountInCents = (int) round(((float) $payment->amount) * 100);
        $signature = hash('sha256', $payment->reference . $amountInCents . $payment->currency . $integrityKey);

        return view('credit-portal.checkout', compact('payment', 'publicKey', 'amountInCents', 'signature'));
    }

    public function finishPayment(Request $request): Response
    {
        $data = $request->validate([
            'id' => ['nullable', 'string'],
            'reference' => ['required', 'string'],
        ]);

        $payment = CreditPayment::where('reference', $data['reference'])->firstOrFail();

        if (! empty($data['id'])) {
            $this->syncPaymentStatus($payment, $data['id']);
        }

        return response()
            ->view('credit-portal.payment-result', [
                'payment' => $payment->fresh(),
            ])
            ->setStatusCode(200);
    }

    public function refreshPayment(Request $request, CreditPayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:60'],
        ]);

        abort_unless($payment->document_number === $data['document_number'], 403);

        $updated = false;

        if ($payment->wompi_transaction_id) {
            $updated = $this->syncPaymentStatus($payment, $payment->wompi_transaction_id);
        } else {
            $updated = $this->syncPaymentStatusByReference($payment);
        }

        return redirect()->route('credit-portal.index', ['document_number' => $payment->document_number])
            ->with('status', $updated
                ? 'Estado de la transacción actualizado correctamente.'
                : 'Aún no se pudo confirmar la transacción en Wompi. Intenta nuevamente en unos minutos.');
    }

    public function wompiWebhook(Request $request): JsonResponse
    {
        $transaction = $request->input('data.transaction');

        if (! is_array($transaction)) {
            return response()->json(['message' => 'Payload inválido.'], 422);
        }

        $reference = (string) ($transaction['reference'] ?? '');

        if (! $reference) {
            return response()->json(['message' => 'Referencia no encontrada.'], 422);
        }

        $payment = CreditPayment::where('reference', $reference)->first();

        if (! $payment) {
            return response()->json(['message' => 'Pago no encontrado.'], 404);
        }

        $this->persistTransaction($payment, $transaction);

        return response()->json(['message' => 'OK']);
    }

    protected function syncPaymentStatus(CreditPayment $payment, string $transactionId): bool
    {
        if (! config('services.wompi.private_key')) {
            return false;
        }

        $response = Http::withToken((string) config('services.wompi.private_key'))
            ->get('https://production.wompi.co/v1/transactions/' . $transactionId);

        if (! $response->successful()) {
            return false;
        }

        $transaction = $response->json('data');

        if (! is_array($transaction)) {
            return false;
        }

        $this->persistTransaction($payment, $transaction);

        return true;
    }

    protected function syncPaymentStatusByReference(CreditPayment $payment): bool
    {
        if (! config('services.wompi.private_key')) {
            return false;
        }

        $response = Http::withToken((string) config('services.wompi.private_key'))
            ->get('https://production.wompi.co/v1/transactions', [
                'reference' => $payment->reference,
            ]);

        if (! $response->successful()) {
            return false;
        }

        $transactions = $response->json('data');

        if (! is_array($transactions) || empty($transactions)) {
            return false;
        }

        $transaction = collect($transactions)->firstWhere('reference', $payment->reference);

        if (! is_array($transaction)) {
            return false;
        }

        $this->persistTransaction($payment, $transaction);

        return true;
    }

    protected function persistTransaction(CreditPayment $payment, array $transaction): void
    {
        $status = strtolower((string) ($transaction['status'] ?? 'pending'));

        $payment->update([
            'wompi_transaction_id' => $transaction['id'] ?? $payment->wompi_transaction_id,
            'status' => $status,
            'wompi_response' => $transaction,
            'paid_at' => $status === 'approved' ? ($payment->paid_at ?? now()) : null,
        ]);
    }
}
