<?php
require __DIR__ . '/../theme/includes/dial_mail_theme.php';

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$items = [[
    'name' => '<Aster & AI>',
    'formatted_price' => 'US$19.00',
    'date' => '2026-10-20',
    'days' => 3,
]];
$en = dial_mail_notifications('renewal', $items, '', false, 'https://example.test/wallos', 'en');
check(str_contains($en, '&lt;Aster &amp; AI&gt;'), 'Subscription name must be escaped');
check(!str_contains($en, '<Aster & AI>'), 'Raw subscription name leaked into HTML');
check(str_contains($en, 'Renewal reminder'), 'English translation missing');
check(str_contains($en, 'https://example.test/wallos'), 'Configured URL missing');
check(substr_count($en, 'https://example.test/wallos') === 2, 'Mail links must use the configured instance URL');

$zh = dial_mail_notifications('cancellation', $items, '', false, '', 'zh_cn');
check(str_contains($zh, '取消提醒'), 'Simplified Chinese translation missing');
check(!str_contains($zh, 'href=""'), 'An empty URL must not make a link');

$fallback = dial_mail_notifications('renewal', $items, '', false, '', 'de');
check(str_contains($fallback, 'Renewal reminder'), 'Unsupported locale must fall back to English');

$account = dial_mail_account('verification', 'https://example.test/wallos/', 'a+b@example.test', 'a&b', 'en');
check(str_contains($account['html'], 'email=a%2Bb%40example.test&amp;token=a%26b'), 'Account link must encode query values and HTML ampersand');
check(str_contains($account['text'], 'email=a%2Bb%40example.test&token=a%26b'), 'Plain text account link missing');
check(str_contains($account['html'], 'href="https://example.test/wallos"'), 'Brand link must use instance URL');

foreach (['javascript:alert(1)', 'https://user@example.test/', 'https://example.test/#fragment'] as $badUrl) {
    try {
        dial_mail_account('reset', $badUrl, 'a@example.test', 'token', 'en');
        throw new RuntimeException('Unsafe URL accepted: ' . $badUrl);
    } catch (InvalidArgumentException $expected) {
    }
}

$mail = new class {
    public $CharSet;
    public $Body;
    public $AltBody;
    public $html = false;
    public $to = ['existing@example.test'];
    public function isHTML($value) { $this->html = $value; }
};
dial_mail_apply($mail, $en, 'Plain text reminder');
check($mail->html && $mail->CharSet === 'UTF-8', 'HTML mode or charset missing');
check($mail->AltBody === 'Plain text reminder', 'Plain-text fallback missing');
check($mail->to === ['existing@example.test'], 'Recipients changed');

$db = new SQLite3(':memory:');
$db->exec('CREATE TABLE user (email TEXT, language TEXT)');
$db->exec("INSERT INTO user VALUES ('a@example.test', 'zh_cn')");
check(dial_mail_language_for_email($db, 'a@example.test') === 'zh_cn', 'Account language was not read');
check(dial_mail_language_for_email($db, 'missing@example.test') === 'en', 'Unknown account must use English');

echo "Mail theme tests passed.\n";
