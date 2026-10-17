<?php
declare(strict_types=1);

const DIAL_ICON_MAX_INPUT = 2097152;
const DIAL_ICON_MAX_OUTPUT = 524288;
const DIAL_ICON_MAX_SIDE = 1024;
const DIAL_SVG_NS = 'http://www.w3.org/2000/svg';

function dialIconNumber(string $value): bool
{
    return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', trim($value)) === 1
        && is_finite((float) $value) && abs((float) $value) <= 100000;
}

function dialIconNumberList(string $value, int $min, int $max): ?array
{
    if (strlen($value) > 4096 || preg_match('/[^0-9eE+.,\-\s]/', $value)) return null;
    $parts = preg_split('/[\s,]+/', trim($value));
    if (!$parts || count($parts) < $min || count($parts) > $max) return null;
    foreach ($parts as $part) if (!dialIconNumber($part)) return null;
    return array_map('floatval', $parts);
}

function dialIconSvgColor(string $value): bool
{
    if (strlen($value) > 64 || stripos($value, 'url(') !== false) return false;
    if (preg_match('/^(?:none|transparent|currentColor|[a-z]{3,20})$/D', $value)) return true;
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/D', $value)) return in_array(strlen($value), [4, 5, 7, 9], true);
    return preg_match('/^rgba?\(\s*[0-9.%,\s]+\)$/D', $value) === 1;
}

function dialIconSvgAttributeSafe(string $name, string $value): bool
{
    if (strlen($value) > 8192 || preg_match('/[<>\x00-\x1f]/', $value)) return false;
    if ($name === 'd') {
        return strlen($value) <= 8192 &&
            preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9eE+.,\-\s]+$/D', $value) === 1 &&
            preg_match_all('/[MmLlHhVvCcSsQqTtAaZz]/', $value) <= 512;
    }
    if ($name === 'points') return dialIconNumberList($value, 4, 1024) !== null;
    if ($name === 'viewBox') {
        $numbers = dialIconNumberList($value, 4, 4);
        return $numbers !== null && $numbers[2] > 0 && $numbers[3] > 0;
    }
    if (in_array($name, ['fill','stroke'], true)) return dialIconSvgColor($value);
    if (in_array($name, ['stroke-linecap','stroke-linejoin'], true)) {
        return in_array($value, ['butt','round','square','miter','bevel'], true);
    }
    if (in_array($name, ['fill-rule','clip-rule'], true)) return in_array($value, ['nonzero','evenodd'], true);
    if ($name === 'transform') {
        if (strlen($value) > 256 || !preg_match('/^(?:(?:matrix|translate|scale|rotate|skewX|skewY)\([0-9eE+.,\-\s]+\)\s*)+$/D', $value)) return false;
        foreach (preg_split('/\)\s*/', $value) as $part) {
            if ($part === '') continue;
            $numbers = dialIconNumberList(substr($part, strpos($part, '(') + 1), 1, 6);
            if ($numbers === null) return false;
        }
        return true;
    }
    if (in_array($name, ['x','y','x1','y1','x2','y2','cx','cy','r','rx','ry','width','height',
        'stroke-width','stroke-miterlimit','opacity','fill-opacity','stroke-opacity'], true)) {
        if (!dialIconNumber($value)) return false;
        $number = (float) $value;
        if (in_array($name, ['r','rx','ry','width','height','stroke-width'], true) && $number < 0) return false;
        if (str_ends_with($name, 'opacity') && ($number < 0 || $number > 1)) return false;
        return true;
    }
    return false;
}

/** Rebuild a small, standalone SVG from a closed allowlist; never render source XML. */
function dialIconSanitizeSvg(string $source): array
{
    if (strlen($source) > 262144 || preg_match('/<!|<\?(?!xml\s)/i', $source)) {
        throw new InvalidArgumentException('unsafe_svg');
    }
    $source = preg_replace('/^\s*<\?xml\s+[^?]*\?>\s*/i', '', $source) ?? '';
    if (str_contains($source, '<?')) throw new InvalidArgumentException('unsafe_svg');
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        if (!$document->loadXML($source, LIBXML_NONET | LIBXML_NOBLANKS) ||
            $document->doctype !== null || !$document->documentElement ||
            $document->documentElement->localName !== 'svg' ||
            $document->documentElement->namespaceURI !== DIAL_SVG_NS) {
            throw new InvalidArgumentException('unsafe_svg');
        }
        $output = new DOMDocument('1.0', 'UTF-8');
        $nodes = 0;
        $copy = static function (DOMElement $element) use (&$copy, &$nodes, $output): DOMElement {
            if (++$nodes > 256 || $element->namespaceURI !== DIAL_SVG_NS ||
                !in_array($element->localName, ['svg','g','path','circle','ellipse','rect','line','polyline','polygon'], true)) {
                throw new InvalidArgumentException('unsafe_svg');
            }
            $clean = $output->createElementNS(DIAL_SVG_NS, $element->localName);
            foreach ($element->attributes as $attribute) {
                if ($attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/' &&
                    $attribute->name === 'xmlns' && $attribute->value === DIAL_SVG_NS) continue;
                if ($attribute->namespaceURI !== null ||
                    !dialIconSvgAttributeSafe($attribute->name, $attribute->value)) {
                    throw new InvalidArgumentException('unsafe_svg');
                }
                $clean->setAttribute($attribute->name, $attribute->value);
            }
            foreach ($element->childNodes as $child) {
                if (!$child instanceof DOMElement) {
                    if ($child instanceof DOMText && trim($child->textContent) === '') continue;
                    throw new InvalidArgumentException('unsafe_svg');
                }
                $clean->appendChild($copy($child));
            }
            return $clean;
        };
        $root = $copy($document->documentElement);
        $output->appendChild($root);
        $viewBox = $root->getAttribute('viewBox');
        $view = $viewBox !== '' ? dialIconNumberList($viewBox, 4, 4) : null;
        $width = $view ? $view[2] : (float) ($root->getAttribute('width') ?: 256);
        $height = $view ? $view[3] : (float) ($root->getAttribute('height') ?: 256);
        if ($width <= 0 || $height <= 0 || $width > 10000 || $height > 10000) {
            throw new InvalidArgumentException('unsafe_svg');
        }
        $factor = min(1.0, 512 / max($width, $height));
        $renderWidth = max(1, min(DIAL_ICON_MAX_SIDE, (int) round($width * $factor)));
        $renderHeight = max(1, min(DIAL_ICON_MAX_SIDE, (int) round($height * $factor)));
        return [$output->saveXML($root), $renderWidth, $renderHeight];
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function dialIconEncodeRaster(GdImage $image, string $mime): string
{
    ob_start();
    $ok = match ($mime) {
        'image/png' => imagepng($image, null, 8),
        'image/jpeg' => imagejpeg($image, null, 82),
        'image/webp' => imagewebp($image, null, 82),
        default => false,
    };
    $bytes = ob_get_clean();
    if (!$ok || !is_string($bytes) || $bytes === '' || strlen($bytes) > DIAL_ICON_MAX_OUTPUT) {
        throw new InvalidArgumentException('icon_image_invalid');
    }
    return $bytes;
}

function dialIconNormalizedRaster(string $input, string $mime): array
{
    $info = @getimagesizefromstring($input);
    if (!$info || !in_array($info['mime'] ?? '', ['image/png','image/jpeg','image/webp'], true) ||
        ($info['mime'] ?? '') !== $mime) throw new InvalidArgumentException('icon_image_invalid');
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096 || $width * $height > 16777216) {
        throw new InvalidArgumentException('icon_image_invalid');
    }
    $image = @imagecreatefromstring($input);
    if (!$image) throw new InvalidArgumentException('icon_image_invalid');
    try {
        $factor = min(1.0, DIAL_ICON_MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $factor));
        $newHeight = max(1, (int) round($height * $factor));
        $output = imagecreatetruecolor($newWidth, $newHeight);
        if (!$output) throw new InvalidArgumentException('icon_image_invalid');
        try {
            if ($mime !== 'image/jpeg') {
                imagealphablending($output, false);
                imagesavealpha($output, true);
                imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
            }
            if (!imagecopyresampled($output, $image, 0, 0, 0, 0,
                $newWidth, $newHeight, $width, $height)) {
                throw new InvalidArgumentException('icon_image_invalid');
            }
            $bytes = dialIconEncodeRaster($output, $mime);
            return ['mime'=>$mime,'bytes'=>$bytes,'sha256'=>hash('sha256', $bytes),
                'width'=>$newWidth,'height'=>$newHeight];
        } finally {
            imagedestroy($output);
        }
    } finally {
        imagedestroy($image);
    }
}

/** $runner is injectable only for a controlled normalization test. */
function dialNormalizeIconUpload(array $file, ?callable $runner = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        !is_string($file['tmp_name'] ?? null) || !is_file($file['tmp_name'])) {
        throw new InvalidArgumentException('icon_image_invalid');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > DIAL_ICON_MAX_INPUT ||
        (isset($file['size']) && (int) $file['size'] !== $size)) {
        throw new InvalidArgumentException('icon_image_invalid');
    }
    $input = file_get_contents($file['tmp_name']);
    if ($input === false) throw new InvalidArgumentException('icon_image_invalid');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($input);
    if (in_array($mime, ['image/png','image/jpeg','image/webp'], true)) {
        return dialIconNormalizedRaster($input, $mime);
    }
    $declaredSvg = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) === 'svg';
    $looksSvg = preg_match('/^\s*(?:<\?xml[^?]*\?>\s*)?<svg\b/i', $input) === 1;
    if (!in_array($mime, ['image/svg+xml','text/xml','application/xml','text/plain'], true) ||
        (!$declaredSvg && !$looksSvg)) {
        throw new InvalidArgumentException('icon_image_invalid');
    }
    [$clean, $width, $height] = dialIconSanitizeSvg($input);
    $source = tempnam(sys_get_temp_dir(), 'dial-svg-in-');
    $output = tempnam(sys_get_temp_dir(), 'dial-svg-out-');
    if ($source === false || $output === false) {
        if ($source) @unlink($source);
        if ($output) @unlink($output);
        throw new InvalidArgumentException('svg_render_failed');
    }
    try {
        if (file_put_contents($source, $clean) === false) throw new RuntimeException('svg_temp_failed');
        if ($runner) {
            $runner($source, $output, $width, $height);
        } else {
            $command = [__DIR__ . '/../bin/dial-render-svg.sh', $source, $output,
                (string) $width, (string) $height];
            $process = proc_open($command, [['pipe','r'],['file','/dev/null','w'],['file','/dev/null','w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('svg_process_failed');
            fclose($pipes[0]);
            if (proc_close($process) !== 0) throw new RuntimeException('svg_process_failed');
        }
        $rendered = @file_get_contents($output);
        if (!is_string($rendered) || strlen($rendered) > 4194304) throw new RuntimeException('svg_output_failed');
        return dialIconNormalizedRaster($rendered, 'image/png');
    } catch (Throwable $error) {
        throw new InvalidArgumentException('svg_render_failed', 0, $error);
    } finally {
        @unlink($source);
        @unlink($output);
    }
}
