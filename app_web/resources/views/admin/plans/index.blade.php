@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <div class="row card-header flex-column flex-md-row border-bottom mx-0 px-3 mb-3">
        <div class="d-md-flex justify-content-between align-items-center col-md-auto me-auto mt-0">
            <h5 class="card-title mb-0">Planes</h5>
        </div>
        <div class="d-md-flex justify-content-between align-items-center col-md-auto ms-auto mt-0">
            <a class="btn btn-primary" href="{{ route('plans.create') }}">Nuevo plan</a>
        </div>
    </div>

    @if (session('success'))
        <div class="px-3"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif

    <div class="table-responsive text-nowrap px-3 pb-3">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Servicios incluidos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td class="small">
                            {{ $plan->gglob_cloud_enabled ? 'Nube ' : '' }}
                            {{ $plan->gglob_pay_enabled ? 'Pay ' : '' }}
                            {{ $plan->gglob_pos_enabled ? 'POS('.($plan->pos_mode === 'multi' ? 'Multi x'.$plan->pos_boxes : 'Mono').') ' : '' }}
                            {{ $plan->gglob_accounting_enabled ? 'Contable' : '' }}
                        </td>
                        <td class="d-flex gap-2">
                            <a href="{{ route('plans.edit', $plan) }}" class="btn btn-sm btn-secondary">Editar</a>
                            <form action="{{ route('plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('¿Eliminar plan?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center">No hay planes creados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-3 pb-3">{{ $plans->links() }}</div>
</div>
@endsection
