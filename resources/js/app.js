import './bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
        });
    }

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
