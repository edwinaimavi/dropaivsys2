<x-table-actions-dropdown label="Acciones de la actividad">
    <x-slot name="main">
        <button type="button" class="btn btn-sm btn-success dp-action-main btn-work-agenda-view" data-id="{{ $item->id }}" title="Ver detalle"><i class="fas fa-eye mr-1"></i> Ver</button>
    </x-slot>
    <x-slot name="menu">
        @if($canEdit && $item->status !== \App\Models\WorkAgendaItem::STATUS_CANCELLED)
            <h6 class="dropdown-header">Gestión</h6>
            <button type="button" class="dropdown-item btn-work-agenda-edit" data-id="{{ $item->id }}"><i class="fas fa-pen text-primary"></i> Editar</button>
        @endif
        @if($canChangeStatus && $primaryAssignment && in_array($primaryAssignment->status, ['pending', 'completed'], true))
            <button type="button" class="dropdown-item btn-work-agenda-status" data-id="{{ $item->id }}" data-assignment-id="{{ $primaryAssignment->id }}" data-status="in_progress"><i class="fas fa-play text-info"></i> {{ $primaryAssignment->status === 'completed' ? 'Reabrir mi tarea' : 'Iniciar mi tarea' }}</button>
        @endif
        @if($canChangeStatus && $primaryAssignment && in_array($primaryAssignment->status, ['pending', 'in_progress'], true))
            <button type="button" class="dropdown-item btn-work-agenda-status" data-id="{{ $item->id }}" data-assignment-id="{{ $primaryAssignment->id }}" data-status="completed"><i class="fas fa-check text-success"></i> Concluir mi tarea</button>
        @endif
        @if($canDelete)
            <div class="dropdown-divider"></div>
            <button type="button" class="dropdown-item text-danger btn-work-agenda-delete" data-id="{{ $item->id }}"><i class="fas fa-trash"></i> Eliminar</button>
        @endif
    </x-slot>
</x-table-actions-dropdown>
