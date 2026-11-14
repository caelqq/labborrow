// Wrapped in a function so its names never clash with the other scripts
(function () {
    const UI = window.StaffUI;
    const { el, stamp, given, live, pill } = UI;
    const section = document.getElementById('sec-approval');
    const csrf = document.getElementById('sec-items').dataset.csrf;
    const PENDING_LIMIT = 8 * 3600;
    const APPROVED_LIMIT = 2 * 3600;
    const TABS = ['pending', 'approved', 'using'];
    const STEP = 40;

    // The search bar goes right under the tab buttons (fixed markup, no user data)
    const searchBar = el('div', 'log-search');
    searchBar.innerHTML =
        '<div class="search-box">' +
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>' +
        '<input type="search" id="req-search" placeholder="Search Log ID, name, username or item" maxlength="60" autocomplete="off">' +
        '</div>';
    section.querySelector('.tabs').after(searchBar);
    const search = document.getElementById('req-search');

    // How many rows each list asks for; "Show more" adds 40
    const limits = { pending: STEP, approved: STEP, using: STEP };
    let lastSig = '';
    let counter = 0;
    let searchTimer;

    function button(label, onClick) {
        const b = el('button', 'row-btn', label);
        b.type = 'button';
        b.addEventListener('click', onClick);
        return b;
    }

    // Puts several buttons side by side in one cell
    function group(...nodes) {
        const box = el('div', 'row-actions');
        box.append(...nodes);
        return box;
    }

    function askMissingReminder(r) {
        UI.askReminder(
            'Send a reminder email to ' + r.name + ' about ' + r.id + ' (' + r.quantity + ' x ' + r.item +
            ')? It will say how long the item has been missing, that it must be returned, and the penalty so far.',
            { type: 'missing', id: r.num }
        );
    }

    const logId = { head: 'Log ID', cell: (r) => el('span', 'log-id', r.id) };
    const person = [
        { head: 'Name', cell: (r) => r.name },
        { head: 'User', cell: (r) => r.username },
        { head: 'ID Number', cell: (r) => r.id_number },
    ];
    const item = [
        { head: 'Item name', cell: (r) => r.item },
        { head: 'Qty', cell: (r) => String(r.quantity) },
    ];
    const purpose = { head: 'Reason', cell: (r) => r.purpose || '-', wrap: true };

    // The student's standing, worked out by the server from the penalty balance
    const STANDING = {
        very_good: ['Very Good', 'ok'],
        good: ['Good', 'low'],
        bad: ['Bad', 'out'],
    };
    const standing = { head: 'Status', cell: (r) => pill(STANDING[r.standing][0], STANDING[r.standing][1]) };

    const COLUMNS = {
        pending: [
            logId,
            { head: 'Waiting', cell: (r) => live('since', r.requested, { kind: 'pending', limit: PENDING_LIMIT }) },
            ...person, ...item,
            { head: 'Requested at', cell: (r) => stamp(r.requested) },
            purpose, standing,
            { head: 'Action', cell: (r) => button('Respond', () => openRespond(r)) },
        ],
        approved: [
            logId,
            { head: 'Approved for', cell: (r) => live('since', r.reviewed, { kind: 'approved', limit: APPROVED_LIMIT }) },
            ...person, ...item,
            purpose,
            { head: 'Given time', cell: (r) => given(r.minutes) },
            { head: 'Action', cell: (r) => button('Picked Up', () => openConfirm(r, 'pickup')) },
        ],
        using: [
            logId, ...person, ...item,
            { head: 'Borrowed at', cell: (r) => stamp(r.picked_up) },
            { head: 'Return time', cell: (r) => given(r.minutes) + ' - ' + stamp(r.due) },
            { head: 'Time left', cell: (r) => live('left', r.due) },
            { head: 'Status', cell: (r) => live('status', r.due) },
            { head: 'Penalty', cell: (r) => live('pen0', r.due) },
            { head: 'Missing for', cell: (r) => live('miss0', r.due) },
            {
                head: 'Action',
                cell: (r) => group(
                    button('Returned', () => openConfirm(r, 'return')),
                    UI.mailButton('Send a missing-item reminder', () => askMissingReminder(r))
                ),
            },
        ],
    };

    const EMPTY = {
        pending: 'No pending requests.',
        approved: 'Nobody is waiting to pick up an item.',
        using: 'No items are being used right now.',
    };

    function renderTab(tab, rows, more, searching) {
        const box = document.getElementById('req-' + tab);
        box.replaceChildren();

        if (rows.length === 0) {
            box.appendChild(el('p', 'empty-state', searching ? 'No requests match your search.' : EMPTY[tab]));
            return;
        }

        box.appendChild(el('p', 'log-note', 'Showing ' + rows.length + (searching ? ' match(es)' : '')));

        const table = el('table', 'data-table log-table');
        const headRow = el('tr');
        COLUMNS[tab].forEach((col) => headRow.appendChild(el('th', '', col.head)));
        table.appendChild(el('thead')).appendChild(headRow);

        const body = el('tbody');
        rows.forEach((row) => {
            const tr = el('tr');
            COLUMNS[tab].forEach((col) => {
                const td = el('td', col.wrap ? 'wrap' : '');
                const value = col.cell(row);
                if (typeof value === 'string') td.textContent = value;
                else td.appendChild(value);
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });
        table.appendChild(body);

        const wrap = el('div', 'table-wrap');
        wrap.appendChild(table);
        box.appendChild(wrap);

        if (more) {
            const showMore = el('button', 'more-btn', 'Show ' + STEP + ' more');
            showMore.type = 'button';
            showMore.addEventListener('click', () => {
                limits[tab] += STEP;
                load();
            });
            box.appendChild(el('div', 'more-wrap')).appendChild(showMore);
        }
    }

    function showFailure(message) {
        lastSig = '';
        TABS.forEach((tab) => {
            document.getElementById('req-' + tab).replaceChildren(el('p', 'empty-state', message));
        });
    }

    async function load() {
        if (document.hidden || !section.classList.contains('active')) return;
        const mine = ++counter;

        const params = new URLSearchParams({ q: search.value.trim() });
        TABS.forEach((tab) => params.set('limit_' + tab, limits[tab]));

        try {
            const response = await fetch('php/background/staff_approval_data.php?' + params);
            if (response.status === 403) {
                window.location.href = 'login.php';
                return;
            }
            if (!response.ok) {
                if (mine === counter) showFailure('Could not load the requests (server error ' + response.status + ').');
                return;
            }
            const data = await response.json();
            // An older, slower answer must not replace a newer search
            if (data.error || mine !== counter) return;

            UI.setServer(data.now, data.penalty);

            const { now, ...rest } = data;
            const sig = JSON.stringify(rest);
            if (sig !== lastSig) {
                lastSig = sig;
                TABS.forEach((tab) => renderTab(tab, data[tab], data.more[tab], data.searching));
            }
            UI.tick();
        } catch (e) {
            if (mine === counter) showFailure('Could not read the requests: ' + e.message);
        }
    }

    // Searches while typing; a new search starts again from the first 40
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        TABS.forEach((tab) => { limits[tab] = STEP; });
        searchTimer = setTimeout(load, 150);
    });

    // ---- The pop-up: Respond (choose, form, confirm), Picked Up and Returned (confirm only) ----
    const modal = document.getElementById('req-modal');
    const STEPS = {
        choose: 'req-choose',
        approve: 'req-approve-form',
        reject: 'req-reject-form',
        confirm: 'req-confirm',
    };
    const hours = document.getElementById('req-hours');
    const mins = document.getElementById('req-mins');
    const reason = document.getElementById('req-reason');
    const confirmBack = document.getElementById('req-confirm-back');
    const confirmYes = document.getElementById('req-confirm-yes');

    let ctx = null;

    function show(step) {
        Object.entries(STEPS).forEach(([name, id]) => {
            document.getElementById(id).hidden = name !== step;
        });
        document.getElementById('req-error').textContent = '';
    }

    const close = () => modal.classList.remove('open');

    function info(r) {
        return r.name + ' (' + r.id_number + ') - ' + r.quantity + ' x ' + r.item;
    }

    function toConfirm(text, back) {
        ctx.back = back;
        document.getElementById('req-confirm-text').textContent = text;
        confirmBack.textContent = back ? 'Back' : 'Cancel';
        show('confirm');
    }

    function openRespond(row) {
        ctx = { row, params: null, back: null };
        document.getElementById('req-title').textContent = 'Respond to ' + row.id;
        document.getElementById('req-info').textContent = info(row);
        hours.value = '';
        mins.value = '';
        reason.value = '';
        document.getElementById('req-err-time').textContent = '';
        document.getElementById('req-err-reason').textContent = '';
        show('choose');
        modal.classList.add('open');
    }

    function openConfirm(row, action) {
        ctx = { row, params: { action }, back: null };
        document.getElementById('req-info').textContent = info(row);
        if (action === 'pickup') {
            document.getElementById('req-title').textContent = 'Confirm pick up';
            toConfirm('Did ' + row.name + ' really pick up the item? The borrow time starts now.', null);
        } else {
            document.getElementById('req-title').textContent = 'Confirm return';
            toConfirm('Did ' + row.name + ' really return the item?', null);
        }
        modal.classList.add('open');
    }

    document.getElementById('req-cancel').addEventListener('click', close);
    document.getElementById('req-approve').addEventListener('click', () => { show('approve'); hours.focus(); });
    document.getElementById('req-reject').addEventListener('click', () => { show('reject'); reason.focus(); });
    modal.querySelectorAll('[data-back]').forEach((b) => b.addEventListener('click', () => show(b.dataset.back)));
    confirmBack.addEventListener('click', () => (ctx.back ? show(ctx.back) : close()));

    [hours, mins].forEach((box) => box.addEventListener('input', () => {
        box.value = box.value.replace(/\D/g, '');
    }));

    // The server checks everything again; this only gives a quicker message
    document.getElementById('req-approve-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const h = hours.value === '' ? 0 : parseInt(hours.value, 10);
        const m = mins.value === '' ? 0 : parseInt(mins.value, 10);
        const error = document.getElementById('req-err-time');
        error.textContent = '';

        if (h > 23 || m > 59) {
            error.textContent = 'Use 0 to 23 hours and 0 to 59 minutes.';
            return;
        }
        if (h * 60 + m < 1) {
            error.textContent = 'Enter at least 1 minute.';
            return;
        }
        ctx.params = { action: 'approve', hours: h, minutes: m };
        toConfirm('Approve ' + ctx.row.id + ' and give ' + ctx.row.name + ' ' + given(h * 60 + m) +
            ' to use the item? The time starts when the item is picked up.', 'approve');
    });

    document.getElementById('req-reject-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const text = reason.value;
        const error = document.getElementById('req-err-reason');
        error.textContent = '';

        if (text.length < 3 || text.length > 100) {
            error.textContent = 'Reason must be 3 to 100 characters.';
            return;
        }
        ctx.params = { action: 'reject', reason: text };
        toConfirm('Reject ' + ctx.row.id + ' from ' + ctx.row.name + '? Reason: ' + text, 'reject');
    });

    confirmYes.addEventListener('click', async () => {
        const data = new FormData();
        data.append('csrf_token', csrf);
        data.append('id', ctx.row.num);
        Object.entries(ctx.params).forEach(([key, value]) => data.append(key, value));

        confirmYes.disabled = true;
        const errorBox = document.getElementById('req-error');
        errorBox.textContent = '';
        try {
            const response = await fetch('php/background/staff_request_action.php', { method: 'POST', body: data });
            if (response.status === 403) {
                window.location.href = 'login.php';
                return;
            }
            // A real action also resets the server's idle timer, so the countdown restarts too
            window.dispatchEvent(new Event('user-active'));
            const result = await response.json();
            if (result.ok) {
                close();
            } else {
                errorBox.textContent = result.error || 'Something went wrong.';
            }
            load();
        } catch (e) {
            errorBox.textContent = 'Could not reach the server.';
        } finally {
            confirmYes.disabled = false;
        }
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });

    document.querySelector('.menu-link[data-section="approval"]').addEventListener('click', load);

    UI.onExpire(load);
    // Pop-ups move out of the animated page box, so they center on the screen
    document.body.append(modal);
    setInterval(load, 5000);
})();