<?php
declare(strict_types=1);
require __DIR__ . '/../theme/includes/dial_logo_limits.php';

$buffer = '';
for ($i = 0; $i < 64; $i++) {
    assert(dialLogoCollectChunk($buffer, str_repeat('a', 1024), 65536) === 1024);
}
assert(strlen($buffer) === 65536);
assert(dialLogoCollectChunk($buffer, 'x', 65536) === 0);
assert(strlen($buffer) === 65536);
assert(dialLogoCollectChunk($buffer, '', 65536) === 0);

$normal = imagecreatetruecolor(128, 64);
ob_start();
imagepng($normal);
$normalBytes = (string) ob_get_clean();
imagedestroy($normal);
assert(dialLogoImageWithinLimits($normalBytes));

$tooWide = imagecreatetruecolor(4097, 1);
ob_start();
imagepng($tooWide);
$tooWideBytes = (string) ob_get_clean();
imagedestroy($tooWide);
assert(strlen($tooWideBytes) < 65536);
assert(!dialLogoImageWithinLimits($tooWideBytes));
assert(!dialLogoImageWithinLimits('not an image'));
assert(!dialLogoImageWithinLimits($normalBytes, strlen($normalBytes) - 1));

$path = tempnam(sys_get_temp_dir(), 'dial-logo-test');
try {
    file_put_contents($path, $normalBytes);
    assert(dialLogoFileWithinLimits($path));
    file_put_contents($path, $tooWideBytes);
    assert(!dialLogoFileWithinLimits($path));
} finally {
    unlink($path);
}
echo "Legacy Logo download and decode bounds passed.\n";
