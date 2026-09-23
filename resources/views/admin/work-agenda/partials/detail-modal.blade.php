<div class="modal fade work-agenda-modal" id="workAgendaDetailModal" tabindex="-1" role="dialog" aria-labelledby="workAgendaDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <span class="work-agenda-modal-icon is-detail"><i class="fas fa-clipboard-check"></i></span>
                    <div>
                        <small class="work-agenda-modal-eyebrow">DETALLE DE ACTIVIDAD</small>
                        <h5 class="modal-title" id="workAgendaDetailTitle">Actividad</h5>
                        <p class="mb-0" id="workAgendaDetailSubtitle">Información registrada</p>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="work-agenda-detail-statusbar">
                    <span id="workAgendaDetailPriority"></span>
                    <span id="workAgendaDetailStatus"></span>
                    <span id="workAgendaDetailTimingFlag"></span>
                </div>
                <div class="work-agenda-detail-description" id="workAgendaDetailDescription">Sin descripción.</div>
                <div class="work-agenda-detail-grid">
                    <div><small>Empresa</small><strong id="workAgendaDetailCompany">—</strong></div>
                    <div><small>Responsables</small><strong id="workAgendaDetailResponsible">—</strong></div>
                    <div><small>Fecha</small><strong id="workAgendaDetailDate">—</strong></div>
                    <div><small>Horario</small><strong id="workAgendaDetailSchedule">—</strong></div>
                    <div><small>Tipo</small><strong id="workAgendaDetailType">—</strong></div>
                    <div><small>Lugar / referencia</small><strong id="workAgendaDetailLocation">—</strong></div>
                    <div><small>Recordatorio</small><strong id="workAgendaDetailReminder">—</strong></div>
                    <div><small>Creado por</small><strong id="workAgendaDetailCreator">—</strong></div>
                    <div><small>Fecha de creación</small><strong id="workAgendaDetailCreatedAt">—</strong></div>
                    <div><small>Fecha de conclusión</small><strong id="workAgendaDetailCompletedAt">—</strong></div>
                </div>
                <section class="work-agenda-trace-section">
                    <h6>RESPONSABLES Y ESTADO INDIVIDUAL</h6>
                    <div id="workAgendaAssignmentList" class="work-agenda-assignment-list"></div>
                </section>
                <section class="work-agenda-trace-section">
                    <h6>HISTORIAL / TRAZABILIDAD</h6>
                    <div id="workAgendaTimeline" class="work-agenda-timeline"></div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cerrar</button>
                <div class="work-agenda-detail-actions">
                    @can('agenda_trabajo.cambiar_estado')
                        <span id="workAgendaDetailStatusActions"></span>
                    @endcan
                    @can('agenda_trabajo.editar')
                        <button type="button" id="btnEditWorkAgendaFromDetail" class="btn btn-outline-primary"><i class="fas fa-pen mr-1"></i>Editar</button>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="workAgendaDeriveModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="workAgendaDeriveForm">
            <div class="modal-header"><div><small class="work-agenda-modal-eyebrow">DERIVACIÓN</small><h5 class="modal-title">Derivar responsabilidad</h5></div><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <input type="hidden" id="workAgendaDeriveItemId"><input type="hidden" id="workAgendaDeriveAssignmentId">
                <div class="form-group"><label for="workAgendaDeriveUser">Nuevo responsable *</label><select id="workAgendaDeriveUser" name="to_user_id" class="form-control" required></select><span class="invalid-feedback" data-error-for="to_user_id"></span></div>
                <div class="form-group mb-0"><label for="workAgendaDeriveReason">Motivo *</label><textarea id="workAgendaDeriveReason" name="reason" class="form-control" rows="3" maxlength="10000" required></textarea><span class="invalid-feedback" data-error-for="reason"></span></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Confirmar derivación</button></div>
        </form>
    </div></div>
</div>
