@extends('layouts.app')

@section('content')
    @php
        $statusColor = [
            'draft' => 'secondary',
            'submitted' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ][$application->status] ?? 'primary';
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Solicitud #{{ $application->id }}</h4>
                <small class="text-muted">Token: {{ $application->public_token }}</small>
            </div>
            <a href="{{ route('admin.credit-applications.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Estado de la solicitud</h5>
                <span class="badge bg-label-{{ $statusColor }}">{{ $statuses[$application->status] ?? ucfirst($application->status) }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.credit-applications.update-status', $application) }}" class="row g-3 align-items-end">
                    @csrf
                    @method('PATCH')
                    <div class="col-md-4">
                        <label class="form-label">Cambiar estado</label>
                        <select name="status" class="form-select" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($application->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary" type="submit">Actualizar estado</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Datos del solicitante</h5></div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><strong>Nombre:</strong> {{ $application->full_name ?: '—' }}</li>
                            <li><strong>Documento:</strong> {{ $application->document_type ?: '—' }} {{ $application->document_number ?: '' }}</li>
                            <li><strong>Celular principal:</strong> {{ $application->phone_primary ?: '—' }}</li>
                            <li><strong>Celular secundario:</strong> {{ $application->phone_secondary ?: '—' }}</li>
                            <li><strong>Correo:</strong> {{ $application->email ?: '—' }}</li>
                            <li><strong>Dirección:</strong> {{ $application->residential_address ?: '—' }}</li>
                            <li><strong>Ciudad:</strong> {{ $application->city ?: '—' }}</li>
                            <li><strong>Empresa:</strong> {{ $application->company?->name ?: ($application->employer_name ?: '—') }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Datos financieros</h5></div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><strong>Ingreso mensual:</strong> {{ $application->monthly_income ? number_format($application->monthly_income, 0, ',', '.') : '—' }}</li>
                            <li><strong>Productos:</strong> {{ $application->requested_products ?: '—' }}</li>
                            <li><strong>Valor cuota:</strong> {{ $application->installment_value ? number_format($application->installment_value, 0, ',', '.') : '—' }}</li>
                            <li><strong>Número de cuotas:</strong> {{ $application->installments_count ?: '—' }}</li>
                            <li><strong>Frecuencia:</strong> {{ $application->payment_frequency ?: '—' }}</li>
                            <li><strong>Enviada:</strong> {{ optional($application->submitted_at)->format('d/m/Y H:i') ?: 'No enviada' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><h5 class="mb-0">Documentos y firma</h5></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <h6>Cédula frente</h6>
                        @if ($application->id_front_path)
                            <a href="{{ asset($application->id_front_path) }}" target="_blank" class="btn btn-outline-primary btn-sm">Ver documento</a>
                        @else
                            <p class="text-muted mb-0">No adjuntado.</p>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <h6>Cédula reverso</h6>
                        @if ($application->id_back_path)
                            <a href="{{ asset($application->id_back_path) }}" target="_blank" class="btn btn-outline-primary btn-sm">Ver documento</a>
                        @else
                            <p class="text-muted mb-0">No adjuntado.</p>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <h6>Selfie con cédula</h6>
                        @if ($application->selfie_with_id_path)
                            <a href="{{ asset($application->selfie_with_id_path) }}" target="_blank" class="btn btn-outline-primary btn-sm">Ver imagen</a>
                        @else
                            <p class="text-muted mb-0">No adjuntado.</p>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <h6>Firma</h6>
                        @if ($application->signature_path)
                            <a href="{{ asset($application->signature_path) }}" target="_blank" class="btn btn-outline-primary btn-sm">Ver firma</a>
                        @else
                            <p class="text-muted mb-0">No adjuntada.</p>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <h6>PDF</h6>
                        @if ($application->pdf_path)
                            <a href="{{ route('credit-applications.pdf', $application) }}" target="_blank" class="btn btn-outline-success btn-sm">Descargar PDF</a>
                        @else
                            <p class="text-muted mb-0">No generado.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
