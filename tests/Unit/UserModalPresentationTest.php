<?php

it('mantiene visible el encabezado de Mi Perfil en modo claro', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/user-profile.css');

    expect($css)
        ->toContain('.dp-profile-heading .modal-title')
        ->toContain('color: #0f172a !important;')
        ->toContain('.dp-profile-heading small')
        ->toContain('color: #64748b !important;')
        ->toContain('.theme-dark .dp-profile-heading .modal-title');
});

it('organiza el detalle del usuario en tres pestañas de solo lectura', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/users/partials/viewModal.blade.php');

    expect($view)
        ->toContain('id="user-detail-personal-tab"')
        ->toContain('id="user-detail-access-tab"')
        ->toContain('id="user-detail-trace-tab"')
        ->toContain('id="vu_photo_placeholder"')
        ->toContain('id="vu_principal_indicator"')
        ->not->toContain('<form')
        ->not->toContain('name="role"')
        ->not->toContain('name="status"');
});
