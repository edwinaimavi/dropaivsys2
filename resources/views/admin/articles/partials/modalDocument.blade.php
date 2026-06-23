<!-- MODAL DOCUMENT -->
<div class="modal fade" id="documentModal" tabindex="-1" role="dialog" aria-hidden="true">

    <div class="modal-dialog modal-md modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            {{-- HEADER --}}
            <div class="modal-header py-2"
                style="
                    background:#f8f9fa;
                    border-bottom:1px solid #dee2e6;
                ">

                <div class="d-flex align-items-center">

                    <div class="mr-2 d-flex align-items-center justify-content-center"
                        style="
                            width:40px;
                            height:40px;
                            border-radius:10px;
                            background:#d1e7dd;
                        ">

                        <i class="fas fa-file-pdf text-success"></i>

                    </div>

                    <div>

                        <h6 class="mb-0 font-weight-bold">

                            Agregar Documento

                        </h6>

                        <small class="text-muted">

                            Documentación del artículo

                        </small>

                    </div>

                </div>

                <button type="button" class="close" data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            {{-- BODY --}}
            <div class="modal-body p-3">

                <form id="documentForm">

                    <div class="form-group mb-2">

                        <label class="mb-1">

                            Tipo Documento

                        </label>

                        <select id="document_type_id" class="form-control form-control-sm">

                            <option value="">

                                Seleccione

                            </option>

                            @foreach ($documentTypes as $documentType)
                                <option value="{{ $documentType->id }}">

                                    {{ $documentType->description }}

                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="form-group mb-2">

                        <label class="mb-1">

                            Archivo PDF

                        </label>

                        <div class="border rounded text-center py-3"
                            style="
                                border-style:dashed !important;
                                cursor:pointer;
                                background:#fafafa;
                            "
                            onclick="document.getElementById('document_file').click()">

                            <i class="fas fa-cloud-upload-alt text-success fa-lg"></i>

                            <div class="font-weight-bold mt-1">

                                Seleccionar PDF

                            </div>

                            <small id="selectedFileName" class="text-muted d-block">

                                Ningún archivo seleccionado

                            </small>

                        </div>

                        <input type="file" id="document_file" accept=".pdf" style="display:none;">

                    </div>

                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group mb-2">

                                <label class="mb-1">

                                    Fecha Emisión

                                </label>

                                <input type="date" id="issue_date" class="form-control form-control-sm">

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group mb-2">

                                <label class="mb-1">

                                    Fecha Vencimiento

                                </label>

                                <input type="date" id="expiration_date" class="form-control form-control-sm">

                            </div>

                        </div>

                    </div>

                    <div class="form-group mb-2">

                        <label class="mb-1">

                            Observación

                        </label>

                        <textarea id="document_observation" rows="2" class="form-control form-control-sm"></textarea>

                    </div>

                    <div class="alert alert-light border py-2 px-3 mb-0">

                        <small>

                            <i class="fas fa-info-circle text-primary"></i>

                            Registro Sanitario, Ficha Técnica,
                            ISO, BPM, Protocolos de Análisis

                        </small>

                    </div>

                </form>

            </div>

            {{-- FOOTER --}}
            <div class="modal-footer py-2">

                <button type="button" class="btn btn-light btn-sm border" data-dismiss="modal">

                    <i class="fas fa-times"></i>

                    Cancelar

                </button>

                <button type="button" class="btn btn-success btn-sm" id="btnSaveDocument">

                    <i class="fas fa-plus-circle"></i>

                    Agregar

                </button>

            </div>

        </div>

    </div>

</div>
