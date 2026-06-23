<!-- =========================================================
     MODAL COTIZACIÓN DE ESTUDIO DE MERCADO
========================================================= -->
<div class="modal fade" id="marketStudyQuoteModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content market-study-quote-modal">

            <!-- HEADER -->
            <div class="market-study-quote-header">

                <div class="d-flex align-items-center">

                    <div class="market-study-quote-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>

                    <div class="ml-3">

                        <h5 class="mb-0 font-weight-bold text-white">
                            Cotización de Proveedor
                        </h5>

                        <small class="text-white-50">
                            Registro y evaluación de ofertas del estudio de mercado
                        </small>

                    </div>
                </div>

                <button type="button" class="market-study-quote-close" data-dismiss="modal" aria-label="Close">

                    <i class="fas fa-times"></i>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2 bg-light">

                <form id="marketStudyQuoteForm" autocomplete="off" enctype="multipart/form-data" class="row">

                    @csrf

                    <input type="hidden" id="market_study_quote_id" name="market_study_quote_id">
                    <input type="hidden" id="market_study_id_quote" name="market_study_id">

                    <!-- PANEL IZQUIERDO -->
                    <div class="col-lg-3 mb-2">

                        <div class="card border-0 rounded-lg shadow-sm h-100">

                            <div class="card-body text-center py-3 px-3">

                                <div class="mb-3">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                        style="
                                            width:72px;
                                            height:72px;
                                            background:linear-gradient(135deg,#198754,#146c43);
                                            color:white;
                                            font-size:28px;
                                            box-shadow:0 6px 18px rgba(0,0,0,.10);
                                         ">
                                        <i class="fas fa-handshake"></i>
                                    </div>
                                </div>

                                <h5 class="font-weight-bold text-dark mb-1" style="font-size:16px;">
                                    Cotización
                                </h5>

                                <small class="text-muted d-block">
                                    Oferta comercial del proveedor
                                </small>

                                <hr class="my-2">

                                <div class="text-left small">

                                    <small class="text-muted d-block">
                                        Estudio de mercado
                                    </small>
                                    <div id="quote_market_study_info" class="font-weight-600 mb-2">
                                        —
                                    </div>

                                    <small class="text-muted d-block">
                                        Proveedor
                                    </small>
                                    <div id="quote_supplier_info" class="font-weight-600 mb-2">
                                        —
                                    </div>

                                    <small class="text-muted d-block">
                                        Moneda
                                    </small>
                                    <div id="quote_currency_info" class="font-weight-600 mb-2">
                                        —
                                    </div>

                                    <small class="text-muted d-block">
                                        Estado inicial
                                    </small>
                                    <div class="badge badge-success py-1 px-2 mt-1 text-white">
                                        ACTIVO
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-lg-9">

                        <div class="card border-0 rounded-lg shadow-sm">

                            <div class="card-body py-3">

                                <!-- FILA 1 -->
                                <div class="form-row">

                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold text-secondary">
                                            NRO. COTIZACIÓN
                                        </label>

                                        <input type="text" id="quote_number" name="quote_number"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Auto / manual" readonly>

                                        <span class="invalid-feedback" id="quote_number-error"></span>
                                    </div>

                                    <div class="form-group col-md-5">
                                        <label class="small font-weight-bold text-secondary">
                                            PROVEEDOR <span class="text-danger">*</span>
                                        </label>

                                        <select id="supplier_id" name="supplier_id"
                                            class="form-control form-control-sm">
                                            <option value="">Seleccione</option>
                                        </select>

                                        <span class="invalid-feedback" id="supplier_id-error"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold text-secondary">
                                            MONEDA
                                        </label>

                                        <select id="currency_id" name="currency_id"
                                            class="form-control form-control-sm">
                                            <option value="">Seleccione</option>
                                        </select>

                                        <span class="invalid-feedback" id="currency_id-error"></span>
                                    </div>
                                </div>

                                <!-- FILA 2 -->
                                <div class="form-row">

                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold text-secondary">
                                            TIPO DE CAMBIO
                                        </label>

                                        <input type="number" step="0.0001" id="exchange_rate" name="exchange_rate"
                                            class="form-control form-control-sm" placeholder="0.0000">

                                        <span class="invalid-feedback" id="exchange_rate-error"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold text-secondary">
                                            CONDICIÓN DE PAGO
                                        </label>

                                        <input type="text" id="payment_condition" name="payment_condition"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ej. CONTADO, 30 DÍAS">

                                        <span class="invalid-feedback" id="payment_condition-error"></span>
                                    </div>

                                    <div class="form-group col-md-2">
                                        <label class="small font-weight-bold text-secondary">
                                            FLETE
                                        </label>

                                        <input type="number" step="0.01" id="shipping_cost" name="shipping_cost"
                                            class="form-control form-control-sm" placeholder="0.00">

                                        <span class="invalid-feedback" id="shipping_cost-error"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold text-secondary">
                                            OTROS COSTOS
                                        </label>

                                        <input type="number" step="0.01" id="other_costs" name="other_costs"
                                            class="form-control form-control-sm" placeholder="0.00">

                                        <span class="invalid-feedback" id="other_costs-error"></span>
                                    </div>
                                </div>

                                <!-- FILA 3 -->
                                <div class="form-row">

                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold text-secondary">
                                            FECHA DE ENTREGA
                                        </label>

                                        <input type="date" id="delivery_date" name="delivery_date"
                                            class="form-control form-control-sm">

                                        <span class="invalid-feedback" id="delivery_date-error"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold text-secondary">
                                            ESTADO
                                        </label>

                                        <select id="status" name="status" class="form-control form-control-sm">
                                            <option value="1" selected>ACTIVO</option>
                                            <option value="0">INACTIVO</option>
                                        </select>

                                        <span class="invalid-feedback" id="status-error"></span>
                                    </div>

                                    <div class="form-group col-md-4 d-flex align-items-end">
                                        <div class="w-100 text-right">
                                            <small class="text-muted d-block mb-1">
                                                Estado inicial sugerido
                                            </small>
                                            <div class="badge badge-success py-1 px-2">
                                                ACTIVO
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- CONDICIONES COMERCIALES -->
                                <div class="form-row">

                                    <div class="form-group col-md-12">
                                        <label class="small font-weight-bold text-secondary">
                                            CONDICIONES COMERCIALES
                                        </label>

                                        <textarea id="commercial_conditions" name="commercial_conditions" rows="3"
                                            class="form-control form-control-sm text-uppercase" placeholder="Ingrese condiciones comerciales"></textarea>

                                        <span class="invalid-feedback" id="commercial_conditions-error"></span>
                                    </div>

                                </div>

                                <!-- ALERT -->
                                <div class="alert border-0 shadow-sm mb-3" style="background:#e8fff0;color:#146c43;">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">
                                            <i class="fas fa-info-circle text-success"></i>
                                        </div>

                                        <div class="small">
                                            <strong>Importante:</strong>
                                            Aquí se registra la oferta del proveedor y luego se agregan los ítems
                                            cotizados.
                                        </div>

                                    </div>

                                </div>

                                <!-- BOTONES -->
                                <div class="d-flex justify-content-end">

                                    <button type="button" class="btn btn-light border btn-sm mr-2"
                                        data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i>
                                        Cerrar
                                    </button>

                                    <button type="submit" id="btnSaveMarketStudyQuote"
                                        class="btn btn-success btn-sm">
                                        <i class="fas fa-save mr-1"></i>
                                        Guardar Cotización
                                    </button>

                                </div>

                            </div>

                        </div>

                        <!-- DETALLE DE ÍTEMS -->
                        <div class="card border-0 rounded-lg shadow-sm mt-2">

                            <div
                                class="card-header bg-white border-0 py-2 px-3 d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-0 text-dark font-weight-bold">
                                        <i class="fas fa-boxes text-success mr-1"></i>
                                        Ítems Cotizados
                                    </h6>

                                    <small class="text-muted">
                                        Artículos incluidos en esta cotización
                                    </small>

                                </div>

                                <div>

                                    <button type="button" id="btnInsertQuoteItem"
                                        class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-plus-circle mr-1"></i>
                                        Agregar ítem
                                    </button>

                                </div>

                            </div>

                            <div class="table-responsive">

                                <table id="marketStudyQuoteItemsTable"
                                    class="table table-hover table-sm mb-0 w-100 text-center">

                                    <thead class="bg-light">

                                        <tr>
                                            <th width="5%">#</th>

                                            <th width="35%">
                                                ARTÍCULO
                                            </th>

                                            <th width="12%">
                                                TIPO IGV
                                            </th>

                                            <th width="10%">
                                                CANT.
                                            </th>

                                            <th width="12%">
                                                P. UNIT.
                                            </th>

                                            <th width="12%">
                                                SUBTOTAL
                                            </th>

                                            <th width="7%">
                                                CONFIG.
                                            </th>

                                            <th width="7%">
                                                ACCIONES
                                            </th>
                                        </tr>

                                    </thead>

                                    <tbody id="marketStudyQuoteItemsTbody">

                                        <tr id="marketStudyQuoteItemsEmptyRow">
                                            <td colspan="8" class="text-muted py-4">
                                                No hay ítems agregados aún.
                                            </td>
                                        </tr>

                                    </tbody>

                                </table>

                            </div>


                        </div>
                        <!-- RESUMEN DE TOTALES -->
                        <div class="quote-summary-container">

                            <table class="table table-borderless table-sm mb-0 quote-summary-table">

                                <tr>
                                    <td>INAFECTA:</td>
                                    <td>
                                        <span id="summary_inafecta">0.000</span>
                                    </td>
                                </tr>

                                <tr>
                                    <td>EXONERADA:</td>
                                    <td>
                                        <span id="summary_exonerada">0.000</span>
                                    </td>
                                </tr>

                                <tr>
                                    <td>GRAVADA:</td>
                                    <td>
                                        <span id="summary_gravada">0.000</span>
                                    </td>
                                </tr>

                                <tr>
                                    <td>IGV:</td>
                                    <td>
                                        <span id="summary_igv">0.000</span>
                                    </td>
                                </tr>

                                <tr class="summary-total-row">
                                    <td>TOTAL:</td>
                                    <td>
                                        <span id="summary_total">0.000</span>
                                    </td>
                                </tr>

                            </table>

                        </div>
                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<style>
    #marketStudyQuoteModal .modal-content {
        border-radius: 14px;
        overflow: hidden;
    }

    #marketStudyQuoteModal .card {
        border-radius: 12px;
    }

    #marketStudyQuoteModal .table td,
    #marketStudyQuoteModal .table th {
        padding-top: .42rem;
        padding-bottom: .42rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    #marketStudyQuoteModal .table thead th {
        font-size: 11px;
        font-weight: 700;
        color: #666;
    }

    #marketStudyQuoteModal .table tbody td {
        font-size: 12px;
    }

    #marketStudyQuoteModal .form-control-sm {
        font-size: 12px;
    }

    #marketStudyQuoteModal .small {
        font-size: 11px;
    }

    #marketStudyQuoteModal .btn-success,
    #marketStudyQuoteModal .btn-outline-success {
        border-radius: 8px;
    }

    #marketStudyQuoteModal .badge {
        font-size: 10px;
        font-weight: 600;
    }

    #marketStudyQuoteModal .font-weight-600 {
        font-weight: 600;
    }

    .market-study-quote-modal {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .25);
    }

    .market-study-quote-header {
        background: linear-gradient(135deg, #198754, #146c43);
        color: #fff;
        padding: 15px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .market-study-quote-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .market-study-quote-close {
        border: none;
        background: rgba(255, 255, 255, .15);
        color: #fff;
        width: 34px;
        height: 34px;
        border-radius: 10px;
    }

    .market-study-quote-close:hover {
        background: rgba(255, 255, 255, .25);
    }

    .modal-backdrop.show {
        opacity: .45 !important;
    }

    @media (max-width:991px) {
        #marketStudyQuoteModal .modal-dialog {
            max-width: 100%;
            margin: .5rem;
        }


    }

    .quote-summary-container {

        display: flex;
        justify-content: flex-end;

        padding: 15px 20px 20px;
        border-top: 1px solid #edf0f2;

        background: #fafafa;
    }

    .quote-summary-table {

        width: 280px;
    }

    .quote-summary-table td {

        font-size: 12px;
        font-weight: 600;
        padding: 4px 8px;
    }

    .quote-summary-table td:first-child {

        color: #666;
        text-align: left;
    }

    .quote-summary-table td:last-child {

        text-align: right;
        width: 120px;
    }

    .summary-total-row {

        border-top: 2px solid #198754;
    }

    .summary-total-row td {

        font-size: 14px;
        font-weight: 700;
        color: #198754;
    }
</style>
