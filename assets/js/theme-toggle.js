/**
 * assets/js/theme-toggle.js
 * Light / dark / system toggle. Persists in localStorage as 'theme'.
 * Sets documentElement.dataset.theme to 'light', 'dark', or removes it (system).
 * Other scripts, charts and the UI spend no time here — pure token flip.
 */
(function () {
    var KEY = 'theme';

    function apply(choice) {
        var root = document.documentElement;
        if (choice === 'light' || choice === 'dark') {
            root.dataset.theme = choice;
        } else {
            delete root.dataset.theme; // system
        }
        document.querySelectorAll('[data-theme-btn]').forEach(function (btn) {
            var v = btn.getAttribute('data-theme-btn');
            var active = v === (root.dataset.theme || 'system');
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
        });
    }

    function stored() {
        try { return localStorage.getItem(KEY) || 'system'; } catch (e) { return 'system'; }
    }

    // Apply before paint-sensitive work (scripts sit at end of body; header adds early script if needed)
    apply(stored());

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-btn]');
        if (!btn) return;
        var v = btn.getAttribute('data-theme-btn');
        try { localStorage.setItem(KEY, v); } catch (err) {}
        apply(v);
    });

    // Keep system mode in sync with OS changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (stored() === 'system') apply('system');
    });
})();
