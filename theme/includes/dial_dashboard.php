<?php
require_once __DIR__ . '/dial_logo_map.php';
/**
 * Desktop dashboard composition. Existing Wallos detail, add, search and
 * settings flows remain the handlers for its controls.
 */
function dialBillingCycle($cycle, $frequency, $i18n)
{
    $units = [
        1 => ['Daily', 'days'],
        2 => ['Weekly', 'weeks'],
        3 => ['Monthly', 'months'],
        4 => ['Yearly', 'years'],
    ];
    $cycle = (int) $cycle;
    $frequency = max(1, (int) $frequency);
    if (!isset($units[$cycle])) {
        return '';
    }
    return $frequency === 1
        ? translate($units[$cycle][0], $i18n)
        : $frequency . ' ' . translate($units[$cycle][1], $i18n);
}

$dialToday = new DateTimeImmutable('today');
$dialTimelineStart = $dialToday->modify('first day of this month');
$dialTimelineEnd = $dialTimelineStart->modify('+2 months +2 days');
$dialTimelineDays = (int) $dialTimelineStart->diff($dialTimelineEnd)->days + 1;
$dialChineseDates = strpos($lang, 'zh_') === 0;
$dialCountdownHeading = $lang === 'zh_cn' ? '倒计时' : ($lang === 'zh_tw' ? '倒數' : 'Countdown');
$dialDateFormatter = new IntlDateFormatter(
    $lang,
    IntlDateFormatter::MEDIUM,
    IntlDateFormatter::NONE,
    null,
    null,
    $dialChineseDates ? 'yyyy.MM.dd' : 'dd MMM yyyy'
);
$dialMonthFormatter = new IntlDateFormatter(
    $lang,
    IntlDateFormatter::MEDIUM,
    IntlDateFormatter::NONE,
    null,
    null,
    $dialChineseDates ? 'yyyy.MM' : 'MMM yyyy'
);
$dialMonthlyValue = CurrencyFormatter::format(
    $totalCostPerMonth ?? 0,
    $currencies[$userData['main_currency']]['code']
);
$dialMonthlyCurrency = $currencies[$userData['main_currency']]['code'];
$dialMonthlyDigits = dialMoneyDigits($dialMonthlyValue);
?>
<div class="dial-dashboard" aria-label="<?= htmlspecialchars(translate('dashboard', $i18n), ENT_QUOTES, 'UTF-8') ?>">
  <div class="dial-hero">
    <div class="dial-hero-primary">
      <p class="dial-eyebrow"><?= htmlspecialchars(translate('monthly_cost', $i18n), ENT_QUOTES, 'UTF-8') ?></p>
      <p class="dial-monthly-value" aria-label="<?= htmlspecialchars($dialMonthlyValue, ENT_QUOTES, 'UTF-8') ?>"><small><?= htmlspecialchars($dialMonthlyCurrency, ENT_QUOTES, 'UTF-8') ?></small><span><?= htmlspecialchars($dialMonthlyDigits, ENT_QUOTES, 'UTF-8') ?></span></p>
    </div>
    <div class="dial-hero-aside">
      <time datetime="<?= $dialToday->format('Y-m-d') ?>"><?= htmlspecialchars($dialDateFormatter->format($dialToday), ENT_QUOTES, 'UTF-8') ?></time>
      <p><?= $lang === 'zh_tw' ? '私密設計。<br>你的訂閱。<br>你的資料。' : ($lang === 'zh_cn' ? '私密设计。<br>你的订阅。<br>你的数据。' : 'Private by design.<br>Your subscriptions.<br>Your data.') ?></p>
      <span class="dial-aside-rule" aria-hidden="true"></span>
    </div>
  </div>

  <div class="dial-timeline" aria-label="<?= htmlspecialchars($dialDateFormatter->format($dialToday), ENT_QUOTES, 'UTF-8') ?>">
    <ol class="dial-timeline-days" style="--timeline-days: <?= $dialTimelineDays ?>">
      <?php for ($dayIndex = 0; $dayIndex < $dialTimelineDays; $dayIndex++):
          $day = $dialTimelineStart->modify('+' . $dayIndex . ' days');
          $dayNumber = (int) $day->format('j');
          $isToday = $day->format('Y-m-d') === $dialToday->format('Y-m-d');
          $isMajor = in_array($dayNumber, [1, 7, 14, 21, 28], true);
          $showDayLabel = $isMajor || $isToday;
          $calendarHref = 'calendar.php?month=' . $day->format('n') . '&amp;year=' . $day->format('Y');
          $dayText = htmlspecialchars($dialDateFormatter->format($day), ENT_QUOTES, 'UTF-8');
      ?>
        <li class="dial-tick<?= $dayNumber === 1 ? ' dial-tick--month' : '' ?><?= $isMajor ? ' dial-tick--major' : '' ?><?= $isToday ? ' dial-tick--today' : '' ?>" data-dial-date="<?= $dayText ?>">
          <?php if ($showDayLabel): ?>
            <a class="dial-tick-link" href="<?= $calendarHref ?>"
              aria-label="<?= $dayText ?> · <?= htmlspecialchars(translate('calendar', $i18n), ENT_QUOTES, 'UTF-8') ?>">
          <?php else: ?>
            <span class="dial-tick-static" aria-hidden="true">
          <?php endif; ?>
          <?php if ($dayNumber === 1): ?>
            <span class="dial-month-label"><?= htmlspecialchars(strtoupper($dialMonthFormatter->format($day)), ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <span class="dial-tick-mark"></span>
          <?php if ($showDayLabel): ?><span class="dial-day-label"><?= $dayNumber ?></span><?php endif; ?>
          <?php if ($showDayLabel): ?>
            <span class="dial-tick-tooltip" aria-hidden="true"><?= $dayText ?><small><?= htmlspecialchars(translate('calendar', $i18n), ENT_QUOTES, 'UTF-8') ?> ↗</small></span>
            </a>
          <?php else: ?>
            </span>
          <?php endif; ?>
        </li>
      <?php endfor; ?>
    </ol>
    <span class="dial-readout" aria-hidden="true"></span>
  </div>

  <div class="dial-payments-heading">
    <h1><?= htmlspecialchars(translate('upcoming_payments', $i18n), ENT_QUOTES, 'UTF-8') ?></h1>
    <a class="dial-add-link" href="subscriptions.php?add=1">
      <i class="fa-solid fa-plus" aria-hidden="true"></i>
      <span><?= htmlspecialchars(translate('new_subscription', $i18n), ENT_QUOTES, 'UTF-8') ?></span>
    </a>
  </div>

  <div class="dial-payments-table">
    <div class="dial-table-head" aria-hidden="true">
      <span><?= htmlspecialchars(translate('subscription', $i18n), ENT_QUOTES, 'UTF-8') ?></span>
      <span><?= htmlspecialchars(translate('next_payment', $i18n), ENT_QUOTES, 'UTF-8') ?></span>
      <span><?= $dialCountdownHeading ?></span>
      <span><?= htmlspecialchars(translate('price', $i18n), ENT_QUOTES, 'UTF-8') ?></span>
      <span><?= htmlspecialchars(translate('frequency', $i18n), ENT_QUOTES, 'UTF-8') ?></span>
      <span></span>
    </div>
    <?php if (empty($upcomingSubscriptions)): ?>
      <p class="dial-empty"><?= htmlspecialchars(translate('no_upcoming_payments', $i18n), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <?php foreach ($upcomingSubscriptions as $subscription):
          $subscriptionName = htmlspecialchars($subscription['name'], ENT_QUOTES, 'UTF-8');
          $subscriptionId = (int) $subscription['id'];
          $subscriptionDate = new DateTimeImmutable($subscription['next_payment']);
          $daysUntilPayment = (int) $dialToday->diff($subscriptionDate)->format('%r%a');
          $countdownText = $daysUntilPayment === 0
              ? ($lang === 'zh_cn' ? '今天' : ($lang === 'zh_tw' ? '今日' : 'Today'))
              : ($daysUntilPayment > 0
                  ? ($lang === 'zh_cn' || $lang === 'zh_tw' ? $daysUntilPayment . ' 天' : $daysUntilPayment . ' ' . ($daysUntilPayment === 1 ? 'day' : 'days'))
                  : ($lang === 'zh_cn' ? '逾期 ' . abs($daysUntilPayment) . ' 天' : ($lang === 'zh_tw' ? '逾期 ' . abs($daysUntilPayment) . ' 天' : 'Overdue ' . abs($daysUntilPayment) . ' ' . (abs($daysUntilPayment) === 1 ? 'day' : 'days'))));
          $subscriptionPrice = formatPrice(
              $subscription['price'],
              $currencies[$subscription['currency_id']]['code'],
              $currencies
          );
          $subscriptionRedrawnLogo = dialRedrawnLogoFor($subscription['logo'] ?? '');
      ?>
        <div class="dial-payment-row" role="button" tabindex="0" data-id="<?= $subscriptionId ?>"
          aria-label="<?= $subscriptionName ?>, <?= htmlspecialchars($dialDateFormatter->format($subscriptionDate), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($countdownText, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($subscriptionPrice, ENT_QUOTES, 'UTF-8') ?>"
          title="<?= htmlspecialchars(translate('subscription', $i18n), ENT_QUOTES, 'UTF-8') ?>: <?= $subscriptionName ?>"
          onclick="showSubscriptionDetails(event, <?= $subscriptionId ?>)"
          onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); showSubscriptionDetails(event, <?= $subscriptionId ?>); }">
          <span class="dial-service">
            <span class="dial-service-logo<?= empty($subscription['logo']) ? ' dial-service-logo--empty' : '' ?>">
              <?php if ($subscriptionRedrawnLogo): ?>
                <img class="dial-redrawn-logo" src="images/dial-logos/<?= htmlspecialchars($subscriptionRedrawnLogo, ENT_QUOTES, 'UTF-8') ?>" alt="">
              <?php elseif (!empty($subscription['logo'])):
                  $subscriptionLogoSrc = 'images/uploads/logos/' . $subscription['logo'];
                  $subscriptionLogoVariantSrc = !empty($subscription['logo_variant'])
                      ? 'images/uploads/logos/' . $subscription['logo_variant'] : null;
                  echo renderThemedLogoImg(
                      $subscriptionLogoSrc,
                      $subscriptionLogoVariantSrc,
                      $subscription['logo_text_color'] ?? null,
                      'dial-logo-image',
                      'alt=""'
                  );
              endif; ?>
            </span>
            <span class="dial-service-name"><?= $subscriptionName ?></span>
          </span>
          <time datetime="<?= htmlspecialchars($subscriptionDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dialDateFormatter->format($subscriptionDate), ENT_QUOTES, 'UTF-8') ?></time>
          <span class="dial-row-countdown<?= $daysUntilPayment <= 3 ? ' dial-row-countdown--urgent' : '' ?>"><?= htmlspecialchars($countdownText, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="dial-row-price"><?= htmlspecialchars($subscriptionPrice, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="dial-row-frequency"><?= htmlspecialchars(dialBillingCycle($subscription['cycle'], $subscription['frequency'], $i18n), ENT_QUOTES, 'UTF-8') ?></span>
          <i class="fa-solid fa-ellipsis dial-row-more" aria-hidden="true"></i>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
