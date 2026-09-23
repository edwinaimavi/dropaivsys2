<div class="modal fade work-agenda-modal" id="workAgendaFormModal" tabindex="-1" role="dialog" aria-labelledby="workAgendaFormModalTitle" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="workAgendaForm" autocomplete="off">
                @csrf
                <input type="hidden" id="workAgendaItemId">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <span class="work-agenda-modal-icon"><i class="fas fa-calendar-plus"></i></span>
                        <div>
                            <small class="work-agenda-modal-eyebrow">AGENDA DE TRABAJO</small>
                            <h5 class="modal-title" id="workAgendaFormModalTitle">Nueva actividad</h5>
                            <p class="mb-0">Registre la fecha, responsable y seguimiento de la actividad.</p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <section class="work-agenda-form-section">
                        <header><span><i class="fas fa-building"></i></span><div><h6>Asignación</h6><p>Empresa y persona responsable de la actividad.</p></div></header>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="work_agenda_company_id">Empresa <b>*</b></label>
                                <select id="work_agenda_company_id" name="company_id" class="form-control" required>
                                    <option value="">Seleccione una empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>
                                    @endforeach
                                </select>
                                <span class="invalid-feedback" data-error-for="company_id"></span>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="work_agenda_responsible_user_id">Responsables <b>*</b></label>
                                @if($canAssign)
                                    <select id="work_agenda_responsible_user_id" name="responsible_user_ids[]" class="form-control" multiple disabled required>
                                        <option value="">Seleccione primero una empresa</option>
                                    </select>
                                @else
                                    <div class="work-agenda-current-user"><span><i class="fas fa-user-check"></i></span><div><small>Responsable asignado</small><strong>{{ trim(auth()->user()->name.' '.auth()->user()->lastname) }}</strong></div></div>
                                    <input type="hidden" id="work_agenda_responsible_user_id" name="responsible_user_ids[]" value="{{ auth()->id() }}">
                                @endif
                                <span class="invalid-feedback" data-error-for="responsible_user_ids"></span>
                            </div>
                        </div>
                    </section>

                    <section class="work-agenda-form-section">
                        <header><span><i class="fas fa-clipboard-list"></i></span><div><h6>Información principal</h6><p>Detalle que permitirá identificar la actividad.</p></div></header>
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="work_agenda_title">Título <b>*</b></label>
                                <input type="text" id="work_agenda_title" name="title" maxlength="180" class="form-control" placeholder="Ej. Seguimiento de orden pendiente" required>
                                <span class="invalid-feedback" data-error-for="title"></span>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="work_agenda_activity_type">Tipo de actividad</label>
                                <select id="work_agenda_activity_type" name="activity_type" class="form-control">
                                    <option value="">Sin tipo</option>
                                    @foreach($activityTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                                </select>
                                <span class="invalid-feedback" data-error-for="activity_type"></span>
                            </div>
                            <div class="form-group col-12 d-none" id="workAgendaOtherTypeWrap">
                                <label for="work_agenda_activity_type_other">Especifique el tipo <b>*</b></label>
                                <input type="text" id="work_agenda_activity_type_other" name="activity_type_other" maxlength="50" class="form-control">
                                <span class="invalid-feedback" data-error-for="activity_type_other"></span>
                            </div>
                            <div class="form-group col-12">
                                <label for="work_agenda_description">Descripción</label>
                                <textarea id="work_agenda_description" name="description" rows="3" maxlength="10000" class="form-control" placeholder="Contexto, acuerdos o información necesaria"></textarea>
                                <span class="invalid-feedback" data-error-for="description"></span>
                            </div>
                        </div>
                    </section>

                    <section class="work-agenda-form-section mb-0">
                        <header><span><i class="far fa-clock"></i></span><div><h6>Programación y seguimiento</h6><p>Fecha, horario, prioridad y estado operativo.</p></div></header>
                        <div class="form-row align-items-end">
                            <div class="form-group col-lg-3 col-md-6">
                                <label for="work_agenda_activity_date">Fecha <b>*</b></label>
                                <input type="date" id="work_agenda_activity_date" name="activity_date" class="form-control" required>
                                <span class="invalid-feedback" data-error-for="activity_date"></span>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label class="d-block">Duración</label>
                                <div class="custom-control custom-switch work-agenda-all-day-switch">
                                    <input type="hidden" name="is_all_day" value="0">
                                    <input type="checkbox" class="custom-control-input" id="work_agenda_is_all_day" name="is_all_day" value="1">
                                    <label class="custom-control-label" for="work_agenda_is_all_day">Todo el día</label>
                                </div>
                            </div>
                            <div class="form-group col-lg-3 col-md-6 work-agenda-time-field">
                                <label for="work_agenda_starts_at">Hora inicio <b>*</b></label>
                                <input type="time" id="work_agenda_starts_at" name="starts_at" class="form-control">
                                <span class="invalid-feedback" data-error-for="starts_at"></span>
                            </div>
                            <div class="form-group col-lg-3 col-md-6 work-agenda-time-field">
                                <label for="work_agenda_ends_at">Hora fin</label>
                                <input type="time" id="work_agenda_ends_at" name="ends_at" class="form-control">
                                <span class="invalid-feedback" data-error-for="ends_at"></span>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label for="work_agenda_priority">Prioridad <b>*</b></label>
                                <select id="work_agenda_priority" name="priority" class="form-control" required>
                                    @foreach($priorities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <span class="invalid-feedback" data-error-for="priority"></span>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label for="work_agenda_status">Estado <b>*</b></label>
                                <select id="work_agenda_status" name="status" class="form-control" @cannot('agenda_trabajo.cambiar_estado') disabled @endcannot required>
                                    @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                @cannot('agenda_trabajo.cambiar_estado')<input type="hidden" id="work_agenda_status_hidden" name="status" value="pending">@endcannot
                                <span class="invalid-feedback" data-error-for="status"></span>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label for="work_agenda_location">Lugar / referencia</label>
                                <input type="text" id="work_agenda_location" name="location" maxlength="180" class="form-control" placeholder="Oficina, cliente o enlace">
                                <span class="invalid-feedback" data-error-for="location"></span>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label for="work_agenda_reminder_at">Recordatorio</label>
                                <input type="datetime-local" id="work_agenda_reminder_at" name="reminder_at" class="form-control">
                                <span class="invalid-feedback" data-error-for="reminder_at"></span>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <small><i class="fas fa-shield-alt mr-1"></i>La asignación será validada nuevamente por el servidor.</small>
                    <div>
                        <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                        <button type="submit" id="btnSaveWorkAgenda" class="btn btn-success"><i class="fas fa-save mr-1"></i><span>Guardar actividad</span></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
