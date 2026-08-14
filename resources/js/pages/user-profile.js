let currentProfileData = null;

document.addEventListener('DOMContentLoaded', function () {
    const $modal = $('#modalUserProfile');
    const $form = $('#userProfileForm');

    if (!$modal.length || !$form.length || !window.DropaivProfile) return;

    $(document).on('click', '#btnOpenUserProfile', function (event) {
        event.preventDefault();
        resetProfileFormState();
        $('#profile-personal-tab').tab('show');
        $modal.modal('show');
        loadUserProfile();
    });

    $('#profileImage').on('change', function () {
        previewProfileImage(this);
    });

    $('#profileDni').on('input', function () {
        this.value = String(this.value || '').replace(/\D/g, '').slice(0, 8);
    });

    $(document).on('click', '[data-profile-password-toggle]', function () {
        const $button = $(this);
        const $input = $($button.data('profile-password-toggle'));
        const showPassword = $input.attr('type') === 'password';

        $input.attr('type', showPassword ? 'text' : 'password');
        $button.find('i').toggleClass('fa-eye', !showPassword).toggleClass('fa-eye-slash', showPassword);
        $button.attr('aria-label', showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        saveUserProfile(this);
    });

    $modal.on('hidden.bs.modal', function () {
        resetProfileFormState();
        currentProfileData = null;
    });
});

function loadUserProfile() {
    setProfileLoading(true);
    clearProfileErrors();

    $.ajax({
        url: window.DropaivProfile.showUrl,
        type: 'GET',
        dataType: 'json'
    }).done(function (response) {
        currentProfileData = response.data || {};
        renderUserProfile(currentProfileData);
    }).fail(function (xhr) {
        $('#modalUserProfile').modal('hide');
        Swal.fire({
            icon: 'error',
            title: 'No se pudo cargar el perfil',
            text: xhr.responseJSON?.message || 'Inténtalo nuevamente en unos momentos.'
        });
    }).always(function () {
        setProfileLoading(false);
    });
}

function saveUserProfile(form) {
    clearProfileErrors();
    setProfileSaving(true);

    const formData = new FormData(form);
    formData.append('_method', 'PUT');

    $.ajax({
        url: window.DropaivProfile.updateUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': window.DropaivProfile.csrf,
            'Accept': 'application/json'
        }
    }).done(function (response) {
        currentProfileData = response.data || {};
        renderUserProfile(currentProfileData);
        clearProfileSecurityFields();
        $('#profileImage').val('');
        updateProfileNavbar(currentProfileData);

        Swal.fire({
            icon: 'success',
            title: response.message || 'Perfil actualizado correctamente.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }).fail(function (xhr) {
        if (xhr.status === 422 && xhr.responseJSON?.errors) {
            showProfileErrors(xhr.responseJSON.errors);
            return;
        }

        Swal.fire({
            icon: 'error',
            title: 'No se pudo guardar',
            text: xhr.responseJSON?.message || 'Ocurrió un error al actualizar tu perfil.'
        });
    }).always(function () {
        setProfileSaving(false);
    });
}

function renderUserProfile(profile) {
    const historical = 'No registrado / histórico';

    $('#profileDni').val(profile.dni || '');
    $('#profileName').val(profile.name || '');
    $('#profileLastname').val(profile.lastname || '');
    $('#profileEmail').val(profile.email || '');
    $('#profilePhone').val(profile.phone || '');
    $('#profileAddress').val(profile.address || '');

    $('#profileSummaryName').text(profile.full_name || 'Usuario');
    $('#profileSummaryRole').text(profile.role || 'Sin rol');
    $('#profileSummaryEmail').text(profile.email || '-');
    $('#profileSummaryStatus')
        .html(`<i class="fas ${Number(profile.status) === 1 ? 'fa-check-circle' : 'fa-ban'}"></i> ${profile.status_label || 'Inactivo'}`)
        .toggleClass('is-active', Number(profile.status) === 1)
        .toggleClass('is-inactive', Number(profile.status) !== 1);

    renderProfileAvatar(profile.photo_url, profile.initials || 'U');

    $('#profileTraceCreatedBy').text(profile.created_by || historical);
    $('#profileTraceCreatedAt').text(profile.created_at || historical);
    $('#profileTraceUpdatedBy').text(profile.updated_by || historical);
    $('#profileTraceUpdatedAt').text(profile.updated_at || historical);
    $('#profileTraceRole').text(profile.role || 'Sin rol');
    $('#profileTraceRoleBy').text(profile.last_role_changed_by || historical);
    $('#profileTraceRoleAt').text(profile.last_role_changed_at || historical);
}

function renderProfileAvatar(photoUrl, initials) {
    const $image = $('#profileAvatarImage');
    const $initials = $('#profileAvatarInitials');

    if (photoUrl) {
        $image.attr('src', photoUrl).removeClass('d-none');
        $initials.addClass('d-none');
        return;
    }

    $image.removeAttr('src').addClass('d-none');
    $initials.text(initials || 'U').removeClass('d-none');
}

function previewProfileImage(input) {
    clearProfileFieldError('image');
    const file = input.files?.[0];
    if (!file) {
        renderProfileAvatar(currentProfileData?.photo_url, currentProfileData?.initials || 'U');
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
        input.value = '';
        setProfileFieldError('image', 'Selecciona una imagen JPG, PNG o WEBP de hasta 2 MB.');
        renderProfileAvatar(currentProfileData?.photo_url, currentProfileData?.initials || 'U');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
        renderProfileAvatar(event.target.result, currentProfileData?.initials || 'U');
    };
    reader.readAsDataURL(file);
}

function showProfileErrors(errors) {
    Object.entries(errors).forEach(function ([field, messages]) {
        setProfileFieldError(field, messages[0]);
    });

    const securityFields = ['current_password', 'password', 'password_confirmation'];
    const firstField = Object.keys(errors)[0];
    $(securityFields.includes(firstField) ? '#profile-security-tab' : '#profile-personal-tab').tab('show');

    const input = document.querySelector(`#userProfileForm [name="${CSS.escape(firstField)}"]`);
    input?.focus({ preventScroll: false });
}

function setProfileFieldError(field, message) {
    const $error = $(`[data-profile-error="${field}"]`);
    const $input = $(`#userProfileForm [name="${field}"]`);

    $error.text(message).addClass('is-visible');
    $input.addClass('dp-profile-input-error').attr('aria-invalid', 'true');
}

function clearProfileFieldError(field) {
    $(`[data-profile-error="${field}"]`).empty().removeClass('is-visible');
    $(`#userProfileForm [name="${field}"]`).removeClass('dp-profile-input-error').removeAttr('aria-invalid');
}

function clearProfileErrors() {
    $('.dp-profile-field-error').empty().removeClass('is-visible');
    $('#userProfileForm .dp-profile-input-error').removeClass('dp-profile-input-error').removeAttr('aria-invalid');
}

function clearProfileSecurityFields() {
    $('#profileCurrentPassword, #profilePassword, #profilePasswordConfirmation').val('').attr('type', 'password');
    $('[data-profile-password-toggle] i').removeClass('fa-eye-slash').addClass('fa-eye');
}

function resetProfileFormState() {
    const form = document.getElementById('userProfileForm');
    form?.reset();
    clearProfileErrors();
    clearProfileSecurityFields();
    $('#profileImage').val('');
}

function setProfileLoading(isLoading) {
    $('#profileModalLoading').toggleClass('is-hidden', !isLoading);
    $('#btnSaveUserProfile').prop('disabled', isLoading);
}

function setProfileSaving(isSaving) {
    $('#btnSaveUserProfile')
        .prop('disabled', isSaving)
        .html(isSaving
            ? '<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...'
            : '<i class="fas fa-save mr-1"></i> Guardar cambios');
}

function updateProfileNavbar(profile) {
    $('.dp-user-name').text(profile.name || 'Usuario');

    if (profile.photo_url) {
        $('.img-avatar-navbar').attr('src', profile.photo_url);
    }
}
