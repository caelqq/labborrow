// Wrapped in a function so its names never clash with the other scripts
(function () {
    const UI = window.StaffUI;
    const { el, stamp, given, duration, durationMin, money, penaltyFor, pill, live } = UI;
    const section = document.getElementById('sec-log');
    const search = document.getElementById('log-search');
    const TABS = ['daily', 'borrowed', 'returned', 'rejected', 'missing'];
    const STEP = 40;

    search.placeholder = 'Search Log ID, name or item';

    // How many rows each tab asks for; "Show more" adds 40
    const limits = { daily: STEP, borrowed: STEP, returned: STEP, rejected: STEP, missing: STEP };
    let lastSig = '';
    let counter = 0;
    let searchTimer;

    const TYPES = {
        borrowed: ['Borrowed', 'info'],
        returned: ['Returned', 'ok'],
        rejected: ['Rejected', 'low'],
        missing: ['Missing', 'out'],
    };

    function details(r) {
        const box = el('span');
        if (r.type === 'borrowed') box.append('Return by ' + stamp(r.due) + ' (' + given(r.minutes) + ')');
        else if (r.type === 'returned') box.append('Due ' + stamp(r.due) + ', returned ' + stamp(r.returned));
        else if (r.type === 'rejected') box.append(r.reason || '-');
        else {
            box.append('Due ' + stamp(r.due) + ', missing ');
            box.append(r.status === 'borrowed' ? live('missing', r.due) : durationMin(r.returned - r.due));
        }
        return box;
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

    const COLUMNS = {
        daily: [
            logId,
            { head: 'Type', cell: (r) => pill(TYPES[r.type][0], TYPES[r.type][1]) },
            ...person, ...item,
            { head: 'Time', cell: (r) => stamp(r.time) },
            { head: 'Details', cell: details },
        ],
        borrowed: [
            logId, ...person, ...item,
            { head: 'Borrowed at', cell: (r) => stamp(r.picked_up) },
            { head: 'Return time', cell: (r) => given(r.minutes) + ' - ' + stamp(r.due) },
            { head: 'Time left', cell: (r) => live('left', r.due) },
        ],
        returned: [
            logId, ...person, ...item,
            { head: 'Due - Returned', cell: (r) => stamp(r.due) + ' - ' + stamp(r.returned) },
            {
                head: 'Status',
                cell: (r) => r.returned > r.due
                    ? pill('Late ' + durationMin(r.returned - r.due), 'low')
                    : pill('On time', 'ok'),
            },
        ],
        rejected: [
            logId, ...person, ...item,
            { head: 'Reason', cell: (r) => r.reason || '-', wrap: true },
            { head: 'Requested', cell: (r) => stamp(r.requested) },
            { head: 'Rejected', cell: (r) => stamp(r.reviewed) },
        ],
        missing: [
            logId, ...person, ...item,
            { head: 'Return time', cell: (r) => stamp(r.due) },
            { head: 'Time given', cell: (r) => given(r.minutes) },
            {
                head: 'Missing for',
                cell: (r) => r.status === 'borrowed' ? live('missing', r.due) : durationMin(r.returned - r.due),
            },
            {
                head: 'Penalty',
                cell: (r) => r.status === 'borrowed' ? live('penalty', r.due) : money(penaltyFor(r.returned - r.due)),
            },
            {
                head: 'Status',
                cell: (r) => r.status === 'borrowed' ? pill('Still missing', 'out') : pill('Returned late', 'low'),
            },
        ],
    };

    function renderTab(tab, rows, more, searching) {
        const box = document.getElementById('log-' + tab);
        box.replaceChildren();

        const where = searching ? ' match(es) from all dates' : (tab === 'daily' ? ' from today' : '');
        box.appendChild(el('p', 'log-note', 'Showing ' + rows.length + where));

        if (rows.length === 0) {
            box.appendChild(el('p', 'empty-state', searching ? 'No logs match your search.' : 'No logs yet.'));
            return;
        }

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
            const button = el('button', 'more-btn', 'Show ' + STEP + ' more');
            button.type = 'button';
            button.addEventListener('click', () => {
                limits[tab] += STEP;
                load();
            });
            box.appendChild(el('div', 'more-wrap')).appendChild(button);
        }
    }

    function showFailure(message) {
        lastSig = '';
        TABS.forEach((tab) => {
            const box = document.getElementById('log-' + tab);
            box.replaceChildren(el('p', 'empty-state', message));
        });
    }

    async function load() {
        if (document.hidden || !section.classList.contains('active')) return;
        const mine = ++counter;

        const params = new URLSearchParams({ q: search.value.trim() });
        TABS.forEach((tab) => params.set('limit_' + tab, limits[tab]));

        try {
            const response = await fetch('php/background/staff_log_data.php?' + params);
            if (response.status === 403) {
                window.location.href = 'login.php';
                return;
            }
            if (!response.ok) {
                if (mine === counter) showFailure('Could not load the logs (server error ' + response.status + ').');
                return;
            }
            const data = await response.json();
            // An older, slower answer must not replace a newer search
            if (data.error || mine !== counter) return;

            UI.setServer(data.now, data.penalty);

            // The time changes on every reply, so it is left out when checking for changes
            const { now, ...rest } = data;
            const sig = JSON.stringify(rest);
            if (sig !== lastSig) {
                lastSig = sig;
                TABS.forEach((tab) => renderTab(tab, data[tab], data.more[tab], data.searching));
            }
            UI.tick();
        } catch (e) {
            if (mine === counter) showFailure('Could not read the logs: ' + e.message);
        }
    }

    // Searches while typing; a new search starts again from the first 40
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        TABS.forEach((tab) => { limits[tab] = STEP; });
        searchTimer = setTimeout(load, 150);
    });

    document.querySelector('.menu-link[data-section="log"]').addEventListener('click', load);

    UI.onExpire(load);
    setInterval(load, 5000);
})();