<?php

it('evita la validación HTML5 nativa en el modal con pestañas', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/warehouse-entries/partials/modal.blade.php');

    expect($blade)
        ->toContain('id="warehouseEntryForm"')
        ->toContain('novalidate')
        ->toContain('id="btnSaveWarehouseEntry"')
        ->not->toMatch('/\srequired(?:\s|>)/');
});

it('valida por javascript, cambia de pestaña y controla el estado de guardado', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');
    $controller = file_get_contents($root.'/app/Http/Controllers/Admin/WarehouseEntryController.php');

    expect($javascript)
        ->toContain("$(document).on('submit', '#warehouseEntryForm'")
        ->toContain('event.preventDefault()')
        ->toContain('validateWarehouseEntryRequiredData()')
        ->toContain('validateWarehouseEntryItems()')
        ->toContain('validateWarehouseEntryLots()')
        ->toContain('validateWarehouseEntryPendingExpense()')
        ->toContain('setWarehouseEntrySaving(true)')
        ->toContain('.always(() => setWarehouseEntrySaving(false))')
        ->toContain('.warehouse-entry-form-tabs a[href=')
        ->and($controller)
        ->toContain("'exclude_unless:expenses.*.source_type,bank'")
        ->toContain("'required_if:expenses.*.source_type,bank'")
        ->toContain("'exclude_unless:expenses.*.source_type,general_cash'")
        ->toContain("'exclude_unless:expenses.*.source_type,petty_cash'");
});

it('presenta y limita los pagos nuevos con información calculada y saldo disponible', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');
    $controller = file_get_contents($root.'/app/Http/Controllers/Admin/WarehouseEntryController.php');
    $service = file_get_contents($root.'/app/Services/WarehouseEntryCreditPaymentService.php');

    expect($javascript)
        ->toContain("account.company?.business_name || account.company?.trade_name")
        ->toContain('`${company} — ${bank} | ${account.account_number} | ${currency} | Saldo ${symbol}')
        ->toContain('value="${escapeWarehouseEntryHtml(account.id)}"')
        ->toContain('type="hidden" name="payment_items[${index}][paid_amount]"')
        ->toContain('Calculado autom&aacute;ticamente')
        ->toContain('function updateWarehouseEntryPendingPaymentBalances()')
        ->toContain('Saldo disponible para aplicar:')
        ->toContain('El monto aplicado no puede superar el saldo pendiente de ${purchaseSymbol}')
        ->toContain("field.attr('max', availableAmount.toFixed(4))")
        ->toContain("$('#btnSaveWarehouseEntry').prop('disabled', warehouseEntrySaving || hasLimitError)")
        ->and($controller)
        ->toContain("'company:id,business_name,trade_name'")
        ->toContain('validatePaymentItemsAgainstPendingAmount(')
        ->and($service)
        ->toContain('public function validatePaymentItemsAgainstPendingAmount(')
        ->toContain('if ($appliedAmount > $remainingAmount + self::MONEY_EPSILON)')
        ->toContain('"payment_items.{$index}.applied_amount"');
});

it('aísla los modales hijos de lotes y asignaciones sin cerrar el ingreso principal', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/warehouse-entries/partials/modal.blade.php');
    $styles = file_get_contents($root.'/resources/views/admin/warehouse-entries/index.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($blade)
        ->toContain('class="close btnCloseWarehouseEntryLotsModal"')
        ->toContain('class="btn btn-outline-secondary btn-sm btnCloseWarehouseEntryLotsModal"')
        ->toContain('class="close text-dark btnCloseWarehouseEntryAllocationsModal"')
        ->toContain('class="btn btn-outline-secondary btn-sm btnCloseWarehouseEntryAllocationsModal"')
        ->and($javascript)
        ->toContain(".off('click.warehouseEntryLotsAction', '.warehouse-entry-item-row .btnManageWarehouseEntryLots')")
        ->toContain("openWarehouseEntryLotsModal($(event.currentTarget).closest('tr.warehouse-entry-item-row'))")
        ->toContain("if ($('#warehouseEntryAllocationsModal').hasClass('show'))")
        ->toContain(".off('click.warehouseEntryAllocationsAction', '.warehouse-entry-item-row .btnManageWarehouseEntryAllocations')")
        ->toContain("openWarehouseEntryAllocationsModal($(event.currentTarget).closest('tr.warehouse-entry-item-row'))")
        ->toContain("if ($('#warehouseEntryLotsModal').hasClass('show'))")
        ->toContain(".off('click.warehouseEntryLotsClose', '#warehouseEntryLotsModal .btnCloseWarehouseEntryLotsModal')")
        ->toContain("$('#warehouseEntryLotsModal').modal('hide');")
        ->toContain(".off('click.warehouseEntryAllocationsClose', '#warehouseEntryAllocationsModal .btnCloseWarehouseEntryAllocationsModal')")
        ->toContain("$('#warehouseEntryAllocationsModal').modal('hide');")
        ->toContain("const allocationsModal = document.getElementById('warehouseEntryAllocationsModal');")
        ->toContain('document.body.appendChild(allocationsModal);')
        ->toContain(".on('hidden.bs.modal.warehouseEntryChild'")
        ->toContain('restoreWarehouseEntryMainModalAfterChild();')
        ->toContain('if (event.target !== this) return;')
        ->and($styles)
        ->toContain('#warehouseEntryAllocationsModal,')
        ->toContain('.warehouse-entry-backdrop-allocations,');
});

it('ignora el editor de costos por defecto y conserva las validaciones al agregar o editar', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    preg_match(
        '/function hasWarehouseEntryPendingExpense\(\) \{(?<body>.*?)\n\}/s',
        $javascript,
        $pendingExpenseFunction
    );
    preg_match(
        '/function addWarehouseEntryExpense\(\) \{(?<body>.*?)\n\}\n\nfunction editWarehouseEntryExpense/s',
        $javascript,
        $addExpenseFunction
    );
    preg_match(
        '/async function saveWarehouseEntry\(form\) \{(?<body>.*?)\n\}\n\nfunction showWarehouseEntryValidationErrors/s',
        $javascript,
        $saveFunction
    );
    preg_match(
        '/function fillWarehouseEntryForm\(entry\) \{(?<body>.*?)\n\}\n\nfunction warehouseEntryBankPaymentCard/s',
        $javascript,
        $fillFunction
    );

    expect($pendingExpenseFunction['body'] ?? '')
        ->toContain("return $('#warehouse_entry_expense_edit_index').val() !== '';")
        ->not->toContain('valueSelectors')
        ->not->toContain('hasFile')
        ->and($addExpenseFunction['body'] ?? '')
        ->toContain("if (type === 'agency_freight' && !isPettyCash && !$('#warehouse_entry_expense_shipping_agency_id').val())")
        ->toContain("'Seleccione la agencia de envío.'")
        ->toContain("if (paymentSource === 'bank' && !$('#warehouse_entry_expense_company_bank_account_id').val())")
        ->toContain("'Seleccione la cuenta bancaria de origen.'")
        ->toContain("if (editIndex === '') warehouseEntryExpenses.push(expense); else warehouseEntryExpenses[Number(editIndex)] = expense;")
        ->toContain('warehouseEntryExpensesDirty = true;')
        ->and($saveFunction['body'] ?? '')
        ->toContain('const syncExpenses = shouldSyncWarehouseEntryExpenses(id);')
        ->toContain("formData.delete('expense_management');")
        ->toContain('if (syncExpenses) {')
        ->not->toContain('addWarehouseEntryExpense(')
        ->and($fillFunction['body'] ?? '')
        ->toContain('warehouseEntryExpenses = (entry.expenses || []).map')
        ->toContain('renderWarehouseEntryExpenses();')
        ->toContain('resetWarehouseEntryExpenseEditor();')
        ->toContain('warehouseEntryExpensesDirty = false;')
        ->and($javascript)
        ->toContain('let warehouseEntryExpensesDirty = false;')
        ->toContain('return !entryId || warehouseEntryExpensesDirty;')
        ->toContain("$('#warehouse_entry_expense_edit_index').val('');")
        ->toContain("$('#warehouse_entry_expense_edit_index').val(index);");
});

it('restaura agencia, distribución, documentos y fuentes al editar costos vinculados', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    preg_match(
        '/function editWarehouseEntryExpense\(index\) \{(?<body>.*?)\n\}\n\nfunction removeWarehouseEntryExpense/s',
        $javascript,
        $editExpenseFunction
    );

    expect($javascript)
        ->toContain('function normalizeWarehouseEntryExpenseAgencyName(value)')
        ->toContain(".normalize('NFD')")
        ->toContain(".replace(/[\\u0300-\\u036f]/g, '')")
        ->toContain('function restoreWarehouseEntryExpenseShippingAgency(expense)')
        ->toContain("const persistedAgencyId = String(expense.shipping_agency_id || '');")
        ->toContain('if (!persistedAgencyId && expense.provider_name)')
        ->toContain('if (matches.length === 1) agencyId = String(matches.first().val());')
        ->toContain("select.val(agencyId).trigger('change.select2');")
        ->toContain('function restoreWarehouseEntryExpenseDistributionMethod(distributionMethod)')
        ->toContain("if (!hasOption) select.append(new Option(labels[method], method));")
        ->and($editExpenseFunction['body'] ?? '')
        ->toContain('restoreWarehouseEntryExpenseShippingAgency(expense);')
        ->toContain("const distributionMethod = expense.distribution_method || 'quantity';")
        ->toContain('restoreWarehouseEntryExpenseDistributionMethod(distributionMethod);')
        ->toContain('renderWarehouseEntryExpenseManualDistribution();')
        ->toContain('(expense.distributions || []).forEach')
        ->toContain("loadWarehouseEntryExpenseBankAccounts(expense.company_bank_account_id || '');")
        ->toContain("$('#warehouse_entry_expense_general_cash_box_id').val(expense.general_cash_box_id || '');")
        ->toContain("renderWarehouseEntryExpenseFileSelection('invoice'")
        ->toContain("renderWarehouseEntryExpenseFileSelection('payment_proof'")
        ->toContain("renderWarehouseEntryExpenseFileSelection('detraction_proof'");
});

it('conserva manual y usa quantity solo como fallback legacy en el servidor', function () {
    $controller = (new ReflectionClass(App\Http\Controllers\Admin\WarehouseEntryController::class))
        ->newInstanceWithoutConstructor();
    $normalize = new ReflectionMethod($controller, 'normalizeLinkedExpenseFields');

    $manual = $normalize->invoke($controller, [
        'source_type' => 'manual',
        'expense_type' => 'agency_freight',
        'document_type' => 'FACTURA',
        'affects_inventory_cost' => true,
        'distribution_method' => 'manual',
    ]);
    $legacy = $normalize->invoke($controller, [
        'source_type' => 'bank',
        'expense_type' => 'agency_freight',
        'document_type' => 'FACTURA',
        'affects_inventory_cost' => true,
        'distribution_method' => null,
    ]);

    expect($manual['distribution_method'])->toBe('manual')
        ->and($legacy['distribution_method'])->toBe('quantity');
});

it('persiste agencia para fletes bancarios y conserva la idempotencia económica existente', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Admin/WarehouseEntryController.php');
    $bankService = file_get_contents($root.'/app/Services/WarehouseEntryExpenseBankService.php');

    expect($controller)
        ->toContain("&& \$sourceType !== WarehouseEntryExpense::SOURCE_PETTY_CASH")
        ->toContain("\$data['provider_name'] = \$agency->trade_name ?: \$agency->business_name;")
        ->toContain("\$data['provider_ruc'] = \$this->upperOrNull(\$agency->ruc);")
        ->and($bankService)
        ->toContain("->where('source_type', 'WAREHOUSE_ENTRY_EXPENSE')")
        ->toContain("(int) \$movement->company_bank_account_id === (int) \$account->id")
        ->toContain("(int) \$movement->currency_id === (int) \$expense->currency_id")
        ->toContain("abs((float) \$movement->amount - \$amount) < 0.00005")
        ->toContain('if ($matchingMovement)');
});

it('presenta los costos vinculados como tarjetas responsivas sin perder sus acciones', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/warehouse-entries/partials/modal.blade.php');
    $styles = file_get_contents($root.'/resources/views/admin/warehouse-entries/index.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($blade)
        ->toContain('warehouse-entry-expense-list-summary')
        ->toContain('id="warehouseEntryExpenseCount"')
        ->toContain('id="warehouseEntryFreightTotal"')
        ->toContain('id="warehouseEntryOtherExpenseTotal"')
        ->toContain('id="warehouseEntryExpenseRegisteredTotal"')
        ->toContain('id="warehouseEntryExpensePendingTotal"')
        ->toContain('id="warehouseEntryExpenseApprovedTotal"')
        ->toContain('id="warehouseEntryExpenseLinkedTotal"')
        ->toContain('id="warehouseEntryExpensesBody" class="warehouse-entry-expense-cards"')
        ->not->toContain('warehouse-entry-expenses-table')
        ->and($styles)
        ->toContain('.warehouse-entry-expense-card-main')
        ->toContain('.warehouse-entry-expense-card.is-pending')
        ->toContain('@media (max-width: 767.98px)')
        ->and($javascript)
        ->toContain('warehouse-entry-expense-doc-dropdown')
        ->toContain('btnViewWarehouseEntryExpenseObservation')
        ->toContain('btnReviewWarehouseEntryExpense')
        ->toContain('btnEditWarehouseEntryExpense')
        ->toContain('btnRemoveWarehouseEntryExpense')
        ->toContain("if ($(this).attr('href') === '#warehouse_entry_tab_expenses')")
        ->toContain('loadWarehouseEntryExpenseBankAccounts()')
        ->toContain('warehouseEntryBankAccountsCompanyId !== companyId')
        ->toContain('renderWarehouseEntryExpenseBankAccounts')
        ->toContain("String(account.company_id) === companyId")
        ->toContain("String(account.status || '').toUpperCase() === 'ACTIVE'")
        ->toContain('help.text(`${accounts.length} cuenta')
        ->toContain("clearWarehouseEntryExpenseFile('payment_proof')")
        ->toContain('const registeredExpenses = warehouseEntryExpenses;')
        ->toContain("$('#warehouseEntryExpensePendingTotal').text");
});

it('presenta el resumen real del anticipo sin completar saldos faltantes con cero', function () {
    $root = dirname(__DIR__, 2);
    $styles = file_get_contents($root.'/resources/views/admin/warehouse-entries/index.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($javascript)
        ->toContain('response.payment_summary')
        ->toContain('Total de la orden')
        ->toContain('Anticipo pagado')
        ->toContain('Saldo pendiente')
        ->toContain('payments_by_currency')
        ->not->toContain('formatWarehouseEntryMoney(response.advance_balance || 0)')
        ->and($styles)
        ->toContain('.warehouse-entry-advance-summary')
        ->toContain('.warehouse-entry-advance-values')
        ->toContain('grid-template-columns: 1fr');
});
