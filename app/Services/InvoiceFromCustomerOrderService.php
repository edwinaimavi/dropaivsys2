<?php

namespace App\Services;

use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceFromCustomerOrderService
{
    public function prepare(CustomerPurchaseOrder $order): array
    {
        $order->loadMissing([
            'customer', 'customerBranch', 'currency',
            'items' => fn ($query) => $query->where('status', '!=', 'deleted')
                ->with(['article', 'unit', 'presentation', 'brand']),
            'electronicInvoices.items', 'electronicInvoices.collections',
        ]);
        $billed = $this->billedQuantities($order);

        return [
            'id' => $order->id,
            'company_id' => $order->company_id,
            'customer_id' => $order->customer_id,
            'customer_branch_id' => $order->customer_branch_id,
            'quote_id' => $order->quote_id,
            'currency_id' => $order->currency_id,
            'purchase_order_number' => $order->purchase_order_number ?: $order->code,
            'siaf_number' => $order->siaf_file_number,
            'process_number' => $order->process_type,
            'summary' => $this->summary($order),
            'items' => $order->items->map(function ($item) use ($billed, $order) {
                $pending = max(0, (float) $item->quantity - (float) ($billed[$item->id] ?? 0));

                return [
                    'customer_purchase_order_item_id' => $item->id,
                    'article_id' => $item->article_id,
                    'product_code' => $item->article_code ?: $item->article?->code,
                    'description' => $item->billing_name_snapshot ?: $item->article?->billing_name,
                    'brand_name' => $item->brand?->description,
                    'presentation_name' => $item->presentation?->description,
                    'unit_code' => $item->unit?->abbreviation ?: 'NIU',
                    'origin' => $item->origin,
                    'expiration_date' => optional($item->expiration_date)->format('Y-m-d'),
                    'ordered_quantity' => (float) $item->quantity,
                    'billed_quantity' => (float) ($billed[$item->id] ?? 0),
                    'pending_quantity' => $pending,
                    'quantity' => $pending,
                    'unit_price' => $item->unit_price,
                    'tax_affectation_code' => $order->affect_igv ? '10' : '20',
                ];
            })->filter(fn ($item) => $item['pending_quantity'] > 0.00001)->values()->all(),
        ];
    }

    public function validateGeneratedInvoice(
        CustomerPurchaseOrder $order,
        array $items,
        ?ElectronicInvoice $currentInvoice = null,
        ?float $invoiceTotal = null
    ): void
    {
        $order = CustomerPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
        if (in_array($order->status, ['cancelled', 'not_attended'], true)) {
            throw ValidationException::withMessages(['customer_purchase_order_id' => 'No se puede facturar una orden anulada o no atendida.']);
        }

        $orderItems = $order->items()->where('status', '!=', 'deleted')->get()->keyBy('id');
        $billed = $this->billedQuantities($order, $currentInvoice?->id);
        $requested = collect($items)->groupBy('customer_purchase_order_item_id')
            ->map(fn (Collection $rows) => $rows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)));

        foreach ($requested as $itemId => $quantity) {
            $orderItem = $orderItems->get((int) $itemId);
            if (! $orderItem) {
                throw ValidationException::withMessages(['items' => 'Todos los artículos deben pertenecer a la orden de compra seleccionada.']);
            }
            $pending = max(0, (float) $orderItem->quantity - (float) ($billed[$orderItem->id] ?? 0));
            if ($quantity > $pending + 0.00001) {
                throw ValidationException::withMessages([
                    'items' => "La cantidad a facturar de {$orderItem->billing_name_snapshot} supera el saldo pendiente ({$pending}).",
                ]);
            }
        }

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'La factura debe incluir artículos pendientes de la orden.']);
        }

        if ($invoiceTotal !== null) {
            $alreadyBilled = ElectronicInvoice::query()
                ->where('customer_purchase_order_id', $order->id)
                ->whereNotIn('status', ['draft', 'cancelled', 'voided'])
                ->where('is_voided', false)
                ->when($currentInvoice, fn ($query) => $query->whereKeyNot($currentInvoice->id))
                ->sum('total_amount');
            $remainingAmount = max(0, (float) $order->grand_total - (float) $alreadyBilled);
            if ($invoiceTotal > $remainingAmount + 0.00001) {
                throw ValidationException::withMessages([
                    'items' => "El total de la factura supera el saldo por facturar de la orden ({$remainingAmount}).",
                ]);
            }
        }
    }

    public function summary(CustomerPurchaseOrder $order): array
    {
        $order->loadMissing(['items', 'electronicInvoices.items', 'electronicInvoices.collections']);
        $invoices = $order->electronicInvoices->filter(fn ($invoice) => $this->countsForBilling($invoice));
        $billed = $invoices->sum(fn ($invoice) => (float) $invoice->total_amount);
        $paid = $invoices->sum(fn ($invoice) => (float) $invoice->paid_amount);
        $pending = $invoices->sum(fn ($invoice) => (float) $invoice->pending_amount);
        $billedQuantities = $this->billedQuantities($order);
        $fullyBilled = $order->items->where('status', '!=', 'deleted')->every(
            fn ($item) => (float) ($billedQuantities[$item->id] ?? 0) + 0.00001 >= (float) $item->quantity
        );

        return [
            'order_total' => (float) $order->grand_total,
            'billed_amount' => round($billed, 10),
            'unbilled_amount' => max(0, round((float) $order->grand_total - $billed, 10)),
            'paid_amount' => round($paid, 10),
            'pending_amount' => round($pending, 10),
            'billing_status' => $invoices->isEmpty() ? 'unbilled' : ($fullyBilled ? 'fully_invoiced' : 'partially_invoiced'),
            'collection_status' => $invoices->isEmpty() ? 'unbilled' : ($pending <= 0.00001 ? 'paid' : ($paid > 0 ? 'partial' : 'pending')),
            'invoices' => $invoices->values(),
        ];
    }

    private function billedQuantities(CustomerPurchaseOrder $order, ?int $excludeInvoiceId = null): Collection
    {
        $invoices = $order->relationLoaded('electronicInvoices')
            ? $order->electronicInvoices
                ->filter(fn ($invoice) => $this->countsForBilling($invoice))
                ->when($excludeInvoiceId, fn (Collection $rows) => $rows->where('id', '!=', $excludeInvoiceId))
            : ElectronicInvoice::query()
                ->where('customer_purchase_order_id', $order->id)
                ->whereNotIn('status', ['draft', 'cancelled', 'voided'])
                ->where('is_voided', false)
                ->when($excludeInvoiceId, fn ($query) => $query->whereKeyNot($excludeInvoiceId))
                ->with('items')
                ->get();

        return $invoices->flatMap->items
            ->whereNotNull('customer_purchase_order_item_id')
            ->groupBy('customer_purchase_order_item_id')
            ->map(fn (Collection $items) => $items->sum(fn ($item) => (float) $item->quantity));
    }

    private function countsForBilling(ElectronicInvoice $invoice): bool
    {
        return ! in_array($invoice->status, ['draft', 'cancelled', 'voided'], true) && ! $invoice->is_voided;
    }
}
