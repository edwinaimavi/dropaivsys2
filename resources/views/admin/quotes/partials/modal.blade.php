<!-- MODAL QUOTE -->
<div class="modal fade" id="quoteModal" tabindex="-1" role="dialog" aria-labelledby="quoteModalLabel" aria-hidden="true"
    data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered quote-modal-dialog" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg">

            <!-- HEADER -->
            <div class="modal-header align-items-center"
                style="
                    background:linear-gradient(90deg,#f4f9ff,#eaf3ff);
                    border-bottom:1px solid #b8d7ff;
                ">

                <div class="d-flex align-items-center">

                    <div class="mr-3"
                        style="
                            background:#dff0ff;
                            width:42px;
                            height:42px;
                            border-radius:10px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        ">

                        <i class="fas fa-file-invoice-dollar text-primary"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="quoteModalLabel">
                            Registrar Cotización
                        </h5>

                        <small class="text-muted">
                            Gestión comercial de cotizaciones generadas desde estudios de mercado
                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background:#f8fbff;">

                <form id="quoteForm" autocomplete="off" class="row">

                    @csrf

                    <input type="hidden" id="quote_id" name="quote_id">
                    <input type="hidden" id="status" name="status" value="sent">

                    <!-- PANEL IZQUIERDO -->
                    <div class="col-lg-3 mb-2">

                        <div class="card border-0 rounded-lg shadow-sm h-100 quote-side-card">

                            <div class="card-body text-center py-3 px-3">

                                <div class="mb-3">

                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                        style="
                                            width:85px;
                                            height:85px;
                                            background:linear-gradient(135deg,#0d6efd,#0b5ed7);
                                            color:white;
                                            font-size:32px;
                                            box-shadow:0 6px 18px rgba(13,110,253,.22);
                                        ">

                                        <i class="fas fa-receipt"></i>

                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">
                                    Cotización
                                </h5>

                                <small class="text-muted">
                                    Documento comercial para clientes
                                </small>

                                <hr class="my-2">

                                <div class="text-left small">

                                    <small class="text-muted d-block">
                                        N° Cotización
                                    </small>

                                    <input type="text" id="quote_number" name="quote_number"
                                        class="form-control form-control-sm mb-2 text-center font-weight-bold"
                                        placeholder="Automático" readonly>

                                    <small class="text-muted d-block">
                                        Fecha de registro
                                    </small>

                                    <div class="font-weight-600 mb-2">
                                        {{ now()->format('d/m/Y') }}
                                    </div>

                                    <small class="text-muted d-block">
                                        Estado inicial
                                    </small>

                                    <div class="badge badge-primary py-1 px-2 mt-1 text-white">
                                        Emitida
                                    </div>

                                    <small class="text-muted d-block mt-2">
                                        Cliente
                                    </small>

                                    <div class="font-weight-600 mb-2" id="quoteSideCustomer">
                                        Seleccione cliente
                                    </div>

                                    <small class="text-muted d-block">
                                        Sucursal / Tienda
                                    </small>

                                    <div class="font-weight-600 mb-2" id="quoteSideBranch">
                                        Seleccione sucursal
                                    </div>

                                    <small class="text-muted d-block">
                                        Total de venta
                                    </small>

                                    <div class="quote-side-total mt-1">
                                        <span class="quote-currency-symbol">S/</span>
                                        <span id="quoteSideGrandTotal">0.00</span>
                                    </div>

                                </div>

                                <div class="alert border-0 shadow-sm mt-3 mb-0 text-left quote-info-alert">

                                    <div class="d-flex align-items-start">

                                        <div class="mr-2">
                                            <i class="fas fa-info-circle text-primary"></i>
                                        </div>

                                        <div class="small">
                                            Selecciona un cliente y luego una sucursal para cargar la dirección de
                                            entrega.
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO SUPERIOR -->
                    <div class="col-lg-9 mb-2">

                        <!-- DATOS DE LA COTIZACIÓN -->
                        <div class="card border-0 rounded-lg shadow-sm">

                            <div class="card-header bg-white border-0 py-2 px-3">

                                <div class="d-flex justify-content-between align-items-center flex-wrap">

                                    <div>

                                        <h6 class="mb-0 text-dark font-weight-bold">
                                            <i class="fas fa-clipboard-list text-primary mr-1"></i>
                                            Datos de la cotización
                                        </h6>

                                        <small class="text-muted">
                                            Información principal del cliente, sucursal, empresa y condiciones
                                            comerciales
                                        </small>

                                    </div>

                                    <div class="mt-2 mt-md-0">

                                        <button type="button" class="btn btn-light border btn-sm mr-2"
                                            data-dismiss="modal">

                                            <i class="fas fa-times mr-1"></i>
                                            Cerrar

                                        </button>

                                        <button type="submit" id="btnSaveQuote" class="btn btn-primary btn-sm">

                                            <i class="fas fa-save mr-1"></i>
                                            Guardar Cotización

                                        </button>

                                    </div>

                                </div>

                            </div>

                            <div class="card-body py-3">

                                <!-- FILA 1 -->
                                <div class="form-row">

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            CLIENTE <span class="text-danger">*</span>
                                        </label>

                                        <div class="input-group input-group-sm quote-inline-select">

                                            <select id="customer_id" name="customer_id"
                                                class="form-control form-control-sm">

                                            <option value="">Seleccione cliente</option>

                                            @isset($customers)
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}">
                                                        {{ $customer->document_number ?? ($customer->ruc ?? '') }}
                                                        {{ $customer->document_number ?? ($customer->ruc ?? null) ? ' | ' : '' }}
                                                        {{ $customer->business_name ?? ($customer->name ?? '') }}
                                                    </option>
                                                @endforeach
                                            @endisset

                                            </select>

                                            <div class="input-group-append">

                                                <button type="button" id="btnOpenQuickCustomerModal"
                                                    class="btn btn-outline-primary"
                                                    title="Crear cliente" data-toggle="tooltip">

                                                    <i class="fas fa-plus"></i>

                                                </button>

                                            </div>

                                        </div>

                                        <span class="invalid-feedback" id="customer_id-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            SUCURSAL / TIENDA
                                        </label>

                                        <select id="customer_branch_id" name="customer_branch_id"
                                            class="form-control form-control-sm" disabled>

                                            <option value="">Seleccione cliente primero</option>

                                        </select>

                                        <span class="invalid-feedback" id="customer_branch_id-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            DIRECCIÓN DE ENTREGA
                                        </label>

                                        <input type="text" id="delivery_address" name="delivery_address"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese dirección de entrega">

                                        <span class="invalid-feedback" id="delivery_address-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 2 -->
                                <div class="form-row">

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            EMPRESA <span class="text-danger">*</span>
                                        </label>

                                        <select id="company_id" name="company_id"
                                            class="form-control form-control-sm">

                                            <option value="">Seleccione empresa</option>

                                            @isset($companies)
                                                @foreach ($companies as $company)
                                                    <option value="{{ $company->id }}">
                                                        {{ $company->trade_name ?? $company->business_name }}
                                                    </option>
                                                @endforeach
                                            @endisset

                                        </select>

                                        <span class="invalid-feedback" id="company_id-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            MONEDA <span class="text-danger">*</span>
                                        </label>

                                        <select id="currency_id" name="currency_id"
                                            class="form-control form-control-sm">

                                            <option value="">Seleccione moneda</option>

                                            @isset($currencies)
                                                @foreach ($currencies as $currency)
                                                    <option value="{{ $currency->id }}" data-code="{{ $currency->code }}"
                                                        data-symbol="{{ $currency->symbol }}" @selected($currency->code === 'PEN')>
                                                        {{ $currency->code }} | {{ $currency->description }}
                                                    </option>
                                                @endforeach
                                            @endisset

                                        </select>

                                        <span class="invalid-feedback" id="currency_id-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            CONDICIONES DE PAGO
                                        </label>

                                        <select id="payment_condition" name="payment_condition"
                                            class="form-control form-control-sm">

                                            <option value="">SELECCIONE CONDICIÓN</option>
                                            <option value="CONTADO" selected>CONTADO</option>
                                            <option value="CRÉDITO 20 DÍAS">CRÉDITO 20 DÍAS</option>
                                            <option value="CRÉDITO 30 DÍAS">CRÉDITO 30 DÍAS</option>
                                            <option value="CRÉDITO 45 DÍAS">CRÉDITO 45 DÍAS</option>
                                            <option value="CRÉDITO 60 DÍAS">CRÉDITO 60 DÍAS</option>

                                        </select>

                                        <span class="invalid-feedback" id="payment_condition-error"></span>

                                    </div>

                                </div>

                                <input type="hidden" id="show_code_type" name="show_code_type" value="internal">

                                <!-- FILA 3 -->
                                <div class="form-row">

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            ORIENTACIÓN
                                        </label>

                                        <select id="orientation" name="orientation"
                                            class="form-control form-control-sm">

                                            <option value="vertical" selected>Vertical</option>
                                            <option value="horizontal">Horizontal</option>

                                        </select>

                                        <span class="invalid-feedback" id="orientation-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            TIPO FACTURACIÓN
                                        </label>

                                        <select id="billing_type" name="billing_type"
                                            class="form-control form-control-sm">

                                            <option value="local" selected>Facturación local</option>
                                            <option value="export">Exportación</option>

                                        </select>

                                        <span class="invalid-feedback" id="billing_type-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            AFECTO IGV
                                        </label>

                                        <select id="affect_igv" name="affect_igv"
                                            class="form-control form-control-sm">

                                            <option value="0" selected>NO</option>
                                            <option value="1">SI</option>

                                        </select>

                                        <span class="invalid-feedback" id="affect_igv-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 4 -->
                                <div class="form-row">

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            FECHA DE VALIDEZ
                                        </label>

                                        <input type="date" id="validity_date" name="validity_date"
                                            class="form-control form-control-sm">

                                        <span class="invalid-feedback" id="validity_date-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            DÍAS DE ENTREGA
                                        </label>

                                        <input type="number" id="delivery_days" name="delivery_days"
                                            class="form-control form-control-sm" min="0"
                                            placeholder="Ejemplo: 15">

                                        <span class="invalid-feedback" id="delivery_days-error"></span>

                                    </div>

                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">
                                            TIEMPO DE ENTREGA
                                        </label>

                                        <input type="text" id="delivery_time" name="delivery_time"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Se genera automáticamente" readonly>

                                        <span class="invalid-feedback" id="delivery_time-error"></span>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- OBSERVACIONES Y ESTUDIO DE MERCADO -->
                        <div class="card border-0 rounded-lg shadow-sm mt-2">

                            <div class="card-header bg-white border-0 py-2 px-3">

                                <h6 class="mb-0 text-dark font-weight-bold">
                                    <i class="fas fa-search-dollar text-primary mr-1"></i>
                                    Estudio de mercado y observaciones
                                </h6>

                                <small class="text-muted">
                                    Selecciona el estudio de mercado relacionado y registra las condiciones del
                                    documento
                                </small>

                            </div>

                            <div class="card-body py-3">

                                <div class="form-row align-items-end">

                                    <div class="form-group col-md-10">

                                        <label class="small font-weight-bold text-secondary">
                                            ESTUDIO DE MERCADO
                                        </label>

                                        <select id="market_study_id" name="market_study_id"
                                            class="form-control form-control-sm">

                                            <option value="">Seleccione estudio de mercado</option>

                                            @isset($marketStudies)
                                                @foreach ($marketStudies as $study)
                                                    <option value="{{ $study->id }}">
                                                        {{ $study->code }}
                                                        |
                                                        {{ $study->description }}
                                                        @if (!empty($study->created_at))
                                                            |
                                                            {{ $study->created_at->format('Y-m-d') }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            @endisset

                                        </select>

                                        <span class="invalid-feedback" id="market_study_id-error"></span>

                                    </div>

                                    <div class="form-group col-md-2">

                                        <button type="button" id="btnFilterMarketStudy"
                                            class="btn btn-outline-primary btn-sm btn-block">

                                            <i class="fas fa-filter mr-1"></i>
                                            Filtrar

                                        </button>

                                    </div>

                                </div>

                                <div class="form-row">

                                    <div class="form-group col-md-12">

                                        <label class="small font-weight-bold text-secondary">
                                            OBSERVACIONES
                                        </label>

                                        <textarea id="observations" name="observations" rows="6" class="form-control form-control-sm text-uppercase"
                                            placeholder="Escriba cada condición en una línea">SE ADJUNTAN FICHA TECNICA PARA SU VALIDACION.
VIGENCIA DE OFERTA: 07 DIAS O HASTA AGOTAR STOCK, LO QUE OCURRA PRIMERO.
TIEMPO DE ENTREGA: 15 DIAS CALENDARIOS POST. APROBACION Y CONF. OC
MONTO MINIMO DE ATENCIÓN: S/2,500.00
GARANTÍA : 12 MESES</textarea>

                                        <small class="form-text text-muted">
                                            Escribe cada condición en una línea. En el PDF se mostrará como viñetas.
                                        </small>

                                        <span class="invalid-feedback" id="observations-error"></span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- ARTÍCULOS A TODO EL ANCHO DEL MODAL -->
                    <div class="col-12 quote-items-full">

                        <div class="card border-0 rounded-lg shadow-sm mt-2">

                            <div
                                class="card-header bg-white border-0 py-2 px-3 d-flex justify-content-between align-items-center flex-wrap">

                                <div>

                                    <h6 class="mb-0 text-dark font-weight-bold">
                                        <i class="fas fa-boxes text-primary mr-1"></i>
                                        Artículos de la cotización
                                    </h6>

                                    <small class="text-muted">
                                        Agrega artículos manualmente o cárgalos desde el estudio de mercado
                                    </small>

                                </div>

                                <div class="mt-2 mt-md-0">

                                    <button type="button" id="btnAddQuoteItem"
                                        class="btn btn-outline-primary btn-sm">

                                        <i class="fas fa-plus-circle mr-1"></i>
                                        Insertar artículo

                                    </button>

                                </div>

                            </div>

                            <div class="table-responsive quote-table-scroll">

                                <table id="quoteItemsTable" class="table table-hover table-sm mb-0 w-100 text-center">

                                    <thead class="bg-light">

                                        <tr>

                                            <th width="4%">#</th>
                                            <th>ARTÍCULO</th>
                                            <th>NOTA</th>
                                            <th>U.M.</th>
                                            <th>PRESENT.</th>
                                            <th>MARCA</th>
                                            <th>ORIGEN</th>
                                            <th>F. VENC.</th>
                                            <th>COSTEO</th>
                                            <th>P. COSTO</th>
                                            <th>CANT.</th>
                                            <th>P. VENTA</th>
                                            <th>DSCTO %</th>
                                            <th>TOTAL</th>
                                            <th width="6%">ACCIÓN</th>

                                        </tr>

                                    </thead>

                                    <tbody id="quoteItemsTbody">

                                        <tr id="quoteItemsEmptyRow">

                                            <td colspan="15" class="text-muted py-4">
                                                No hay artículos agregados aún.
                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                            <!-- TOTALES -->
                            <div class="quote-totals-area">

                                <div class="row justify-content-end">

                                    <div class="col-lg-4 col-md-6 col-sm-12">

                                        <div class="quote-total-line">

                                            <span>Venta Exonerada</span>

                                            <div class="input-group input-group-sm">

                                                <div class="input-group-prepend">
                                                    <span class="input-group-text quote-currency-code">PEN</span>
                                                </div>

                                                <input type="text" id="subtotal_exonerated"
                                                    name="subtotal_exonerated" class="form-control text-right"
                                                    value="0.00" readonly>

                                            </div>

                                        </div>

                                        <div class="quote-total-line">

                                            <span>Venta Gravada</span>

                                            <div class="input-group input-group-sm">

                                                <div class="input-group-prepend">
                                                    <span class="input-group-text quote-currency-code">PEN</span>
                                                </div>

                                                <input type="text" id="subtotal_taxed" name="subtotal_taxed"
                                                    class="form-control text-right" value="0.00" readonly>

                                            </div>

                                        </div>

                                        <div class="quote-total-line">

                                            <span>Total I.G.V.</span>

                                            <div class="input-group input-group-sm">

                                                <div class="input-group-prepend">
                                                    <span class="input-group-text quote-currency-code">PEN</span>
                                                </div>

                                                <input type="text" id="igv" name="igv"
                                                    class="form-control text-right" value="0.00" readonly>

                                            </div>

                                        </div>

                                        <div class="quote-total-line quote-total-grand">

                                            <span>Total Precio de Venta</span>

                                            <div class="input-group input-group-sm">

                                                <div class="input-group-prepend">
                                                    <span class="input-group-text quote-currency-code">PEN</span>
                                                </div>

                                                <input type="text" id="grand_total" name="grand_total"
                                                    class="form-control text-right font-weight-bold" value="0.00"
                                                    readonly>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- TEMPLATE ITEM -->
                    <template id="quoteItemRowTemplate">

                        <tr class="quote-item-row">

                            <td class="quote-item-index align-middle"></td>

                            <td>

                                <input type="hidden" class="item-market-study-item-id"
                                    name="items[__INDEX__][market_study_item_id]">

                                <input type="hidden" class="item-article-id" name="items[__INDEX__][article_id]">

                                <input type="hidden" class="item-article-code"
                                    name="items[__INDEX__][article_code]">

                                <input type="hidden" class="item-is-winner" name="items[__INDEX__][is_winner]"
                                    value="0">

                                <input type="hidden" class="item-billing-name-value"
                                    name="items[__INDEX__][billing_name_snapshot]">

                                <div class="input-group input-group-sm quote-inline-select">

                                    <select class="form-control form-control-sm item-article-select"
                                        data-placeholder="Buscar artículo...">
                                        <option value=""></option>
                                    </select>

                                    <div class="input-group-append">

                                        <button type="button" class="btn btn-outline-primary btnOpenQuickQuoteArticle"
                                            title="Crear artículo" data-toggle="tooltip">

                                            <i class="fas fa-plus"></i>

                                        </button>

                                    </div>

                                </div>

                                <div class="input-group input-group-sm quote-legacy-article-input d-none">

                                    <input type="text" name="items[__INDEX__][billing_name_snapshot]"
                                        class="form-control item-billing-name text-uppercase"
                                        placeholder="Seleccione o escriba artículo">

                                    <div class="input-group-append">

                                        <button type="button" class="btn btn-outline-primary btnSearchQuoteArticle">

                                            <i class="fas fa-search"></i>

                                        </button>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <input type="text" name="items[__INDEX__][note]"
                                    class="form-control form-control-sm item-note text-uppercase" placeholder="Nota">

                            </td>

                            <td>

                                <select name="items[__INDEX__][unit_id]"
                                    class="form-control form-control-sm item-unit-id">

                                    <option value="">UND</option>

                                    @isset($units)
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}"
                                                data-abbreviation="{{ $unit->abbreviation }}"
                                                data-description="{{ $unit->description }}">
                                                {{ $unit->abbreviation }} | {{ $unit->description }}
                                            </option>
                                        @endforeach
                                    @endisset

                                </select>

                            </td>

                            <td>

                                <select name="items[__INDEX__][presentation_id]"
                                    class="form-control form-control-sm item-presentation-id">

                                    <option value="">Seleccione</option>

                                    @isset($presentations)
                                        @foreach ($presentations as $presentation)
                                            <option value="{{ $presentation->id }}">
                                                {{ $presentation->name ?? $presentation->description }}
                                            </option>
                                        @endforeach
                                    @endisset

                                </select>

                            </td>

                            <td>

                                <div class="input-group input-group-sm quote-inline-select">

                                    <select name="items[__INDEX__][brand_id]"
                                        class="form-control form-control-sm item-brand-id">

                                    <option value="">Seleccione</option>

                                    @isset($brands)
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}">
                                                {{ $brand->name ?? $brand->description }}
                                            </option>
                                        @endforeach
                                    @endisset

                                    </select>

                                    <div class="input-group-append">

                                        <button type="button" class="btn btn-outline-primary btnOpenQuickQuoteBrand"
                                            title="Crear marca" data-toggle="tooltip">

                                            <i class="fas fa-plus"></i>

                                        </button>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <input type="text" name="items[__INDEX__][origin]"
                                    class="form-control form-control-sm item-origin text-uppercase"
                                    placeholder="NACIONAL">

                            </td>

                            <td>

                                <input type="date" name="items[__INDEX__][expiration_date]"
                                    class="form-control form-control-sm item-expiration-date">

                            </td>

                            <td>

                                <select name="items[__INDEX__][cost_type]"
                                    class="form-control form-control-sm item-cost-type">

                                    <option value="PESO" selected>PESO</option>
                                    <option value="UNIDAD">UNIDAD</option>
                                    <option value="CAJA">CAJA</option>

                                </select>

                            </td>

                            <td>

                                <input type="number" name="items[__INDEX__][cost_price]"
                                    class="form-control form-control-sm text-right item-cost-price" value="0.00"
                                    min="0" step="0.01">

                            </td>

                            <td>

                                <input type="number" name="items[__INDEX__][quantity]"
                                    class="form-control form-control-sm text-right item-quantity" value="1"
                                    min="0" step="0.01">

                            </td>

                            <td>

                                <input type="number" name="items[__INDEX__][unit_price]"
                                    class="form-control form-control-sm text-right item-unit-price" value="0.00"
                                    min="0" step="0.01">

                            </td>

                            <td>

                                <input type="number" name="items[__INDEX__][discount_percentage]"
                                    class="form-control form-control-sm text-right item-discount-percentage"
                                    value="0.00" min="0" step="0.01">

                                <input type="hidden" name="items[__INDEX__][discount_amount]"
                                    class="item-discount-amount" value="0.00">

                            </td>

                            <td>

                                <input type="number" name="items[__INDEX__][line_total]"
                                    class="form-control form-control-sm text-right item-line-total font-weight-bold"
                                    value="0.00" min="0" step="0.01" readonly>

                            </td>

                            <td class="align-middle">

                                <button type="button" class="btn btn-outline-danger btn-sm btnRemoveQuoteItem">

                                    <i class="fas fa-times"></i>

                                </button>

                            </td>

                        </tr>

                    </template>

                </form>

            </div>

        </div>

    </div>

</div>

<!-- MODAL RAPIDO CLIENTE DESDE COTIZACION -->
<div class="modal fade" id="quickCustomerModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content quick-quote-modal">
            <div class="modal-header align-items-center quick-quote-modal-header">
                <div>
                    <h5 class="modal-title mb-0 font-weight-bold">Nuevo Cliente</h5>
                    <small class="text-muted">Registro rápido para cotización comercial</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body bg-light">
                <form id="quickCustomerForm" autocomplete="off">
                    @csrf

                    <div class="alert alert-info py-2 d-none" id="quickCustomerDocumentLoading">
                        <i class="fas fa-spinner fa-spin mr-1"></i>
                        Consultando documento...
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>TIPO PERSONA <span class="text-danger">*</span></label>
                            <select id="quick_customer_person_type" name="person_type"
                                class="form-control form-control-sm">
                                <option value="natural">Persona Natural</option>
                                <option value="juridica">Persona Jurídica</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>TIPO DOCUMENTO <span class="text-danger">*</span></label>
                            <select id="quick_customer_document_type" name="document_type"
                                class="form-control form-control-sm">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="CE">CE</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>N° DOCUMENTO <span class="text-danger">*</span></label>
                            <input type="text" id="quick_customer_document_number" name="document_number"
                                class="form-control form-control-sm" maxlength="11">
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>CANAL</label>
                            <select id="quick_customer_channel" name="channel" class="form-control form-control-sm">
                                <option value="">Seleccione</option>
                                <option value="PRESTADOR DE SALUD">PRESTADOR DE SALUD</option>
                                <option value="DISTRIBUIDOR">DISTRIBUIDOR</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>SUB CANAL</label>
                            <select id="quick_customer_subchannel" name="subchannel"
                                class="form-control form-control-sm">
                                <option value="">Seleccione</option>
                                <option value="PUBLICO">PÚBLICO</option>
                                <option value="PRIVADO">PRIVADO</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>¿AGENTE DE RETENCIÓN?</label>
                            <select id="quick_customer_withholding_agent" name="withholding_agent"
                                class="form-control form-control-sm">
                                <option value="0">NO</option>
                                <option value="1">SI</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label>RAZÓN SOCIAL / NOMBRES <span class="text-danger">*</span></label>
                            <input type="text" id="quick_customer_name" name="name"
                                class="form-control form-control-sm text-uppercase">
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>TELÉFONO</label>
                            <input type="text" id="quick_customer_phone" name="phone"
                                class="form-control form-control-sm">
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>EMAIL</label>
                            <input type="email" id="quick_customer_email" name="email"
                                class="form-control form-control-sm">
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>DIRECCIÓN</label>
                            <input type="text" id="quick_customer_address" name="address"
                                class="form-control form-control-sm text-uppercase">
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <span class="badge badge-primary py-2 px-3">ESTADO: ACTIVO</span>

                        <div class="mt-2 mt-sm-0">
                            <button type="button" class="btn btn-light border btn-sm mr-2" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>
                                Cerrar
                            </button>
                            <button type="submit" id="btnSaveQuickCustomer" class="btn btn-primary btn-sm">
                                <i class="fas fa-save mr-1"></i>
                                Guardar Cliente
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RAPIDO ARTICULO DESDE COTIZACION -->
<div class="modal fade" id="quickQuoteArticleModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content quick-quote-modal">
            <div class="modal-header align-items-center quick-quote-modal-header">
                <div>
                    <h5 class="modal-title mb-0 font-weight-bold">Nuevo Artículo</h5>
                    <small class="text-muted">Registro rápido para cotización comercial</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body bg-light">
                <form id="quickQuoteArticleForm" autocomplete="off">
                    @csrf

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>CÓDIGO</label>
                            <input type="text" id="quick_quote_article_code" name="code"
                                class="form-control form-control-sm font-weight-bold" readonly>
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>TIPO CÓDIGO</label>
                            <select id="quick_quote_article_code_type" name="code_type"
                                class="form-control form-control-sm">
                                <option value="SIGA/SISMED" selected>SIGA/SISMED</option>
                                <option value="SAP/IETSI">SAP/IETSI</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>CÓDIGO INSTITUCIONAL</label>
                            <input type="text" id="quick_quote_article_institutional_code"
                                name="institutional_code" class="form-control form-control-sm text-uppercase">
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>NOMBRE LEGAL <span class="text-danger">*</span></label>
                            <input type="text" id="quick_quote_article_legal_name" name="legal_name"
                                class="form-control form-control-sm text-uppercase">
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>NOMBRE COMERCIAL</label>
                            <input type="text" id="quick_quote_article_commercial_name" name="commercial_name"
                                class="form-control form-control-sm text-uppercase">
                            <span class="invalid-feedback"></span>
                        </div>

                        <div class="form-group col-md-4">
                            <label>NOMBRE FACTURACIÓN <span class="text-danger">*</span></label>
                            <input type="text" id="quick_quote_article_billing_name" name="billing_name"
                                class="form-control form-control-sm text-uppercase">
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <span class="badge badge-primary py-2 px-3">ESTADO: ACTIVO</span>

                        <div class="mt-2 mt-sm-0">
                            <button type="button" class="btn btn-light border btn-sm mr-2" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>
                                Cerrar
                            </button>
                            <button type="submit" id="btnSaveQuickQuoteArticle" class="btn btn-primary btn-sm">
                                <i class="fas fa-save mr-1"></i>
                                Guardar Artículo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL RAPIDO MARCA DESDE COTIZACION -->
<div class="modal fade" id="quickQuoteBrandModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content quick-quote-modal">
            <div class="modal-header align-items-center quick-quote-modal-header">
                <div>
                    <h5 class="modal-title mb-0 font-weight-bold">Nueva Marca</h5>
                    <small class="text-muted">Registro rápido para la fila actual</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body bg-light">
                <form id="quickQuoteBrandForm" autocomplete="off">
                    @csrf

                    <div class="form-group">
                        <label>NOMBRE DE MARCA <span class="text-danger">*</span></label>
                        <input type="text" id="quick_quote_brand_description" name="description"
                            class="form-control form-control-sm text-uppercase">
                        <span class="invalid-feedback"></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <span class="badge badge-primary py-2 px-3">ESTADO: ACTIVO</span>

                        <div class="mt-2 mt-sm-0">
                            <button type="button" class="btn btn-light border btn-sm mr-2" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>
                                Cerrar
                            </button>
                            <button type="submit" id="btnSaveQuickQuoteBrand" class="btn btn-primary btn-sm">
                                <i class="fas fa-save mr-1"></i>
                                Guardar Marca
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    #quoteModal .quote-modal-dialog {
        max-width: 94%;
    }

    #quoteModal .modal-content {
        border-radius: 14px;
    }

    #quoteModal .modal-body {
        max-height: calc(100vh - 85px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    #quoteModal .card {
        border-radius: 12px;
    }

    #quoteModal .quote-side-card {
        background: #ffffff;
    }

    #quoteModal .quote-side-total {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        color: #fff;
        border-radius: 12px;
        padding: 10px 12px;
        text-align: center;
        font-size: 20px;
        font-weight: 800;
        box-shadow: 0 6px 16px rgba(13, 110, 253, .22);
    }

    #quoteModal .quote-currency-symbol {
        font-size: 13px;
        font-weight: 600;
        opacity: .9;
    }

    #quoteModal .quote-info-alert {
        background: #eaf3ff;
        color: #084298;
    }

    #quoteModal label {
        font-size: 11px;
        font-weight: 700;
        color: #495057;
        margin-bottom: 2px;
    }

    #quoteModal .form-group {
        margin-bottom: .50rem;
    }

    #quoteModal .form-control,
    #quoteModal .custom-select {
        height: 31px;
        font-size: 12px;
    }

    #quoteModal textarea.form-control {
        height: auto;
        min-height: 85px;
        line-height: 1.4;
        resize: vertical;
    }

    #quoteModal .form-control:focus,
    #quoteModal .custom-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .12rem rgba(13, 110, 253, .15);
    }

    #quoteModal .card-header h6 {
        font-size: 14px;
    }

    #quoteModal .small {
        font-size: 11px;
    }

    #quoteModal .btn-primary,
    #quoteModal .btn-outline-primary,
    #quoteModal .btn-outline-danger {
        border-radius: 8px;
    }

    #quoteModal .badge {
        font-size: 10px;
        font-weight: 600;
    }

    #quoteModal .font-weight-600 {
        font-weight: 600;
    }

    #quoteModal .quote-items-full {
        width: 100%;
    }

    #quoteModal .quote-table-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        scrollbar-width: thin;
    }

    #quoteModal .quote-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    #quoteModal .quote-table-scroll::-webkit-scrollbar-track {
        background: #edf2f7;
        border-radius: 20px;
    }

    #quoteModal .quote-table-scroll::-webkit-scrollbar-thumb {
        background: #b8d7ff;
        border-radius: 20px;
    }

    #quoteModal .quote-table-scroll::-webkit-scrollbar-thumb:hover {
        background: #86b7fe;
    }

    #quoteModal #quoteItemsTable {
        min-width: 1550px;
        table-layout: auto;
    }

    #quoteModal #quoteItemsTable thead th {
        border-top: 0;
        border-bottom: 2px solid #dbeafe;
        font-size: 10px;
        font-weight: 700;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: .25px;
        padding: .50rem .35rem;
        white-space: nowrap;
    }

    #quoteModal #quoteItemsTable tbody td {
        vertical-align: middle;
        border-top: 1px solid #eef2f7;
        padding: .30rem .30rem;
        white-space: nowrap;
    }

    #quoteModal #quoteItemsTable tbody tr:hover {
        background: #f8fbff;
    }

    #quoteModal #quoteItemsTable .item-billing-name {
        min-width: 300px;
    }

    #quoteModal #quoteItemsTable .item-article-select {
        min-width: 300px;
    }

    #quoteModal .quote-inline-select {
        flex-wrap: nowrap;
    }

    #quoteModal .quote-inline-select .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
    }

    #quoteModal .quote-inline-select .input-group-append .btn {
        width: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0 8px 8px 0;
    }

    #quoteModal #quoteItemsTable .item-note {
        min-width: 75px;
    }

    #quoteModal #quoteItemsTable .item-unit-id {
        min-width: 70px;
    }

    #quoteModal #quoteItemsTable .item-presentation-id {
        min-width: 110px;
    }

    #quoteModal #quoteItemsTable .item-brand-id {
        min-width: 110px;
    }

    #quoteModal #quoteItemsTable .item-origin {
        min-width: 95px;
    }

    #quoteModal #quoteItemsTable .item-expiration-date {
        min-width: 110px;
    }

    #quoteModal #quoteItemsTable .item-cost-type {
        min-width: 80px;
    }

    #quoteModal #quoteItemsTable .item-cost-price,
    #quoteModal #quoteItemsTable .item-quantity,
    #quoteModal #quoteItemsTable .item-unit-price,
    #quoteModal #quoteItemsTable .item-discount-percentage,
    #quoteModal #quoteItemsTable .item-line-total {
        min-width: 85px;
    }

    #quoteModal .quote-totals-area {
        border-top: 1px solid #dbeafe;
        padding: 12px 16px;
        background: #fff;
    }

    #quoteModal .quote-total-line {
        display: grid;
        grid-template-columns: 1fr 145px;
        gap: 10px;
        align-items: center;
        margin-bottom: 7px;
    }

    #quoteModal .quote-total-line span {
        font-size: 12px;
        font-weight: 700;
        color: #495057;
        text-align: right;
    }

    #quoteModal .quote-total-line .input-group-text {
        font-size: 11px;
        font-weight: 700;
        background: #f8fbff;
        color: #0d6efd;
        border-color: #dbeafe;
    }

    #quoteModal .quote-total-grand span {
        color: #0d6efd;
    }

    #quoteModal .quote-total-grand input {
        color: #0d6efd;
        background: #f4f9ff;
        border-color: #b8d7ff;
    }

    #quoteModal .invalid-feedback {
        font-size: 11px;
    }

    /* SELECT2 GENERAL */
    #quoteModal .select2-container--bootstrap4 .select2-selection {
        min-height: 31px;
    }

    #quoteModal .select2-container--bootstrap4 .select2-selection--single {
        height: 31px !important;
        display: flex;
        align-items: center;
    }

    #quoteModal .select2-container--bootstrap4 .select2-selection__rendered {
        line-height: 29px !important;
        padding-left: 8px !important;
        padding-right: 18px !important;
        font-size: 12px;
    }

    /* SELECT2 UNIDAD COMPACTA EN TABLA */
    #quoteModal #quoteItemsTable .item-unit-id+.select2-container {
        min-width: 70px !important;
        width: 70px !important;
    }

    /* SELECT2 UNIDAD ANCHA AL ABRIR */
    .select2-container--bootstrap4 .quote-unit-dropdown {
        min-width: 260px !important;
        width: auto !important;
    }

    .quote-unit-dropdown .select2-results__option {
        white-space: nowrap !important;
        font-size: 12px;
        padding: 6px 10px;
    }

    .quick-quote-modal {
        border: none;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .25);
    }

    .quick-quote-modal-header {
        background: linear-gradient(90deg, #f4f9ff, #eaf3ff);
        border-bottom: 1px solid #b8d7ff;
    }

    #quickCustomerModal label,
    #quickQuoteArticleModal label,
    #quickQuoteBrandModal label {
        font-size: 11px;
        font-weight: 700;
        color: #495057;
        margin-bottom: 2px;
    }

    #quickCustomerModal .form-control,
    #quickQuoteArticleModal .form-control,
    #quickQuoteBrandModal .form-control {
        height: 31px;
        font-size: 12px;
    }

    .quote-unit-option {
        display: inline-block;
        min-width: 230px;
        white-space: nowrap;
    }

    @media (max-width: 1366px) {

        #quoteModal .quote-modal-dialog {
            max-width: 96%;
        }

        #quoteModal #quoteItemsTable {
            min-width: 1480px;
        }

        #quoteModal #quoteItemsTable .item-billing-name {
            min-width: 260px;
        }

    }

    @media (max-width: 991px) {

        #quoteModal .modal-dialog {
            max-width: 98%;
            margin: .5rem auto;
        }

        #quoteModal .modal-body {
            max-height: calc(100vh - 70px);
        }

        #quoteModal .quote-side-card {
            margin-bottom: 8px;
        }

        #quoteModal #quoteItemsTable {
            min-width: 1320px;
        }

        #quoteModal .quote-total-line {
            grid-template-columns: 1fr;
            gap: 3px;
        }

        #quoteModal .quote-total-line span {
            text-align: left;
        }

    }

    @media (max-width: 576px) {

        #quoteModal .modal-dialog {
            max-width: 100%;
            margin: 0;
        }

        #quoteModal .modal-content {
            border-radius: 0;
            min-height: 100vh;
        }

        #quoteModal .modal-body {
            max-height: calc(100vh - 60px);
            padding: .5rem !important;
        }

        #quoteModal .modal-header {
            padding: .65rem .85rem;
        }

        #quoteModal .modal-title {
            font-size: 15px;
        }

        #quoteModal .card-body {
            padding: .75rem !important;
        }

        #quoteModal .card-header {
            padding: .65rem .75rem !important;
        }

        #quoteModal #quoteItemsTable {
            min-width: 1180px;
        }

        #quoteModal #quoteItemsTable thead th {
            font-size: 9px;
            padding: .45rem .25rem;
        }

        #quoteModal #quoteItemsTable tbody td {
            padding: .25rem;
        }

        #quoteModal .form-control,
        #quoteModal .custom-select {
            height: 30px;
            font-size: 11px;
        }

        #quoteModal textarea.form-control {
            min-height: 75px;
        }

        #quoteModal .btn-sm {
            padding: .25rem .45rem;
            font-size: 11px;
        }

    }
</style>
