<?php
declare(strict_types=1);
require __DIR__ . '/../theme/includes/dial_icon_catalog.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$want = ['apple-one', 'chatgpt', 'claude', 'jianying', 'netcup', 'nmcloud', 'proxy-seller', 'racknerd', 'vmiss'];
sort($want);
$got = array_keys(dialIconCatalog());
sort($got);
check($got === $want, 'catalog must contain exactly nine reviewed keys');
check(dialIconCatalogItem('../escape') === null, 'path-like key rejected');
check(dialIconCatalogItem('made-up') === null, 'unknown key rejected');

$catalogDir = __DIR__ . '/../theme/images/dial-catalog';
$files = glob($catalogDir . '/*.svg') ?: [];
check(count($files) === 9, 'catalog directory must hold exactly nine SVGs');
foreach ($want as $key) {
    $item = dialIconCatalogItem($key);
    check($item !== null, "missing $key");
    check($item['file'] === $key . '.svg', "unexpected filename for $key");
    check($item['optical_scale'] >= .95 && $item['optical_scale'] <= 1.1, "optical scale for $key");
    foreach (['label', 'source', 'source_url', 'license_note', 'trademark_note'] as $field) {
        check(isset($item[$field]) && is_string($item[$field]) && trim($item[$field]) !== '', "$key lacks $field");
    }
    check($item['source'] === 'Wallos Dial original artwork', "$key is not first-party artwork");
    check($item['rights_status'] === 'first_party_original', "$key needs a first-party rights record");
    check(str_contains($item['license_note'], 'GPLv3'), "$key must state its actual license");
    check(str_contains($item['source_url'], '/theme/images/dial-catalog/' . $key . '.svg'), "$key provenance points to another file");
    $svg = file_get_contents($catalogDir . '/' . $item['file']);
    check(is_string($svg) && preg_match('/<svg\b[^>]*\bviewBox="0 0 24 24"/i', $svg) === 1, "$key has no common 24px SVG viewBox");
    check(strlen($svg) < 4096, "$key SVG is unexpectedly complex");
    check(str_contains($svg, 'stroke="#191817"') && str_contains($svg, 'stroke-width="1.7"'), "$key is outside the common icon system");
    $document = new DOMDocument();
    check(@$document->loadXML($svg, LIBXML_NONET), "$key SVG is not well-formed XML");
    check($document->doctype === null, "$key SVG includes a DTD");
    check($document->documentElement?->namespaceURI === 'http://www.w3.org/2000/svg', "$key is not an SVG document");
    check(preg_match('/<script\b|<foreignObject\b|\bon[a-z]+\s*=|\b(?:href|xlink:href)\s*=/i', $svg) !== 1,
        "$key contains active or external SVG content");
}
foreach (['dolphtek', 'erenship', 'jarvishub', 'lukevoidx', 'mostai', 'mostrahub', 'withluke'] as $private) {
    check(!is_file($catalogDir . '/' . $private . '.svg'), "private $private SVG packaged");
}
echo "Public icon catalog structure checks passed.\n";
