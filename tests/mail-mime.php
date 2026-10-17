<?php
require '/var/www/html/libs/PHPMailer/Exception.php';
require '/var/www/html/libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/../theme/includes/dial_mail_theme.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);
$mail->setFrom('sender@example.test', 'Wallos');
$mail->addAddress('recipient@example.test');
$mail->Subject = 'Wallos Notification';
$html = dial_mail_notifications('renewal', [[
    'name' => 'Aster AI', 'formatted_price' => 'US$19.00', 'date' => '2026-10-20', 'days' => 3,
]], '', false, 'https://example.test', 'zh_cn');
$plain = "Aster AI for US$19.00 (In 3 days)\n";
dial_mail_apply($mail, $html, $plain);
if (!$mail->preSend()) {
    throw new RuntimeException('PHPMailer could not prepare the message');
}
$mime = $mail->getSentMIMEMessage();
foreach (['multipart/alternative', 'text/plain', 'text/html', 'Aster AI', 'sender@example.test', 'recipient@example.test'] as $part) {
    if (!str_contains($mime, $part)) {
        throw new RuntimeException('MIME message missing ' . $part);
    }
}
echo "PHPMailer MIME test passed without sending.\n";
