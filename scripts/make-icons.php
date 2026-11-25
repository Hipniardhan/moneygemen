<?php
/**
 * Membuat semua ikon dari logo sumber (resources/branding/logo-source.jpg).
 * Jalankan: php scripts/make-icons.php
 *
 * Latar kertas pada logo dihapus dengan mask halus berdasarkan jarak warna, lalu lambang
 * ditempatkan di atas warna krem yang rata, jadi tidak ada sambungan/tekstur yang terlihat.
 */

$root = dirname(__DIR__);
$srcFile = "$root/resources/branding/logo-source.jpg";
$out = "$root/public/icons";
@mkdir($out, 0755, true);

$src = imagecreatefromjpeg($srcFile);
$BG = [247, 235, 224]; // krem latar logo (#f7ebe0), hasil pengukuran

// area pada gambar sumber (hasil pengukuran): lambang dan tulisan
$MARK = [150, 76, 726, 612];     // x0,y0,x1,y1
$FULL = [70, 76, 772, 738];      // lambang + tulisan "moneygemen"

/** Potong area sumber menjadi gambar RGBA: latar menjadi transparan, tepi dibersihkan dari warna latar. */
function extractMark($src, array $box, array $bg, float $lo = 30, float $hi = 68)
{
    [$x0, $y0, $x1, $y1] = $box;
    $w = $x1 - $x0;
    $h = $y1 - $y0;
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($src, $x0 + $x, $y0 + $y);
            $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
            $d = sqrt(($r - $bg[0]) ** 2 + ($g - $bg[1]) ** 2 + ($b - $bg[2]) ** 2);
            $t = max(0.0, min(1.0, ($d - $lo) / ($hi - $lo)));
            $a = $t * $t * (3 - 2 * $t); // smoothstep
            if ($a > 0.02) {
                // lepas campuran warna latar dari piksel tepi (menghilangkan halo terang)
                $r = (int) max(0, min(255, ($r - $bg[0] * (1 - $a)) / $a));
                $g = (int) max(0, min(255, ($g - $bg[1] * (1 - $a)) / $a));
                $b = (int) max(0, min(255, ($b - $bg[2] * (1 - $a)) / $a));
            }
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $g, $b, 127 - (int) round($a * 127)));
        }
    }

    return $im;
}

/** Kanvas penuh warna krem. */
function solid(int $size, array $bg, ?int $h = null)
{
    $im = imagecreatetruecolor($size, $h ?? $size);
    imagealphablending($im, true);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocate($im, $bg[0], $bg[1], $bg[2]));
    return $im;
}

/** Persegi dengan sudut membulat (tepi halus lewat supersampling), di luar sudut transparan. */
function roundedSquare(int $size, array $bg, float $radiusRatio = 0.22)
{
    $k = 4;
    $s = $size * $k;
    $big = imagecreatetruecolor($s, $s);
    imagealphablending($big, false);
    imagesavealpha($big, true);
    imagefill($big, 0, 0, imagecolorallocatealpha($big, 0, 0, 0, 127));
    $col = imagecolorallocate($big, $bg[0], $bg[1], $bg[2]);
    $r = (int) ($s * $radiusRatio);
    imagefilledrectangle($big, $r, 0, $s - $r, $s, $col);
    imagefilledrectangle($big, 0, $r, $s, $s - $r, $col);
    foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$x, $y]) {
        imagefilledellipse($big, $x, $y, $r * 2, $r * 2, $col);
    }
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagecopyresampled($im, $big, 0, 0, 0, 0, $size, $size, $s, $s);
    imagealphablending($im, true);
    return $im;
}

/** Tempel $mark di tengah $canvas dengan lebar $targetW. */
function place($canvas, $mark, int $targetW, ?int $cx = null, ?int $cy = null): void
{
    $cw = imagesx($canvas);
    $ch = imagesy($canvas);
    $mw = imagesx($mark);
    $mh = imagesy($mark);
    $tw = $targetW;
    $th = (int) round($mh * $tw / $mw);
    $cx ??= intdiv($cw, 2);
    $cy ??= intdiv($ch, 2);
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $mark, $cx - intdiv($tw, 2), $cy - intdiv($th, 2), 0, 0, $tw, $th, $mw, $mh);
}

/** Siluet putih (alpha dari mark) untuk badge notifikasi Android. */
function silhouette($mark)
{
    $w = imagesx($mark);
    $h = imagesy($mark);
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $a = (imagecolorat($mark, $x, $y) >> 24) & 127; // 0 = buram, 127 = transparan
            // garis terang (kuning/biru muda) tetap dianggap bagian lambang: pertegas alpha
            $op = 1 - $a / 127;
            $op = min(1.0, $op * 1.6);
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 255, 255, 255, 127 - (int) round($op * 127)));
        }
    }
    return $im;
}

function save($im, string $path): void
{
    imagesavealpha($im, true);
    imagepng($im, $path, 9);
    echo basename($path) . "  " . imagesx($im) . "x" . imagesy($im) . "\n";
}

$mark = extractMark($src, $MARK, $BG);
$full = extractMark($src, $FULL, $BG);

// ikon PWA "any": persegi membulat, lambang ±74% lebar
foreach ([192, 512] as $size) {
    $c = roundedSquare($size, $BG);
    place($c, $mark, (int) round($size * 0.74));
    save($c, "$out/icon-$size.png");
}

// maskable: isi penuh sampai tepi, lambang di zona aman (lingkaran 80% di tengah)
$c = solid(512, $BG);
place($c, $mark, (int) round(512 * 0.60));
save($c, "$out/maskable-512.png");

// ikon iOS: opak penuh (iOS yang membulatkan sudutnya)
$c = solid(180, $BG);
place($c, $mark, (int) round(180 * 0.66));
save($c, "$out/apple-touch-icon.png");

// badge notifikasi: siluet putih di atas transparan
$c = imagecreatetruecolor(96, 96);
imagealphablending($c, false);
imagesavealpha($c, true);
imagefill($c, 0, 0, imagecolorallocatealpha($c, 0, 0, 0, 127));
imagealphablending($c, true);
place($c, silhouette($mark), 88);
save($c, "$out/badge-96.png");

// logo lengkap (lambang + tulisan) untuk halaman masuk: latar krem rata, tanpa sudut (dibulatkan lewat CSS)
$pad = 44;
$fw = imagesx($full) + $pad * 2;
$fh = imagesy($full) + $pad * 2;
$c = solid($fw, $BG, $fh);
place($c, $full, imagesx($full));
$scaled = imagecreatetruecolor(560, (int) round($fh * 560 / $fw));
imagecopyresampled($scaled, $c, 0, 0, 0, 0, 560, imagesy($scaled), $fw, $fh);
save($scaled, "$out/logo-full.png");

// favicon.ico (berisi PNG 32 & 48 px, didukung semua browser modern)
$entries = [];
foreach ([32, 48] as $size) {
    $c = roundedSquare($size, $BG, 0.2);
    place($c, $mark, (int) round($size * 0.78));
    ob_start();
    imagepng($c);
    $entries[] = [$size, ob_get_clean()];
}
$ico = pack('vvv', 0, 1, count($entries));
$offset = 6 + 16 * count($entries);
foreach ($entries as [$size, $png]) {
    $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($png), $offset);
    $offset += strlen($png);
}
foreach ($entries as [, $png]) {
    $ico .= $png;
}
file_put_contents("$root/public/favicon.ico", $ico);
echo "favicon.ico  32+48\n";
