const code = document.getElementById('code');
const resendBtn = document.getElementById('resend-btn');

if (code) {
    code.addEventListener('input', () => {
        code.value = code.value.replace(/\D/g, '');
    });
}

// The server also checks the cooldown, this countdown is only for the user to see
let wait = resendBtn ? parseInt(resendBtn.dataset.wait, 10) || 0 : 0;

function tick() {
    if (!resendBtn) return;
    if (wait > 0) {
        resendBtn.disabled = true;
        resendBtn.textContent = `Resend code in ${wait}s`;
        wait--;
        setTimeout(tick, 1000);
    } else {
        resendBtn.disabled = false;
        resendBtn.textContent = 'Resend code';
    }
}

tick();