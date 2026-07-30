const viewportPadding = 12;
const menuGap = 6;

function getActionMenu(dropdown) {
    const container = window.jQuery(dropdown);
    return container.data('dpActionMenu') || container.find('.dp-action-menu');
}

function positionActionMenu(dropdown) {
    const container = window.jQuery(dropdown);
    const button = container.find('.dp-action-trigger').get(0);
    const menu = getActionMenu(dropdown);
    if (!button || !menu.length) return;

    const buttonRect = button.getBoundingClientRect();
    menu.css({
        display: 'block',
        visibility: 'hidden',
        maxHeight: `${window.innerHeight - (viewportPadding * 2)}px`,
    });

    const menuWidth = Math.min(menu.outerWidth(), window.innerWidth - (viewportPadding * 2));
    const naturalHeight = menu.outerHeight();
    const spaceBelow = Math.max(0, window.innerHeight - buttonRect.bottom - viewportPadding - menuGap);
    const spaceAbove = Math.max(0, buttonRect.top - viewportPadding - menuGap);
    const opensUp = naturalHeight > spaceBelow && spaceAbove > spaceBelow;
    const availableHeight = Math.max(80, opensUp ? spaceAbove : spaceBelow);
    const left = Math.min(
        Math.max(viewportPadding, buttonRect.right - menuWidth),
        window.innerWidth - menuWidth - viewportPadding,
    );

    container.toggleClass('dropup', opensUp);
    menu.css('max-height', `${availableHeight}px`);

    const renderedHeight = menu.outerHeight();
    const top = opensUp
        ? Math.max(viewportPadding, buttonRect.top - renderedHeight - menuGap)
        : Math.min(buttonRect.bottom + menuGap, window.innerHeight - renderedHeight - viewportPadding);
    const element = menu.get(0);

    element.style.setProperty('--dp-action-menu-top', `${top}px`);
    element.style.setProperty('--dp-action-menu-left', `${left}px`);
    menu.css('visibility', 'visible');
}

function portalActionMenu(dropdown) {
    const container = window.jQuery(dropdown);
    const menu = container.find('.dp-action-menu');
    if (!menu.length) return;

    container.data('dpActionMenu', menu);
    menu.data('dpActionOwner', container)
        .addClass('dp-action-menu-portal')
        .appendTo(document.body);
    positionActionMenu(dropdown);
}

function restoreActionMenu(dropdown) {
    const container = window.jQuery(dropdown);
    const menu = container.data('dpActionMenu');
    if (!menu?.length) return;

    menu.removeClass('dp-action-menu-portal')
        .removeAttr('style')
        .removeData('dpActionOwner')
        .appendTo(container);
    container.removeData('dpActionMenu').removeClass('dropup');
}

function closeOpenActionMenus() {
    window.jQuery('.dp-action-trigger[aria-expanded="true"]').dropdown('hide');
}

if (window.jQuery) {
    const $ = window.jQuery;

    $(document)
        .on('show.bs.dropdown.dpTableActions', '.dp-action-dropdown', function () {
            portalActionMenu(this);
        })
        .on('shown.bs.dropdown.dpTableActions', '.dp-action-dropdown', function () {
            positionActionMenu(this);
        })
        .on('hidden.bs.dropdown.dpTableActions', '.dp-action-dropdown', function () {
            restoreActionMenu(this);
        })
        .on('click.dpTableActions', '.dp-action-menu .dropdown-item', function () {
            $(this).closest('.dp-action-menu').data('dpActionOwner')
                ?.find('.dp-action-trigger').dropdown('hide');
        })
        .on('preDraw.dt.dpTableActions', 'table.dataTable', closeOpenActionMenus);

    $(window).on('resize.dpTableActions scroll.dpTableActions', closeOpenActionMenus);
}
