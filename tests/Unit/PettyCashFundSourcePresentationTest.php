<?php

it('mantiene visible y recarga el origen bancario en la primera apertura', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/petty-cash.js');

    expect($script)
        ->toContain('const approved = hasPreviousBox ? Math.max(0, approvedAmount - previous) : approvedAmount;')
        ->toContain('$(\'#pc_fund_source_company_id\').val(companyId || \'\');')
        ->toContain('currency_id: currencyId')
        ->toContain('No hay cuentas bancarias activas para esta empresa y moneda.');
});
