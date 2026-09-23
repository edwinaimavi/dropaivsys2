<?php

it('separa los pagos guardados del nuevo pago y deshabilita el formulario sin saldo', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/supplier-purchase-order.js');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/supplier-purchase-orders/partials/modal.blade.php');

    expect($script)
        ->toContain('const purchaseBalance = Math.max(totalPurchase - storedPaid, 0);')
        ->toContain('updateSupplierOrderNewPaymentAvailability(paymentEnabled, purchaseBalance);')
        ->toContain('La orden ya no tiene saldo pendiente para registrar un nuevo pago.')
        ->toContain("fields.find(':input').prop('disabled', true);")
        ->toContain("fields.toggleClass('d-none', !enabled);")
        ->toContain('formatSupplierOrderMoney(storedPaid)')
        ->and($view)
        ->toContain('id="supplierOrderNewPaymentUnavailable"')
        ->toContain('id="supplierOrderNewPaymentFields"');
});

it('presenta el saldo de compra y no el saldo del anticipo en el detalle', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/supplier-purchase-order.js');
    $legacyBalancePresentation = <<<'JS'
$('#vspo_fin_balance').text(`${paymentCurrencyCode} ${formatSupplierOrderMoney(advanceBalance)}`.trim());
JS;

    expect($script)
        ->toContain('const paymentSummary = order.payment_summary || {};')
        ->toContain('const purchasePaid = paymentSummary.paid_total === null')
        ->toContain('const purchaseBalance = paymentSummary.balance === null')
        ->toContain("$('#vspo_fin_balance').text(purchaseFinancialMoney(purchaseBalance));")
        ->toContain("$('#vspo_fin_paid').text(purchaseFinancialMoney(purchasePaid));")
        ->not->toContain($legacyBalancePresentation);
});
