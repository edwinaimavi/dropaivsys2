<div class="modal fade petty-image-editor-modal" id="pettyCashImageEditorModal" tabindex="-1" role="dialog" aria-labelledby="pcie_title" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-crop-alt"></i></span>
                    <div><small>EDICIÓN PREVIA</small><h4 id="pcie_title">Editar comprobante</h4><p>Ajusta la orientación o recorta la imagen antes de guardarla.</p></div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>
            <div class="modal-body petty-image-editor-body">
                <div class="petty-image-editor-tools" role="toolbar" aria-label="Herramientas de edición">
                    <button type="button" class="btn" id="pcie_rotate_left" title="Girar izquierda"><i class="fas fa-undo"></i><span>Girar izquierda</span></button>
                    <button type="button" class="btn" id="pcie_rotate_right" title="Girar derecha"><i class="fas fa-redo"></i><span>Girar derecha</span></button>
                    <button type="button" class="btn" id="pcie_crop" title="Activar recorte libre"><i class="fas fa-crop-alt"></i><span>Recortar</span></button>
                    <button type="button" class="btn" id="pcie_reset" title="Restablecer imagen"><i class="fas fa-sync-alt"></i><span>Restablecer</span></button>
                </div>
                <div class="petty-image-editor-stage"><img id="pcie_image" src="" alt="Comprobante para editar"></div>
                <small class="petty-image-editor-help"><i class="fas fa-info-circle mr-1"></i>Arrastra los bordes para recortar libremente. Los cambios se aplicarán al archivo temporal.</small>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="pcie_apply"><i class="fas fa-check mr-1"></i>Aplicar cambios</button>
            </div>
        </div>
    </div>
</div>
