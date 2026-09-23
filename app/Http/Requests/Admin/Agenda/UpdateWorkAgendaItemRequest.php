<?php

namespace App\Http\Requests\Admin\Agenda;

class UpdateWorkAgendaItemRequest extends StoreWorkAgendaItemRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agenda_trabajo.editar') ?? false;
    }
}
