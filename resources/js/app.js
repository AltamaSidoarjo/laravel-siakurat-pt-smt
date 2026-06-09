import './bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    // ─── Select2 helpers ─────────────────────────────────────────────

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

    // ─── Sidebar: desktop toggle (collapse) ──────────────────────────

    const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // ─── Sidebar: mobile open / close ────────────────────────────────

    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function () {
            document.body.classList.add('sidebar-open');
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function () {
            document.body.classList.remove('sidebar-open');
        });
    }

    // Auto-close sidebar on mobile when a sub-link is clicked
    const appSidebar = document.getElementById('app-sidebar');
    if (appSidebar) {
        appSidebar.querySelectorAll('a.sidebar-sublink').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    }
});
