<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\Log;

class CleanEmptyOrders extends Command
{
    protected $signature = 'orders:clean-empty';
    protected $description = 'Elimina órdenes vacías antiguas y libera mesas';

    public function handle()
    {
        $this->info('🔍 Buscando órdenes vacías para limpiar...');
        
        // Encontrar órdenes abiertas sin items y con más de 5 minutos
        $emptyOrders = Order::with('table')
            ->where('status', 'abierto')
            ->doesntHave('items')
            ->where('created_at', '<', now()->subMinutes(5))
            ->get();

        $count = 0;
        $tablesFreed = 0;
        
        foreach ($emptyOrders as $order) {
            try {
                // Liberar mesa si existe
                if ($order->table_id && $order->table) {
                    $order->table->update(['status' => 'disponible']);
                    $tablesFreed++;
                    $this->line("  📋 Mesa #{$order->table_id} liberada");
                }
                
                // Eliminar la orden vacía
                $order->delete();
                $count++;
                
                $this->line("  🗑️ Orden #{$order->id} eliminada");
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error con orden #{$order->id}: " . $e->getMessage());
                Log::error('Error eliminando orden vacía', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($count === 0) {
            $this->info('✅ No se encontraron órdenes vacías para eliminar.');
        } else {
            $this->info("✅ Limpieza completada:");
            $this->info("   - Órdenes eliminadas: {$count}");
            $this->info("   - Mesas liberadas: {$tablesFreed}");
        }
        
        // También limpiar órdenes para llevar y domicilio vacías
        $this->cleanTakeawayAndDeliveryOrders();
        
        return Command::SUCCESS;
    }
    
    protected function cleanTakeawayAndDeliveryOrders()
    {
        $takeawayCount = Order::where('type', 'llevar')
            ->where('status', 'abierto')
            ->doesntHave('items')
            ->where('created_at', '<', now()->subMinutes(10))
            ->delete();
            
        $deliveryCount = Order::where('type', 'domicilio')
            ->where('status', 'abierto')
            ->doesntHave('items')
            ->where('created_at', '<', now()->subMinutes(10))
            ->delete();
            
        if ($takeawayCount > 0 || $deliveryCount > 0) {
            $this->info("  🚚 Órdenes para llevar/domicilio eliminadas:");
            $this->info("     - Para llevar: {$takeawayCount}");
            $this->info("     - Domicilio: {$deliveryCount}");
        }
    }
}