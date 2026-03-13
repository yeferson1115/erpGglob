@props(['status'])

@if ($status)
    <div style="    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
    padding: 15px;" {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
