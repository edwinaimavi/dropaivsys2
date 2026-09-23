<?php

namespace App\Http\Requests\Admin\Agenda;

use App\Models\WorkAgendaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agenda_trabajo.crear') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $assignments = $this->input('responsible_user_ids');
        if ($assignments === null && $this->filled('responsible_user_id')) {
            $assignments = [$this->input('responsible_user_id')];
        }

        $this->merge(['is_all_day' => $this->boolean('is_all_day'), 'responsible_user_ids' => $assignments]);
    }

    public function rules(): array
    {
        $user = $this->user();
        $canAssignOthers = $user?->can(WorkAgendaItem::PERMISSION_ASSIGN) ?? false;

        return [
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query
                    ->where('status', true)
                    ->whereNull('deleted_at')),
                Rule::exists('company_user', 'company_id')->where(fn ($query) => $query
                    ->where('user_id', $user?->getKey())),
            ],
            'responsible_user_ids' => $canAssignOthers
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array', 'max:1'],
            'responsible_user_ids.*' => $canAssignOthers
                ? [
                    'integer',
                    'distinct',
                    Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 1)),
                    Rule::exists('company_user', 'user_id')->where(fn ($query) => $query
                        ->where('company_id', $this->input('company_id'))),
                ]
                : ['integer', Rule::in([$user?->getKey()])],
            'responsible_user_id' => $canAssignOthers
                ? ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where(fn ($query) => $query
                    ->where('company_id', $this->input('company_id')))]
                : ['nullable', 'integer', Rule::in([$user?->getKey()])],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'activity_date' => ['required', 'date_format:Y-m-d'],
            'is_all_day' => ['required', 'boolean'],
            'starts_at' => [
                Rule::requiredIf(! $this->boolean('is_all_day')),
                'nullable',
                'date_format:H:i',
            ],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'activity_type' => ['nullable', 'string', Rule::in(WorkAgendaItem::ACTIVITY_TYPES)],
            'activity_type_other' => ['nullable', 'string', 'max:50', 'required_if:activity_type,Otro'],
            'priority' => ['required', Rule::in(array_keys(WorkAgendaItem::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(WorkAgendaItem::STATUSES))],
            'location' => ['nullable', 'string', 'max:180'],
            'reminder_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Debe seleccionar una empresa.',
            'company_id.exists' => 'La empresa no está autorizada o no se encuentra disponible.',
            'responsible_user_ids.required' => 'Debe seleccionar al menos un responsable.',
            'responsible_user_ids.min' => 'Debe seleccionar al menos un responsable.',
            'responsible_user_ids.*.exists' => 'Uno de los responsables no pertenece a la empresa seleccionada.',
            'responsible_user_ids.*.distinct' => 'No puede repetir responsables.',
            'responsible_user_ids.*.in' => 'No puede asignar actividades a otro usuario.',
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no debe superar los 180 caracteres.',
            'activity_date.required' => 'La fecha es obligatoria.',
            'activity_date.date_format' => 'La fecha no tiene un formato válido.',
            'starts_at.required' => 'La hora de inicio es obligatoria cuando la actividad no dura todo el día.',
            'starts_at.date_format' => 'La hora de inicio no tiene un formato válido.',
            'ends_at.date_format' => 'La hora de fin no tiene un formato válido.',
            'ends_at.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'activity_type_other.required_if' => 'Especifique el tipo de actividad.',
            'priority.required' => 'Debe seleccionar una prioridad.',
            'priority.in' => 'La prioridad seleccionada no es válida.',
            'status.required' => 'Debe seleccionar un estado.',
            'status.in' => 'El estado seleccionado no es válido.',
            'reminder_at.date_format' => 'El recordatorio no tiene un formato válido.',
        ];
    }
}
