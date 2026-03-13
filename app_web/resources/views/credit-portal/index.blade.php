<x-public-layout>
    <div class="container py-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <h4 class="mb-3">Consultar y pagar crédito</h4>
                <form method="GET" action="{{ route('credit-portal.index') }}" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cédula</label>
                        <input class="form-control" name="document_number" value="{{ $documentNumber }}" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-soft-brand w-100 fw-semibold">Consultar</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($documentNumber)
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Créditos aprobados</h5></div>
                <div class="card-body">
                    @forelse ($applications as $application)
                        @php
                            $paidInstallments = (int) ($application->approved_payments_count ?? 0);
                            $pendingInstallments = max(((int) $application->installments_count) - $paidInstallments, 0);
                        @endphp
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <strong>Crédito #{{ $application->id }}</strong><br>
                                    Cliente: {{ $application->full_name }}
                                </div>
                                <div class="text-end">
                                    Cuota: <strong>${{ number_format((float) $application->installment_value, 0, ',', '.') }}</strong><br>
                                    Pendientes: <strong>{{ $pendingInstallments }}</strong>
                                </div>
                            </div>
                            @if ($pendingInstallments > 0)
                                <form method="POST" action="{{ route('credit-portal.pay') }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="credit_application_id" value="{{ $application->id }}">
                                    <input type="hidden" name="document_number" value="{{ $documentNumber }}">
                                    <button class="btn btn-success">Pagar próxima cuota con Wompi</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No encontramos créditos aprobados para esta cédula.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Historial de pagos</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Referencia</th>
                                <th>Crédito</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td>{{ $payment->reference }}</td>
                                    <td>#{{ $payment->credit_application_id }}</td>
                                    <td>${{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                                    <td><span class="badge bg-{{ $payment->status === 'approved' ? 'success' : 'secondary' }}">{{ $payment->status }}</span></td>
                                    <td>{{ optional($payment->paid_at ?? $payment->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if ($payment->status !== 'approved')
                                            <form method="POST" action="{{ route('credit-portal.refresh', $payment) }}">
                                                @csrf
                                                <input type="hidden" name="document_number" value="{{ $documentNumber }}">
                                                <button class="btn btn-sm btn-outline-primary">Consultar transacción</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">Sin pagos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-public-layout>
