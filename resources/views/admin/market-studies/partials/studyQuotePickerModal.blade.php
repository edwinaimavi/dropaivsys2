<!-- =========================================================
     MODAL SELECTOR DE ÍTEMS PARA COTIZACIÓN
========================================================= -->
<div class="modal fade" id="studyQuotePickerModal" tabindex="-1" data-backdrop="static" data-keyboard="false"

    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content quote-item-picker-modal">

            <!-- HEADER -->
            <div class="quote-item-picker-header">

                <div class="d-flex align-items-center">

                    <div class="quote-item-picker-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>

                    <div class="ml-3">
                        <h5 class="mb-0 font-weight-bold text-white">
                            Seleccionar ítems del estudio
                        </h5>
                        <small class="text-white-50">
                            Elija uno o varios artículos para agregarlos a la cotización
                        </small>
                    </div>
                </div>

                <button type="button" class="quote-item-picker-close" data-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-4 bg-light">

                <!-- RESUMEN -->
                <div class="picker-info-box">

                    <div class="picker-info-icon">
                        <i class="fas fa-info"></i>
                    </div>

                    <div class="flex-grow-1">
                        <h6 class="mb-1 font-weight-bold text-dark" style="font-size:14px;">
                            Ítems disponibles del estudio
                        </h6>
                        <p class="mb-0 text-muted" style="font-size:12px;">
                            Marque los artículos que este proveedor sí cotiza.
                            Puede seleccionar varios y agregarlos en un solo paso.
                        </p>
                    </div>

                    <div class="ml-3 text-right">
                        <small class="text-muted d-block">Seleccionados</small>
                        <span class="badge badge-success px-2 py-1" id="quoteSelectedCount">
                            0
                        </span>
                    </div>
                </div>

                <!-- CARD TABLA -->
                <div class="picker-table-card">

                    <div class="picker-table-header d-flex justify-content-between align-items-center flex-wrap">

                        <div>
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-boxes text-success mr-2"></i>
                                Catálogo del estudio
                            </h6>
                            <small class="text-muted">
                                Seleccione los ítems que cotizará este proveedor
                            </small>
                        </div>

                        <div class="mt-2 mt-md-0">
                            <button type="button" id="btnAddSelectedQuoteItems" class="btn btn-success btn-sm">
                                <i class="fas fa-plus-circle mr-1"></i>
                                Agregar seleccionados
                            </button>
                        </div>

                    </div>

                    <div class="table-responsive">

                        <table id="tableArticlePicker" class="table table-hover align-middle w-100 mb-0">

                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="checkAllQuoteItems">
                                    </th>
                                    <th width="60">ID</th>
                                    <th width="120">CÓDIGO</th>
                                    <th>NOMBRE FACTURACIÓN</th>
                                    <th>CATEGORÍA</th>
                                    <th>SUBCATEGORÍA</th>
                                    <th>PRESENTACIÓN</th>
                                    {{-- <th width="130">COND. COSTEO</th> --}}
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>

                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer border-0 bg-white">

                <button type="button" class="btn btn-light px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>

            </div>

        </div>

    </div>

</div>

<style>
    /* BACKDROP */
    .modal-backdrop.show {
        opacity: .45 !important;
    }

    /* MODAL PRINCIPAL */
    .quote-item-picker-modal {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .30);
    }

    /* HEADER */
    .quote-item-picker-header {
        background: linear-gradient(135deg, #198754, #146c43);
        color: #fff;
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .quote-item-picker-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .quote-item-picker-close {
        border: none;
        background: rgba(255, 255, 255, .15);
        color: #fff;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        transition: .2s ease;
    }

    .quote-item-picker-close:hover {
        background: rgba(255, 255, 255, .25);
        transform: scale(1.03);
    }

    /* INFO BOX */
    .picker-info-box {
        background: #f8fffb;
        border: 1px solid #d4edda;
        border-radius: 16px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        margin-bottom: 14px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
    }

    .picker-info-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #198754;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        margin-right: 12px;
        flex-shrink: 0;
    }

    /* TABLE CARD */
    .picker-table-card {
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .06);
        border: 1px solid #eef1f4;
    }

    .picker-table-header {
        padding: 12px 16px;
        border-bottom: 1px solid #eef1f4;
        background: #fff;
    }

    /* TABLE */
    #tableArticlePicker thead th {
        background: #f8f9fa;
        border: none;
        font-size: 11px;
        font-weight: 700;
        color: #555;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
    }

    #tableArticlePicker tbody td {
        padding: 12px 10px;
        vertical-align: middle;
        font-size: 12px;
        white-space: nowrap;
    }

    #tableArticlePicker tbody tr:hover {
        background: #f8fff9;
        transition: .2s ease;
    }

    #tableArticlePicker .form-check-input {
        margin-top: 0;
        transform: scale(1.05);
        cursor: pointer;
    }

    /* DATA TABLE */
    #articlePickerModal .dataTables_filter input {
        border-radius: 12px !important;
        border: 1px solid #dee2e6 !important;
        padding: 8px 12px !important;
    }

    #articlePickerModal .dataTables_length select {
        border-radius: 10px !important;
    }

    /* BADGES */
    #articlePickerModal .badge {
        font-size: 10px;
        font-weight: 600;
    }

    /* ANIMACIÓN */
    #articlePickerModal .modal-dialog {
        transform: scale(.96);
        transition: .2s ease;
    }

    #articlePickerModal.show .modal-dialog {
        transform: scale(1);
    }

    /* BOTÓN AGREGAR */
    #btnAddSelectedQuoteItems {
        border-radius: 10px;
    }

    /* SELECT ALL */
    #checkAllQuoteItems {
        cursor: pointer;
    }

    @media (max-width: 991px) {
        #articlePickerModal .modal-dialog {
            max-width: 100%;
            margin: .5rem;
        }
    }
</style>
