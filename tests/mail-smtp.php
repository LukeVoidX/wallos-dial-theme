<?php
// Optional isolated integration test. Point this only at a disposable SMTP capture service.
require '/var/www/html/libs/PHPMailer/Exception.php';
require '/var/www/html/libs/PHPMailer/SMTP.php';
require '/var/www/html/libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/../theme/includes/dial_mail_theme.php';

$host = getenv('DIAL_TEST_SMTP_HOST');
if ($host !== 'mailpit') {
    throw new RuntimeException('This test only sends to the isolated mailpit container.');
}
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
$mail->isSMTP();
$mail->Host = $host;
$mail->Port = 1025;
$mail->SMTPAuth = false;
$mail->SMTPAutoTLS = false;
$mail->setFrom('wallos@example.test', 'Wallos');
$mail->addAddress('recipient@example.test');
$mail->Subject = 'Wallos Dial synthetic mail test';
$html = dial_mail_notifications('renewal', [[
    'name' => 'Aster AI', 'formatted_price' => 'US$19.00', 'date' => '2026-10-20', 'days' => 3,
]], '', false, 'https://demo.example.org', 'zh_cn');
dial_mail_apply($mail, $html, "Aster AI for US$19.00 (In 3 days)\n");
if (!$mail->send()) {
    throw new RuntimeException('SMTP capture did not accept the message.');
}
echo "Synthetic message accepted by isolated SMTP capture.\n";
