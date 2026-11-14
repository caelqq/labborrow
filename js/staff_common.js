// Shared helpers and the one-second counters for the Log and Approval tabs
window.StaffUI = (function () {
    let offset = 0;
    let penalty = 5;
    const expireHandlers = [];

    // textContent only, never innerHTML, so data can never run as code (protection 2)
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    // Server time, so a wrong clock on this computer does not change the counters
    const nowSec = () => Math.floor(Date.now() / 1000) + offset;

    function setServer(now, pesos) {
        offset = now - Math.floor(Date.now() / 1000);
        if (pesos) penalty = pesos;
    }

    const clock = (t) => new Date(t * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const money = (n) => '\u20B1' + n;
    const penaltyFor = (secs) => Math.floor(Math.max(0, secs) / 3600) * penalty;
    const pill = (text, cls) => el('span', 'pill ' + cls, text);

    // Time given, in minutes: 150 -> "2 hours 30 min"
    function given(minutes) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        const parts = [];
        if (h) parts.push(h + (h === 1 ? ' hour' : ' hours'));
        if (m) parts.push(m + ' min');
        return parts.join(' ') || '0 min';
    }

    function stamp(t) {
        if (!t) return '-';
        const d = new Date(t * 1000);
        if (d.toDateString() === new Date(nowSec() * 1000).toDateString()) return clock(t);
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' + clock(t);
    }

    // withSeconds is for counters that are still running
    function duration(secs, withSeconds) {
        secs = Math.max(0, secs);
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const parts = [];
        if (h) parts.push(h + ' hr');
        if (h || m) parts.push(m + ' min');
        if (withSeconds || parts.length === 0) parts.push((secs % 60) + ' s');
        return parts.join(' ');
    }

    // Hours and minutes only: 8100 -> "2 hr 15 min", 40 -> "0 min"
    function durationMin(secs) {
        secs = Math.max(0, secs);
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        return h ? h + ' hr ' + m + ' min' : m + ' min';
    }

    // A counter that tick() updates every second. t is the time (in seconds) it counts from or to.
    // modes: missing, penalty (always red) | miss0, pen0 (red only when above 0) | left | status | since
    function live(mode, t, extra) {
        const node = el('span', 'live live-' + mode);
        node.dataset.live = mode;
        node.dataset.t = t;
        Object.assign(node.dataset, extra || {});
        return node;
    }

    function colorize(node, cls) {
        node.classList.remove('live-good', 'live-warn', 'live-bad');
        if (cls) node.classList.add(cls);
    }

    function tick() {
        const n = nowSec();
        let expired = false;

        document.querySelectorAll('[data-live]').forEach((node) => {
            const t = parseInt(node.dataset.t, 10);
            const mode = node.dataset.live;

            if (mode === 'missing') {
                node.textContent = durationMin(n - t);
            } else if (mode === 'penalty') {
                node.textContent = money(penaltyFor(n - t));
            } else if (mode === 'miss0') {
                node.textContent = n > t ? durationMin(n - t) : '0';
                colorize(node, n > t ? 'live-bad' : '');
            } else if (mode === 'pen0') {
                const p = penaltyFor(n - t);
                node.textContent = money(p);
                colorize(node, p >= penalty ? 'live-bad' : '');
            } else if (mode === 'status') {
                const onUse = t > n;
                node.textContent = onUse ? 'On Use' : 'Still Missing';
                node.className = 'pill live ' + (onUse ? 'ok' : 'out');
            } else if (mode === 'since') {
                const s = Math.max(0, n - t);
                const minutes = Math.floor(s / 60);
                if (node.dataset.kind === 'pending') {
                    node.textContent = duration(s, true);
                    colorize(node, s < 3600 ? 'live-good' : s < 3 * 3600 ? 'live-warn' : 'live-bad');
                } else {
                    const label = minutes <= 30 ? 'Early' : minutes < 60 ? 'Late' : 'So late';
                    node.textContent = duration(s, true) + ' - ' + label;
                    colorize(node, minutes <= 30 ? 'live-good' : minutes < 60 ? 'live-warn' : 'live-bad');
                }
                // The server drops the request at this age, so ask it now
                if (s >= parseInt(node.dataset.limit, 10) && !node.dataset.done) {
                    node.dataset.done = '1';
                    expired = true;
                }
            } else {
                node.textContent = t > n ? duration(t - n, true) : 'Overdue';
                // The countdown ended: ask the server now, so the row moves right away
                if (n > t && !node.dataset.done) {
                    node.dataset.done = '1';
                    expired = true;
                }
            }
        });

        if (expired) expireHandlers.forEach((fn) => fn());
    }

    setInterval(tick, 1000);

    // ---- Mail reminders: an envelope button, and one confirm pop-up shared by Users and Using ----
    const ENVELOPE = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>';

    function mailButton(title, onClick, disabled) {
        const b = el('button', 'row-btn mail-btn');
        b.type = 'button';
        b.title = title;
        b.setAttribute('aria-label', title);
        // Fixed markup, no user data
        b.innerHTML = ENVELOPE;
        b.disabled = !!disabled;
        b.addEventListener('click', onClick);
        return b;
    }

    let remind = null;
    let remindParams = null;

    function buildReminder() {
        const modal = el('div', 'modal');
        modal.innerHTML =
            '<div class="modal-box">' +
            '<h2>Send reminder email?</h2>' +
            '<p class="confirm-text"></p>' +
            '<div class="modal-actions">' +
            '<button type="button" class="ghost-btn">Cancel</button>' +
            '<button type="button">Yes, send</button>' +
            '</div>' +
            '<div class="error"></div>' +
            '</div>';
        document.body.append(modal);

        remind = {
            modal,
            text: modal.querySelector('.confirm-text'),
            cancel: modal.querySelector('.ghost-btn'),
            yes: modal.querySelectorAll('button')[1],
            error: modal.querySelector('.error'),
        };

        // Only the Cancel / Close button closes it
        remind.cancel.addEventListener('click', () => modal.classList.remove('open'));

        remind.yes.addEventListener('click', async () => {
            const data = new FormData();
            data.append('csrf_token', document.getElementById('sec-items').dataset.csrf);
            Object.entries(remindParams).forEach(([key, value]) => data.append(key, value));

            remind.yes.disabled = true;
            remind.error.textContent = '';
            try {
                const response = await fetch('php/background/staff_notify.php', { method: 'POST', body: data });
                if (response.status === 403) {
                    window.location.href = 'login.php';
                    return;
                }
                // A real action also resets the server's idle timer, so the countdown restarts too
                window.dispatchEvent(new Event('user-active'));

                let result = null;
                try {
                    result = await response.json();
                } catch (e) {}
                if (!result) {
                    remind.error.textContent = 'Server error (' + response.status + '). Check the error log.';
                } else if (result.ok) {
                    remind.text.textContent = result.message;
                    remind.yes.hidden = true;
                    remind.cancel.textContent = 'Close';
                } else {
                    remind.error.textContent = result.error || 'Something went wrong.';
                }
            } catch (e) {
                remind.error.textContent = 'Could not reach the server.';
            } finally {
                remind.yes.disabled = false;
            }
        });
    }

    // text = what the staff reads; params = what the server needs (type and id)
    function askReminder(text, params) {
        if (!remind) buildReminder();
        remindParams = params;
        remind.text.textContent = text;
        remind.error.textContent = '';
        remind.yes.hidden = false;
        remind.yes.disabled = false;
        remind.cancel.textContent = 'Cancel';
        remind.modal.classList.add('open');
    }

    return {
        el, nowSec, setServer, clock, stamp, given, duration, durationMin, money, penaltyFor, pill, live, tick,
        mailButton, askReminder,
        onExpire: (fn) => expireHandlers.push(fn),
    };
})();