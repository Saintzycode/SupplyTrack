/**
 * assets/js/future-date-warn.js
 * Warns (without blocking) when a date input marked with `data-future-warn`
 * holds a date after today. Stock/transactions are saved immediately
 * regardless of the date, so a future date only changes where it shows up
 * in reports. The message text comes from the attribute.
 */
(function () {
    function todayStr() {
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function check(input) {
        var next = input.nextElementSibling;
        var note = (next && next.classList.contains('future-date-warn')) ? next : null;
        var isFuture = input.value !== '' && input.value > todayStr();

        if (isFuture) {
            input.classList.add('future-date-input');
            if (!note) {
                note = document.createElement('div');
                note.className = 'form-text text-warning future-date-warn';
                note.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>';
                note.appendChild(document.createTextNode(input.getAttribute('data-future-warn') || 'This date is in the future.'));
                input.insertAdjacentElement('afterend', note);
            }
        } else {
            input.classList.remove('future-date-input');
            if (note) note.remove();
        }
    }

    function init() {
        document.querySelectorAll('input[type="date"][data-future-warn]').forEach(function (input) {
            check(input);
            input.addEventListener('input', function () { check(input); });
            input.addEventListener('change', function () { check(input); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
