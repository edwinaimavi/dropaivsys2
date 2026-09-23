<?php

namespace App\Services;

use App\Models\CompanyWarehouse;
use App\Models\WarehouseStock;
use Illuminate\Validation\ValidationException;

class CompanyWarehouseService
{
    public function assertEnabled(int $companyId, int $warehouseId): CompanyWarehouse
    {
        if ($companyId <= 0) {
            throw ValidationException::withMessages([
                'company_id' => 'La operación debe indicar una empresa propietaria del inventario.',
            ]);
        }

        $relation = CompanyWarehouse::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->first();

        if (! $relation) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El almacén seleccionado no está habilitado para la empresa de la operación.',
            ]);
        }

        return $relation;
    }

    public function assertStockOwner(WarehouseStock $stock, int $companyId): void
    {
        if ($stock->company_id === null) {
            throw ValidationException::withMessages([
                'stock' => 'El stock seleccionado no tiene una empresa propietaria resuelta. Requiere revisión antes de continuar.',
            ]);
        }

        if ((int) $stock->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'stock' => 'El stock seleccionado pertenece a una empresa distinta de la operación.',
            ]);
        }
    }
}
