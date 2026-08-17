$(function () {
    if (!document.getElementById('tableGeneralCash')) return;

    const routes = window.generalCashRoutes || {};
    const permissions = window.generalCashPermissions || {};
    const csrf = $('meta[name="csrf-token"]').attr('content');
    let table;

    const money = value => Number(value || 0).toLocaleString('es-PE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const escapeHtml = value => $('<div>').text(value ?? '').html();
    const uuid = prefix => `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    const dateText = value => value ? new Date(value).toLocaleString('es-PE') : '—';
    const statusLabel = value => ({REGISTERED:'Registrado',APPROVED:'Aprobado',OBSERVED:'Observado',CANCELLED:'Anulado',CLOSED:'Cerrado',ACTIVE:'Activo',INACTIVE:'Inactivo'}[value] || value || '—');
    const status = value => `<span class="general-cash-status ${escapeHtml(value)}">${escapeHtml(statusLabel(value))}</span>`;
    const empty = text => `<div class="general-cash-empty"><i class="fas fa-inbox d-block mb-2"></i>${escapeHtml(text)}</div>`;

    function notifyError(xhr) {
        const errors = xhr.responseJSON?.errors || {};
        const message = Object.values(errors).flat()[0] || xhr.responseJSON?.message || 'No se pudo completar la operación.';
        Swal.fire('No se pudo guardar', message, 'error');
    }

    function setBusy(form, busy) {
        const button = $(form).find('button[type="submit"]').prop('disabled', busy);
        if (form.id === 'generalCashFundingForm') {
            button.find('i')
                .toggleClass('fa-arrow-right', !busy)
                .toggleClass('fa-spinner fa-spin', busy);
            button.find('span').text(busy ? 'Procesando...' : 'Ingresar efectivo');
        }
    }

    function submitForm(form, url, method, success) {
        const data = new FormData(form);
        if (method !== 'POST') data.append('_method', method);
        setBusy(form, true);
        $.ajax({url, method:'POST', data, processData:false, contentType:false, headers:{'X-CSRF-TOKEN':csrf}})
            .done(response => {
                $(form).closest('.modal').modal('hide');
                Swal.fire('Operación completada', response.message, 'success');
                table.ajax.reload(null, false);
                success?.(response);
            }).fail(notifyError).always(() => setBusy(form, false));
    }

    function summaryValue(rows, key) {
        if (!rows?.length) return 'S/ 0.00';
        return rows.map(row => `${row.symbol || row.currency} ${money(row[key])}`).join(' · ');
    }

    table = $('#tableGeneralCash').DataTable({
        processing:true, serverSide:true, responsive:false, autoWidth:false,
        ajax:{url:routes.list,data:d=>Object.assign(d,{
            company_id:$('#generalCashFilterCompany').val(), currency_id:$('#generalCashFilterCurrency').val(),
            status:$('#generalCashFilterStatus').val(), date_from:$('#generalCashFilterFrom').val(), date_to:$('#generalCashFilterTo').val()
        }),dataSrc:json=>{
            const rows=json.summary?.currencies||[];
            $('#generalCashSummaryTotal,#generalCashSummaryAvailable').text(summaryValue(rows,'balance'));
            $('#generalCashSummaryIncome').text(summaryValue(rows,'income'));
            $('#generalCashSummaryExpense').text(summaryValue(rows,'expense'));
            $('#generalCashSummaryPending').text(json.summary?.pending||0);
            return json.data;
        }},
        columns:[
            {data:'DT_RowIndex',orderable:false,searchable:false},{data:'code'},{data:'name'},{data:'company'},
            {data:'currency'},{data:'responsible'},{data:'income',orderable:false,searchable:false},
            {data:'expense',orderable:false,searchable:false},{data:'current_balance'},{data:'status'},
            {data:'actions',orderable:false,searchable:false}
        ],
        order:[[1,'desc']], language:{url:'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'}
    });

    $('#btnGeneralCashFilter').on('click', () => table.ajax.reload());

    function resetBoxModal() {
        document.getElementById('generalCashBoxForm').reset();
        $('#general_cash_box_id').val('');
        $('#generalCashBoxModalTitle').text('Nueva Caja General');
        $('#general_cash_status').val('ACTIVE');
        $('#generalCashBoxModal').modal('show');
    }
    $('#btnNewGeneralCash').on('click', resetBoxModal);
    $('#generalCashBoxForm').on('submit', function (event) {
        event.preventDefault();
        const id=$('#general_cash_box_id').val();
        submitForm(this,id?`${routes.show}/${id}`:routes.store,id?'PUT':'POST');
    });

    $(document).on('click','.btn-general-cash-edit',function(){
        $.get(`${routes.show}/${$(this).data('id')}`).done(response=>{
            const box=response.data.generalCash;
            $('#general_cash_box_id').val(box.id); $('#general_cash_company_id').val(box.company_id);
            $('#general_cash_currency_id').val(box.currency_id); $('#general_cash_name').val(box.name);
            $('#general_cash_status').val(box.status); $('#general_cash_responsible_user_id').val(box.responsible_user_id||'');
            $('#general_cash_description').val(box.description||''); $('#generalCashBoxModalTitle').text('Editar Caja General');
            $('#generalCashBoxModal').modal('show');
        }).fail(notifyError);
    });

    function resetFundingVisuals() {
        $('#generalCashFundingFileName')
            .removeClass('has-file')
            .html('<i class="far fa-file mr-1"></i>Ningún archivo seleccionado');
        $('.general-cash-funding-upload').removeClass('has-file is-dragging');
        $('#generalCashFundingObservationCount').text('0 / 1500');
    }

    function openFunding(boxId='') {
        document.getElementById('generalCashFundingForm').reset();
        resetFundingVisuals();
        $('#general_cash_funding_key').val(uuid('funding'));
        $('#general_cash_funding_box_id').val(boxId).trigger('change');
        $('#generalCashFundingModal').modal('show');
    }
    $('#btnGeneralCashFunding').on('click',()=>openFunding());
    $(document).on('click','.btn-general-cash-fund',function(){openFunding($(this).data('id'));});
    $('#general_cash_funding_box_id').on('change',function(){
        const option=$(this).find(':selected'); const account=$('#general_cash_bank_account_id');
        account.html('<option value="">Cargando cuentas...</option>');
        $('#generalCashBankBalance').html('<i class="fas fa-circle-notch fa-spin mr-1"></i>Consultando cuentas disponibles...');
        if(!this.value) {
            $('#generalCashBankBalance').html('<i class="fas fa-info-circle mr-1"></i>El saldo disponible aparecerá al seleccionar una cuenta.');
            return account.html('<option value="">Seleccione primero una caja</option>');
        }
        $.get(routes.bankAccounts,{company_id:option.data('company'),currency_id:option.data('currency')}).done(response=>{
            const rows=response.data||[];
            account.html(rows.length?'<option value="">Seleccione</option>':'<option value="">No hay cuentas activas disponibles</option>');
            rows.forEach(row=>account.append(`<option value="${row.id}" data-balance="${row.current_balance}">${escapeHtml(row.bank?.short_name||row.bank?.description)} · ${escapeHtml(row.account_number)} · Saldo ${money(row.current_balance)}</option>`));
            $('#generalCashBankBalance').html(rows.length
                ? '<i class="fas fa-info-circle mr-1"></i>Selecciona una cuenta para consultar su saldo.'
                : '<i class="fas fa-exclamation-circle mr-1"></i>No hay cuentas activas para esta caja y moneda.');
        }).fail(notifyError);
    });
    $('#general_cash_bank_account_id').on('change',function(){
        const balance=$(this).find(':selected').data('balance');
        $('#generalCashBankBalance').html(balance===undefined
            ? '<i class="fas fa-info-circle mr-1"></i>Selecciona una cuenta para consultar su saldo.'
            : `<i class="fas fa-wallet mr-1"></i>Saldo bancario disponible: <strong>${money(balance)}</strong>`);
    });
    $('#general_cash_funding_support_file').on('change',function(){
        const file=this.files?.[0];
        $('.general-cash-funding-upload').toggleClass('has-file', Boolean(file));
        $('#generalCashFundingFileName')
            .toggleClass('has-file', Boolean(file))
            .html(file
                ? `<i class="fas fa-check-circle mr-1"></i>${escapeHtml(file.name)}`
                : '<i class="far fa-file mr-1"></i>Ningún archivo seleccionado');
    }).on('dragenter dragover',function(){$('.general-cash-funding-upload').addClass('is-dragging');})
        .on('dragleave drop',function(){$('.general-cash-funding-upload').removeClass('is-dragging');});
    $('#general_cash_funding_observation').on('input',function(){
        $('#generalCashFundingObservationCount').text(`${this.value.length} / 1500`);
    });
    $('#generalCashFundingForm').on('submit',function(e){e.preventDefault();submitForm(this,routes.funding,'POST');});

    function openExpense(boxId='') {
        document.getElementById('generalCashExpenseForm').reset();
        $('#general_cash_expense_key').val(uuid('expense')); $('#general_cash_expense_box_id').val(boxId);
        $('#general_cash_expense_document_type').val('FACTURA').trigger('change');
        $('#generalCashExpenseModal').modal('show');
    }
    $('#btnGeneralCashExpense').on('click',()=>openExpense());
    $(document).on('click','.btn-general-cash-expense',function(){openExpense($(this).data('id'));});
    $('#general_cash_expense_supplier_id').on('change',function(){const option=$(this).find(':selected');if(this.value){$('#general_cash_expense_person_name').val(option.data('name'));$('#general_cash_expense_identity').val(option.data('ruc'));}});
    $('#general_cash_expense_document_type').on('change',function(){
        const supportsIgv=['FACTURA','BOLETA'].includes(this.value); $('#general_cash_expense_affects_igv').val('0').prop('disabled',!supportsIgv);
    });
    $('#generalCashExpenseForm').on('submit',function(e){e.preventDefault();$('#general_cash_expense_affects_igv').prop('disabled',false);submitForm(this,routes.expense,'POST');});

    function openReconciliation(boxId='') {
        document.getElementById('generalCashReconciliationForm').reset();
        $('#general_cash_reconciliation_box_id').val(boxId).trigger('change');
        $('#generalCashReconciliationModal').modal('show');
    }
    $('#btnGeneralCashReconciliation').on('click',()=>openReconciliation());
    $(document).on('click','.btn-general-cash-reconcile',function(){openReconciliation($(this).data('id'));});
    $('#general_cash_reconciliation_box_id').on('change',function(){
        const id=this.value; if(!id){$('#general_cash_system_balance,#general_cash_difference').val('0.00');return;}
        $.get(`${routes.show}/${id}`).done(response=>{const balance=Number(response.data.generalCash.current_balance||0);$('#general_cash_system_balance').val(balance.toFixed(2)).data('balance',balance);updateDifference();}).fail(notifyError);
    });
    $('#general_cash_physical_balance').on('input',updateDifference);
    function updateDifference(){const system=Number($('#general_cash_system_balance').data('balance')||0);const physical=Number($('#general_cash_physical_balance').val()||0);$('#general_cash_difference').val((physical-system).toFixed(2));}
    $('#generalCashReconciliationForm').on('submit',function(e){e.preventDefault();submitForm(this,routes.reconciliation,'POST');});

    $(document).on('click','.btn-general-cash-view',function(){showDetail($(this).data('id'));});
    function showDetail(id){
        $('#generalCashSummaryTab').html(empty('Cargando detalle...')); $('#generalCashDetailModal').modal('show');
        $.get(`${routes.show}/${id}`).done(response=>renderDetail(response.data)).fail(xhr=>{$('#generalCashDetailModal').modal('hide');notifyError(xhr);});
    }

    function renderDetail(data){
        const box=data.generalCash, symbol=box.currency?.symbol||box.currency?.code||'';
        $('#generalCashDetailTitle').text(`${box.code} · ${box.name}`);
        $('#generalCashDetailSubtitle').text(`${box.company?.trade_name||box.company?.business_name} · ${box.currency?.code} · ${statusLabel(box.status)}`);
        const income=(data.movements||[]).filter(x=>x.direction==='IN').reduce((s,x)=>s+Number(x.amount),0);
        const expense=(data.movements||[]).filter(x=>x.direction==='OUT').reduce((s,x)=>s+Number(x.amount),0);
        $('#generalCashSummaryTab').html(`<div class="general-cash-detail-kpis"><div class="general-cash-detail-kpi"><small>Saldo disponible</small><strong>${symbol} ${money(box.current_balance)}</strong></div><div class="general-cash-detail-kpi"><small>Ingresos</small><strong>${symbol} ${money(income)}</strong></div><div class="general-cash-detail-kpi"><small>Egresos</small><strong>${symbol} ${money(expense)}</strong></div><div class="general-cash-detail-kpi"><small>Responsable</small><strong>${escapeHtml([box.responsible?.name,box.responsible?.lastname].filter(Boolean).join(' ')||'No asignado')}</strong></div></div><div class="card border-0 shadow-sm mt-3"><div class="card-body"><small class="text-muted d-block">DESCRIPCIÓN</small>${escapeHtml(box.description||'Sin descripción registrada.')}</div></div>`);
        renderMovements(data.movements||[],symbol); renderExpenses(data.expenses||[],symbol); renderDocuments(data); renderReconciliations(data.reconciliations||[],symbol); renderTrace(data.trace||[]);
    }

    function tableWrap(headers,rows){return rows?`<div class="table-responsive"><table class="table table-hover general-cash-detail-table"><thead><tr>${headers.map(x=>`<th>${x}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div>`:empty('No hay información registrada.');}
    function renderMovements(items,symbol){
        const rows=items.map(item=>{const cancel=permissions.cancelFunding&&item.source_type==='GENERAL_CASH_FUNDING'&&item.status==='REGISTERED'?`<button class="btn btn-outline-danger btn-xs btn-cancel-funding" data-id="${item.id}"><i class="fas fa-ban"></i></button>`:'';return `<tr><td>${dateText(item.movement_date)}</td><td><strong>${escapeHtml(item.code)}</strong></td><td>${escapeHtml(item.source_type==='GENERAL_CASH_FUNDING'?'Ingreso desde banco':item.source_type==='GENERAL_CASH_EXPENSE'?'Gasto general':'Reversa')}</td><td>${escapeHtml(item.description)}</td><td class="text-right text-success">${item.direction==='IN'?`${symbol} ${money(item.amount)}`:''}</td><td class="text-right text-danger">${item.direction==='OUT'?`${symbol} ${money(item.amount)}`:''}</td><td class="text-right">${symbol} ${money(item.new_balance)}</td><td>${status(item.status)}</td><td>${cancel}</td></tr>`;}).join('');
        $('#generalCashMovementsTab').html(tableWrap(['Fecha','Código','Origen','Detalle','Ingreso','Egreso','Saldo','Estado',''],rows));
    }
    function renderExpenses(items,symbol){
        const rows=items.map(item=>{let actions='';if(permissions.approve&&['REGISTERED','OBSERVED'].includes(item.status))actions+=`<button class="btn btn-outline-success btn-xs mr-1 btn-approve-expense" data-id="${item.id}" title="Aprobar"><i class="fas fa-check"></i></button>`;if(permissions.approve&&['REGISTERED','APPROVED'].includes(item.status))actions+=`<button class="btn btn-outline-warning btn-xs mr-1 btn-observe-expense" data-id="${item.id}" title="Observar"><i class="fas fa-exclamation"></i></button>`;if(permissions.cancelExpense&&item.status!=='CANCELLED')actions+=`<button class="btn btn-outline-danger btn-xs btn-cancel-expense" data-id="${item.id}" title="Anular"><i class="fas fa-ban"></i></button>`;return `<tr><td>${escapeHtml(item.code)}</td><td>${dateText(item.expense_date)}</td><td>${escapeHtml(item.expense_type)}</td><td>${escapeHtml(item.supplier?.business_name||item.person_name)}</td><td>${escapeHtml(item.document_type)}<small class="d-block text-muted">${escapeHtml([item.document_series,item.document_number].filter(Boolean).join('-'))}</small></td><td>${escapeHtml(item.concept)}</td><td class="text-right"><strong>${symbol} ${money(item.amount)}</strong></td><td>${status(item.status)}</td><td class="text-nowrap">${actions}</td></tr>`;}).join('');
        $('#generalCashExpensesTab').html(tableWrap(['Código','Fecha','Tipo','Proveedor / persona','Documento','Concepto','Importe','Estado','Acciones'],rows));
    }
    function renderDocuments(data){
        const records=[...(data.movements||[]),...(data.expenses||[]),...(data.reconciliations||[])];
        const docs=records.flatMap(record=>(record.documents||[]).map(doc=>({...doc,record_code:record.code})));
        $('#generalCashDocumentsTab').html(docs.length?`<div class="general-cash-document-list">${docs.map(doc=>`<div class="general-cash-document-card"><small class="text-muted">${escapeHtml(doc.observation||'Documento')}</small><strong class="d-block mb-2">${escapeHtml(doc.original_name||'Archivo adjunto')}</strong><small class="d-block text-muted mb-2">Origen: ${escapeHtml(doc.record_code)}</small>${doc.view_url?`<a href="${doc.view_url}" target="_blank" class="btn btn-outline-info btn-xs"><i class="fas fa-eye mr-1"></i>Ver</a>`:'<span class="text-danger small">Documento no disponible</span>'}</div>`).join('')}</div>`:empty('No hay documentos adjuntos.'));
    }
    function renderReconciliations(items,symbol){const rows=items.map(item=>`<tr><td>${dateText(item.reconciliation_date)}</td><td>${escapeHtml(item.code)}</td><td class="text-right">${symbol} ${money(item.system_balance)}</td><td class="text-right">${symbol} ${money(item.physical_balance)}</td><td class="text-right ${Number(item.difference)===0?'text-success':'text-danger'}"><strong>${symbol} ${money(item.difference)}</strong></td><td>${escapeHtml([item.responsible?.name,item.responsible?.lastname].filter(Boolean).join(' ')||item.responsible_name||'—')}</td><td>${escapeHtml(item.observation||'—')}</td></tr>`).join('');$('#generalCashReconciliationsTab').html(tableWrap(['Fecha','Código','Esperado','Físico','Diferencia','Responsable','Observación'],rows));}
    function renderTrace(items){$('#generalCashAuditTab').html(items.length?items.map(item=>`<div class="general-cash-trace-item"><span><i class="fas ${escapeHtml(item.icon)}"></i></span><div><strong>${escapeHtml(item.title)}</strong><small>${dateText(item.date)} · ${escapeHtml(item.detail||'')}</small></div></div>`).join(''):empty('Sin eventos de auditoría.'));}

    function postAction(url,data,success){$.ajax({url,method:'POST',data:{...data,_token:csrf}}).done(response=>{Swal.fire('Operación completada',response.message,'success');table.ajax.reload(null,false);$('#generalCashDetailModal').modal('hide');success?.();}).fail(notifyError);}
    $(document).on('click','.btn-approve-expense',function(){const id=$(this).data('id');Swal.fire({title:'¿Aprobar gasto?',text:'La aprobación quedará registrada en auditoría.',icon:'question',showCancelButton:true,confirmButtonText:'Sí, aprobar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)postAction(`${routes.expenses}/${id}/approve`,{});});});
    $(document).on('click','.btn-observe-expense',function(){const id=$(this).data('id');reasonAction('Observar gasto','Ingrese el motivo de la observación.',reason=>postAction(`${routes.expenses}/${id}/observe`,{reason}));});
    $(document).on('click','.btn-cancel-expense',function(){const id=$(this).data('id');reasonAction('Anular gasto','Se registrará una reversa y se restaurará el saldo.',reason=>postAction(`${routes.expenses}/${id}/cancel`,{reason}));});
    $(document).on('click','.btn-cancel-funding',function(){const id=$(this).data('id');reasonAction('Anular ingreso desde banco','Se reversarán tanto el banco como Caja General.',reason=>postAction(`${routes.cancelFunding}/${id}/cancel`,{reason}));});
    function reasonAction(title,text,callback){Swal.fire({title,text,input:'textarea',inputPlaceholder:'Motivo obligatorio',showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Cancelar',inputValidator:value=>!value||value.trim().length<5?'Ingrese un motivo de al menos 5 caracteres.':undefined}).then(r=>{if(r.isConfirmed)callback(r.value.trim());});}
    if (window.generalCashAutoOpenBoxId) showDetail(window.generalCashAutoOpenBoxId);
});
