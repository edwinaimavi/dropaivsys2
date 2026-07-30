@props(['label' => 'Acciones'])

<div {{ $attributes->class(['dp-table-actions']) }} role="group" aria-label="{{ $label }}">
    {{ $main }}

    @if (isset($menu) && trim((string) $menu) !== '')
        <div class="dropdown dp-action-dropdown">
            <button type="button" class="btn btn-sm btn-light border dropdown-toggle dp-action-trigger"
                data-toggle="dropdown" data-boundary="window" data-display="static"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-ellipsis-v mr-1" aria-hidden="true"></i> Acciones
            </button>
            <div class="dropdown-menu dropdown-menu-right dp-action-menu">
                {{ $menu }}
            </div>
        </div>
    @endif
</div>
