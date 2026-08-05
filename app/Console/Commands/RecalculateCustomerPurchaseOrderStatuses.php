<?php

namespace App\Console\Commands;

use App\Models\CustomerPurchaseOrder;
use Illuminate\Console\Command;

class RecalculateCustomerPurchaseOrderStatuses extends Command
{
    protected $signature = 'customer-orders:recalculate-status';

    protected $description = 'Recalcula los estados de las órdenes de compra de clientes según cantidades compradas e ingresadas';

    public function handle(): int
    {
        $reviewed = 0;
        $updated = 0;
        $errors = 0;

        CustomerPurchaseOrder::query()
            ->select(['id', 'status'])
            ->chunkById(100, function ($orders) use (&$reviewed, &$updated, &$errors) {
                foreach ($orders as $order) {
                    $reviewed++;

                    try {
                        if ($order->refreshSupplyStatus()) {
                            $updated++;
                        }
                    } catch (\Throwable $exception) {
                        $errors++;
                        $this->error("OC Cliente #{$order->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->newLine();
        $this->table(
            ['Total revisadas', 'Total actualizadas', 'Total con error'],
            [[$reviewed, $updated, $errors]]
        );

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
