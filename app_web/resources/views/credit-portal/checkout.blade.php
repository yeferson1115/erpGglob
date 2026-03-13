<x-public-layout>
    <div class="container py-5">
        <div class="card mx-auto" style="max-width: 720px;">
            <div class="card-body">
                <h4>Confirmar pago</h4>
                <p class="text-muted">Referencia: {{ $payment->reference }}</p>
                <p>Monto a pagar: <strong>${{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></p>

                @if (!$publicKey || !$signature)
                    <div class="alert alert-warning">Falta configurar llaves de Wompi en <code>config/services.php</code>.</div>
                @else
                    <form>
                        <script
                            src="https://checkout.wompi.co/widget.js"
                            data-render="button"
                            data-public-key="{{ $publicKey }}"
                            data-currency="{{ $payment->currency }}"
                            data-amount-in-cents="{{ $amountInCents }}"
                            data-reference="{{ $payment->reference }}"
                            data-signature:integrity="{{ $signature }}"
                            data-redirect-url="{{ route('credit-portal.finish', ['reference' => $payment->reference]) }}">
                        </script>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-public-layout>
