<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/captcha.php';

$code = captchaNewCode();

// Draw small, then stretch: the stretching makes the letters rough and harder for bots
$small = imagecreatetruecolor(90, 28);
imagefill($small, 0, 0, imagecolorallocate($small, 241, 236, 251));

for ($i = 0; $i < 60; $i++) {
    imagesetpixel($small, random_int(0, 89), random_int(0, 27), imagecolorallocate($small, 180, 165, 220));
}
for ($i = 0; $i < 5; $i++) {
    imageline($small, random_int(0, 89), random_int(0, 27), random_int(0, 89), random_int(0, 27),
        imagecolorallocate($small, random_int(120, 190), random_int(100, 170), random_int(190, 230)));
}
for ($i = 0; $i < strlen($code); $i++) {
    $color = imagecolorallocate($small, random_int(40, 90), random_int(20, 60), random_int(110, 160));
    imagestring($small, 5, 10 + $i * 14, random_int(3, 9), $code[$i], $color);
}

$big = imagecreatetruecolor(210, 65);
imagecopyresized($big, $small, 0, 0, 0, 0, 210, 65, 90, 28);
imagesetthickness($big, 2);
for ($i = 0; $i < 3; $i++) {
    imageline($big, 0, random_int(5, 60), 209, random_int(5, 60), imagecolorallocate($big, 108, 74, 182));
}

header('Content-Type: image/png');
header('Cache-Control: no-store');
imagepng($big);