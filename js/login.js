const username = document.getElementById('username');
const password = document.getElementById('password');
const captcha = document.getElementById('captcha');
const eyeBtn = document.getElementById('eye-btn');

function usernameError(v) {
    if (v === '') return 'Username is required.';
    if (/[^A-Za-z0-9]/.test(v)) return 'Username may contain letters and digits only.';
    if (!/^[A-Za-z]/.test(v)) return 'Username must start with a letter.';
    if (v.length < 8 || v.length > 17) return 'Username must be 8 to 17 characters.';
    return '';
}

function passwordError(v) {
    if (v === '') return 'Password is required.';
    if (v.includes(' ')) return 'Password must not contain spaces.';
    if (v.length < 9 || v.length > 64) return 'Password must be 9 to 64 characters.';
    return '';
}

function showError(input, message) {
    input.closest('.field').querySelector('.error').textContent = message;
    return message;
}

// The server checks the same rules again, this only saves the user a round trip
document.querySelector('form').addEventListener('submit', (event) => {
    const bad = [
        [username, usernameError(username.value)],
        [password, passwordError(password.value)],
        [captcha, captcha.value === '' ? 'Please enter the characters shown in the image.' : ''],
    ].filter(([input, message]) => showError(input, message));

    if (bad.length) {
        event.preventDefault();
        bad[0][0].focus();
    }
});

[username, password, captcha].forEach((input) => {
    input.addEventListener('input', () => showError(input, ''));
});

eyeBtn.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    eyeBtn.classList.toggle('on', show);
    eyeBtn.title = show ? 'Hide Password' : 'Show Password';
    eyeBtn.setAttribute('aria-label', eyeBtn.title);
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
            window.location.href = 'login.php';
        }
    }, 1000);
}