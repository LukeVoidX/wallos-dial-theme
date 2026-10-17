<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/logo_theme_variant.php';
require_once 'includes/dial_icon_resolver.php';
require_once 'includes/dial_categories.php';
require_once 'includes/dial_category_i18n.php';
require_once 'includes/dial_stored_text.php';
$categoryCopy = dial_category_copy($lang);
$dialIconCopies = [
  'en' => [
    'icon' => 'Icon', 'change' => 'Change icon', 'chooser' => 'Choose an icon',
    'original' => 'Original', 'catalog' => 'Built-in', 'library' => 'My icons', 'upload' => 'Upload',
    'originalHint' => 'Use the original subscription logo, if available.',
    'catalogHint' => 'Independent neutral symbols, not official service logos. Service names are for identification only.',
    'libraryHint' => 'Your uploaded icons are private to this account.',
    'emptyLibrary' => 'No icons uploaded yet.', 'preview' => 'Actual size preview',
    'warm' => 'Warm background', 'dark' => 'Dark background',
    'presentation' => 'Presentation', 'plain' => 'Plain', 'framed' => 'Framed',
    'treatment' => 'Treatment', 'color' => 'Color', 'mono' => 'Monochrome',
    'size' => 'Size', 'uploadFile' => 'Choose an image', 'uploadLabel' => 'Icon name',
    'uploadAction' => 'Add to my icons', 'delete' => 'Delete', 'save' => 'Use this icon',
    'cancel' => 'Cancel', 'demoNotice' => 'This demo is read-only. Install Wallos Dial to save icon changes.',
    'fileLimit' => 'PNG, JPEG, WebP or SVG · up to 2 MB. SVG is converted to a safe image on the server.',
    'loading' => 'Loading your icons…', 'loadError' => 'Could not load your icons.',
    'saveError' => 'Could not save the icon. Try again.',
    'uploadError' => 'Could not upload the icon.', 'uploadSuccess' => 'Icon added.',
    'usage' => 'My icons: {count}/{limitCount} · {usedMib}/{limitMib} MiB used',
    'quotaCount' => 'My icons is full ({count}/{limitCount} icons, {usedMib}/{limitMib} MiB). Delete an unused icon to make room.',
    'quotaBytes' => 'My icons storage is full ({count}/{limitCount} icons, {usedMib}/{limitMib} MiB). Delete an unused icon to make room.',
    'manageIcons' => 'Manage my icons',
    'lowResolution' => 'Low resolution; may look blurry.',
    'deleteSuccess' => 'Icon deleted.', 'deleteError' => 'This icon cannot be deleted while in use.',
    'deleteConfirm' => 'Delete this icon from your library?',
    'selectFile' => 'Choose an image first.', 'sourceMissing' => 'Choose an icon from this source.',
    'uploadBusy' => 'Uploading icon…', 'none' => 'No original logo',
  ],
  'zh_cn' => [
    'icon' => '图标', 'change' => '更换图标', 'chooser' => '选择图标',
    'original' => '原图', 'catalog' => '内置', 'library' => '我的图标', 'upload' => '上传',
    'originalHint' => '使用原有订阅 Logo（如有）。',
    'catalogHint' => '独立设计的中性符号，并非服务官方 Logo；服务名称仅用于识别。',
    'libraryHint' => '上传的图标仅当前账户可见。',
    'emptyLibrary' => '还没有上传图标。', 'preview' => '实际尺寸预览',
    'warm' => '暖色背景', 'dark' => '深色背景',
    'presentation' => '呈现方式', 'plain' => '无边框', 'framed' => '带边框',
    'treatment' => '色彩', 'color' => '原色', 'mono' => '单色',
    'size' => '尺寸', 'uploadFile' => '选择图片', 'uploadLabel' => '图标名称',
    'uploadAction' => '加入我的图标', 'delete' => '删除', 'save' => '使用此图标',
    'cancel' => '取消', 'demoNotice' => '公开演示仅供浏览。安装 Wallos Dial 后才可保存图标更改。',
    'fileLimit' => 'PNG、JPEG、WebP 或 SVG · 最大 2 MB。服务器会将 SVG 转换为安全图片。',
    'loading' => '正在加载图标…', 'loadError' => '无法加载你的图标。',
    'saveError' => '图标保存失败，请重试。',
    'uploadError' => '图标上传失败。', 'uploadSuccess' => '已添加图标。',
    'usage' => '我的图标：{count}/{limitCount} 张 · 已用 {usedMib}/{limitMib} MiB',
    'quotaCount' => '我的图标已满（{count}/{limitCount} 张，已用 {usedMib}/{limitMib} MiB）。请删除未使用的图标后重试。',
    'quotaBytes' => '图标存储空间已满（{count}/{limitCount} 张，已用 {usedMib}/{limitMib} MiB）。请删除未使用的图标后重试。',
    'manageIcons' => '管理我的图标',
    'lowResolution' => '分辨率较低，放大后可能模糊。',
    'deleteSuccess' => '已删除图标。', 'deleteError' => '图标正在使用，无法删除。',
    'deleteConfirm' => '要从图标库删除此图标吗？',
    'selectFile' => '请先选择图片。', 'sourceMissing' => '请从此来源选择图标。',
    'uploadBusy' => '正在上传图标…', 'none' => '没有原始 Logo',
  ],
  'zh_tw' => [
    'icon' => '圖標', 'change' => '更換圖標', 'chooser' => '選擇圖標',
    'original' => '原圖', 'catalog' => '內建', 'library' => '我的圖標', 'upload' => '上傳',
    'originalHint' => '使用原有訂閱 Logo（如有）。',
    'catalogHint' => '獨立設計的中性符號，並非服務官方 Logo；服務名稱僅用於識別。',
    'libraryHint' => '上傳的圖標僅目前帳戶可見。',
    'emptyLibrary' => '尚未上傳圖標。', 'preview' => '實際尺寸預覽',
    'warm' => '暖色背景', 'dark' => '深色背景',
    'presentation' => '呈現方式', 'plain' => '無邊框', 'framed' => '有邊框',
    'treatment' => '色彩', 'color' => '原色', 'mono' => '單色',
    'size' => '尺寸', 'uploadFile' => '選擇圖片', 'uploadLabel' => '圖標名稱',
    'uploadAction' => '加入我的圖標', 'delete' => '刪除', 'save' => '使用此圖標',
    'cancel' => '取消', 'demoNotice' => '公開展示僅供瀏覽。安裝 Wallos Dial 後才能儲存圖標變更。',
    'fileLimit' => 'PNG、JPEG、WebP 或 SVG · 最大 2 MB。伺服器會將 SVG 轉換為安全圖片。',
    'loading' => '正在載入圖標…', 'loadError' => '無法載入你的圖標。',
    'saveError' => '圖標儲存失敗，請重試。',
    'uploadError' => '圖標上傳失敗。', 'uploadSuccess' => '已新增圖標。',
    'usage' => '我的圖標：{count}/{limitCount} 張 · 已用 {usedMib}/{limitMib} MiB',
    'quotaCount' => '我的圖標已滿（{count}/{limitCount} 張，已用 {usedMib}/{limitMib} MiB）。請刪除未使用的圖標後重試。',
    'quotaBytes' => '圖標儲存空間已滿（{count}/{limitCount} 張，已用 {usedMib}/{limitMib} MiB）。請刪除未使用的圖標後重試。',
    'manageIcons' => '管理我的圖標',
    'lowResolution' => '解析度較低，放大後可能模糊。',
    'deleteSuccess' => '已刪除圖標。', 'deleteError' => '圖標正在使用，無法刪除。',
    'deleteConfirm' => '要從圖標庫刪除此圖標嗎？',
    'selectFile' => '請先選擇圖片。', 'sourceMissing' => '請從此來源選擇圖標。',
    'uploadBusy' => '正在上傳圖標…', 'none' => '沒有原始 Logo',
  ],
];
$dialIconCopy = $dialIconCopies[$lang] ?? $dialIconCopies['en'];
$dialIconHtml = static fn(string $key): string => htmlspecialchars($dialIconCopy[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

include_once 'includes/list_subscriptions.php';

$sort = "next_payment";
$sortOrder = $sort;

if ($settings['disabledToBottom'] === 'true') {
  $sql = "SELECT * FROM subscriptions WHERE user_id = :userId ORDER BY inactive ASC, next_payment ASC";
} else {
  $sql = "SELECT * FROM subscriptions WHERE user_id = :userId ORDER BY next_payment ASC, inactive ASC";
}

$params = array();

if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
  $sort = $_COOKIE['sortOrder'] ?? 'next_payment';
}

$sortOrder = $sort;
$allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id', 'inactive', 'alphanumeric', 'renewal_type'];
$order = ($sort == "price" || $sort == "id") ? "DESC" : "ASC";

if ($sort == "alphanumeric") {
  $sort = "name";
}

if (!in_array($sort, $allowedSortCriteria)) {
  $sort = "next_payment";
}

if ($sort == "renewal_type") {
  $sort = "auto_renew";
}

$sql = "SELECT * FROM subscriptions WHERE user_id = :userId";

if (isset($_GET['member'])) {
  $memberIds = explode(',', $_GET['member']);
  $placeholders = array_map(function ($key) {
    return ":member{$key}";
  }, array_keys($memberIds));

  $sql .= " AND payer_user_id IN (" . implode(',', $placeholders) . ")";

  foreach ($memberIds as $key => $memberId) {
    $params[":member{$key}"] = $memberId;
  }
}

if (isset($_GET['category'])) {
  $categoryIds = explode(',', $_GET['category']);
  $placeholders = array_map(function ($key) {
    return ":category{$key}";
  }, array_keys($categoryIds));

  $sql .= " AND category_id IN (" . implode(',', $placeholders) . ")";

  foreach ($categoryIds as $key => $categoryId) {
    $params[":category{$key}"] = $categoryId;
  }
}

if (isset($_GET['payment'])) {
  $paymentIds = explode(',', $_GET['payment']);
  $placeholders = array_map(function ($key) {
    return ":payment{$key}";
  }, array_keys($paymentIds));

  $sql .= " AND payment_method_id IN (" . implode(',', $placeholders) . ")";

  foreach ($paymentIds as $key => $paymentId) {
    $params[":payment{$key}"] = $paymentId;
  }
}

if (!isset($settings['hideDisabledSubscriptions']) || $settings['hideDisabledSubscriptions'] !== 'true') {
  if (isset($_GET['state']) && $_GET['state'] != "") {
    $sql .= " AND inactive = :inactive";
    $params[':inactive'] = $_GET['state'];
  }
}

$orderByClauses = [];

if ($settings['disabledToBottom'] === 'true') {
  if (in_array($sort, ["payer_user_id", "category_id", "payment_method_id"])) {
    $orderByClauses[] = "$sort $order";
    $orderByClauses[] = "inactive ASC";
  } else {
    $orderByClauses[] = "inactive ASC";
    $orderByClauses[] = "$sort $order";
  }
} else {
  $orderByClauses[] = "$sort $order";
  if ($sort != "inactive") {
    $orderByClauses[] = "inactive ASC";
  }
}

if ($sort != "next_payment") {
  $orderByClauses[] = "next_payment ASC";
}

$sql .= " ORDER BY " . implode(", ", $orderByClauses);

$stmt = $db->prepare($sql);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if (!empty($params)) {
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_INTEGER);
  }
}

$result = $stmt->execute();
if ($result) {
  $subscriptions = array();
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
  }
}

foreach ($subscriptions as $subscription) {
  $memberId = $subscription['payer_user_id'];
  $members[$memberId]['count']++;
  $categoryId = $subscription['category_id'];
  $categories[$categoryId]['count']++;
  $paymentMethodId = $subscription['payment_method_id'];
  $payment_methods[$paymentMethodId]['count']++;
}

if ($sortOrder == "category_id") {
  usort($subscriptions, function ($a, $b) use ($categories) {
    return $categories[$a['category_id']]['order'] - $categories[$b['category_id']]['order'];
  });
}

if ($sortOrder == "payment_method_id") {
  usort($subscriptions, function ($a, $b) use ($payment_methods) {
    return $payment_methods[$a['payment_method_id']]['order'] - $payment_methods[$b['payment_method_id']]['order'];
  });
}

$headerClass = count($subscriptions) > 0 ? "main-actions" : "main-actions hidden";

$subscriptionsView = (isset($_COOKIE['subscriptionsView']) && $_COOKIE['subscriptionsView'] === 'grid') ? 'grid' : 'list';
$dialGrouped = ($_COOKIE['subscriptionsGrouped'] ?? '1') !== '0';
?>
<style>
  .logo-preview:after {
    content: '<?= translate('upload_logo', $i18n) ?>';
  }
</style>

<section class="contain">
  <header class="<?= $headerClass ?>" id="main-actions">
    <button class="button" onClick="addSubscription()">
      <i class="fa-solid fa-circle-plus"></i>
      <?= translate('new_subscription', $i18n) ?>
    </button>
    <?php if (count($subscriptions) > 0): ?>
    <a href="categories.php" class="button secondary-button dial-category-manage-link"
       data-testid="dial-category-manage-link" aria-label="<?= htmlspecialchars($categoryCopy['manage'], ENT_QUOTES, 'UTF-8') ?>">
      <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
      <span><?= htmlspecialchars($categoryCopy['manage'], ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <?php endif; ?>
    <div class="top-actions">
      <button class="button secondary-button mobile-search-toggle" id="mobile-search-toggle"
        title="<?= translate('search', $i18n) ?>" onClick="toggleMobileSearch()">
        <i class="fa-solid fa-magnifying-glass"></i>
      </button>
      <div class="search">
        <input type="text" autocomplete="off" name="search" id="search" placeholder="<?= translate('search', $i18n) ?>"
          onkeyup="searchSubscriptions()" />
        <span class="fa-solid fa-magnifying-glass search-icon"></span>
        <span class="fa-solid fa-xmark clear-search" onClick="clearSearch()"></span>
      </div>

      <div class="filtermenu on-dashboard">
        <button class="button secondary-button" id="filtermenu-button" title="<?= translate("filter", $i18n) ?>">
          <i class="fa-solid fa-filter"></i>
        </button>
        <?php include 'includes/filters_menu.php'; ?>
      </div>

      <div class="sort-container">
        <button class="button secondary-button" value="Sort" onClick="toggleSortOptions()" id="sort-button"
          title="<?= translate('sort', $i18n) ?>">
          <i class="fa-solid fa-arrow-down-wide-short"></i>
        </button>
        <?php include 'includes/sort_options.php'; ?>
      </div>

      <button type="button" class="button secondary-button dial-group-toggle<?= $dialGrouped ? ' selected' : '' ?>"
        id="dial-group-toggle" title="<?= translate('category', $i18n) ?>" aria-pressed="<?= $dialGrouped ? 'true' : 'false' ?>"
        onClick="setSubscriptionsGrouping()">
        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
        <span><?= translate('category', $i18n) ?></span>
      </button>

      <div class="view-toggle" id="view-toggle">
        <button type="button" class="view-toggle-button<?= $subscriptionsView === 'list' ? ' selected' : '' ?>"
          id="view-list-button" title="<?= translate('list_view', $i18n) ?>" onClick="setSubscriptionsView('list')">
          <i class="fa-solid fa-list"></i>
        </button>
        <button type="button" class="view-toggle-button<?= $subscriptionsView === 'grid' ? ' selected' : '' ?>"
          id="view-grid-button" title="<?= translate('grid_view', $i18n) ?>" onClick="setSubscriptionsView('grid')">
          <i class="fa-solid fa-table-cells-large"></i>
        </button>
      </div>
    </div>
  </header>
  <div class="subscriptions<?= $subscriptionsView === 'grid' ? ' grid-view' : '' ?><?= $dialGrouped ? ' dial-grouped' : '' ?>" id="subscriptions">
    <?php
    $formatter = new IntlDateFormatter(
      'en', // Force English locale
      IntlDateFormatter::SHORT,
      IntlDateFormatter::NONE,
      null,
      null,
      'MMM d, yyyy'
    );

    $print = [];
    foreach ($subscriptions as $subscription) {
      if ($subscription['inactive'] == 1 && isset($settings['hideDisabledSubscriptions']) && $settings['hideDisabledSubscriptions'] === 'true') {
        continue;
      }
      $id = $subscription['id'];
      $print[$id]['id'] = $id;
      $print[$id]['dial_icon'] = dialResolveIcon($db, (int) $userId, $subscription);
      $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/" . $subscription['logo'] : "";
      $print[$id]['logo_text_color'] = $subscription['logo_text_color'] ?? null;
      $print[$id]['logo_variant'] = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
      $print[$id]['name'] = $subscription['name'];
      $cycle = $subscription['cycle'];
      $frequency = $subscription['frequency'];
      $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency, $i18n);
      $print[$id]['one_time'] = ($cycle == 5);
      $paymentMethodId = $subscription['payment_method_id'];
      $print[$id]['currency_code'] = $currencies[$subscription['currency_id']]['code'];
      $currencyId = $subscription['currency_id'];
      $print[$id]['auto_renew'] = $subscription['auto_renew'];
      $next_payment_timestamp = strtotime($subscription['next_payment']);
      $formatted_date = $formatter->format($next_payment_timestamp);
      $print[$id]['next_payment'] = $formatted_date;
      $paymentIconFolder = (strpos($payment_methods[$paymentMethodId]['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
      $print[$id]['payment_method_icon'] = $paymentIconFolder . $payment_methods[$paymentMethodId]['icon'];
      $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
      $print[$id]['payment_method_id'] = $paymentMethodId;
      $print[$id]['category_id'] = $subscription['category_id'];
      $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
      $print[$id]['price'] = floatval($subscription['price']);
      $print[$id]['progress'] = getSubscriptionProgress($cycle, $frequency, $subscription['next_payment']);
      $print[$id]['inactive'] = $subscription['inactive'];
      $print[$id]['url'] = $subscription['url'];
      $print[$id]['notes'] = $subscription['notes'];
      $print[$id]['replacement_subscription_id'] = $subscription['replacement_subscription_id'];

      if (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
        $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
        $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code'];
      }
      if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
        $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
      }
      if (isset($settings['showOriginalPrice']) && $settings['showOriginalPrice'] === 'true') {
        $print[$id]['original_price'] = floatval($subscription['price']);
        $print[$id]['original_currency_code'] = $currencies[$subscription['currency_id']]['code'];
      }
    }

    if ($sortOrder == "alphanumeric") {
      usort($print, function ($a, $b) {
        return strnatcmp(strtolower($a['name']), strtolower($b['name']));
      });
      if ($settings['disabledToBottom'] === 'true') {
        usort($print, function ($a, $b) {
          return $a['inactive'] - $b['inactive'];
        });
      }
    }

    if (isset($print)) {
      printSubscriptions($print, $sort, $categories, $members, $i18n, $colorTheme, "", $settings['disabledToBottom'], $settings['mobileNavigation'], $settings['showSubscriptionProgress'], $currencies, $lang, $dialGrouped);
    }

    $googleSearchEnabled = false;
    if ($db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='google_search'") > 0) {
      $googleSearchStmt = $db->prepare("SELECT COUNT(*) AS count FROM google_search WHERE user_id = :userId AND api_key != ''");
      $googleSearchStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
      $googleSearchResult = $googleSearchStmt->execute();
      if ($googleSearchResult) {
        $googleSearchRow = $googleSearchResult->fetchArray(SQLITE3_ASSOC);
        $googleSearchEnabled = $googleSearchRow && $googleSearchRow['count'] > 0;
      }
    }

    $db->close();

    if (count($subscriptions) == 0) {
      ?>
      <div class="empty-page dial-empty-page">
        <p class="dial-empty-kicker"><?= htmlspecialchars(translate('active_subscriptions', $i18n), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="dial-empty-readout" aria-hidden="true"><span>00</span><i></i></div>
        <h2><?= htmlspecialchars(translate('no_subscriptions_yet', $i18n), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="dial-empty-actions">
          <button class="button" onClick="addSubscription()">
            <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
            <?= htmlspecialchars(translate('add_first_subscription', $i18n), ENT_QUOTES, 'UTF-8') ?>
          </button>
          <a href="categories.php" class="button secondary-button dial-category-manage-link"
             data-testid="dial-category-manage-link" aria-label="<?= htmlspecialchars($categoryCopy['manage'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span><?= htmlspecialchars($categoryCopy['manage'], ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        </div>
      </div>
      <?php
    }
    ?>
  </div>
</section>
<section class="subscription-form" id="subscription-form">
  <header>
    <h3 id="form-title"><?= translate('add_subscription', $i18n) ?></h3>
    <span class="fa-solid fa-xmark close-form" onClick="closeAddSubscription()"></span>
  </header>
  <form action="endpoints/subscription/add.php" method="post" id="subs-form">

    <div class="form-group-inline">
      <input type="text" id="name" name="name" autocomplete="off"
        placeholder="<?= translate('subscription_name', $i18n) ?>"
        onchange="setSearchButtonStatus()" onkeypress="this.onchange();" onpaste="this.onchange();"
        oninput="this.onchange();" required>
      <label for="logo" class="logo-preview">
        <img src="" alt="<?= translate('logo_preview', $i18n) ?>" id="form-logo">
      </label>
      <input type="file" id="logo" name="logo" accept="image/jpeg, image/png, image/gif, image/webp, image/svg+xml"
        onchange="handleFileSelect(event)" class="hidden-input">
      <input type="hidden" id="logo-url" name="logo-url">
      <div id="logo-search-button" class="image-button medium disabled" title="<?= translate('search_logo', $i18n) ?>"
        onClick="searchLogo()">
        <i class="fa-solid fa-magnifying-glass"></i>
      </div>
      <input type="hidden" id="id" name="id">
      <div class="logo-search-backdrop" id="logo-search-backdrop" onClick="closeLogoSearch()"></div>
      <div id="logo-search-results" class="logo-search"<?= !empty($googleSearchEnabled) ? ' data-google-search="1"' : '' ?>>
        <button type="button" class="close-logo-search" onClick="closeLogoSearch()" title="<?= translate('cancel', $i18n) ?>">
          <i class="fa-solid fa-xmark"></i>
        </button>
        <header>
          <h3 id="logo-search-title" data-title="<?= translate('web_search', $i18n) ?>">
            <?= translate('web_search', $i18n) ?>
          </h3>
        </header>
        <div class="logo-search-controls">
          <div class="logo-search-query">
            <input type="text" id="logo-search-query" autocomplete="off" inputmode="search"
              placeholder="<?= translate('search_logo', $i18n) ?>"
              aria-label="<?= translate('search_logo', $i18n) ?>">
            <button type="button" id="logo-search-submit" title="<?= translate('search', $i18n) ?>">
              <i class="fa-solid fa-magnifying-glass"></i>
            </button>
          </div>
          <div id="logo-search-nav" class="logo-search-nav"></div>
        </div>
        <div id="logo-search-images"></div>
      </div>
    </div>

    <div class="form-group-inline">
      <input type="number" step="0.01" id="price" name="price" autocomplete="off"
        placeholder="<?= translate('price', $i18n) ?>" required>
      <select id="currency" name="currency_id" placeholder="<?= translate('add_subscription', $i18n) ?>">
        <?php
        foreach ($currencies as $currency) {
          $selected = ($currency['id'] == $main_currency) ? 'selected' : '';
          ?>
          <option value="<?= $currency['id'] ?>" <?= $selected ?>><?= $currency['name'] ?></option>
          <?php
        }
        ?>
      </select>
    </div>

    <div class="form-group">
      <div class="inline">
        <div class="split66">
          <label for="cycle"><?= translate('payment_every', $i18n) ?></label>
          <div class="inline">
            <select id="frequency" name="frequency" placeholder="<?= translate('frequency', $i18n) ?>">
              <?php
              for ($i = 1; $i <= 366; $i++) {
                ?>
                <option value="<?= $i ?>"><?= $i ?></option>
                <?php
              }
              ?>
            </select>
            <select id="cycle" name="cycle" placeholder="Cycle">
              <?php
              foreach ($cycles as $cycle) {
                ?>
                <option value="<?= $cycle['id'] ?>" <?= $cycle['id'] == 3 ? "selected" : "" ?>>
                  <?= translate(strtolower($cycle['name']), $i18n) ?>
                </option>
                <?php
              }
              ?>
            </select>
          </div>
        </div>
        <div class="split33" id="auto-renew-group">
          <label><?= translate('auto_renewal', $i18n) ?></label>
          <div class="inline height50">
            <input type="checkbox" id="auto_renew" name="auto_renew" checked>
            <label for="auto_renew"><?= translate('automatically_renews', $i18n) ?></label>
          </div>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="inline">
        <div class="split50">
          <label for="start_date"><?= translate('start_date', $i18n) ?></label>
          <div class="date-wrapper">
            <input type="date" id="start_date" name="start_date" autocomplete="off">
          </div>
        </div>
        <button type="button" id="autofill-next-payment-button"
          class="button secondary-button autofill-next-payment hideOnMobile"
          title="<?= translate('calculate_next_payment_date', $i18n) ?>" onClick="autoFillNextPaymentDate(event)">
          <i class="fa-solid fa-wand-magic-sparkles"></i>
        </button>
        <div class="split50">
          <label for="next_payment" class="split-label">
            <span id="next-payment-label-recurring"><?= translate('next_payment', $i18n) ?></span>
            <span id="next-payment-label-onetime" style="display:none"><?= translate('payment_date', $i18n) ?></span>
            <div id="autofill-next-payment-button" class="autofill-next-payment hideOnDesktop"
              title="<?= translate('calculate_next_payment_date', $i18n) ?>" onClick="autoFillNextPaymentDate(event)">
              <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
          </label>
          <div class="date-wrapper">
            <input type="date" id="next_payment" name="next_payment" autocomplete="off" required>
          </div>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="inline">
        <div class="split50">
          <label for="payment_method"><?= translate('payment_method', $i18n) ?></label>
          <select id="payment_method" name="payment_method_id">
            <?php
            foreach ($payment_methods as $payment) {
              ?>
              <option value="<?= $payment['id'] ?>">
                <?= $payment['name'] ?>
              </option>
              <?php
            }
            ?>
          </select>
        </div>
        <div class="split50">
          <label for="payer_user"><?= translate('paid_by', $i18n) ?></label>
          <select id="payer_user" name="payer_user_id">
            <?php
            foreach ($members as $member) {
              ?>
              <option value="<?= $member['id'] ?>"><?= $member['name'] ?></option>
              <?php
            }
            ?>
          </select>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label for="category"><?= translate('category', $i18n) ?></label>
      <select id="category" name="category_id">
        <?php
        foreach ($categories as $category) {
          ?>
          <option value="<?= $category['id'] ?>">
            <?= dial_category_html($category['name']) ?>
          </option>
          <?php
        }
        ?>
      </select>
    </div>

    <div class="form-group dial-icon-form-entry">
      <div>
        <span class="dial-icon-form-label"><?= $dialIconHtml('icon') ?></span>
        <span id="dial-icon-current-label" class="dial-icon-form-current"><?= $dialIconHtml('original') ?></span>
      </div>
      <button type="button" id="dial-icon-open-form" class="secondary-button"
        data-testid="dial-icon-open-form"><?= $dialIconHtml('change') ?></button>
    </div>

    <div class="form-group-inline grow" id="notifications-group">
      <input type="checkbox" id="notifications" name="notifications" onchange="toggleNotificationDays()">
      <label for="notifications" class="grow"><?= translate('enable_notifications', $i18n) ?></label>
    </div>

    <div class="form-group" id="notify-days-cancellation-group">
      <div class="inline">
        <div class="split66 mobile-split-50">
          <label for="notify_days_before"><?= translate('notify_me', $i18n) ?></label>
          <select id="notify_days_before" name="notify_days_before" disabled>
            <option value="-1"><?= translate('default_value_from_settings', $i18n) ?></option>
            <option value="0"><?= translate('on_due_date', $i18n) ?></option>
            <option value="1">1 <?= translate('day_before', $i18n) ?></option>
            <?php
            for ($i = 2; $i <= 180; $i++) {
              ?>
              <option value="<?= $i ?>"><?= $i ?>   <?= translate('days_before', $i18n) ?></option>
              <?php
            }
            ?>
          </select>
        </div>
        <div class="split33 mobile-split-50">
          <label for="cancellation_date"><?= translate('cancellation_notification', $i18n) ?></label>
          <div class="date-wrapper">
            <input type="date" id="cancellation_date" name="cancellation_date" autocomplete="off">
          </div>
        </div>
      </div>
    </div>

    <div class="form-group">
      <input type="text" id="url" name="url" autocomplete="off" placeholder="<?= translate('url', $i18n) ?>">
    </div>

    <div class="form-group">
      <div class="notes-editor">
        <div class="notes-toolbar">
          <button type="button" class="notes-toolbar-button" title="<?= translate('notes_bold', $i18n) ?>" onClick="applyNotesMarkdown('bold')">
            <i class="fa-solid fa-bold"></i>
          </button>
          <button type="button" class="notes-toolbar-button" title="<?= translate('notes_italic', $i18n) ?>" onClick="applyNotesMarkdown('italic')">
            <i class="fa-solid fa-italic"></i>
          </button>
          <button type="button" class="notes-toolbar-button" title="<?= translate('notes_list', $i18n) ?>" onClick="applyNotesMarkdown('list')">
            <i class="fa-solid fa-list-ul"></i>
          </button>
          <button type="button" class="notes-toolbar-button" title="<?= translate('notes_link', $i18n) ?>" onClick="applyNotesMarkdown('link')">
            <i class="fa-solid fa-link"></i>
          </button>
        </div>
        <textarea id="notes" name="notes" class="thin" autocomplete="off" placeholder="<?= translate('notes', $i18n) ?>"></textarea>
      </div>
    </div>

    <div class="form-group">
      <div class="inline grow">
        <input type="checkbox" id="inactive" name="inactive" onchange="toggleReplacementSub()">
        <label for="inactive" class="grow"><?= translate('inactive', $i18n) ?></label>
      </div>
    </div>

    <?php
    $orderedSubscriptions = $subscriptions;
    usort($orderedSubscriptions, function ($a, $b) {
      return strnatcmp(strtolower($a['name']), strtolower($b['name']));
    });
    ?>

    <div class="form-group hide" id="replacement_subscritpion">
      <label for="replacement_subscription_id"><?= translate('replaced_with', $i18n) ?>:</label>
      <select id="replacement_subscription_id" name="replacement_subscription_id">
        <option value="0"><?= translate('none', $i18n) ?></option>
        <?php
        foreach ($orderedSubscriptions as $sub) {
          if ($sub['inactive'] == 0) {
            ?>
            <option value="<?= htmlspecialchars($sub['id']) ?>"><?= dialStoredHtml($sub['name']) ?>
            </option>
            <?php
          }
        }
        ?>
      </select>
    </div>

  </form>
  <div class="buttons dial-form-actions">
    <input type="button" value="<?= translate('delete', $i18n) ?>" class="warning-button left thin" id="deletesub" style="display: none">
    <input type="button" value="<?= translate('cancel', $i18n) ?>" class="secondary-button thin" onClick="closeAddSubscription()">
    <input type="submit" value="<?= translate('save', $i18n) ?>" class="thin" id="save-button" form="subs-form">
  </div>
</section>
<div class="dial-form-backdrop" aria-hidden="true" onClick="closeAddSubscription()"></div>

<dialog id="dial-icon-chooser" class="dial-icon-chooser" data-testid="dial-icon-chooser"
  data-demo-mode="<?= $demoMode === '1' ? '1' : '0' ?>" aria-labelledby="dial-icon-chooser-title">
  <div class="dial-icon-dialog-head">
    <div>
      <p class="dial-icon-eyebrow">WALLOS DIAL / ICONS</p>
      <h2 id="dial-icon-chooser-title"><?= $dialIconHtml('chooser') ?></h2>
    </div>
    <button type="button" id="dial-icon-close" class="dial-icon-close"
      aria-label="<?= $dialIconHtml('cancel') ?>" title="<?= $dialIconHtml('cancel') ?>">×</button>
  </div>
  <nav class="dial-icon-source-tabs" aria-label="<?= $dialIconHtml('chooser') ?>">
    <?php foreach (['original', 'catalog', 'library', 'upload'] as $source): ?>
      <button type="button" data-dial-source="<?= $source ?>"
        data-testid="dial-icon-source-<?= $source ?>" aria-pressed="<?= $source === 'original' ? 'true' : 'false' ?>">
        <?= $dialIconHtml($source) ?>
      </button>
    <?php endforeach; ?>
  </nav>
  <div class="dial-icon-source-body">
    <section id="dial-icon-panel-original" class="dial-icon-panel">
      <p><?= $dialIconHtml('originalHint') ?></p>
      <div id="dial-icon-original-choice" class="dial-icon-original-choice"></div>
    </section>
    <section id="dial-icon-panel-catalog" class="dial-icon-panel" hidden>
      <p><?= $dialIconHtml('catalogHint') ?></p>
      <div class="dial-icon-options" id="dial-icon-catalog-options">
        <?php foreach (dialIconCatalog() as $key => $item): ?>
          <button type="button" class="dial-icon-option" data-catalog-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
            data-testid="dial-icon-catalog-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
            data-optical-scale="<?= htmlspecialchars((string) $item['optical_scale'], ENT_QUOTES, 'UTF-8') ?>"
            aria-pressed="false">
            <span class="dial-icon-option-art" aria-hidden="true"><img src="images/dial-catalog/<?= rawurlencode($item['file']) ?>" alt=""></span>
            <span><?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
    <section id="dial-icon-panel-library" class="dial-icon-panel" hidden>
      <p><?= $dialIconHtml('libraryHint') ?></p>
      <p id="dial-icon-library-usage" class="dial-icon-usage" aria-live="polite"></p>
      <div id="dial-icon-library-options" class="dial-icon-options" aria-live="polite"></div>
    </section>
    <section id="dial-icon-panel-upload" class="dial-icon-panel" hidden>
      <p><?= $dialIconHtml('fileLimit') ?></p>
      <p id="dial-icon-upload-usage" class="dial-icon-usage" aria-live="polite"></p>
      <div class="dial-icon-upload-fields">
        <label for="dial-icon-upload-file"><?= $dialIconHtml('uploadFile') ?></label>
        <input id="dial-icon-upload-file" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg"
          data-testid="dial-icon-upload">
        <label for="dial-icon-upload-label"><?= $dialIconHtml('uploadLabel') ?></label>
        <input id="dial-icon-upload-label" type="text" maxlength="80" autocomplete="off">
        <button type="button" id="dial-icon-upload-action" class="dial-icon-action-button"><?= $dialIconHtml('uploadAction') ?></button>
      </div>
    </section>
  </div>
  <div class="dial-icon-controls">
    <fieldset>
      <legend><?= $dialIconHtml('presentation') ?></legend>
      <div class="dial-icon-segmented">
        <?php foreach (['plain', 'framed'] as $presentation): ?>
          <button type="button" data-dial-presentation="<?= $presentation ?>"
            data-testid="dial-icon-presentation-<?= $presentation ?>"
            aria-pressed="<?= $presentation === 'plain' ? 'true' : 'false' ?>"><?= $dialIconHtml($presentation) ?></button>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= $dialIconHtml('treatment') ?></legend>
      <div class="dial-icon-segmented">
        <?php foreach (['color', 'mono'] as $treatment): ?>
          <button type="button" data-dial-treatment="<?= $treatment ?>"
            data-testid="dial-icon-treatment-<?= $treatment ?>"
            aria-pressed="<?= $treatment === 'color' ? 'true' : 'false' ?>"><?= $dialIconHtml($treatment) ?></button>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label class="dial-icon-scale-control" for="dial-icon-scale">
      <span><?= $dialIconHtml('size') ?> <output id="dial-icon-scale-value" for="dial-icon-scale">100%</output></span>
      <input id="dial-icon-scale" type="range" min="80" max="120" step="5" value="100"
        data-testid="dial-icon-scale">
      <span class="dial-icon-scale-ends"><span>80%</span><span>120%</span></span>
    </label>
  </div>
  <div class="dial-icon-preview-section" data-testid="dial-icon-preview">
    <p><?= $dialIconHtml('preview') ?></p>
    <div class="dial-icon-preview-pair">
      <div class="dial-icon-preview-surface is-warm" data-preview-tone="warm">
        <span class="dial-icon-preview-art" aria-hidden="true"></span>
        <span><?= $dialIconHtml('warm') ?></span>
      </div>
      <div class="dial-icon-preview-surface is-dark" data-preview-tone="dark">
        <span class="dial-icon-preview-art" aria-hidden="true"></span>
        <span><?= $dialIconHtml('dark') ?></span>
      </div>
    </div>
  </div>
  <p id="dial-icon-status" class="dial-icon-status" role="status" aria-live="polite"></p>
  <?php if ($demoMode === '1'): ?>
    <p class="dial-icon-demo-note"><?= $dialIconHtml('demoNotice') ?></p>
  <?php endif; ?>
  <div class="dial-icon-dialog-actions">
    <button type="button" id="dial-icon-cancel" class="secondary-button"><?= $dialIconHtml('cancel') ?></button>
    <button type="button" id="dial-icon-save" class="dial-icon-action-button" data-testid="dial-icon-save"
      <?= $demoMode === '1' ? 'disabled' : '' ?>><?= $dialIconHtml('save') ?></button>
  </div>
</dialog>

<?php require_once 'includes/subscription_details_popup.php'; ?>
<script>window.DialIconCopy = <?= json_encode($dialIconCopy, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="scripts/dial-icon-chooser.js?dial=<?= rawurlencode(DIAL_VERSION) ?>"></script>
<script src="scripts/subscriptions.js?dial=<?= rawurlencode(DIAL_VERSION) ?>"></script>
<?php if (isset($_GET['focus']) && $_GET['focus'] === 'search'): ?>
<script>document.getElementById('search')?.focus();</script>
<?php endif; ?>
<?php
if (isset($_GET['add'])) {
  ?>
  <script>
    addSubscription();
  </script>
  <?php
}

require_once 'includes/footer.php';
?>
