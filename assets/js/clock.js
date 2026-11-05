/**
 * assets/js/clock.js
 * Live clock in the top bar (shown on every page, including the dashboard).
 * Uses the server timezone from data-timezone so it matches the app's
 * timestamp, and ticks aligned to the second.
 */
(function () {
    var el = document.querySelector('.live-clock');
    if (!el) return;

    var tz = el.getAttribute('data-timezone') || undefined;
    var timeOpts = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, timeZone: tz };

    var timeFmt;
    try {
        timeFmt = new Intl.DateTimeFormat('en-US', timeOpts);
    } catch (e) {
        delete timeOpts.timeZone;
        timeFmt = new Intl.DateTimeFormat('en-US', timeOpts);
    }

    function render() {
        var now = new Date();
        el.textContent = timeFmt.format(now);
    }

    render();
    (function scheduleTick() {
        setTimeout(function () {
            render();
            scheduleTick();
        }, 1000 - (Date.now() % 1000));
    })();
})();
