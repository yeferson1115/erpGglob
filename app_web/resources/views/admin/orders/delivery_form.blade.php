@extends('layouts.app')
@section('content')
<div class="container">
  <h4>Crear pedido a domicilio</h4>
  <form method="POST" action="{{ route('orders.delivery.create') }}">
    @csrf
    <div class="mb-2"><label>Nombre</label><input name="customer_name" class="form-control" required></div>
    <div class="mb-2"><label>Teléfono</label><input name="customer_phone" class="form-control" required></div>
    <div class="mb-2"><label>Dirección</label><input name="customer_address" class="form-control" required></div>
    <button class="btn btn-primary">Crear pedido</button>
  </form>
</div>
@endsection
