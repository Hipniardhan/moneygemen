<?php
// Membuat ikon PWA dengan GD. Jalankan: php scripts/make-icons.php

$out = __DIR__ . '/../public/icons';
@mkdir($out, 0755, true);
$font = 'C:/Windows/Fonts/arialbd.ttf';

function draw(int $size, bool $maskable, string $font, bool $badge = false)
{
    $s = $size * 4; // supersampling
    $im = imagecreatetruecolor($s, $s);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $clear);
    imagealphablending($im, true);

    $teal = imagecolorallocate($im, 15, 118, 110);
    $white = imagecolorallocate($im, 255, 255, 255);

    if ($badge) {
        imagefilledellipse($im, $s / 2, $s / 2, $s, $s, $white);
        $fg = $white; $bg = $white;
    } elseif ($maskable) {
        imagefilledrectangle($im, 0, 0, $s, $s, $teal); // penuh, OS yang memotong
    } else {
        $r = (int) ($s * 0.22);
        imagefilledrectangle($im, $r, 0, $s - $r, $s, $teal);
        imagefilledrectangle($im, 0, $r, $s, $s - $r, $teal);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$x, $y]) {
            imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $teal);
        }
    }

    // koin + huruf M
    $scale = $maskable ? 0.40 : 0.56;
    $d = (int) ($s * $scale);
    $gold = imagecolorallocate($im, 252, 211, 77);
    $ink = imagecolorallocate($im, 17, 94, 89);
    imagefilledellipse($im, $s / 2, $s / 2, $d, $d, $badge ? $teal : $gold);
    if (is_file($font)) {
        $fs = $d * 0.5;
        $box = imagettfbbox($fs, 0, $font, 'M');
        $w = $box[2] - $box[0];
        $h = $box[1] - $box[7];
        imagettftext($im, $fs, 0, (int) (($s - $w) / 2 - $box[0]), (int) (($s + $h) / 2), $badge ? $white : $ink, $font, 'M');
    }

    $final = imagecreatetruecolor($size, $size);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
    imagecopyresampled($final, $im, 0, 0, 0, 0, $size, $size, $s, $s);
    return $final;
}

$targets = [
    'icon-192.png' => [192, false],
    'icon-512.png' => [512, false],
    'maskable-512.png' => [512, true],
    'apple-touch-icon.png' => [180, true],
];
foreach ($targets as $name => [$size, $mask]) {
    imagepng(draw($size, $mask, $font), "$out/$name");
    echo "$name\n";
}

// badge notifikasi Android: siluet putih di atas transparan
$b = draw(96, false, $font, true);
imagepng($b, "$out/badge-96.png");
echo "badge-96.png\n";
