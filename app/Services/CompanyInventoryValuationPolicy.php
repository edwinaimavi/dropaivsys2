<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SunatCatalogItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CompanyInventoryValuationPolicy
{
    public const CATALOG_CODE = '14';

    public const WEIGHTED_AVERAGE_CODE = '1';

    public function availableMethods(): Collection
    {
        return SunatCatalogItem::query()
            ->with('catalog')
            ->where('catalog_code', self::CATALOG_CODE)
            ->where('item_code', self::WEIGHTED_AVERAGE_CODE)
            ->where('status', 'ACTIVE')
            ->whereHas('catalog', fn ($query) => $query
                ->where('code', self::CATALOG_CODE)
                ->where('is_active', true))
            ->orderBy('item_code')
            ->get();
    }

    public function resolveSupportedMethod(int $itemId): SunatCatalogItem
    {
        $item = SunatCatalogItem::query()
            ->with('catalog')
            ->whereKey($itemId)
            ->where('catalog_code', self::CATALOG_CODE)
            ->where('item_code', self::WEIGHTED_AVERAGE_CODE)
            ->where('status', 'ACTIVE')
            ->whereHas('catalog', fn ($query) => $query
                ->where('code', self::CATALOG_CODE)
                ->where('is_active', true))
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'inventory_valuation_method_item_id' => 'Seleccione un método de valuación SUNAT válido. El motor actual de inventario soporta 1 — PROMEDIO PONDERADO.',
            ]);
        }

        return $item;
    }

    public function assertCanAssign(Company $company, ?int $newItemId): void
    {
        $currentItemId = $company->inventory_valuation_method_item_id
            ? (int) $company->inventory_valuation_method_item_id
            : null;

        if ($newItemId !== null) {
            $this->resolveSupportedMethod($newItemId);
        }

        if ($currentItemId === $newItemId) {
            return;
        }

        $hasHistory = $this->hasInventoryHistory($company);

        if ($currentItemId === null && $newItemId !== null) {
            // Primera configuración: permitida incluso cuando ya existe historial legacy.
            return;
        }

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'inventory_valuation_method_item_id' => 'El método de valuación no puede eliminarse ni modificarse porque la empresa ya tiene historial de inventario.',
            ]);
        }
    }

    public function hasInventoryHistory(Company $company): bool
    {
        return WarehouseStock::query()
            ->where('company_id', $company->id)
            ->exists()
            || WarehouseKardexMovement::query()
                ->where('company_id', $company->id)
                ->exists();
    }

    public function configuredMethod(Company $company): ?SunatCatalogItem
    {
        if (! $company->inventory_valuation_method_item_id) {
            return null;
        }

        return SunatCatalogItem::query()
            ->with('catalog')
            ->find($company->inventory_valuation_method_item_id);
    }
}
