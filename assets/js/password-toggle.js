// Show / hide password fields.
// Markup: a button with data-toggle-password="<input id>" containing an <i> icon.
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var icon = btn.querySelector('i');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        if (icon) {
            icon.classList.toggle('bi-eye', !show);
            icon.classList.toggle('bi-eye-slash', show);
        }
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
})();
