<div class="row">
@foreach($products as $p)
    <div class="col-6 mb-2">
        <div class="card p-2">
            <div class="d-flex justify-content-between align-items-center">

                @if($p->image)
                <img src="{{ asset($p->image) }}" 
                     style="height:50px;width:50px;object-fit:cover;margin-right:10px" class="rounded">
                @endif

                <div class="flex-grow-1">
                    <strong>{{ $p->name }}</strong><br>
                    <p>{{ $p->note }}</p>
                    <small>{{ number_format($p->price,2) }}</small>
                </div>

                <div>
                    <button class="btn btn-sm btn-primary add-product"
                        data-id="{{ $p->id }}"
                        data-price="{{ $p->price }}"
                        data-name="{{ $p->name }}">
                        Agregar
                    </button>
                </div>

            </div>
        </div>
    </div>
@endforeach
</div>
