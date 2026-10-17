<?php
// Wallos Dial email presentation. Recipient selection, SMTP, and scheduling stay upstream.

function dial_mail_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dial_mail_language(?string $language): string
{
    return in_array($language, ['zh_cn', 'zh_tw'], true) ? $language : 'en';
}

function dial_mail_language_for_email(SQLite3 $db, string $email): string
{
    $stmt = $db->prepare('SELECT language FROM user WHERE email = :email LIMIT 1');
    if ($stmt === false) {
        return 'en';
    }
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result === false ? false : $result->fetchArray(SQLITE3_ASSOC);
    return dial_mail_language($row['language'] ?? null);
}

function dial_mail_copy(string $key, ?string $language): string
{
    $copy = [
        'en' => [
            'open' => 'Open Wallos', 'renewal' => 'Renewal reminder', 'cancellation' => 'Cancellation reminder',
            'summary_title' => 'Subscription summary', 'renewal_intro' => 'Review upcoming renewals and expected charges.',
            'cancellation_intro' => 'These subscriptions have reached their cancellation reminder date.',
            'summary_intro' => 'A summary of your subscription costs for this period.',
            'renewal_group' => 'Subscriptions', 'cancellation_group' => 'Cancellation',
            'amount' => 'Expected charge', 'subscription_amount' => 'Subscription amount',
            'today' => 'Expected today', 'days' => 'Expected in %d days', 'next' => 'Next payment',
            'period' => 'Period summary', 'test_title' => 'Email notification test', 'test_group' => 'System notification',
            'test_label' => 'Notification channel test', 'verify_title' => 'Verify your email',
            'verify_message' => 'Wallos registration is complete. Use the button below to verify your email.',
            'verify_action' => 'Verify email', 'reset_title' => 'Reset your password',
            'reset_message' => 'We received a password reset request. Use the button below to set a new password.',
            'reset_action' => 'Reset password', 'account_group' => 'Account',
        ],
        'zh_cn' => [
            'open' => '打开 Wallos', 'renewal' => '订阅续费提醒', 'cancellation' => '订阅取消提醒',
            'summary_title' => '本期订阅摘要', 'renewal_intro' => '核对即将续费的订阅与预计扣款金额。',
            'cancellation_intro' => '以下订阅已到你设置的取消提醒日，请核对取消安排。',
            'summary_intro' => '本期的订阅费用与预算摘要。',
            'renewal_group' => '订阅管理', 'cancellation_group' => '取消安排',
            'amount' => '预计扣款', 'subscription_amount' => '订阅金额',
            'today' => '预计今天扣款', 'days' => '距离预计扣款 %d 天', 'next' => '下次付款',
            'period' => '本期费用摘要', 'test_title' => '邮件通知测试', 'test_group' => '系统通知',
            'test_label' => '通知渠道测试', 'verify_title' => '验证你的邮箱',
            'verify_message' => 'Wallos 注册已完成。请通过下方按钮完成邮箱验证。',
            'verify_action' => '验证邮箱', 'reset_title' => '重置你的密码',
            'reset_message' => '我们收到了你的密码重置请求。请通过下方按钮设置新密码。',
            'reset_action' => '重置密码', 'account_group' => '账户管理',
        ],
        'zh_tw' => [
            'open' => '開啟 Wallos', 'renewal' => '訂閱續費提醒', 'cancellation' => '訂閱取消提醒',
            'summary_title' => '本期訂閱摘要', 'renewal_intro' => '核對即將續費的訂閱與預計扣款金額。',
            'cancellation_intro' => '以下訂閱已到你設定的取消提醒日，請核對取消安排。',
            'summary_intro' => '本期的訂閱費用與預算摘要。',
            'renewal_group' => '訂閱管理', 'cancellation_group' => '取消安排',
            'amount' => '預計扣款', 'subscription_amount' => '訂閱金額',
            'today' => '預計今天扣款', 'days' => '距預計扣款 %d 天', 'next' => '下次付款',
            'period' => '本期費用摘要', 'test_title' => '郵件通知測試', 'test_group' => '系統通知',
            'test_label' => '通知渠道測試', 'verify_title' => '驗證你的信箱',
            'verify_message' => 'Wallos 註冊已完成。請透過下方按鈕完成信箱驗證。',
            'verify_action' => '驗證信箱', 'reset_title' => '重設你的密碼',
            'reset_message' => '我們收到你的密碼重設請求。請透過下方按鈕設定新密碼。',
            'reset_action' => '重設密碼', 'account_group' => '帳戶管理',
        ],
    ];
    return $copy[dial_mail_language($language)][$key];
}

function dial_mail_base_url(string $base): string
{
    $base = rtrim(trim($base), '/');
    if ($base === '' || preg_match('/[\x00-\x20\x7f]/', $base)) {
        return '';
    }
    $parts = parse_url($base);
    if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }
    return $base;
}

function dial_mail_p(string $text, string $style = '', string $class = 'ink'): string
{
    return '<p class="' . $class . '" style="margin:0;font-size:14px;line-height:1.65;color:#191817;' . $style . '">'
        . dial_mail_escape($text) . '</p>';
}

function dial_mail_frame(string $title, string $eyebrow, string $content, string $preview,
    string $baseUrl = '', string $actionUrl = '', string $actionLabel = '', string $language = 'en'): string
{
    $base = dial_mail_base_url($baseUrl);
    $title = dial_mail_escape($title);
    $eyebrow = dial_mail_escape($eyebrow);
    $preview = dial_mail_escape($preview);
    $brand = $base === '' ? 'WALLOS' : '<a class="ink" href="' . dial_mail_escape($base)
        . '" style="font-size:27px;line-height:1;font-weight:750;letter-spacing:-1px;text-decoration:none;color:#191817">WALLOS</a>';
    $button = $actionUrl === '' ? '' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px"><tr><td>'
        . '<a class="button" href="' . dial_mail_escape($actionUrl) . '" style="display:inline-block;background:#191817;color:#f6f3e9;padding:12px 20px;border-radius:2px;font-size:13px;line-height:20px;font-weight:500;text-decoration:none">'
        . dial_mail_escape($actionLabel) . '</a></td></tr></table>';
    $htmlLanguage = str_replace('_', '-', dial_mail_language($language));
    return <<<HTML
<!doctype html><html lang="{$htmlLanguage}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><title>{$title}</title>
<style>
body,table,td,p,a,h1,h2{font-family:'Helvetica Neue',Arial,'PingFang SC','Microsoft YaHei',sans-serif}table{mso-table-lspace:0pt;mso-table-rspace:0pt}
@media only screen and (max-width:600px){.outer{padding:0!important}.pad{padding:26px 22px!important}.stack{display:block!important;width:100%!important;box-sizing:border-box!important;padding:0!important}.right{padding-top:16px!important}.card{height:auto!important}.amount{font-size:30px!important}}
@media(prefers-color-scheme:dark){body,.canvas{background-color:#151513!important}.paper{background-color:#1c1c1a!important}.soft{background-color:#272722!important}.ink{color:#f6f3e9!important}.muted{color:#bdb7a9!important}.signal{color:#f1767f!important}.line{border-color:#555148!important}.button{background-color:#f6f3e9!important;color:#191817!important}}
</style></head><body class="canvas" style="margin:0;padding:0;background:#e8e5dc;-webkit-text-size-adjust:100%">
<div style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;max-height:0;overflow:hidden;mso-hide:all">{$preview}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="canvas" bgcolor="#e8e5dc"><tr><td class="outer" align="center" style="padding:32px 16px">
<!--[if mso]><table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" class="paper" bgcolor="#f6f3e9" style="width:100%;max-width:640px;background:#f6f3e9;border-collapse:collapse"><tr><td class="pad paper" style="padding:36px 40px 32px;color:#191817;font-family:'Helvetica Neue',Arial,'PingFang SC','Microsoft YaHei',sans-serif;font-variant-numeric:tabular-nums">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="font-size:27px;line-height:1;font-weight:750;letter-spacing:-1px;color:#191817">{$brand}</td><td class="muted" align="right" style="font-size:12px;line-height:1.6;color:#65615a">{$eyebrow}</td></tr></table>
<h1 class="ink" style="margin:30px 0 24px;font-size:24px;line-height:1.35;font-weight:600;letter-spacing:-.5px;color:#191817">{$title}</h1>
{$content}
{$button}
</td></tr></table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>
HTML;
}

function dial_mail_notifications(string $kind, array $items, string $summary = '', bool $includeSummary = false,
    string $baseUrl = '', string $language = 'en'): string
{
    $cancel = $kind === 'cancellation';
    $empty = !$cancel && empty($items);
    $title = dial_mail_copy($empty ? 'summary_title' : ($cancel ? 'cancellation' : 'renewal'), $language);
    $intro = dial_mail_copy($empty ? 'summary_intro' : ($cancel ? 'cancellation_intro' : 'renewal_intro'), $language);
    $content = dial_mail_p($intro, 'color:#65615a;margin:0 0 24px;', 'muted');
    $cards = [];
    foreach (array_values($items) as $item) {
        $days = max(0, (int) ($item['days'] ?? 0));
        $status = $cancel ? dial_mail_copy('cancellation_group', $language)
            : ($days === 0 ? dial_mail_copy('today', $language) : sprintf(dial_mail_copy('days', $language), $days));
        $cards[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="soft card" style="padding:23px 22px;background:#eeebe2;height:194px;vertical-align:top">'
            . '<h2 class="ink" style="margin:0 0 22px;font-size:20px;line-height:1.4;font-weight:600;color:#191817;overflow-wrap:anywhere">' . dial_mail_escape($item['name'] ?? '') . '</h2>'
            . dial_mail_p(dial_mail_copy($cancel ? 'subscription_amount' : 'amount', $language), 'font-size:12px;color:#65615a;', 'muted')
            . '<p class="ink amount" style="margin:2px 0 10px;font-size:32px;line-height:1.2;font-weight:500;letter-spacing:-1px;color:#191817;overflow-wrap:anywhere">'
            . dial_mail_escape($item['formatted_price'] ?? $item['price'] ?? '') . '</p>'
            . dial_mail_p($status, 'font-size:12px;color:#d20b1c;', 'signal')
            . '<p class="muted" style="margin:18px 0 0;font-size:12px;line-height:1.6;color:#65615a">'
            . dial_mail_escape(dial_mail_copy('next', $language) . ' ' . ($item['date'] ?? '')) . '</p></td></tr></table>';
    }
    foreach (array_chunk($cards, 2) as $row) {
        $content .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px"><tr>';
        foreach ($row as $index => $card) {
            $two = count($row) === 2;
            $padding = $two ? ($index === 0 ? '0 8px 0 0' : '0 0 0 8px') : '0';
            $content .= '<td class="stack ' . ($index ? 'right' : '') . '" width="' . ($two ? '50%' : '100%')
                . '" style="vertical-align:top;padding:' . $padding . '">' . $card . '</td>';
        }
        $content .= '</tr></table>';
    }
    if ($includeSummary) {
        $content .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px"><tr><td class="soft" style="padding:22px;background:#eeebe2">'
            . dial_mail_p(dial_mail_copy('period', $language), 'font-size:12px;color:#65615a;margin:0 0 8px;', 'muted')
            . dial_mail_p($summary, 'font-size:16px;font-weight:500;overflow-wrap:anywhere;') . '</td></tr></table>';
    }
    $base = dial_mail_base_url($baseUrl);
    return dial_mail_frame($title, dial_mail_copy($cancel ? 'cancellation_group' : 'renewal_group', $language),
        $content, $intro, $base, $base, dial_mail_copy('open', $language), $language);
}

function dial_mail_test(string $message, string $baseUrl = '', string $language = 'en'): string
{
    $content = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="soft" style="padding:26px;background:#eeebe2">'
        . dial_mail_p(dial_mail_copy('test_label', $language), 'font-size:12px;color:#65615a;margin:0 0 10px;', 'muted')
        . dial_mail_p($message, 'font-size:18px;font-weight:500;') . '</td></tr></table>';
    $base = dial_mail_base_url($baseUrl);
    return dial_mail_frame(dial_mail_copy('test_title', $language), dial_mail_copy('test_group', $language),
        $content, $message, $base, $base, dial_mail_copy('open', $language), $language);
}

function dial_mail_account(string $kind, string $baseUrl, string $email, string $token, string $language = 'en'): array
{
    $base = dial_mail_base_url($baseUrl);
    if ($base === '') {
        throw new InvalidArgumentException('Wallos Server URL must be an absolute HTTP(S) URL for account emails.');
    }
    $verify = $kind === 'verification';
    $title = dial_mail_copy($verify ? 'verify_title' : 'reset_title', $language);
    $message = dial_mail_copy($verify ? 'verify_message' : 'reset_message', $language);
    $url = $base . ($verify ? '/verifyemail.php?' : '/passwordreset.php?')
        . http_build_query(['email' => $email, 'token' => $token], '', '&', PHP_QUERY_RFC3986);
    $content = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="soft" style="padding:26px;background:#eeebe2">'
        . dial_mail_p($message, 'font-size:16px;') . '</td></tr></table>';
    return [
        'html' => dial_mail_frame($title, dial_mail_copy('account_group', $language), $content, $message, $base,
            $url, dial_mail_copy($verify ? 'verify_action' : 'reset_action', $language), $language),
        'text' => $title . "\n\n" . $message . "\n\n" . $url . "\n",
    ];
}

function dial_mail_apply($mail, string $html, string $text): void
{
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Body = $html;
    $mail->AltBody = $text;
}
