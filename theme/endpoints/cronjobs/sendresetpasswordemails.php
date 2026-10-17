<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'validate.php';
require_once __DIR__ . '/../../includes/dial_mail_theme.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

require 'settimezone.php';
require_once __DIR__ . '/../../includes/instance_config.php';

// The admin row with anything the deployment owns applied over it, so this job
// uses the same mail server the admin page shows.
$admin = wallos_get_admin_settings($db);

$query = "SELECT * FROM password_resets WHERE email_sent = 0";
$stmt = $db->prepare($query);
$result = $stmt->execute();

$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

if ($rows) {
    if ($admin['smtp_address'] && $admin['smtp_port'] && $admin['smtp_username'] && $admin['smtp_password'] && $admin['encryption']) {
        // There are SMTP settings
        $smtpAddress = $admin['smtp_address'];
        $smtpPort = $admin['smtp_port'];
        $smtpUsername = $admin['smtp_username'];
        $smtpPassword = $admin['smtp_password'];
        $fromEmail = empty($admin['from_email']) ? 'wallos@wallosapp.com' : $admin['from_email'];
        $encryption = $admin['encryption'];
        $server_url = $admin['server_url'];
        $smtpAuth = (isset($admin["smtp_username"]) && $admin["smtp_username"] != "") || (isset($admin["smtp_password"]) && $admin["smtp_password"] != "");

        require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
        require __DIR__ . '/../../libs/PHPMailer/SMTP.php';
        require __DIR__ . '/../../libs/PHPMailer/Exception.php';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Timeout = 15;
        $mail->Host = $smtpAddress;
        $mail->SMTPAuth = $smtpAuth;
        if ($smtpAuth) {
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
        }
        if ($encryption != "none") {
            $mail->SMTPSecure = $encryption;
        }
        $mail->Port = $smtpPort;
        $mail->setFrom($fromEmail);

        try {
            foreach ($rows as $user) {
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Wallos - Reset Password';
                try {
                    $themedEmail = dial_mail_account('reset', $server_url, $user['email'], $user['token'], dial_mail_language_for_email($db, $user['email']));
                } catch (\InvalidArgumentException $e) {
                    echo "Password reset email skipped: configure a valid Wallos Server URL.<br>";
                    $mail->clearAddresses();
                    continue;
                }
                dial_mail_apply($mail, $themedEmail['html'], $themedEmail['text']);

                $mail->send();

                $query = "UPDATE password_resets SET email_sent = 1 WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $user['id'], SQLITE3_INTEGER);
                $stmt->execute();

                $mail->clearAddresses();

                echo "Password reset email sent to " . $user['email'] . "<br>";

            }
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo} <br>";
        }
    } else {
        // There are no SMTP settings
        if (php_sapi_name() !== 'cli') {
            echo "SMTP settings are not configured. Please configure SMTP settings in the admin page.";
        }
        exit();
    }
} else {
    // There are no password reset emails to be sent
    if (php_sapi_name() !== 'cli') {
        echo "There are no password reset emails to be sent.";
    }
    exit();
}

?>
