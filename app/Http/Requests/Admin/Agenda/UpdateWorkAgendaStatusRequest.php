<?php

namespace App\Http\Requests\Admin\Agenda;

use App\Models\WorkAgendaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkAgendaStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agenda_trabajo.cambiar_estado') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(WorkAgendaItem::STATUSES))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Debe indicar el nuevo estado.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
