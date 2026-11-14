// Wrapped in a function so its names never clash with the other scripts
(function () {
    const UI = window.StaffUI;
    const { el, pill, money } = UI;
    const section = document.getElementById('sec-users');
    const csrf = document.getElementById('sec-items').dataset.csrf;
    const search = document.getElementById('user-search');
    const box = document.getElementById('user-list');
    const pageMain = document.querySelector('main');
    const STEP = 40;

    let limit = STEP;
    let lastSig = '';
    let counter = 0;
    let searchTimer;

    const STANDING = {
        very_good: ['Very Good', 'ok'],
        good: ['Good', 'low'],
        bad: ['Bad', 'out'],
    };

    // staff.js adds the page class but only removes its own three, so this one is cleaned up here
    document.querySelectorAll('.menu-link').forEach((link) => {
        link.addEventListener('click', () => {
            pageMain.classList.toggle('sec-users', link.dataset.section === 'users');
        });
    });

    function countUp(node, target) {
        const from = parseInt(node.dataset.value || '0', 10);
        node.dataset.value = target;
        if (from === target) {
            node.textContent = target;
            return;
        }
        const start = performance.now();
        function step(now) {
            const t = Math.min((now - start) / 600, 1);
            node.textContent = Math.round(from + (target - from) * t);
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // Days and hours only; 0 when nothing is owed
    function unpaidText(since) {
        if (!since) return '0';
        const s = Math.max(0, UI.nowSec() - since);
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        return (d ? d + (d === 1 ? ' day ' : ' days ') : '') + h + ' hr';
    }

    function refreshUnpaid() {
        document.querySelectorAll('#user-list .unpaid[data-since]').forEach((node) => {
            node.textContent = unpaidText(parseInt(node.dataset.since, 10));
        });
    }

    function button(label, onClick) {
        const b = el('button', 'row-btn', label);
        b.type = 'button';
        b.addEventListener('click', onClick);
        return b;
    }

    function askBalanceReminder(u) {
        UI.askReminder(
            'Send a reminder email to ' + u.name + ' (' + u.email + ')? It will tell them their username, their balance of ' +
            money(u.balance) + ' and how long it has been unpaid.',
            { type: 'balance', id: u.id_number }
        );
    }

    const COLUMNS = [
        { head: 'Name', cell: (u) => u.name },
        { head: 'Username', cell: (u) => u.username },
        { head: 'Email', cell: (u) => u.email },
        { head: 'ID Number', cell: (u) => u.id_number },
        { head: 'Course', cell: (u) => u.course },
        { head: 'Year', cell: (u) => u.year },
        { head: 'Status', cell: (u) => pill(STANDING[u.standing][0], STANDING[u.standing][1]) },
        { head: 'Penalty balance', cell: (u) => el('span', u.balance > 0 ? 'bal-owed' : '', money(u.balance)) },
        {
            head: 'Unpaid time',
            cell: (u) => {
                const node = el('span', 'unpaid', unpaidText(u.since));
                if (u.since) node.dataset.since = u.since;
                return node;
            },
        },
        {
            head: 'Action',
            cell: (u) => {
                const pay = button('Pay', () => openPay(u));
                pay.disabled = u.balance <= 0;
                const mail = UI.mailButton('Send a reminder email', () => askBalanceReminder(u), u.balance <= 0);
                const group = el('div', 'row-actions');
                group.append(pay, mail);
                return group;
            },
        },
    ];

    function render(users, more, searching) {
        box.replaceChildren();

        if (users.length === 0) {
            box.appendChild(el('p', 'empty-state', searching ? 'No users match your search.' : 'No registered users yet.'));
            return;
        }

        box.appendChild(el('p', 'log-note', 'Showing ' + users.length + (searching ? ' match(es)' : '')));

        const table = el('table', 'data-table log-table');
        const headRow = el('tr');
        COLUMNS.forEach((col) => headRow.appendChild(el('th', '', col.head)));
        table.appendChild(el('thead')).appendChild(headRow);

        const body = el('tbody');
        users.forEach((user) => {
            const tr = el('tr');
            COLUMNS.forEach((col) => {
                const td = el('td');
                const value = col.cell(user);
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
                limit += STEP;
                load();
            });
            box.appendChild(el('div', 'more-wrap')).appendChild(showMore);
        }
    }

    async function load() {
        if (document.hidden || !section.classList.contains('active')) return;
        const mine = ++counter;
        const params = new URLSearchParams({ q: search.value.trim(), limit });

        try {
            const response = await fetch('php/background/staff_users_data.php?' + params);
            if (response.status === 403) {
                window.location.href = 'login.php';
                return;
            }
            if (!response.ok) {
                if (mine === counter) box.replaceChildren(el('p', 'empty-state', 'Could not load the users (server error ' + response.status + ').'));
                return;
            }
            const data = await response.json();
            // An older, slower answer must not replace a newer search
            if (data.error || mine !== counter) return;

            UI.setServer(data.now, 0);

            // The time changes on every reply, so it is left out when checking for changes
            const { now, ...rest } = data;
            const sig = JSON.stringify(rest);
            if (sig !== lastSig) {
                lastSig = sig;
                countUp(document.querySelector('[data-user-key="total"]'), data.cards.total);
                countUp(document.querySelector('[data-user-key="owing"]'), data.cards.owing);
                countUp(document.querySelector('[data-user-key="bad"]'), data.cards.bad);
                render(data.users, data.more, data.searching);
            }
        } catch (e) {
            if (mine === counter) box.replaceChildren(el('p', 'empty-state', 'Could not read the users: ' + e.message));
        }
    }

    // Searches while typing; a new search starts again from the first 40
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        limit = STEP;
        searchTimer = setTimeout(load, 150);
    });

    // ---- The Pay pop-up: type the amount, then confirm. Only Cancel closes it ----
    const payModal = el('div', 'modal');
    payModal.innerHTML =
        '<div class="modal-box">' +
        '<h2>Collect payment</h2>' +
        '<p id="pay-info"></p>' +
        '<form id="pay-form" novalidate>' +
        '<div class="field">' +
        '<label for="pay-amount">Amount to pay (&#8369;) <span class="required">*</span></label>' +
        '<input type="text" id="pay-amount" maxlength="4" inputmode="numeric" autocomplete="off">' +
        '<p class="small-note" id="pay-note"></p>' +
        '<div class="error" id="pay-err"></div>' +
        '</div>' +
        '<div class="modal-actions">' +
        '<button type="button" class="ghost-btn" id="pay-cancel">Cancel</button>' +
        '<button type="submit">Next</button>' +
        '</div>' +
        '</form>' +
        '<div id="pay-confirm" hidden>' +
        '<p class="confirm-text" id="pay-confirm-text"></p>' +
        '<div class="modal-actions">' +
        '<button type="button" class="ghost-btn" id="pay-back">Back</button>' +
        '<button type="button" id="pay-yes">Yes, confirm</button>' +
        '</div>' +
        '</div>' +
        '<div class="error" id="pay-error"></div>' +
        '</div>';
    document.body.append(payModal);

    const payForm = document.getElementById('pay-form');
    const payConfirm = document.getElementById('pay-confirm');
    const payAmount = document.getElementById('pay-amount');
    const payErr = document.getElementById('pay-err');
    const payError = document.getElementById('pay-error');
    const payYes = document.getElementById('pay-yes');
    let payUser = null;
    let payValue = 0;

    function payStep(step) {
        payForm.hidden = step !== 'form';
        payConfirm.hidden = step !== 'confirm';
        payError.textContent = '';
    }

    const closePay = () => payModal.classList.remove('open');

    function openPay(user) {
        payUser = user;
        document.getElementById('pay-info').textContent = user.name + ' (' + user.id_number + ') owes ' + money(user.balance) + '.';
        document.getElementById('pay-note').textContent = 'Type ' + money(5) + ' up to ' + money(user.balance) + ', in 5s (5, 10, 15 ...).';
        payAmount.value = '';
        payErr.textContent = '';
        payStep('form');
        payModal.classList.add('open');
        payAmount.focus();
    }

    // The server checks everything again; this only gives a quicker message
    function checkAmount(text, balance) {
        if (text === '') return 'Enter an amount.';
        const n = parseInt(text, 10);
        if (n < 5) return 'The smallest payment is ' + money(5) + '.';
        if (n % 5 !== 0) return 'Payments must be in 5s (5, 10, 15 ...).';
        if (n > balance) return 'The most you can pay is ' + money(balance) + '.';
        return '';
    }

    payAmount.addEventListener('input', () => {
        payAmount.value = payAmount.value.replace(/\D/g, '');
    });

    payForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const error = checkAmount(payAmount.value, payUser.balance);
        payErr.textContent = error;
        if (error) return;

        payValue = parseInt(payAmount.value, 10);
        document.getElementById('pay-confirm-text').textContent =
            'Collect ' + money(payValue) + ' from ' + payUser.name + '? Their balance will be ' +
            money(payUser.balance - payValue) + ' after this, and the unpaid time starts again from 0.';
        payStep('confirm');
    });

    document.getElementById('pay-cancel').addEventListener('click', closePay);
    document.getElementById('pay-back').addEventListener('click', () => payStep('form'));

    payYes.addEventListener('click', async () => {
        const data = new FormData();
        data.append('csrf_token', csrf);
        data.append('id', payUser.id_number);
        data.append('amount', payValue);

        payYes.disabled = true;
        payError.textContent = '';
        try {
            const response = await fetch('php/background/staff_pay.php', { method: 'POST', body: data });
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
                payError.textContent = 'Server error (' + response.status + '). Check the error log.';
                return;
            }

            if (result.ok) {
                closePay();
            } else {
                payError.textContent = result.error || 'Something went wrong.';
            }
            load();
        } catch (e) {
            payError.textContent = 'Could not reach the server.';
        } finally {
            payYes.disabled = false;
        }
    });

    document.querySelector('.menu-link[data-section="users"]').addEventListener('click', load);

    setInterval(load, 5000);
    // The hours change slowly, so once every 30 seconds is enough
    setInterval(refreshUnpaid, 30000);
})();