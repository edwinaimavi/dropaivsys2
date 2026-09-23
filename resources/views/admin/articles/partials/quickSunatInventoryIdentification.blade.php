<input type="hidden" name="sunat_inventory_catalog_use_internal_code" class="quick-sunat-use-internal-code-flag" value="0">
<div class="col-12 mt-2 d-none quick-sunat-inventory-identification-group">
    <details class="border rounded bg-light p-2">
        <summary class="font-weight-bold" style="cursor:pointer;">
            CONFIGURACIÓN SUNAT AVANZADA
            <small class="text-muted ml-1">Se completa automáticamente con el código interno en el uso habitual.</small>
        </summary>
        <div class="mt-3">
            <div class="font-weight-bold mb-2">IDENTIFICACIÓN SUNAT DE EXISTENCIA</div>
        <div class="form-row">
            <div class="form-group col-md-5 mb-2">
                <label>CATÁLOGO PRINCIPAL <span class="text-danger">*</span></label>
                <select name="sunat_inventory_catalog_item_id"
                    class="form-control form-control-sm quick-sunat-inventory-catalog" disabled>
                    <option value="">Seleccione</option>
                </select>
                <span class="invalid-feedback"></span>
            </div>
            <div class="form-group col-md-5 mb-2">
                <label>CÓDIGO DE EXISTENCIA <span class="text-danger">*</span></label>
                <input type="text" name="sunat_inventory_catalog_code" maxlength="24"
                    class="form-control form-control-sm text-uppercase quick-sunat-inventory-code" disabled>
                <span class="invalid-feedback"></span>
                <small class="form-text text-muted d-none quick-sunat-own-code-help">
                    Puede utilizar el código interno propio del artículo.
                </small>
            </div>
            <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-primary btn-sm btn-block quick-use-internal-code" disabled>
                    Usar código interno
                </button>
            </div>
        </div>
        <div class="font-weight-bold mb-1 mt-1">
            CÓDIGO INTERNACIONAL <span class="badge badge-secondary">OPCIONAL</span>
        </div>
        <small class="text-muted d-block mb-2">Solo Naciones Unidas/UNSPSC o GS1/GTIN real.</small>
        <div class="form-row">
            <div class="form-group col-md-6 mb-0">
                <label>CATÁLOGO INTERNACIONAL</label>
                <select name="sunat_standard_catalog_item_id"
                    class="form-control form-control-sm quick-sunat-standard-catalog" disabled>
                    <option value="">No configurado</option>
                </select>
                <span class="invalid-feedback"></span>
            </div>
            <div class="form-group col-md-6 mb-0">
                <label>CÓDIGO UNSPSC / GTIN</label>
                <input type="text" name="sunat_standard_code" maxlength="128"
                    class="form-control form-control-sm text-uppercase quick-sunat-standard-code" disabled>
                <span class="invalid-feedback"></span>
            </div>
        </div>
            </div>
    </details>
</div>
