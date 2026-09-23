let sunatExistenceTypesRequest = null;
let sunatInventoryCatalogsRequest = null;

function syncQuickSunatExistenceType(form) {
    const itemKind = form.find('.quick-item-kind').val();
    const isInventory = form.find('.quick-is-inventory-item').val() === '1';
    const applies = itemKind === 'product' && isInventory;
    const group = form.find('.quick-sunat-existence-type-group');
    const select = form.find('.quick-sunat-existence-type');

    group.toggleClass('d-none', !applies);
    select.prop('disabled', !applies).prop('required', applies);

    if (!applies) {
        select.val(null).trigger('change');
    }

    const inventoryGroup = form.find('.quick-sunat-inventory-identification-group');
    const inventoryCatalog = form.find('.quick-sunat-inventory-catalog');
    const inventoryCode = form.find('.quick-sunat-inventory-code');
    const standardCatalog = form.find('.quick-sunat-standard-catalog');
    const standardCode = form.find('.quick-sunat-standard-code');
    inventoryGroup.toggleClass('d-none', !applies);
    inventoryCatalog.prop('disabled', !applies).prop('required', applies);
    inventoryCode.prop('disabled', !applies).prop('required', applies);
    standardCatalog.prop('disabled', !applies);
    standardCode.prop('disabled', !applies);
    form.find('.quick-use-internal-code').prop('disabled', !applies);

    if (!applies) {
        inventoryCatalog.val(null).trigger('change.select2');
        inventoryCode.val('');
        form.find('.quick-sunat-use-internal-code-flag').val('0');
        standardCatalog.val(null).trigger('change.select2');
        standardCode.val('');
    } else {
        if (!inventoryCatalog.val()) {
            const ownCatalogOption = inventoryCatalog.find('option').filter(function () {
                return String($(this).data('item-code')) === '9';
            }).first();

            if (ownCatalogOption.length) {
                inventoryCatalog.val(ownCatalogOption.val()).trigger('change.select2');
            }
        }

        const selectedCode = String(inventoryCatalog.find(':selected').data('item-code') || '');
        if (selectedCode === '9' && !$.trim(inventoryCode.val())) {
            inventoryCode.val($.trim(form.find('[name="code"]').val()));
            form.find('.quick-sunat-use-internal-code-flag').val('1');
        }
    }

    const isOther = String(inventoryCatalog.find(':selected').data('item-code')) === '9';
    form.find('.quick-sunat-own-code-help').toggleClass('d-none', !applies || !isOther);
}

function initializeSearchableSelect(select) {
    if (!$.fn.select2 || select.hasClass('select2-hidden-accessible')) {
        return;
    }

    select.select2({
        dropdownParent: select.closest('.modal'),
        width: '100%',
        placeholder: 'Buscar por código o descripción',
        allowClear: true
    });
}

function loadSunatExistenceTypes() {
    const url = window.routes?.sunatExistenceTypes;
    if (!url || sunatExistenceTypesRequest) {
        return sunatExistenceTypesRequest;
    }

    sunatExistenceTypesRequest = $.getJSON(url).done(function (response) {
        $('.quick-sunat-existence-type').each(function () {
            const select = $(this);
            const selected = select.val();

            select.empty().append(new Option('Seleccione', '', false, false));
            (response.data || []).forEach(item => {
                select.append(new Option(item.text, item.id, false, String(item.id) === String(selected)));
            });
            initializeSearchableSelect(select);
        });
    });

    return sunatExistenceTypesRequest;
}

function loadSunatInventoryCatalogs() {
    const url = window.routes?.sunatInventoryCatalogs;
    if (!url || sunatInventoryCatalogsRequest) {
        return sunatInventoryCatalogsRequest;
    }

    sunatInventoryCatalogsRequest = $.getJSON(url).done(function (response) {
        $('.quick-sunat-inventory-catalog').each(function () {
            const select = $(this);
            select.empty().append(new Option('Seleccione', '', false, false));
            (response.data || []).forEach(item => {
                const option = new Option(item.text, item.id, false, false);
                $(option).attr('data-item-code', item.code);
                select.append(option);
            });
            initializeSearchableSelect(select);
        });
        $('.quick-sunat-standard-catalog').each(function () {
            const select = $(this);
            select.empty().append(new Option('No configurado', '', false, false));
            (response.standard || []).forEach(item => select.append(new Option(item.text, item.id, false, false)));
            initializeSearchableSelect(select);
        });

        $('form:has(.quick-sunat-existence-type)').each(function () {
            syncQuickSunatExistenceType($(this));
        });
    });

    return sunatInventoryCatalogsRequest;
}

export function initQuickSunatExistenceTypes() {
    $(document).on('change', '.quick-item-kind, .quick-is-inventory-item, .quick-sunat-inventory-catalog', function () {
        syncQuickSunatExistenceType($(this).closest('form'));
    });

    $(document).on('input', '.quick-sunat-inventory-code', function () {
        $(this).closest('form').find('.quick-sunat-use-internal-code-flag').val('0');
    });

    $(document).on('input', 'form:has(.quick-sunat-existence-type) [name="code"]', function () {
        const form = $(this).closest('form');
        if (form.find('.quick-sunat-use-internal-code-flag').val() === '1') {
            form.find('.quick-sunat-inventory-code').val($.trim($(this).val()));
        }
    });

    $(document).on('click', '.quick-use-internal-code', function () {
        const form = $(this).closest('form');
        form.find('.quick-sunat-inventory-code').val($.trim(form.find('[name="code"]').val())).trigger('input');
        form.find('.quick-sunat-use-internal-code-flag').val('1');
    });

    $(document).on('reset', 'form:has(.quick-sunat-existence-type)', function () {
        const form = $(this);
        setTimeout(() => syncQuickSunatExistenceType(form), 0);
    });

    $(function () {
        loadSunatExistenceTypes();
        loadSunatInventoryCatalogs();
        $('form:has(.quick-sunat-existence-type)').each(function () {
            syncQuickSunatExistenceType($(this));
        });
    });
}
