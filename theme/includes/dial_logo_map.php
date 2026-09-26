<?php
/** Optional, user-owned logo mappings. Unmapped uploads retain their original artwork. */
function dialRedrawnLogoFor($logo)
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $path = getenv('WALLOS_DIAL_LOGO_MAP') ?: __DIR__ . '/dial_logo_map.local.php';
        if (is_file($path)) {
            $candidate = require $path;
            if (is_array($candidate)) {
                foreach ($candidate as $upload => $asset) {
                    if (!is_string($upload) || !is_string($asset)) continue;
                    if (basename($upload) !== $upload || basename($asset) !== $asset) continue;
                    if (!preg_match('/^[a-z0-9][a-z0-9._-]*\.svg$/i', $asset)) continue;
                    if (!is_file(__DIR__ . '/../images/dial-logos/' . $asset)) continue;
                    $map[$upload] = $asset;
                }
            }
        }
    }
    return $map[basename((string) $logo)] ?? null;
}
