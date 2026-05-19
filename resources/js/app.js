import './bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    window.initSelect2Fields = function (scope = document, selector = '.select2') {
        if (!(window.jQuery && window.jQuery.fn.select2)) {
            return;
        }

        window.jQuery(scope).find(selector).each(function () {
            const $select = window.jQuery(this);
            if ($select.data('select2')) {
                return;
            }

            const $modalParent = $select.closest('.modal');
            const $dropdownParent = $modalParent.length ? $modalParent : window.jQuery(document.body);

            $select.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $dropdownParent,
            });
        });
    };

    window.initSelect2Fields(document, '.select2');

    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach((dropdown) => {
        dropdown.addEventListener('hide.bs.dropdown', function () {
            const shownSubmenus = this.querySelectorAll('.dropdown-submenu.show, .dropdown-menu.show');
            shownSubmenus.forEach((submenu) => submenu.classList.remove('show'));
        });
    });

    const submenuToggles = document.querySelectorAll('.dropdown-submenu > .dropdown-toggle');
    submenuToggles.forEach((toggle) => {
        toggle.addEventListener('click', function (event) {
            if (window.innerWidth >= 1200) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.parentElement.classList.toggle('show');
        });
    });
});
