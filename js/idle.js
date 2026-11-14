const tag = document.currentScript;
const limit = parseInt(tag.dataset.limit, 10) * 1000;
const warn = parseInt(tag.dataset.warn, 10) * 1000;
// How often activity is reported; always much shorter than the warning time
const CHECK_EVERY = Math.min(15000, warn / 3);

let warnTimer;
let outTimer;
let tickTimer;
let idleBox;
let moved = false;

function start() {
    warnTimer = setTimeout(showWarning, warn);
    outTimer = setTimeout(() => {
        window.location.href = 'logout.php';
    }, limit);
}

function showWarning() {
    let left = Math.round((limit - warn) / 1000);
    idleBox = document.createElement('div');
    idleBox.className = 'idle-overlay';
    idleBox.innerHTML =
        '<div class="idle-box">' +
        '<h2>Are you still there?</h2>' +
        '<p>You will be logged out in <strong id="idle-left">' + left + '</strong> seconds.</p>' +
        '<button type="button" id="idle-stay">Stay logged in</button>' +
        '</div>';
    document.body.appendChild(idleBox);

    tickTimer = setInterval(() => {
        left--;
        document.getElementById('idle-left').textContent = left;
    }, 1000);

    document.getElementById('idle-stay').addEventListener('click', stay);
}

// Starts the countdown again from zero
function restart() {
    clearTimeout(warnTimer);
    clearTimeout(outTimer);
    clearInterval(tickTimer);
    if (idleBox) {
        idleBox.remove();
        idleBox = null;
    }
    start();
}

// Asking the server resets the real timer; the page timers only follow it
async function stay() {
    try {
        await fetch('php/background/keepalive.php');
    } catch (e) {}
    restart();
}

// Any real use of the page counts as activity (the automatic refresh does not)
['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach((name) => {
    window.addEventListener(name, () => { moved = true; }, { passive: true });
});

// On every check: if the user did something, tell the server and restart the countdown
// While the warning box is open, only the Stay button counts
setInterval(async () => {
    if (!moved || idleBox) return;
    moved = false;
    try {
        await fetch('php/background/keepalive.php');
    } catch (e) {}
    if (!idleBox) restart();
}, CHECK_EVERY);

// Pages that do real work without reloading (the staff page) send this after each action
window.addEventListener('user-active', restart);

start();