<?php
declare(strict_types=1);
require_once __DIR__ . '/dial_icon_repository.php';
require_once __DIR__ . '/dial_logo_map.php';

function dialOriginalIconDescriptor(array $subscription): array
{
    $logo = basename((string) ($subscription['logo'] ?? ''));
    $variant = basename((string) ($subscription['logo_variant'] ?? ''));
    $kind = $logo === '' ? 'fallback' : 'original';
    $name = (string) ($subscription['name'] ?? '');
    preg_match('/^./us', $name, $first);
    return [
        'kind'=>$kind,
        'src'=>$logo === '' ? '' : 'images/uploads/logos/' . rawurlencode($logo),
        'variant_src'=>$logo !== '' && $variant !== '' ? 'images/uploads/logos/' . rawurlencode($variant) : null,
        'native_text_color'=>in_array($subscription['logo_text_color'] ?? null, ['dark', 'light'], true)
            ? $subscription['logo_text_color'] : null,
        'presentation'=>'plain', 'treatment'=>'color', 'scale'=>100, 'optical_scale'=>1.0,
        'fallback_text'=>$first[0] ?? '?',
    ];
}

function dialResolveIcon(SQLite3 $db, int $userId, array $subscription): array
{
    $base = dialOriginalIconDescriptor($subscription);
    $subscriptionId = filter_var($subscription['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$subscriptionId || !dialIconOne($db,
        'SELECT id FROM subscriptions WHERE id=:subscription AND user_id=:user',
        [':subscription'=>$subscriptionId, ':user'=>$userId])) {
        return [
            'kind'=>'fallback','src'=>'','variant_src'=>null,'native_text_color'=>null,
            'presentation'=>'plain','treatment'=>'color','scale'=>100,'optical_scale'=>1.0,
            'fallback_text'=>$base['fallback_text'],
        ];
    }
    $preference = dialIconGetPreference($db, $userId, $subscriptionId);
    if ($preference !== null) {
        if (!in_array($preference['presentation'], ['plain','framed'], true) ||
            !in_array($preference['treatment'], ['color','mono'], true) ||
            $preference['scale'] < 80 || $preference['scale'] > 120) return $base;
        if ($preference['source'] === 'original') {
            return array_replace($base, [
                'presentation'=>$preference['presentation'],
                'treatment'=>$preference['treatment'], 'scale'=>$preference['scale'],
            ]);
        }
        if ($preference['source'] === 'catalog') {
            $item = dialIconCatalogItem((string) ($preference['catalog_key'] ?? ''));
            if (!$item) return $base;
            return array_replace($base, [
                'kind'=>'catalog','src'=>'images/dial-catalog/' . rawurlencode($item['file']),
                'variant_src'=>null,'native_text_color'=>null,
                'presentation'=>$preference['presentation'], 'treatment'=>$preference['treatment'],
                'scale'=>$preference['scale'], 'optical_scale'=>(float) $item['optical_scale'],
            ]);
        }
        if ($preference['source'] === 'asset') {
            $assetId = (int) ($preference['asset_id'] ?? 0);
            // Rendering a list only needs existence. The image endpoint alone
            // reads the BLOB, avoiding a 512 KiB allocation for every card.
            if ($assetId <= 0 || !dialIconOne($db,
                'SELECT id FROM dial_icon_assets WHERE id=:asset AND user_id=:user',
                [':asset'=>$assetId, ':user'=>$userId])) return $base;
            return array_replace($base, [
                'kind'=>'asset','src'=>'/endpoints/dial/icon.php?id=' . $assetId,
                'variant_src'=>null,'native_text_color'=>null,
                'presentation'=>$preference['presentation'], 'treatment'=>$preference['treatment'],
                'scale'=>$preference['scale'],
            ]);
        }
        return $base;
    }
    $legacy = dialRedrawnLogoFor($subscription['logo'] ?? '');
    if ($legacy !== null) {
        return array_replace($base, [
            'kind'=>'legacy','src'=>'images/dial-logos/' . rawurlencode($legacy),
            'variant_src'=>null,'native_text_color'=>null,
        ]);
    }
    return $base;
}

function dialRenderIcon(array $icon, string $className): string
{
    if (!in_array($className, ['dial-service-icon', 'dial-card-icon'], true)) {
        throw new InvalidArgumentException('icon_class_invalid');
    }
    $kind = in_array($icon['kind'] ?? null, ['original','catalog','asset','legacy','fallback'], true)
        ? $icon['kind'] : 'fallback';
    $presentation = in_array($icon['presentation'] ?? null, ['plain','framed'], true)
        ? $icon['presentation'] : 'plain';
    $treatment = in_array($icon['treatment'] ?? null, ['color','mono'], true)
        ? $icon['treatment'] : 'color';
    $scale = filter_var($icon['scale'] ?? null, FILTER_VALIDATE_INT);
    if ($scale === false || $scale < 80 || $scale > 120) $scale = 100;
    $optical = is_numeric($icon['optical_scale'] ?? null) ? (float) $icon['optical_scale'] : 1.0;
    $optical = max(.8, min(1.2, $optical));
    $cssScale = number_format(($scale / 100) * $optical, 3, '.', '');
    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<span class="' . $className . '" aria-hidden="true"'
        . ' data-dial-icon-kind="' . $kind . '"'
        . ' data-dial-icon-presentation="' . $presentation . '"'
        . ' data-dial-icon-treatment="' . $treatment . '"'
        . ' data-dial-icon-scale="' . $scale . '"'
        . ' style="--dial-optical-scale:' . $cssScale . '">';
    $source = (string) ($icon['src'] ?? '');
    if ($kind === 'fallback' || $source === '') {
        $html .= '<span class="dial-icon-fallback">' . $escape($icon['fallback_text'] ?? '?') . '</span>';
    } else {
        $variant = (string) ($icon['variant_src'] ?? '');
        $native = $icon['native_text_color'] ?? null;
        if ($kind === 'original' && $variant !== '' && in_array($native, ['dark','light'], true)) {
            $nativeTheme = $native === 'dark' ? 'light' : 'dark';
            $html .= '<img class="dial-icon-image logo-theme-original" src="' . $escape($source)
                . '" data-native-theme="' . $nativeTheme . '" alt="" loading="lazy">';
            $html .= '<img class="dial-icon-image logo-theme-variant" src="' . $escape($variant)
                . '" data-native-theme="' . $nativeTheme . '" alt="" loading="lazy">';
        } else {
            $html .= '<img class="dial-icon-image" src="' . $escape($source) . '" alt="" loading="lazy">';
        }
    }
    return $html . '</span>';
}
