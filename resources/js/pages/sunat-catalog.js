document.addEventListener('DOMContentLoaded', function () {
    $('#tableSunatCatalogs').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.sunatCatalogList,
        columns: [
            { data: 'catalog_code', name: 'catalog_code' },
            { data: 'item_code', name: 'item_code' },
            { data: 'description', name: 'description' },
            { data: 'short_name', name: 'short_name', defaultContent: '-' },
            { data: 'status', name: 'status' }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });
});
