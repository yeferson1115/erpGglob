<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Fecha actual
        $today = Carbon::today();
        $month = Carbon::now()->month;

        // Total ventas del día (cerrados y pagados)
        $salesToday = 0;

        // Total ventas del mes
        $salesMonth = 0;

        // Cantidad de pedidos del día
        $ordersToday = 0;

        // Cantidad de pedidos del mes
        $ordersMonth = 0;

        return view('admin.home.dashboard', compact(
            'salesToday',
            'salesMonth',
            'ordersToday',
            'ordersMonth'
        ));
    }

    
}
