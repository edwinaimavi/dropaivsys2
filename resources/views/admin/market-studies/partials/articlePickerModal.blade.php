<!-- =========================================================
     MODAL SELECTOR DE ARTÍCULOS
========================================================= -->
<div class="modal fade" id="articlePickerModal" tabindex="-1" data-backdrop="static" data-keyboard="false">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content article-picker-modal">

            <!-- HEADER -->
            <div class="article-picker-header">

                <div class="d-flex align-items-center">

                    <div class="article-picker-icon">

                        <i class="fas fa-boxes"></i>

                    </div>

                    <div class="ml-3">

                        <h5 class="mb-0 font-weight-bold">
                            Catálogo de Artículos
                        </h5>

                        <small class="text-white-50">
                            Seleccione artículos para incorporar al estudio
                        </small>
                    </div>

                </div>

                <button type="button" class="article-picker-close" data-dismiss="modal">

                    <i class="fas fa-times"></i>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-4">

                <!-- RESUMEN -->
                <div class="picker-info-box">

                    <div class="picker-info-icon">

                        <i class="fas fa-shopping-cart"></i>

                    </div>

                    <div>

                        <h6 class="mb-1 font-weight-bold" style="font-size:14px;">
                            Artículos disponibles
                        </h6>

                        <p class="mb-0 text-muted" style="font-size:12px;">
                            Seleccione uno o varios artículos para agregarlos al estudio.
                            Los datos de categoría, presentación y condición de costeo
                            serán copiados automáticamente.
                        </p>

                    </div>

                </div>

                <!-- TABLA -->
                <div class="picker-table-card">

                    <div class="picker-table-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">

                        <div>

                            <h6 class="mb-0 font-weight-bold">

                                <i class="fas fa-box-open text-success mr-2"></i>
                                Catálogo General

                                </h5>

                                <small>
                                    Busque y seleccione artículos
                                </small>

                        </div>

                        <div class="mt-3 mt-md-0 d-flex align-items-center">

                            <span class="badge badge-light border text-muted mr-2">
                                <span id="articlePickerSelectedCount">0</span> seleccionados
                            </span>

                            <button type="button" id="btnAddSelectedArticles" class="btn btn-success btn-sm">
                                <i class="fas fa-check-square mr-1"></i>
                                Agregar seleccionados
                            </button>

                        </div>

                    </div>

                    <div class="table-responsive">

                        <table id="tableMarketStudyArticlePicker" class="table table-hover align-middle w-100 mb-0">

                            <thead>

                                <tr>

                                    <th width="44" class="text-center">
                                        <input type="checkbox" id="checkAllArticlePicker" title="Seleccionar visibles">
                                    </th>

                                    <th width="70">ID</th>

                                    <th width="120">CÓDIGO</th>

                                    <th>NOMBRE FACTURACIÓN</th>

                                    <th>CATEGORÍA</th>

                                    <th>SUBCATEGORÍA</th>

                                    <th>PRESENTACIÓN</th>

                                </tr>

                            </thead>

                            <tbody></tbody>

                        </table>

                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer border-0 bg-white">

                <button type="button" class="btn btn-light px-2" data-dismiss="modal">

                    <i class="fas fa-times mr-1"></i>
                    Cerrar

                </button>

                <button type="button" id="btnAddSelectedArticlesFooter" class="btn btn-success px-3">

                    <i class="fas fa-plus mr-1"></i>
                    Agregar seleccionados

                </button>

            </div>

        </div>

    </div>

</div>

<style>
    /* OSCURECER FUERTE EL FONDO */
    .modal-backdrop.show {
        opacity: .45 !important;
    }

    /* MODAL */
    .article-picker-modal {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .35);
    }

    /* HEADER */
    .article-picker-header {
        background: linear-gradient(135deg,
                #198754,
                #146c43);

        color: #fff;

        padding: 15px 22px;

        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .article-picker-header h3 {
        margin: 0;
        font-weight: 700;
    }

    .article-picker-header p {
        opacity: .9;
    }

    .article-picker-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .15);

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 12px;
    }

    .article-picker-close {
        border: none;
        background: rgba(255, 255, 255, .15);

        color: #fff;

        width: 30px;
        height: 30px;

        border-radius: 12px;
    }

    .article-picker-close:hover {
        background: rgba(255, 255, 255, .25);
    }

    /* INFO */
    .picker-info-box {
        background: #f8fffb;
        border: 1px solid #d4edda;

        border-radius: 16px;

        padding: 10px;

        display: flex;
        align-items: center;

        margin-bottom: 15px;
    }

    .picker-info-icon {
        width: 30px;
        height: 30px;

        border-radius: 50%;

        background: #198754;
        color: #fff;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 14px;

        margin-right: 15px;
    }

    /* CARD TABLA */
    .picker-table-card {
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .06);
    }

    .picker-table-header {
        padding: 12px 18px;
        border-bottom: 1px solid #eee;
    }

    /* TABLA */
    #tableMarketStudyArticlePicker thead th {

        background: #f8f9fa;

        border: none;

        font-size: 12px;
        font-weight: 700;

        color: #555;

        text-transform: uppercase;
    }

    #tableMarketStudyArticlePicker tbody td {

        padding: 14px 10px;
        vertical-align: middle;
        font-size: 12px;
    }

    #tableMarketStudyArticlePicker tbody tr:hover {

        background: #f8fff9;
        font-size: 12px;
    }

    /* DATATABLE */
    #articlePickerModal .dataTables_filter input {

        border-radius: 12px !important;
        border: 1px solid #dee2e6 !important;

        padding: 8px 12px !important;
    }

    #articlePickerModal .dataTables_length select {

        border-radius: 10px !important;
    }

    #tableMarketStudyArticlePicker .form-check-input,
    #checkAllArticlePicker {
        cursor: pointer;
        margin-top: 0;
        transform: scale(1.05);
    }

    /* ANIMACIÓN */
    #articlePickerModal .modal-dialog {

        transform: scale(.95);
        transition: .2s ease;
    }

    #articlePickerModal.show .modal-dialog {

        transform: scale(1);
    }
</style>
