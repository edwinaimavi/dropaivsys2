const themeConfig = window.DropaivTheme || { current: 'light' };
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
let savedTheme = themeConfig.current || 'light';

function effectiveTheme(preference) {
    return preference === 'system' ? (systemTheme.matches ? 'dark' : 'light') : preference;
}

function applyTheme(preference) {
    const effective = effectiveTheme(preference);
    [document.documentElement, document.body].forEach((element) => {
        if (!element) return;
        element.classList.remove('theme-light', 'theme-dark', 'theme-system');
        element.classList.add(`theme-${effective}`);
        if (element === document.body) element.classList.add(`theme-${preference}`);
    });
    document.documentElement.dataset.themePreference = preference;
    updateThemeControl(preference, effective);
}

function updateThemeControl(preference, effective = effectiveTheme(preference)) {
    const labels = { light: 'Claro', dark: 'Oscuro', system: 'Sistema' };
    const icons = { light: 'fa-sun', dark: 'fa-moon', system: 'fa-desktop' };
    document.querySelectorAll('.dp-theme-option').forEach((option) => option.classList.toggle('active', option.dataset.theme === preference));
    const icon = document.getElementById('dpThemeNavbarIcon');
    const text = document.getElementById('dpThemeNavbarText');
    if (icon) icon.className = `fas ${icons[preference] || icons[effective]}`;
    if (text) text.textContent = labels[preference] || labels[effective];
}

async function saveTheme(preference, option) {
    const previous = savedTheme;
    applyTheme(preference);
    option?.classList.add('is-saving');
    try {
        const response = await fetch(themeConfig.updateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': themeConfig.csrf },
            body: JSON.stringify({ theme_mode: preference })
        });
        if (!response.ok) throw new Error('No se pudo guardar el tema.');
        const payload = await response.json();
        savedTheme = payload.theme_mode;
        themeConfig.current = savedTheme;
        try { localStorage.setItem('dropaiv-theme-preference', savedTheme); } catch (_) {}
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Tema actualizado', showConfirmButton:false, timer:1500, timerProgressBar:true });
        }
    } catch (error) {
        applyTheme(previous);
        if (typeof Swal !== 'undefined') Swal.fire({ icon:'error', title:'No se guard&oacute; el tema', text:'Int&eacute;ntalo nuevamente.' });
    } finally {
        option?.classList.remove('is-saving');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(savedTheme);
    document.querySelectorAll('.dp-theme-option').forEach((option) => option.addEventListener('click', (event) => {
        event.preventDefault();
        if (option.dataset.theme !== savedTheme) saveTheme(option.dataset.theme, option);
    }));
});

systemTheme.addEventListener?.('change', () => { if (savedTheme === 'system') applyTheme('system'); });
