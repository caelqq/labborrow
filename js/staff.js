const section = document.getElementById('sec-items');
const csrf = section.dataset.csrf;
const grid = document.getElementById('item-grid');
const empty = document.getElementById('items-empty');
const showHidden = document.getElementById('show-hidden');
const search = document.getElementById('item-search');
const chips = document.querySelectorAll('#item-filter .chip');
const pageMain = document.querySelector('main');

let latest = null;
let lastText = '';
let filterValue = 'all';

// textContent only, never innerHTML, so item names can never run as code (protection 2)
function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function setNumber(node, target) {
    const from = parseInt(node.textContent, 10) || 0;
    if (from === target) return;
    const start = performance.now();

    function step(now) {
        const t = Math.min((now - start) / 600, 1);
        node.textContent = Math.round(from + (target - from) * t);
        if (t < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

async function send(data) {
    data.append('csrf_token', csrf);
    const response = await fetch('php/background/staff_item_action.php', { method: 'POST', body: data });
    // 403 = the session ended or this is not a staff account
    if (response.status === 403) {
        window.location.href = 'login.php';
        return null;
    }
    // A real action also resets the server's idle timer, so the countdown in idle.js restarts too
    window.dispatchEvent(new Event('user-active'));
    return response.json();
}

function openModal(modal) {
    modal.classList.add('open');
}

function closeModal(modal) {
    modal.classList.remove('open');
}

const addModal = document.getElementById('add-modal');
const stockModal = document.getElementById('stock-modal');
const deleteModal = document.getElementById('delete-modal');
let deleteId = 0;
let stockId = 0;
let stockMode = 'stock';

// One pop-up for both: mode is 'stock' (add) or 'unstock' (remove)
function openStock(item, mode, maxRemove) {
    stockId = item.id;
    stockMode = mode;
    const adding = mode === 'stock';
    document.getElementById('stock-title').textContent = adding ? 'Add stock' : 'Remove stock';
    document.getElementById('stock-text').textContent = adding
        ? 'How many more ' + item.name + ' are you adding?'
        : 'How many ' + item.name + ' do you want to remove? You can remove up to ' + maxRemove + '.';
    document.getElementById('stock-label').childNodes[0].textContent = adding ? 'Amount to add ' : 'Amount to remove ';
    document.getElementById('stock-submit').textContent = adding ? 'Add stock' : 'Remove stock';
    document.getElementById('stock-amount').value = '';
    document.getElementById('err-amount').textContent = '';
    openModal(stockModal);
    document.getElementById('stock-amount').focus();
}

function buildCard(item, lowAt) {
    const card = el('article', 'item-card' + (item.hidden ? ' is-hidden' : ''));

    const photo = el('div', 'item-photo');
    if (item.photo) {
        const img = document.createElement('img');
        img.src = 'images/items/' + item.photo;
        img.alt = item.name;
        photo.appendChild(img);
    } else {
        photo.appendChild(el('span', 'no-photo', 'No photo'));
    }
    // A big stamp on the photo when every copy is out
    if (item.available <= 0) {
        photo.classList.add('is-out');
        photo.appendChild(el('span', 'stamp', 'No Stock'));
    }
    card.appendChild(photo);

    if (item.hidden) card.appendChild(el('span', 'hidden-tag', 'Hidden'));

    const dots = el('button', 'dots-btn', '\u22EE');
    dots.type = 'button';
    dots.setAttribute('aria-label', 'Item options');
    const menu = el('div', 'dots-menu');

    const addStock = el('button', '', 'Add stock');
    addStock.type = 'button';
    addStock.addEventListener('click', () => openStock(item, 'stock', 0));

    // At least 1 must stay, and copies that are approved or borrowed cannot be removed
    const taken = item.quantity - item.available;
    const maxRemove = item.quantity - Math.max(1, taken);
    const removeStock = el('button', '', 'Remove stock');
    removeStock.type = 'button';
    if (maxRemove <= 0) {
        removeStock.disabled = true;
        removeStock.title = 'At least 1 must stay in stock';
    }
    removeStock.addEventListener('click', () => openStock(item, 'unstock', maxRemove));

    const toggle = el('button', '', item.hidden ? 'Show item' : 'Hide item');
    toggle.type = 'button';
    toggle.addEventListener('click', () => changeItem(item.hidden ? 'show' : 'hide', item.id));

    const del = el('button', 'danger', 'Delete item');
    del.type = 'button';
    if (item.open_requests > 0) {
        del.disabled = true;
        del.title = 'Someone has requested or is borrowing this item';
    }
    del.addEventListener('click', () => {
        deleteId = item.id;
        document.getElementById('delete-name').textContent = item.name;
        document.getElementById('delete-error').textContent = '';
        openModal(deleteModal);
    });

    menu.append(addStock, removeStock, toggle, del);
    dots.addEventListener('click', (event) => {
        event.stopPropagation();
        const wasOpen = menu.classList.contains('open');
        document.querySelectorAll('.dots-menu.open').forEach((m) => m.classList.remove('open'));
        menu.classList.toggle('open', !wasOpen);
    });
    card.append(dots, menu);

    const body = el('div', 'item-body');
    body.appendChild(el('h3', 'item-name', item.name));
    let level = 'ok';
    let label = item.available + ' of ' + item.quantity + ' available';
    if (item.available <= 0) {
        level = 'out';
        label = 'No Stock';
    } else if (item.available <= lowAt) {
        level = 'low';
    }
    body.appendChild(el('span', 'pill ' + level, label));
    if (item.open_requests > 0) {
        body.appendChild(el('p', 'item-meta', item.open_requests + ' active request(s)'));
    }
    card.appendChild(body);
    return card;
}

// The filter and the search work together: the search only looks inside the chosen filter
function matchesFilter(item, mode, lowAt) {
    if (mode === 'hidden') return item.hidden;
    if (item.hidden && !showHidden.checked) return false;
    if (mode === 'nostock') return item.available <= 0;
    if (mode === 'low') return item.available > 0 && item.available <= lowAt;
    if (mode === 'available') return item.available > lowAt;
    return true;
}

function updateFilterCounts() {
    chips.forEach((chip) => {
        const count = latest.items.filter((item) => matchesFilter(item, chip.dataset.filter, latest.low_at)).length;
        chip.querySelector('.chip-count').textContent = count;
    });
}

function render() {
    if (!latest) return;
    updateFilterCounts();

    // The search matches the start of the name and ignores capital letters
    const q = search.value.trim().toLowerCase();
    const list = latest.items.filter((item) =>
        matchesFilter(item, filterValue, latest.low_at) && item.name.toLowerCase().startsWith(q)
    );
    grid.replaceChildren();
    list.forEach((item) => grid.appendChild(buildCard(item, latest.low_at)));

    empty.hidden = list.length > 0;
    if (latest.items.length === 0) {
        empty.textContent = 'No equipment yet. Click Add Item to list one.';
    } else if (q !== '') {
        empty.textContent = 'No items match your search.';
    } else if (filterValue !== 'all') {
        empty.textContent = 'No items in this filter.';
    } else {
        empty.textContent = 'All items are hidden. Tick "Show hidden items" to see them.';
    }
}

// Real-time: ask the server every 5 seconds, and redraw only when something changed
async function load() {
    if (document.hidden) return;
    try {
        const response = await fetch('php/background/staff_data.php');
        if (response.status === 403) {
            window.location.href = 'login.php';
            return;
        }
        const text = await response.text();
        if (text === lastText) return;
        const data = JSON.parse(text);
        if (data.error) return;

        lastText = text;
        latest = data;
        Object.entries(data.summary).forEach(([key, value]) => {
            const num = document.querySelector('.stat-num[data-key="' + key + '"]');
            setNumber(num, value);
            if (key === 'missing') {
                num.closest('.stat').classList.toggle('warn', value > 0);
            }
        });
        render();
    } catch (e) {}
}

async function changeItem(action, id) {
    const data = new FormData();
    data.append('action', action);
    data.append('id', id);
    try {
        const result = await send(data);
        if (result && result.error) window.alert(result.error);
    } catch (e) {}
    load();
}

showHidden.addEventListener('change', render);
search.addEventListener('input', render);

chips.forEach((chip) => {
    chip.addEventListener('click', () => {
        filterValue = chip.dataset.filter;
        chips.forEach((c) => c.classList.toggle('active', c === chip));
        render();
    });
});

document.addEventListener('click', () => {
    document.querySelectorAll('.dots-menu.open').forEach((m) => m.classList.remove('open'));
});

const addForm = document.getElementById('add-form');
const photoInput = document.getElementById('photo');
const preview = document.getElementById('photo-preview');
const quantityInput = document.getElementById('quantity');

function setError(key, message) {
    document.getElementById('err-' + key).textContent = message;
}

function clearAddErrors() {
    ['photo', 'name', 'quantity'].forEach((key) => setError(key, ''));
    document.getElementById('add-error').textContent = '';
}

document.getElementById('add-open').addEventListener('click', () => {
    addForm.reset();
    clearAddErrors();
    preview.hidden = true;
    openModal(addModal);
});

document.getElementById('add-cancel').addEventListener('click', () => closeModal(addModal));

photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    preview.hidden = !file;
    if (file) preview.src = URL.createObjectURL(file);
});

quantityInput.addEventListener('input', () => {
    quantityInput.value = quantityInput.value.replace(/\D/g, '');
});

// The server checks everything again; this only gives a quicker message
addForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearAddErrors();

    const file = photoInput.files[0];
    if (file && file.size > 2 * 1024 * 1024) {
        setError('photo', 'Photo must be 2 MB or smaller.');
        return;
    }

    const button = document.getElementById('add-submit');
    button.disabled = true;
    const data = new FormData(addForm);
    data.append('action', 'add');
    try {
        const result = await send(data);
        if (!result) return;
        if (result.ok) {
            closeModal(addModal);
            load();
            return;
        }
        Object.entries(result.errors || {}).forEach(([key, message]) => setError(key, message));
        if (result.error) document.getElementById('add-error').textContent = result.error;
    } catch (e) {
        document.getElementById('add-error').textContent = 'Could not reach the server.';
    } finally {
        button.disabled = false;
    }
});

const stockForm = document.getElementById('stock-form');
const stockAmount = document.getElementById('stock-amount');

stockAmount.addEventListener('input', () => {
    stockAmount.value = stockAmount.value.replace(/\D/g, '');
});

document.getElementById('stock-cancel').addEventListener('click', () => closeModal(stockModal));

stockForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setError('amount', '');

    const button = document.getElementById('stock-submit');
    button.disabled = true;
    const data = new FormData();
    data.append('action', stockMode);
    data.append('id', stockId);
    data.append('amount', stockAmount.value);
    try {
        const result = await send(data);
        if (!result) return;
        if (result.ok) {
            closeModal(stockModal);
            load();
            return;
        }
        Object.entries(result.errors || {}).forEach(([key, message]) => setError(key, message));
        if (result.error) setError('amount', result.error);
    } catch (e) {
        setError('amount', 'Could not reach the server.');
    } finally {
        button.disabled = false;
    }
});

document.getElementById('delete-cancel').addEventListener('click', () => closeModal(deleteModal));

document.getElementById('delete-confirm').addEventListener('click', async () => {
    const data = new FormData();
    data.append('action', 'delete');
    data.append('id', deleteId);
    try {
        const result = await send(data);
        if (!result) return;
        if (result.ok) {
            closeModal(deleteModal);
            load();
        } else {
            document.getElementById('delete-error').textContent = result.error || 'Could not delete.';
        }
    } catch (e) {
        document.getElementById('delete-error').textContent = 'Could not reach the server.';
    }
});

[addModal, stockModal, deleteModal].forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal(modal);
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeModal(addModal);
        closeModal(stockModal);
        closeModal(deleteModal);
    }
});

const sideMenu = document.getElementById('staff-menu');
const overlay = document.getElementById('staff-overlay');

function setMenu(open) {
    sideMenu.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
}

document.getElementById('menu-btn').addEventListener('click', () => setMenu(true));
document.getElementById('menu-close').addEventListener('click', () => setMenu(false));
overlay.addEventListener('click', () => setMenu(false));

// Sidebar links: show one section (Items, Approval or Log) and close the menu
const links = document.querySelectorAll('.menu-link');
const sections = document.querySelectorAll('.staff-section');

links.forEach((link) => {
    link.addEventListener('click', () => {
        links.forEach((l) => l.classList.toggle('active', l === link));
        sections.forEach((s) => s.classList.toggle('active', s.id === 'sec-' + link.dataset.section));
        // The class on <main> lets the CSS choose which title and cards each page shows
        pageMain.classList.remove('sec-items', 'sec-approval', 'sec-log');
        pageMain.classList.add('sec-' + link.dataset.section);
        setMenu(false);
    });
});

// Sub-tabs work only inside their own section
document.querySelectorAll('.tabs').forEach((group) => {
    const buttons = group.querySelectorAll('.tab-btn');
    const panels = group.closest('.staff-section').querySelectorAll('.tab-panel');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((b) => b.classList.toggle('active', b === button));
            panels.forEach((p) => p.classList.toggle('active', p.id === 'tab-' + button.dataset.tab));
        });
    });
});

// Pop-ups move out of the animated page box, so they center on the screen
document.body.append(addModal, stockModal, deleteModal);

load();
setInterval(load, 5000);