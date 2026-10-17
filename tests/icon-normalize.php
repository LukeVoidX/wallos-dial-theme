<?php
declare(strict_types=1);
require __DIR__ . '/../theme/includes/dial_icon_normalize.php';

function must(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$scratch = sys_get_temp_dir() . '/dial-icon-normalize-' . bin2hex(random_bytes(6));
must(mkdir($scratch, 0700), 'could not create fixture directory');
function upload(string $path, string $name, string $type): array {
    return ['tmp_name'=>$path,'name'=>$name,'type'=>$type,'size'=>filesize($path),'error'=>UPLOAD_ERR_OK];
}
try {
    $image = imagecreatetruecolor(128, 128);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, 127, 127, $clear);
    $red = imagecolorallocatealpha($image, 200, 10, 30, 0);
    imagefilledellipse($image, 64, 64, 80, 80, $red);
    foreach (['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'] as $extension=>$type) {
        $path = "$scratch/source.$extension";
        $saved = match ($extension) {
            'png'=>imagepng($image, $path), 'jpg'=>imagejpeg($image, $path, 90),
            'webp'=>imagewebp($image, $path, 90),
        };
        must($saved, "could not create $extension fixture");
        $result = dialNormalizeIconUpload(upload($path, "source.$extension", $type));
        must(in_array($result['mime'], ['image/png','image/jpeg','image/webp'], true), "$extension MIME invalid");
        must($result['width'] === 128 && $result['height'] === 128, "$extension dimensions changed");
        must(strlen($result['bytes']) <= 524288, "$extension normalized size too large");
        must(hash('sha256', $result['bytes']) === $result['sha256'], "$extension hash mismatch");
        must(imagecreatefromstring($result['bytes']) !== false, "$extension normalized bytes cannot decode");
    }
    imagedestroy($image);

    $simple = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="20" fill="#b01924"/></svg>';
    file_put_contents("$scratch/simple.svg", $simple);
    $result = dialNormalizeIconUpload(upload("$scratch/simple.svg", 'simple.svg', 'image/svg+xml'));
    must($result['width'] <= 1024 && $result['height'] <= 1024, 'SVG output dimensions unbounded');
    must(!str_contains($result['bytes'], '<svg'), 'raw SVG bytes returned');
    must(imagecreatefromstring($result['bytes']) !== false, 'SVG raster result cannot decode');

    $bad = [
        'doctype'=>'<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>' . $simple,
        'external'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image href="https://example.invalid/x"/></svg>',
        'xlink'=>'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 64 64"><use xlink:href="file:///etc/passwd"/></svg>',
        'foreign'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><foreignObject><p>bad</p></foreignObject></svg>',
        'script'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><script>alert(1)</script></svg>',
        'event'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" onload="bad()"><circle r="1"/></svg>',
        'animation'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><animate attributeName="x"/></svg>',
        'url'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle r="2" fill="url(file:///etc/passwd)"/></svg>',
        'namespace'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle xmlns="https://example.invalid" r="2"/></svg>',
        'many-nodes'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">' . str_repeat('<circle r="1"/>', 257) . '</svg>',
        'style'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle style="fill:url(https://example.invalid/x)"/></svg>',
        'path'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="' . str_repeat('M1 1 ', 15000) . '"/></svg>',
    ];
    foreach ($bad as $name=>$xml) {
        $path = "$scratch/$name.svg";
        file_put_contents($path, $xml);
        try {
            dialNormalizeIconUpload(upload($path, "$name.svg", 'image/svg+xml'));
            throw new RuntimeException("$name SVG accepted");
        } catch (InvalidArgumentException $error) {
            must($error->getMessage() === 'unsafe_svg', "$name wrong rejection: " . $error->getMessage());
        }
    }
    file_put_contents("$scratch/fake.png", 'not an image');
    try {
        dialNormalizeIconUpload(upload("$scratch/fake.png", 'fake.png', 'image/png'));
        throw new RuntimeException('fake raster accepted');
    } catch (InvalidArgumentException $error) {
        must($error->getMessage() === 'icon_image_invalid', 'wrong fake raster error');
    }
    file_put_contents("$scratch/truncated.png", "\x89PNG\r\n\x1a\n" . str_repeat("\0", 20));
    try {
        dialNormalizeIconUpload(upload("$scratch/truncated.png", 'truncated.png', 'image/png'));
        throw new RuntimeException('truncated PNG accepted');
    } catch (InvalidArgumentException $error) {
        must($error->getMessage() === 'icon_image_invalid', 'wrong truncated raster error');
    }
    file_put_contents("$scratch/large.svg", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">' . str_repeat(' ', 262145) . '</svg>');
    try {
        dialNormalizeIconUpload(upload("$scratch/large.svg", 'large.svg', 'image/svg+xml'));
        throw new RuntimeException('oversized SVG accepted');
    } catch (InvalidArgumentException $error) {
        must($error->getMessage() === 'unsafe_svg', 'wrong oversized SVG error');
    }
    file_put_contents("$scratch/request.svg", str_repeat(' ', DIAL_ICON_MAX_INPUT + 1));
    try {
        dialNormalizeIconUpload(upload("$scratch/request.svg", 'request.svg', 'image/svg+xml'));
        throw new RuntimeException('over-2 MiB upload accepted');
    } catch (InvalidArgumentException $error) {
        must($error->getMessage() === 'icon_image_invalid', 'wrong upload size error');
    }
    try {
        dialNormalizeIconUpload(upload("$scratch/simple.svg", 'simple.svg', 'image/svg+xml'),
            static function (): void { throw new RuntimeException('timeout'); });
        throw new RuntimeException('renderer timeout accepted');
    } catch (InvalidArgumentException $error) {
        must($error->getMessage() === 'svg_render_failed', 'wrong renderer failure error');
    }
    echo "Raster, SVG, unsafe-input, and renderer-limit checks passed.\n";
} finally {
    foreach (glob($scratch . '/*') ?: [] as $file) unlink($file);
    rmdir($scratch);
}
