<x-guest-layout>
    <div class="container py-5">
        <div class="card mx-auto" style="max-width: 720px;">
            <div class="card-body">
                @php
                    $isApproved = $payment->status === 'approved';
                @endphp

                <h4 class="mb-3">Resultado del pago</h4>

                <div class="alert alert-{{ $isApproved ? 'success' : 'warning' }}">
                    {{ $isApproved ? '¡Pago aprobado correctamente!' : 'El pago no fue aprobado.' }}
                </div>

                <ul class="list-unstyled mb-4">
                    <li><strong>Referencia:</strong> {{ $payment->reference }}</li>
                    <li><strong>Estado:</strong> {{ strtoupper($payment->status) }}</li>
                    <li><strong>Monto:</strong> ${{ number_format((float) $payment->amount, 0, ',', '.') }}</li>
                    @if ($payment->wompi_transaction_id)
                        <li><strong>Transacción Wompi:</strong> {{ $payment->wompi_transaction_id }}</li>
                    @endif
                </ul>

                <a href="{{ route('credit-portal.index', ['document_number' => $payment->document_number]) }}" class="btn btn-primary">
                    Volver al portal de pagos
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
