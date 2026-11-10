const filterForm = document.getElementById('filters');
let chart;

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

// textContent shows data as plain text, so a <script> typed by an attacker never runs (protection 2)
function fillTable(id, rows, keys, emptyText) {
    const body = document.getElementById(id);
    body.replaceChildren();

    if (rows.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = keys.length;
        td.className = 'empty';
        td.textContent = emptyText;
        tr.appendChild(td);
        body.appendChild(tr);
        return;
    }

    rows.forEach((row) => {
        const tr = document.createElement('tr');
        keys.forEach((key) => {
            const td = document.createElement('td');
            td.textContent = row[key] ?? '';
            if (key === 'user_agent') {
                td.className = 'ua';
                td.title = td.textContent;
            }
            tr.appendChild(td);
        });
        body.appendChild(tr);
    });
}

// Redrawn on every refresh, so the colors also follow day or night mode
function drawChart(hours) {
    if (chart) {
        chart.destroy();
    }
    chart = new Chart(document.getElementById('hour-chart'), {
        type: 'bar',
        data: {
            labels: hours.map((h) => h.label),
            datasets: [{ data: hours.map((h) => h.count), backgroundColor: css('--purple'), borderRadius: 4 }],
        },
        options: {
            animation: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: css('--muted') }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: css('--muted'), precision: 0 }, grid: { color: css('--border') } },
            },
        },
    });
}

async function load() {
    const params = new URLSearchParams(new FormData(filterForm));
    try {
        const response = await fetch('php/background/dashboard_data.php?' + params);
        // The server sends an empty 403 when the session ended or the user is not an admin
        if (response.status === 403) {
            window.location.href = 'login.php';
            return;
        }
        const data = await response.json();

        Object.entries(data.summary).forEach(([key, value]) => {
            document.querySelector('[data-key="' + key + '"]').textContent = value;
        });
        drawChart(data.hours);
        fillTable('ips-body', data.top_ips.map((r) => ({ ...r, blocked: r.blocked ? 'Blocked' : 'Not blocked' })),
            ['ip', 'failures', 'blocked'], 'No failed log-ins in the last 24 hours.');
        fillTable('flags-body', data.flags,
            ['severity', 'pattern', 'target', 'details', 'flag_date'], 'No flags yet. Run the monitoring script.');
        fillTable('events-body', data.events,
            ['created_at', 'event_type', 'username_typed', 'ip_address', 'user_agent', 'details'], 'No events match.');

        document.getElementById('status').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
        document.getElementById('status').textContent = 'Could not load the data.';
    }
}

filterForm.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
});

document.getElementById('filter-reset').addEventListener('click', () => {
    filterForm.reset();
    load();
});

load();
setInterval(load, 60000);