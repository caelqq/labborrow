const username = document.getElementById('username');

// Same rule as registration: the user cannot type the wrong characters; the server checks again
if (username) {
    username.addEventListener('input', () => {
        username.value = username.value.replace(/[^A-Za-z0-9]/g, '');
    });
}

document.querySelectorAll('.answer-input').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^A-Za-z ]/g, '');
    });
});

// The server enforces the lock; this countdown is only for the user to see
const countdown = document.getElementById('countdown');
if (countdown) {
    let left = parseInt(countdown.dataset.wait, 10);
    const timer = setInterval(() => {
        left--;
        countdown.textContent = left;
        if (left <= 0) {
            clearInterval(timer);
            window.location.href = 'forgot.php';
        }
    }, 1000);
}