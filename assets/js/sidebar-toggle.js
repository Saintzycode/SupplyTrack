/**
 * assets/js/sidebar-toggle.js
 * Off-canvas sidebar for phones/tablets: the top-bar button opens it,
 * the backdrop, Escape key or a nav link closes it.
 */
(function () {
    function close() { document.body.classList.remove('nav-open'); }

    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('nav-open');
        });
    }

    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.querySelectorAll('.sidebar-nav .nav-item').forEach(function (a) {
        a.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
