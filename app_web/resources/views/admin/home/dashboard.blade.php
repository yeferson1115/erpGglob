@extends('layouts.app')

@section('content')
<div class="container">

    <h3 class="mb-4"></h3>

    <div class="row g-4 mt-5">
@can('Administracion')
        <!-- VENTAS DEL DÍA -->
        <div class="col-md-6">
            <div class="card shadow-lg border-0 dashboard-card text-center p-4">
                <div class="dashboard-icon mb-3">
                    <i class="fas fa-coins fa-3x text-primary"></i>
                </div>
                <h5 class="fw-bold text-secondary mb-1">Pagos del Día</h5>
                <h2 class="fw-bold text-dark mb-0">
                    ${{ number_format($salesToday, 2) }}
                </h2>
                <p class="mt-2 text-muted">Pedidos: <strong>{{ $ordersToday }}</strong></p>
            </div>
        </div>


        <!-- VENTAS DEL MES -->
        <div class="col-md-6">
            <div class="card shadow-lg border-0 dashboard-card text-center p-4">
                <div class="dashboard-icon mb-3">
                    <i class="fas fa-chart-line fa-3x text-success"></i>
                </div>
                <h5 class="fw-bold text-secondary mb-1">Solicitudes del del Día</h5>
                <h2 class="fw-bold text-dark mb-0">
                    ${{ number_format($salesMonth, 2) }}
                </h2>
                <p class="mt-2 text-muted">Pedidos: <strong>{{ $ordersMonth }}</strong></p>
            </div>
        </div>
        @endcan

    </div>

</div>

<style>
    .dashboard-card {
        min-height: 220px;
        border-radius: 18px;
        transition: 0.25s ease;
        padding-top: 35px !important;
        padding-bottom: 35px !important;
    }
    .dashboard-card:hover {
        transform: translateY(-4px);
        box-shadow: 0px 8px 25px rgba(0,0,0,0.15);
    }
    .dashboard-icon i {
        padding: 18px;
        border-radius: 50%;
        background: #f2f4f7;
    }
</style>
@endsection
