<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura POS</title>

<style>
    * { font-family: Arial; }
    body { width: 260px; margin: 0 auto; }
    .center { text-align: center; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border: 1px solid #000; padding: 3px; }
    .no-border { border: none; }
</style>
</head>

<body>

<div class="center">
    <h3><img style="height: 70px;" src="{{ asset('imagenes/logo.jpg') }}"/> </h3>
    <div>Carrera 4 #2-17, La Plata, Huila</div>
    <div>NIT: 1081409072-3</div>
    <div>Teléfono: 321 982 8455</div>
</div>

<br>

<div>
    <strong>Id orden:</strong> {{ $order->id }} <br>

    @if($order->type === 'mesa')
        <strong>Mesa:</strong> {{ $order->table?->name }} <br>

    @elseif($order->type === 'llevar')
        <strong>Tipo de orden:</strong> Para llevar <br>        

    @elseif($order->type === 'domicilio')
        <strong>Tipo de orden:</strong> Entrega a domicilio <br>
        <strong>Nombre quien recibe:</strong> {{ $order->customer_name }} <br>
        <strong>Teléfono:</strong> {{ $order->customer_phone }} <br>
        <strong>Dirección:</strong> {{ $order->customer_address }} <br>
    @endif
    @if($order->customer)
        <strong>Cliente:</strong> {{ $order->customer->name }} <br>
    @else
            <strong>Cliente:</strong> General<br>
    @endif

    <strong>Fecha y hora:</strong> {{ $order->updated_at->format('d/m/Y - h:i A') }}
</div>

<br>

<table>
    <thead>
        <tr>
            <th>Cantidad</th>
            <th>Descripción</th>
            <th>Precio</th>
        </tr>
    </thead>

    <tbody>
        @foreach($order->items as $item)
        <tr>
            <td>{{ $item->quantity }}</td>
            <td>
                {{ $item->product->name }}

                @if($item->note)
                    <br><small>NOTA: {{ $item->note }}</small>
                @endif

                @if($item->addons->count())
                    @foreach($item->addons as $ad)
                        <br><small>+ {{ $ad->addon->name }} ({{ number_format($ad->addon->price, 0) }})</small>
                    @endforeach
                @endif
            </td>

            <td>
                ${{ number_format($item->price * $item->quantity, 0) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<br>

<table class="no-border">
    <tr>
        <td><strong>NOTA GENERAL</strong></td>
    </tr>
    <tr>
        <td>
            {{ $order->note ?: 'Sin anotaciones' }}
        </td>
    </tr>

    @if($order->type === 'domicilio')
        <tr>
            <td><strong>VALOR DOMICILIO:</strong> ${{ number_format($order->shipping_cost ?? 0, 0) }}</td>
        </tr>
    @endif

    <tr>
        <td><strong style="font-size: 15px;">TOTAL: ${{ number_format($order->total + ($order->shipping_cost ?? 0),0) }}</strong></td>
    </tr>
</table>

<br>
<div class="center">
    <strong>¡MUCHAS GRACIAS POR VISITARNOS!</strong>
</div>

<br>

<div class="center">
    <small>Copyright - Design Zerox Devs</small>
</div>

</body>
</html>
