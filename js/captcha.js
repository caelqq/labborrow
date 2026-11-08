const captchaImg = document.getElementById('captcha-img');

// The time at the end of the address forces the browser to load a new picture
document.getElementById('captcha-refresh').addEventListener('click', () => {
    captchaImg.src = 'php/background/captcha_image.php?t=' + Date.now();
});