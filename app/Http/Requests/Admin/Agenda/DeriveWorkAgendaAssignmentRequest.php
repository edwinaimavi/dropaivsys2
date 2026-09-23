<?php

namespace App\Http\Requests\Admin\Agenda;

use App\Models\WorkAgendaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeriveWorkAgendaAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkAgendaItem::PERMISSION_DERIVE) ?? false;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 1))],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Debe seleccionar el usuario destino.',
            'to_user_id.exists' => 'El usuario destino no está disponible.',
            'reason.required' => 'El motivo de la derivación es obligatorio.',
            'reason.max' => 'El motivo no debe superar los 1000 caracteres.',
        ];
    }
}
