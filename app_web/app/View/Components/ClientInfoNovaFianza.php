<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ClientInfoNovaFianza extends Component
{
    public $client;

    // Constructor para recibir el cliente
    public function __construct($client)
    {
        $this->client = $client;
    }
    
    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.client-info-nova-fianza');
    }
}
