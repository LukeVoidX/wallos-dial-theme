<?php
require_once 'includes/header.php';
require_once 'includes/dial_categories.php';
require_once 'includes/dial_category_i18n.php';
$copy = dial_category_copy($lang);
$rows = dial_category_rows($db, (int) $userId);
$demoReadOnly = getenv('DEMO_MODE') === '1';
$totalSubscriptions = array_sum(array_column($rows, 'subscription_count'));
$starterNames = ['AI', 'Infra', 'Domains', 'Media'];
$existingNames = array_map(static fn(array $row): string => dial_category_name_for_input($row['name']), $rows);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<header class="dial-category-page-header">
  <div>
    <p class="dial-category-eyebrow"><?= $escape($copy['eyebrow']) ?></p>
    <h1><?= $escape($copy['title']) ?></h1>
    <p class="dial-category-intro"><?= $escape($copy['intro']) ?></p>
  </div>
  <a href="subscriptions.php" class="dial-category-back">← <?= $escape(translate('subscriptions', $i18n)) ?></a>
</header>

<?php if ($demoReadOnly): ?>
  <p class="dial-category-readonly" data-testid="dial-category-readonly">
    <?= $escape($copy['readonly']) ?>
    <a href="https://github.com/LukeVoidX/wallos-dial-theme#install-a-new-instance" target="_blank" rel="noopener noreferrer"><?= $escape($copy['install']) ?> ↗</a>
  </p>
<?php else: ?>
  <form id="dial-category-add-form" class="dial-category-add-form">
    <label for="dial-category-new-name"><?= $escape($copy['name']) ?></label>
    <div class="dial-category-add-fields">
      <input id="dial-category-new-name" data-testid="dial-category-new-name" type="text" maxlength="60" autocomplete="off" placeholder="<?= $escape($copy['add_placeholder']) ?>">
      <button type="submit" data-testid="dial-category-add" class="dial-category-primary">+ <?= $escape($copy['add']) ?></button>
    </div>
  </form>
<?php endif; ?>

<p id="dial-category-status" data-testid="dial-category-status" role="status" aria-live="polite"></p>

<div class="dial-category-list-header" aria-hidden="true">
  <span><?= $escape($copy['name']) ?></span>
  <span><?= $escape($copy['count']) ?></span>
  <?php if (!$demoReadOnly): ?><span></span><?php endif; ?>
</div>
<ol id="dial-category-list" class="dial-category-list">
<?php foreach ($rows as $index => $row):
    $id = (int) $row['id'];
    $count = (int) $row['subscription_count'];
    $fixed = $id === 1;
    $inputId = 'dial-category-name-' . $id;
?>
  <li class="dial-category-row<?= $fixed ? ' is-fixed' : '' ?><?= $fixed || $demoReadOnly ? ' is-readonly' : '' ?>"
      data-testid="dial-category-row" data-category-id="<?= $id ?>"
      data-category-count="<?= $count ?>" data-category-name="<?= $escape($row['name']) ?>">
    <span class="dial-category-row-index" aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></span>
    <?php if (!$demoReadOnly && !$fixed): ?>
      <span class="dial-category-drag" title="<?= $escape($copy['drag']) ?>" aria-hidden="true">⋮⋮</span>
    <?php endif; ?>
    <div class="dial-category-row-name">
      <span class="dial-category-label"<?= !$demoReadOnly && !$fixed ? ' aria-hidden="true"' : '' ?>><?= dial_category_html($row['name']) ?></span>
      <?php if (!$demoReadOnly && !$fixed): ?>
        <label class="dial-category-input-label" for="<?= $inputId ?>"><?= $escape($copy['name']) ?>: <?= dial_category_html($row['name']) ?></label>
        <input id="<?= $inputId ?>" data-testid="dial-category-name" type="text" maxlength="60" value="<?= dial_category_html($row['name']) ?>" autocomplete="off">
      <?php elseif ($fixed): ?>
        <small><?= $escape($copy['fixed']) ?></small>
      <?php endif; ?>
    </div>
    <span class="dial-category-row-count" aria-label="<?= $count . ' ' . $escape($copy['count']) ?>"><?= $count ?></span>
    <?php if (!$demoReadOnly && !$fixed): ?>
      <div class="dial-category-actions">
        <button type="button" data-testid="dial-category-move-up" aria-label="<?= $escape($copy['move_up']) ?>: <?= dial_category_html($row['name']) ?>" title="<?= $escape($copy['move_up']) ?>">↑</button>
        <button type="button" data-testid="dial-category-move-down" aria-label="<?= $escape($copy['move_down']) ?>: <?= dial_category_html($row['name']) ?>" title="<?= $escape($copy['move_down']) ?>">↓</button>
        <button type="button" data-testid="dial-category-save" aria-label="<?= $escape($copy['save']) ?>: <?= dial_category_html($row['name']) ?>"><?= $escape($copy['save']) ?></button>
        <button type="button" data-testid="dial-category-merge" aria-label="<?= $escape($copy['merge']) ?>: <?= dial_category_html($row['name']) ?>"><?= $escape($copy['merge']) ?></button>
        <button type="button" data-testid="dial-category-delete" aria-label="<?= $escape($copy['delete']) ?>: <?= dial_category_html($row['name']) ?>"<?= $count > 0 ? ' disabled title="' . $escape($copy['category_in_use']) . '"' : '' ?>><?= $escape($copy['delete']) ?></button>
      </div>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ol>

<?php if (!$demoReadOnly): ?>
<section class="dial-category-starter-section">
  <div>
    <p class="dial-category-eyebrow">AI · INFRA · DOMAINS · MEDIA</p>
    <h2><?= $escape($copy['starter']) ?></h2>
    <p><?= $escape($totalSubscriptions === 0 ? $copy['starter_help'] : $copy['starter_unavailable']) ?></p>
  </div>
  <button type="button" data-testid="dial-category-starter"<?= $totalSubscriptions > 0 ? ' disabled' : '' ?>><?= $escape($copy['starter']) ?> ↗</button>
</section>

<dialog class="dial-category-dialog" data-testid="dial-category-merge-dialog" aria-labelledby="dial-category-merge-title">
  <h2 id="dial-category-merge-title"><?= $escape($copy['merge']) ?></h2>
  <p id="dial-category-merge-help"><?= $escape($copy['merge_help']) ?></p>
  <label for="dial-category-merge-target"><?= $escape($copy['merge_target']) ?></label>
  <select id="dial-category-merge-target" data-testid="dial-category-merge-target"></select>
  <div class="dial-category-dialog-actions">
    <button type="button" data-dialog-cancel><?= $escape($copy['cancel']) ?></button>
    <button type="button" data-testid="dial-category-merge-confirm" class="dial-category-primary"><?= $escape($copy['merge_confirm']) ?></button>
  </div>
</dialog>

<dialog class="dial-category-dialog" data-testid="dial-category-delete-dialog" aria-labelledby="dial-category-delete-title">
  <h2 id="dial-category-delete-title"><?= $escape($copy['delete_confirm']) ?></h2>
  <p><?= $escape($copy['delete_help']) ?></p>
  <p id="dial-category-delete-name" class="dial-category-dialog-name"></p>
  <div class="dial-category-dialog-actions">
    <button type="button" data-dialog-cancel><?= $escape($copy['cancel']) ?></button>
    <button type="button" data-testid="dial-category-delete-confirm" class="dial-category-primary"><?= $escape($copy['delete_confirm']) ?></button>
  </div>
</dialog>

<dialog class="dial-category-dialog" data-testid="dial-category-starter-dialog" aria-labelledby="dial-category-starter-title">
  <h2 id="dial-category-starter-title"><?= $escape($copy['starter']) ?></h2>
  <p><?= $escape($copy['starter_help']) ?></p>
  <h3><?= $escape($copy['starter_missing']) ?></h3>
  <ul id="dial-category-starter-missing">
    <?php foreach ($starterNames as $starterName): ?>
      <?php if (!in_array($starterName, $existingNames, true)): ?><li><?= $escape($starterName) ?></li><?php endif; ?>
    <?php endforeach; ?>
  </ul>
  <h3><?= $escape($copy['starter_remove']) ?></h3>
  <div class="dial-category-starter-choices">
    <?php foreach ($rows as $row): ?>
      <?php if ((int) $row['id'] === 1 || (int) $row['subscription_count'] > 0) continue; ?>
      <label>
        <input type="checkbox" data-testid="dial-category-starter-delete"
               data-category-id="<?= (int) $row['id'] ?>"
               data-stored-name="<?= $escape($row['name']) ?>">
        <?= dial_category_html($row['name']) ?>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="dial-category-dialog-actions">
    <button type="button" data-dialog-cancel><?= $escape($copy['cancel']) ?></button>
    <button type="button" data-testid="dial-category-starter-confirm" class="dial-category-primary"><?= $escape($copy['starter_confirm']) ?></button>
  </div>
</dialog>
<script id="dial-category-copy" type="application/json"><?= json_encode($copy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?></script>
<script src="scripts/libs/sortable.min.js?<?= $version ?>"></script>
<script src="scripts/dial-categories.js?dial=<?= rawurlencode(DIAL_VERSION) ?>" defer></script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
