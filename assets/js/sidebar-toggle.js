/**
 * assets/js/sidebar-toggle.js
 * Phone/tablet: the top-bar button opens the off-canvas sidebar; the backdrop,
 * Escape or a nav link closes it.
 * Desktop (>=992px): the same button collapses the sidebar to an icon rail.
 * The choice is remembered in localStorage.
 */
(function () {
    var BP = 992;                                   // matches the CSS breakpoint
    var wide = function () { return window.innerWidth >= BP; };
    var close = function () { document.body.classList.remove('nav-open'); };

    // Restore the collapsed rail before paint-sensitive work
    try {
        if (localStorage.getItem('sidebar') === 'collapsed') {
            document.body.classList.add('nav-collapsed');
        }
    } catch (e) { /* private mode: collapse is just not remembered */ }

    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            if (wide()) {
                var collapsed = document.body.classList.toggle('nav-collapsed');
                toggle.setAttribute('aria-expanded', String(!collapsed));
                toggle.setAttribute('aria-label', collapsed ? 'Expand menu' : 'Collapse menu');
                try { localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'open'); } catch (e) {}
            } else {
                var open = document.body.classList.toggle('nav-open');
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            }
        });
    }

    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    // On phones a nav link closes the drawer; on desktop it must NOT un-collapse the rail
    document.querySelectorAll('.sidebar-nav .nav-item').forEach(function (a) {
        a.addEventListener('click', function () { if (!wide()) close(); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    // Crossing the breakpoint must not leave the drawer state stuck
    window.addEventListener('resize', function () {
        if (wide()) close();
    });
})();
