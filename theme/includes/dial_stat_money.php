<?php

function dialMoneyDigits($formatted)
{
  preg_match('/\p{N}[\p{N}\p{Zs}.,\'\x{2019}\x{066B}\x{066C}]*/u', $formatted, $matches);
  return trim($matches[0] ?? $formatted);
}

function dialStatisticMoney($amount, $currencyCode, $sign = '')
{
  $formatted = CurrencyFormatter::format(abs((float) $amount), $currencyCode);
  $digits = dialMoneyDigits($formatted);
  $prefix = $sign !== '' ? $sign : ($amount < 0 ? '−' : '');
  $fullValue = $prefix . $formatted;

  return '<span class="dial-stat-money" aria-label="' . htmlspecialchars($fullValue, ENT_QUOTES, 'UTF-8') . '">' .
    '<small class="dial-stat-currency">' . htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8') . '</small>' .
    '<strong class="dial-stat-digits">' . htmlspecialchars($prefix . $digits, ENT_QUOTES, 'UTF-8') . '</strong>' .
    '</span>';
}
