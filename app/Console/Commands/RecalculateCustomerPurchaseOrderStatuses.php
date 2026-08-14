<?php

namespace App\Console\Commands;

use App\Models\CustomerPurchaseOrder;
use App\Services\CustomerPurchaseOrderStatusService;
use Illuminate\Console\Command;

class RecalculateCustomerPurchaseOrderStatuses extends Command
{
    protected $signature = 'customer-orders:recalculate-statuses
        {--order= : ID, código interno o número de OC cliente}';

    protected $aliases = ['customer-orders:recalculate-status'];

    protected $description = 'Recalcula los estados de las órdenes de compra de clientes según sus ingresos reales de almacén';

    public function handle(CustomerPurchaseOrderStatusService $statusService): int
    {
        $query = CustomerPurchaseOrder::query()
            ->select(['id', 'code', 'purchase_order_number', 'status']);

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
                ? "No se encontró una OC cliente con la referencia {$orderReference}."
                : 'No existen órdenes de compra de clientes para revisar.');

            return self::FAILURE;
        }

        $reviewed = 0;
        $updated = 0;
        $errors = 0;

        $query->chunkById(100, function ($orders) use ($statusService, &$reviewed, &$updated, &$errors) {
            foreach ($orders as $order) {
                $reviewed++;

                try {
                    $result = $statusService->recalculate($order);
                    if (! $result['changed']) {
                        continue;
                    }

                    $updated++;
                    $reference = $order->purchase_order_number ?: $order->code ?: "#{$order->id}";
                    $this->line(sprintf(
                        '%s: %s → %s',
                        $reference,
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
            ['Revisadas', 'Actualizadas', 'Con error'],
            [[$reviewed, $updated, $errors]]
        );

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function statusLabel(string $status): string
    {
        return CustomerPurchaseOrder::statusPresentation($status)['label']." ({$status})";
    }
}
