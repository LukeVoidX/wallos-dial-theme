<?php
declare(strict_types=1);

/** Only reviewed, bundled files may become a public icon URL. */
function dialIconCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) return $catalog;
    $catalog = [];
    $directory = realpath(__DIR__ . '/../images/dial-catalog');
    if ($directory === false) return $catalog;
    $manifest = json_decode((string) @file_get_contents($directory . '/catalog.json'), true);
    if (!is_array($manifest)) return $catalog;
    $allowed = ['apple-one', 'chatgpt', 'claude', 'jianying', 'netcup', 'nmcloud', 'proxy-seller', 'racknerd', 'vmiss'];
    foreach ($allowed as $key) {
        $item = $manifest[$key] ?? null;
        if (!is_array($item) || ($item['file'] ?? null) !== $key . '.svg') continue;
        $path = realpath($directory . '/' . $item['file']);
        if ($path === false || dirname($path) !== $directory || !is_file($path) || is_link($directory . '/' . $item['file'])) continue;
        $scale = $item['optical_scale'] ?? null;
        if (!is_numeric($scale) || (float) $scale < .8 || (float) $scale > 1.2) continue;
        $valid = true;
        foreach (['label', 'source', 'source_url', 'license_note', 'trademark_note'] as $field) {
            if (!isset($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '') $valid = false;
        }
        if (!$valid) continue;
        $catalog[$key] = $item;
    }
    return $catalog;
}

function dialIconCatalogItem(string $key): ?array
{
    if (!preg_match('/\A(?:apple-one|chatgpt|claude|jianying|netcup|nmcloud|proxy-seller|racknerd|vmiss)\z/D', $key)) return null;
    return dialIconCatalog()[$key] ?? null;
}
