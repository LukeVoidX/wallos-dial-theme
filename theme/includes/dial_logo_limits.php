<?php
declare(strict_types=1);

const DIAL_LOGO_MAX_BYTES = 4 * 1024 * 1024;
const DIAL_LOGO_MAX_SIDE = 4096;
const DIAL_LOGO_MAX_PIXELS = 4_000_000;

/** cURL write callback: returning zero stops a transfer before it grows. */
function dialLogoCollectChunk(string &$buffer, string $chunk, int $limit = DIAL_LOGO_MAX_BYTES): int
{
    $length = strlen($chunk);
    if ($length === 0 || $limit < 1 || strlen($buffer) > $limit - $length) return 0;
    $buffer .= $chunk;
    return $length;
}

function dialLogoDimensionsWithinLimits(array $info): bool
{
    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    return $width > 0 && $height > 0 && $width <= DIAL_LOGO_MAX_SIDE &&
        $height <= DIAL_LOGO_MAX_SIDE && $width <= intdiv(DIAL_LOGO_MAX_PIXELS, $height);
}

/** Inspect the encoded header before GD allocates a decoded image. */
function dialLogoImageWithinLimits(string $bytes, int $limit = DIAL_LOGO_MAX_BYTES): bool
{
    if ($limit < 1 || $bytes === '' || strlen($bytes) > $limit) return false;
    $info = @getimagesizefromstring($bytes);
    return is_array($info) && dialLogoDimensionsWithinLimits($info);
}

function dialLogoFileWithinLimits(string $path): bool
{
    if (!is_file($path) || is_link($path)) return false;
    $size = @filesize($path);
    if (!is_int($size) || $size <= 0 || $size > DIAL_LOGO_MAX_BYTES) return false;
    $info = @getimagesize($path);
    return is_array($info) && dialLogoDimensionsWithinLimits($info);
}
