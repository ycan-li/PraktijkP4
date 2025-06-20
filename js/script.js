/**
 * Initializes tab button active state toggling and dropdown filters after DOM is loaded.
 * @returns {void}
 */
document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    /**
     * Attaches click handler to toggle visibility of a dropdown menu when its title is clicked.
     *
     * @param {string} titleId - ID of the element acting as dropdown title.
     * @param {string} menuId - ID of the dropdown menu element to show or hide.
    function setupDropdownFilter(titleId, menuId) {
        const title = document.getElementById(titleId);
        const menu = document.getElementById(menuId);
        if (!title || !menu) return;
        title.style.cursor = 'pointer';
        title.addEventListener('click', function (e) {
            e.stopPropagation();
            // document.querySelectorAll('.dropdown-menu').forEach(m => {
            //     if (m !== menu) m.classList.add('d-none');
            // });
            // menu.classList.toggle('d-none');
        });
    }
    setupDropdownFilter('genreFilterTitle', 'genreDropdownMenu');
    setupDropdownFilter('tagsFilterTitle', 'tagsDropdownMenu');
    setupDropdownFilter('auteurFilterTitle', 'auteurDropdownMenu');
    setupDropdownFilter('tijdFilterTitle', 'tijdDropdownMenu');
    // Hide dropdowns when clicking outside
    // document.addEventListener('click', function () {
    //     document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('d-none'));
    // });
});
