<?php


namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesExport implements FromCollection, WithHeadings
{
    protected $start;
    protected $end;
    protected $search;

    public function __construct($start, $end, $search)
    {
        $this->start  = $start;
        $this->end    = $end;
        $this->search = $search;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Cliente',
            'Tipo',
            'Mesa',
            'Total',
            'Pagado'
        ];
    }

    public function collection()
    {
        $q = Order::query();

        // FILTRO POR FECHAS
        if ($this->start) {
            $q->whereDate('created_at', '>=', $this->start);
        }
        if ($this->end) {
            $q->whereDate('created_at', '<=', $this->end);
        }

        // FILTRO SEARCH
        if ($this->search) {
            $q->where(function ($qq) {
                $qq->where('id', $this->search)
                   ->orWhere('customer_name', 'LIKE', "%{$this->search}%")
                   ->orWhereHas('table', function($t){
                        $t->where('name', 'LIKE', "%{$this->search}%");
                   });
            });
        }

        $q->where('status', 'cerrado');
        
        return $q->get()->map(function ($o) {
            return [
                $o->id,
                $o->created_at->format('d/m/Y H:i'),
                $o->customer_name ?? ($o->customer->name ?? '—'),
                ucfirst($o->type),
                $o->table->name ?? '—',
                $o->total,
                $o->paid ? 'SI' : 'NO'
            ];
        });
    }
}


