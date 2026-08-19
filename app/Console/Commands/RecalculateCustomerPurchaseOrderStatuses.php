<?php

namespace App\Console\Commands;

use App\Models\CustomerPurchaseOrder;
use App\Services\CustomerPurchaseOrderStatusService;
use Illuminate\Console\Command;

class RecalculateCustomerPurchaseOrderStatuses extends Command
{
    protected $signature = 'customer-orders:sync-statuses
        {--order= : ID, código interno o número de OC cliente}';

    protected $aliases = [
        'customer-orders:recalculate-statuses',
        'customer-orders:recalculate-status',
    ];

    protected $description = 'Sincroniza los estados de las órdenes de compra de clientes según sus ingresos reales de almacén';

    public function handle(CustomerPurchaseOrderStatusService $statusService): int
    {
        $query = CustomerPurchaseOrder::query()
            ->select(['id', 'code', 'purchase_order_number', 'status'])
            ->where('status', '!=', 'cancelled');

        if (filled($orderReference = $this->option('order'))) {
            $query->where(function ($orderQuery) use ($orderReference) {
                $orderQuery
                    ->where('code', $orderReference)
                    ->orWhere('purchase_order_number', $orderReference);

                if (ctype_digit((string) $orderReference)) {
                    $orderQuery->orWhere('id', (int) $orderReference);
                }
            });
        }

        if (! $query->exists()) {
            $this->warn(filled($orderReference)
                ? "No se encontró una OC cliente activa con la referencia {$orderReference}."
                : 'No existen órdenes de compra de clientes activas para revisar.');

            return self::FAILURE;
        }

        $reviewed = 0;
        $updated = 0;
        $errors = 0;

        $query->chunkById(100, function ($orders) use ($statusService, &$reviewed, &$updated, &$errors) {
            foreach ($orders as $order) {
                $reviewed++;

                try {
                    $result = $statusService->syncStatus($order);
                    if (! $result['changed']) {
                        continue;
                    }

                    $updated++;
                    $this->line(sprintf(
                        '%s | %s | %s -> %s',
                        $order->code ?: "#{$order->id}",
                        $order->purchase_order_number ?: '-',
                        $this->statusLabel($result['previous_status']),
                        $this->statusLabel($result['status'])
                    ));
                } catch (\Throwable $exception) {
                    $errors++;
                    $this->error("OC Cliente #{$order->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->table(
            ['Total revisadas', 'Total corregidas', 'Con error'],
            [[$reviewed, $updated, $errors]]
        );

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function statusLabel(string $status): string
    {
        return CustomerPurchaseOrder::statusPresentation($status)['label']." ({$status})";
    }
}
