const password = document.getElementById('password');
const password2 = document.getElementById('password2');
const meter = document.getElementById('password-meter');

// Same rule as registration: spaces cannot be typed
[password, password2].forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\s/g, '');
    });
});

// Counts the kinds of characters: lowercase, uppercase, digit, symbol (same rule as the server)
function strength(v) {
    const kinds = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/].filter((r) => r.test(v)).length;
    if (kinds <= 1) return 'Weak';
    return kinds === 4 ? 'Strong' : 'Medium';
}

function updateMeter() {
    const level = password.value === '' ? '' : strength(password.value);
    meter.className = 'meter ' + level.toLowerCase();
    meter.querySelector('.meter-text').textContent = level;
}

password.addEventListener('input', updateMeter);
updateMeter();