@foreach($orders as $o)
<tr class="@if($o->cancelled) table-danger @endif">
  <td>{{ $o->id }}
    @if($o->cancelled)
      <span class="badge bg-danger ms-1">Anulada</span>
    @endif
  </td>
  <td>{{ $o->created_at->format('Y-m-d H:i') }}</td>
  <td>
    @if($o->customer)
        {{ $o->customer->name }}
    @elseif($o->customer_name)
        {{ $o->customer_name }}
    @else
        Cliente general
    @endif
  </td>
  <td>{{ number_format($o->total,2) }}</td>
  <td>{{ $o->payment_method ?? 'N/A' }}</td>
  <td>{{ ucfirst($o->type) }}</td>
  <td>{{ $o->table ? $o->table->name : '' }}</td>
  <td>
    @if($o->cancelled)
      <span class="badge bg-danger">Anulada</span>
    @elseif($o->status == 'cerrado')
      <span class="badge bg-success">Cerrada</span>
    @else
      <span class="badge bg-warning">{{ ucfirst($o->status) }}</span>
    @endif
  </td>
  <td>{{ $o->paid ? 'SI' : 'NO' }}</td>
  <td>
    @if(!$o->cancelled)
      <a href="{{ route('reports.orders.show', $o->id) }}" class="btn btn-sm btn-info mb-1">
        <i class="fa-solid fa-eye"></i>
      </a>
      <a href="{{ route('orders.ticket', $o->id) }}" target="_blank" class="btn btn-sm btn-secondary mb-1">
        <i class="fa-solid fa-print"></i>
      </a>
      <button class="btn btn-sm btn-danger btn-cancel-order mb-1" data-id="{{ $o->id }}" title="Anular orden ">
        <i class="fa-solid fa-ban"></i>
      </button>
    @else
      <span class="text-muted small">
        {{ $o->cancelled_at->format('d/m/Y H:i') }}<br>
        {{ Str::limit($o->cancelled_reason, 30) }}
      </span>
      <button class="btn btn-sm btn-success btn-restore-order mt-1" data-id="{{ $o->id }}" title="Restaurar orden">
        <i class="fa-solid fa-rotate-left"></i>
      </button>
    @endif
  </td>
</tr>
@endforeach