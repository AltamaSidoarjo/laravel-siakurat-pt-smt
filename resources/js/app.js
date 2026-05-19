import './bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    window.getSelect2Options = function (element) {
        const $select = window.jQuery(element);
        const $modalParent = $select.closest('.modal');
        const $dropdownParent = $modalParent.length ? $modalParent : window.jQuery(document.body);
        const placeholder = $select.data('placeholder')
            || $select.attr('placeholder')
            || $select.find('option[value=""]').first().text().trim()
            || undefined;

        const options = {
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $dropdownParent,
        };

        if (placeholder) {
            options.placeholder = placeholder;
            options.allowClear = !$select.prop('required');
        }

        return options;
    };

    window.initSelect2Fields = function (scope = document, selector = '.select2') {
        if (!(window.jQuery && window.jQuery.fn.select2)) {
            return;
        }

        window.jQuery(scope).find(selector).each(function () {
            const $select = window.jQuery(this);
            if ($select.data('select2')) {
                return;
            }

            $select.select2(window.getSelect2Options(this));
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
