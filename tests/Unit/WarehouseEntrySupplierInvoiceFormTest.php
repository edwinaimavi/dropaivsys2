<?php

use App\Http\Controllers\Admin\WarehouseEntryController;

it('calcula el vencimiento desde la fecha del documento', function () {
    $controller = (new ReflectionClass(WarehouseEntryController::class))
        ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controller, 'calculateCreditDueDate');

    expect($method->invoke($controller, '2026-08-28', null, null, 30))
        ->toBe('2026-09-27');
});

it('presenta catálogos de pago y selección múltiple de OC Cliente', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/warehouse-entries/partials/modal.blade.php');

    expect($blade)
        ->toContain('id="warehouse_entry_payment_method"')
        ->toContain('WarehouseEntry::PAYMENT_METHODS')
        ->toContain('id="warehouse_entry_payment_condition"')
        ->toContain('WarehouseEntry::PAYMENT_CONDITIONS')
        ->toContain('id="warehouse_entry_credit_days"')
        ->toContain('id="warehouseEntryCreditTermsGroup"')
        ->toContain('id="warehouse_entry_expected_payment_date"')
        ->toContain('La fecha de vencimiento se calcula desde la fecha del documento.')
        ->toContain('id="warehouse_entry_customer_purchase_order_ids"')
        ->toContain('name="customer_purchase_order_ids[]" multiple')
        ->toContain('Seleccione orden de compra relacionada')
        ->not->toContain('name="payment_method"' . PHP_EOL . '                                            class="form-control form-control-sm text-uppercase"');
});

it('recalcula y alterna los términos de crédito en frontend', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($javascript)
        ->toContain("$(document).on('change', '#warehouse_entry_document_date'")
        ->toContain("$(document).on('change', '#warehouse_entry_payment_condition'")
        ->toContain("$(document).on('input change', '#warehouse_entry_credit_days'")
        ->toContain("$('#warehouseEntryCreditTermsGroup').toggleClass('d-none', !isCredit)")
        ->toContain('baseDate.setDate(baseDate.getDate() + creditDays)')
        ->toContain('setWarehouseEntryCustomerOrders(entry.customer_purchase_orders || [])')
        ->toContain('clearWarehouseEntryCustomerOrders()');
});

it('valida y persiste condiciones y órdenes relacionadas en backend', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Admin/WarehouseEntryController.php');
    $model = file_get_contents($root.'/app/Models/WarehouseEntry.php');

    expect($controller)
        ->toContain("'payment_method' => ['required', Rule::in(array_keys(WarehouseEntry::PAYMENT_METHODS))]")
        ->toContain("'payment_condition' => ['required', Rule::in(array_keys(WarehouseEntry::PAYMENT_CONDITIONS))]")
        ->toContain("'credit_days' => [")
        ->toContain("'customer_purchase_order_ids.*' => [")
        ->toContain('$entry->customerPurchaseOrders()->sync(')
        ->toContain('Las OC Cliente relacionadas deben pertenecer a la empresa del ingreso.')
        ->and($model)
        ->toContain("'credit_days' => 'integer'")
        ->toContain('public function customerPurchaseOrders()');
});
