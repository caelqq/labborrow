const tag = document.currentScript;
const limit = parseInt(tag.dataset.limit, 10) * 1000;
const warn = parseInt(tag.dataset.warn, 10) * 1000;

let warnTimer;
let outTimer;
let tickTimer;
let overlay;

function start() {
    warnTimer = setTimeout(showWarning, warn);
    outTimer = setTimeout(() => {
        window.location.href = 'logout.php';
    }, limit);
}

function showWarning() {
    let left = Math.round((limit - warn) / 1000);
    overlay = document.createElement('div');
    overlay.className = 'idle-overlay';
    overlay.innerHTML =
        '<div class="idle-box">' +
        '<h2>Are you still there?</h2>' +
        '<p>You will be logged out in <strong id="idle-left">' + left + '</strong> seconds.</p>' +
        '<button type="button" id="idle-stay">Stay logged in</button>' +
        '</div>';
    document.body.appendChild(overlay);

    tickTimer = setInterval(() => {
        left--;
        document.getElementById('idle-left').textContent = left;
    }, 1000);

    document.getElementById('idle-stay').addEventListener('click', stay);
}

// Asking the server resets the real timer; the page timers only follow it
async function stay() {
    try {
        await fetch('php/keepalive.php');
    } catch (e) {}
    clearInterval(tickTimer);
    clearTimeout(outTimer);
    overlay.remove();
    start();
}

start();