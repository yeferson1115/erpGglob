@extends('layouts.app')

@section('content')
<div class="container-xxl container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Pagos de créditos</h4>
        <a href="{{ route('admin.credit-payments.export', request()->query()) }}" class="btn btn-outline-success">Descargar reporte CSV</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3"><label class="form-label">Crédito</label><input class="form-control" name="credit_application_id" value="{{ request('credit_application_id') }}"></div>
                <div class="col-md-3"><label class="form-label">Cédula</label><input class="form-control" name="document_number" value="{{ request('document_number') }}"></div>
                <div class="col-md-2"><label class="form-label">Desde</label><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div>
                <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filtrar</button></div>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Crédito</th><th>Cédula</th><th>Referencia</th><th>Monto</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>#{{ $payment->credit_application_id }}</td>
                            <td>{{ $payment->document_number }}</td>
                            <td>{{ $payment->reference }}</td>
                            <td>${{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge bg-label-secondary text-uppercase">{{ $payment->status }}</span>
                            </td>
                            <td>{{ optional($payment->paid_at ?? $payment->created_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.credit-payments.update-status', ['payment' => $payment->id] + request()->query()) }}" class="d-flex gap-2 align-items-center">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm" aria-label="Estado pago {{ $payment->id }}">
                                        @foreach (['pending' => 'Pendiente', 'approved' => 'Aprobado', 'declined' => 'Declinado', 'voided' => 'Anulado', 'error' => 'Error'] as $statusKey => $statusLabel)
                                            <option value="{{ $statusKey }}" @selected($payment->status === $statusKey)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">No hay pagos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $payments->links() }}
        </div>
    </div>
</div>
@endsection
