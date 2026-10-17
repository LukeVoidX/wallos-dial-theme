<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/logo_theme_variant.php';
require_once '../../includes/logo_cleanup.php';
require_once '../../includes/dial_icon_transaction.php';
require_once '../../includes/dial_logo_limits.php';

if (getenv('DEMO_MODE') === '1') {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['status'=>'Error', 'message'=>'The public demo is read-only.']);
    exit();
}

if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

function sanitizeFilename($filename)
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $settings, $i18n)
{
    $maxRedirects = 3;
    $currentUrl = $url;

    for ($i = 0; $i <= $maxRedirects; $i++) {
        if (!filter_var($currentUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $currentUrl)) {
            return ['success' => false, 'message' => 'Invalid URL format.'];
        }

        $parts = parse_url($currentUrl);
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);

        $ip = gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || is_cgnat_ip($ip)) {
            return ['success' => false, 'message' => 'Invalid IP Address.'];
        }

        $ch = curl_init($currentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Manual handling to re-validate IPs
        curl_setopt($ch, CURLOPT_MAXFILESIZE, DIAL_LOGO_MAX_BYTES);
        $imageData = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION,
            static function($handle, string $chunk) use (&$imageData): int {
                return dialLogoCollectChunk($imageData, $chunk);
            });
        curl_setopt($ch, CURLOPT_RESOLVE, ["$host:$port:$ip"]);

        $downloaded = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 300 && $httpCode < 400) {
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            unset($ch);

            if (!$redirectUrl) {
                break;
            }

            $currentUrl = $redirectUrl;
            continue;
        }

        if ($downloaded !== false && $httpCode === 200) {
            $timestamp = time();
            $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
            $uploadFile = $uploadDir . $fileName; // Note: Use the provided $uploadDir variable

            if (saveLogo($imageData, $uploadFile, $name, $settings)) {
                unset($ch);
                return ['success' => true, 'filename' => $fileName];
            }

            unset($ch);
            return ['success' => false, 'message' => translate('error_saving_logo', $i18n)];
        }

        $error = curl_error($ch);
        unset($ch);
        return ['success' => false, 'message' => translate('error_fetching_image', $i18n) . ': ' . $error];
    }

    return ['success' => false, 'message' => translate('error_fetching_image', $i18n)];
}

function saveLogo($imageData, $uploadFile, $name, $settings)
{
    if (!dialLogoImageWithinLimits((string) $imageData)) return false;
    $image = imagecreatefromstring($imageData);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';

    if ($image !== false) {
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, $tempFile);
        imagedestroy($image);

        $newImage = imagecreatefrompng($tempFile);
        if ($newImage !== false) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);

            if ($removeBackground) {
                require_once __DIR__ . '/../../includes/gd_background_removal.php';
                // On palette images imagecolorat() returns palette indexes, not RGB values
                if (!imageistruecolor($newImage)) {
                    imagepalettetotruecolor($newImage);
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                }
                // Paint out the corner color with ~10% fuzz
                $corner = imagecolorat($newImage, 0, 0);
                if ((($corner >> 24) & 0x7F) !== 127) {
                    gdRemoveBackgroundColor($newImage, ($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF);
                }
            }

            // Crop/trim transparent margins
            require_once __DIR__ . '/../../includes/gd_background_removal.php';
            $newImage = gdCropTransparent($newImage, 2);

            $saved = imagepng($newImage, $uploadFile);
            imagedestroy($newImage);
        } else {
            unlink($tempFile);
            return false;
        }

        unlink($tempFile);
        return $saved;
    }

    return false;
}

function resizeAndUploadLogo($uploadedFile, $uploadDir, $name, $settings)
{
    if (!dialLogoFileWithinLimits((string) ($uploadedFile['tmp_name'] ?? ''))) return '';
    $targetWidth = 135;
    $targetHeight = 42;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                return "";
            }

            if ($fileExtension === 'png') {
                imagesavealpha($image, true);
            }

            // Crop/trim transparent margins (ensure we update dimensions after cropping)
            require_once __DIR__ . '/../../includes/gd_background_removal.php';
            $image = gdCropTransparent($image, 2);
            $width = imagesx($image);
            $height = imagesy($image);

            $newWidth = $width;
            $newHeight = $height;

            if ($width > $targetWidth) {
                $newWidth = (int) $targetWidth;
                $newHeight = (int) (($targetWidth / $width) * $height);
            }

            if ($newHeight > $targetHeight) {
                $newWidth = (int) (($targetHeight / $newHeight) * $newWidth);
                $newHeight = (int) $targetHeight;
            }

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($resizedImage, true);
            $transparency = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefill($resizedImage, 0, 0, $transparency);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if ($fileExtension === 'png') {
                $saved = imagepng($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $saved = imagejpeg($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'gif') {
                $saved = imagegif($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'webp') {
                $saved = imagewebp($resizedImage, $uploadFile);
            } else {
                return "";
            }

            imagedestroy($image);
            imagedestroy($resizedImage);

            if (!$saved) {
                if (file_exists($uploadFile)) {
                    unlink($uploadFile);
                }
                return "";
            }

            return $fileName;
        }
    }

    return "";
}

$isEdit = isset($_POST['id']) && $_POST['id'] != "";
$name = validate($_POST["name"]);
$price = $_POST['price'];
$currencyId = $_POST["currency_id"];
$frequency = $_POST["frequency"];
$cycle = $_POST["cycle"];
$nextPayment = $_POST["next_payment"];
$autoRenew = isset($_POST['auto_renew']) ? true : false;
$startDate = $_POST["start_date"];
$paymentMethodId = $_POST["payment_method_id"];
$payerUserId = $_POST["payer_user_id"];
$categoryId = $_POST['category_id'];
$notes = validate_markdown($_POST["notes"]);
$url = validate($_POST['url']);
$logoUrl = validate($_POST['logo-url']);
$logo = "";
$logoError = "";
$notify = isset($_POST['notifications']) ? true : false;
$notifyDaysBefore = $_POST['notify_days_before'];
$inactive = isset($_POST['inactive']) ? true : false;
$cancellationDate = $_POST['cancellation_date'] ?? null;
$replacementSubscriptionId = $_POST['replacement_subscription_id'];

if ($replacementSubscriptionId == 0 || $inactive == 0) {
    $replacementSubscriptionId = null;
}

if ($replacementSubscriptionId !== null) {
    $ownerCheck = $db->prepare("SELECT id FROM subscriptions WHERE id = :id AND user_id = :userId");
    $ownerCheck->bindParam(':id', $replacementSubscriptionId, SQLITE3_INTEGER);
    $ownerCheck->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $ownerResult = $ownerCheck->execute();
    if (!$ownerResult || !$ownerResult->fetchArray()) {
        $replacementSubscriptionId = null;
    }
}

// Reject any foreign key that doesn't belong to the authenticated user, so a
// subscription can't be attached to another tenant's currency/category/
// household member/payment method (IDs are global auto-increment integers
// and easily enumerable).
function rejectForeignId($message)
{
    header('Content-Type: application/json');
    echo json_encode(['status' => 'Error', 'message' => $message]);
    exit();
}

$currStmt = $db->prepare("SELECT id FROM currencies WHERE id = :id AND user_id = :userId");
$currStmt->bindParam(':id', $currencyId, SQLITE3_INTEGER);
$currStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$currResult = $currStmt->execute();
if (!$currResult || !$currResult->fetchArray()) {
    rejectForeignId('The specified currency does not exist or does not belong to you.');
}

if ($categoryId !== null && $categoryId !== '') {
    $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :userId");
    $catStmt->bindParam(':id', $categoryId, SQLITE3_INTEGER);
    $catStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $catResult = $catStmt->execute();
    if (!$catResult || !$catResult->fetchArray()) {
        rejectForeignId('The specified category does not exist or does not belong to you.');
    }
}

if ($payerUserId !== null && $payerUserId !== '') {
    $payerStmt = $db->prepare("SELECT id FROM household WHERE id = :id AND user_id = :userId");
    $payerStmt->bindParam(':id', $payerUserId, SQLITE3_INTEGER);
    $payerStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $payerResult = $payerStmt->execute();
    if (!$payerResult || !$payerResult->fetchArray()) {
        rejectForeignId('The specified household member does not exist or does not belong to you.');
    }
}

if ($paymentMethodId !== null && $paymentMethodId !== '') {
    $pmStmt = $db->prepare("SELECT id FROM payment_methods WHERE id = :id AND (user_id = :userId OR user_id = 0 OR user_id IS NULL)");
    $pmStmt->bindParam(':id', $paymentMethodId, SQLITE3_INTEGER);
    $pmStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $pmResult = $pmStmt->execute();
    if (!$pmResult || !$pmResult->fetchArray()) {
        rejectForeignId('The specified payment method does not exist or does not belong to you.');
    }
}

// Validate the optional Dial fields before making even a temporary Logo file.
// Older Wallos clients omit them; edits from such clients keep the saved choice.
try {
    $iconPreference = dialSubmittedIconPreference($db, (int) $userId, $_POST);
} catch (Throwable $error) {
    if (!($error instanceof InvalidArgumentException)) error_log('Dial icon validation failed: ' . $error->getMessage());
    rejectForeignId($error instanceof InvalidArgumentException ? $error->getMessage() : translate('error', $i18n));
}
if ($isEdit) {
    $id = $_POST['id'];
    if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0 ||
        !dialIconOne($db, 'SELECT id FROM subscriptions WHERE id=:id AND user_id=:user',
            [':id'=>(int) $id, ':user'=>(int) $userId])) {
        rejectForeignId('The specified subscription does not exist or does not belong to you.');
    }
}
// Logo URL fetches and image transforms can take seconds. Release the session
// lock so other authenticated requests from the same tab are not serialized.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$logosDir = '../../images/uploads/logos/';
$stageDir = '';
try {
    dialRetryLogoCleanup($db, $logosDir);
    if ($logoUrl !== '' || !empty($_FILES['logo']['name'])) {
        $stageDir = dialCreateLogoStageDirectory($logosDir);
    }
} catch (Throwable $error) {
    header('Content-Type: application/json');
    echo json_encode(['status'=>'Error', 'message'=>translate('error', $i18n)]);
    exit();
}
$stagedFiles = [];
$id = null;
// Also discard staging on a PHP fatal in the upstream image decoder, before
// the transaction helper is reached. A successful move leaves this dir empty.
if ($stageDir !== '') {
    register_shutdown_function(static function() use ($stageDir): void {
        dialDiscardLogoStageDirectory($stageDir);
    });
}

if ($logoUrl !== "") {
    $result = getLogoFromUrl($logoUrl, $stageDir . '/', $name, $settings, $i18n);
    if ($result['success']) {
        $logo = $result['filename'];
    } else {
        $logoError = $result['message'];
    }
} else {
    if (!empty($_FILES['logo']['name'])) {
        $fileType = mime_content_type($_FILES['logo']['tmp_name']);
        if (strpos($fileType, 'image') === false) {
            dialDiscardLogoStageDirectory($stageDir);
            header('Content-Type: application/json');
            echo json_encode(['status'=>'Error', 'message'=>translate("fill_all_fields", $i18n)]);
            exit();
        }
        $logo = resizeAndUploadLogo($_FILES['logo'], $stageDir . '/', $name, $settings);
        if ($logo === "") {
            $logoError = translate('error_saving_logo', $i18n);
        }
    }
}

$logoTextColor = null;
$logoVariant = null;
$removeBackgroundEnabled = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';

// Themed variant generation piggybacks on the same "remove background"
// setting: both only make sense for logos we're already reprocessing, and
// this avoids running pixel classification on every single upload.
if ($logo !== "" && $removeBackgroundEnabled) {
    $logoExtension = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
    $logoPath = $stageDir . '/' . $logo;

    if ($logoExtension === 'png' || $logoExtension === 'webp') {
        $sourceImage = $logoExtension === 'png' ? imagecreatefrompng($logoPath) : imagecreatefromwebp($logoPath);

        if ($sourceImage !== false) {
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);

            $logoTextColor = classifyLogoTextColor($sourceImage);

            if ($logoTextColor !== null) {
                $variantImage = generateThemedLogoVariant($sourceImage);
                $logoVariant = pathinfo($logo, PATHINFO_FILENAME) . '-variant.png';
                imagepng($variantImage, $stageDir . '/' . $logoVariant);
                imagedestroy($variantImage);
            }

            imagedestroy($sourceImage);
        }
    }
}

if ($logo !== '') {
    $stageLogo = $logo;
    $logo = 'dial-' . bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($stageLogo, PATHINFO_EXTENSION));
    $stagedFiles[] = ['temporary'=>$stageDir . '/' . $stageLogo, 'final'=>$logosDir . $logo];
    if ($logoVariant !== null) {
        $stageVariant = $logoVariant;
        $logoVariant = 'dial-' . bin2hex(random_bytes(16)) . '.png';
        $stagedFiles[] = ['temporary'=>$stageDir . '/' . $stageVariant, 'final'=>$logosDir . $logoVariant];
    }
}

if (!$isEdit) {
    $sql = "INSERT INTO subscriptions (
                        name, logo, price, currency_id, next_payment, cycle, frequency, notes,
                        payment_method_id, payer_user_id, category_id, notify, inactive, url,
                        notify_days_before, user_id, cancellation_date, replacement_subscription_id,
                        auto_renew, start_date, logo_text_color, logo_variant
                    ) VALUES (
                        :name, :logo, :price, :currencyId, :nextPayment, :cycle, :frequency, :notes,
                        :paymentMethodId, :payerUserId, :categoryId, :notify, :inactive, :url,
                        :notifyDaysBefore, :userId, :cancellationDate, :replacement_subscription_id,
                        :autoRenew, :startDate, :logoTextColor, :logoVariant
                    )";
} else {
    $id = $_POST['id'];

    $sql = "UPDATE subscriptions SET
                        name = :name,
                        price = :price,
                        currency_id = :currencyId,
                        next_payment = :nextPayment,
                        auto_renew = :autoRenew,
                        start_date = :startDate,
                        cycle = :cycle,
                        frequency = :frequency,
                        notes = :notes,
                        payment_method_id = :paymentMethodId,
                        payer_user_id = :payerUserId,
                        category_id = :categoryId,
                        notify = :notify,
                        inactive = :inactive,
                        url = :url,
                        notify_days_before = :notifyDaysBefore,
                        cancellation_date = :cancellationDate,
                        replacement_subscription_id = :replacement_subscription_id";

    if ($logo != "") {
        $sql .= ", logo = :logo, logo_text_color = :logoTextColor, logo_variant = :logoVariant";
    }

    $sql .= " WHERE id = :id AND user_id = :userId";
}

$stmt = $db->prepare($sql);
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
if ($logo != "") {
    $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
    $stmt->bindParam(':logoTextColor', $logoTextColor, SQLITE3_TEXT);
    $stmt->bindParam(':logoVariant', $logoVariant, SQLITE3_TEXT);
}
$stmt->bindParam(':price', $price, SQLITE3_FLOAT);
$stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
$stmt->bindParam(':autoRenew', $autoRenew, SQLITE3_INTEGER);
$stmt->bindParam(':startDate', $startDate, SQLITE3_TEXT);
$stmt->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
$stmt->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
$stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
$stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
$stmt->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
$stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
$stmt->bindParam(':notify', $notify, SQLITE3_INTEGER);
$stmt->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
$stmt->bindParam(':url', $url, SQLITE3_TEXT);
$stmt->bindParam(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
$stmt->bindParam(':cancellationDate', $cancellationDate, SQLITE3_TEXT);
if ($isEdit) {
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
}
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindParam(':replacement_subscription_id', $replacementSubscriptionId, SQLITE3_INTEGER);
$oldLogo = null;
$oldLogoVariant = null;

try {
    dialWriteSubscriptionWithIcon(
        $db, (int) $userId,
        static function(SQLite3 $db) use ($stmt, $isEdit, $id, $userId, $logo, &$oldLogo, &$oldLogoVariant): int {
            if ($isEdit && $logo !== '') {
                // BEGIN IMMEDIATE is already held here, so another editor's
                // just-committed Logo cannot be missed during postcommit cleanup.
                $previous = dialCurrentLogoFiles($db, (int) $userId, (int) $id);
                $oldLogo = $previous['logo'];
                $oldLogoVariant = $previous['logo_variant'];
            }
            $result = $stmt->execute();
            if (!$result) throw new RuntimeException('subscription_write_failed');
            $result->finalize();
            if ($isEdit && $db->changes() !== 1) throw new RuntimeException('subscription_not_owned');
            return $isEdit ? (int) $id : (int) $db->lastInsertRowID();
        },
        $iconPreference, $stagedFiles
    );
} catch (Throwable $error) {
    dialDiscardLogoStageDirectory($stageDir);
    error_log('Dial subscription write failed: ' . $error->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status'=>'Error', 'message'=>translate('error', $i18n)]);
    $db->close();
    exit();
}
dialDiscardLogoStageDirectory($stageDir);

$success['status'] = 'Success';
$text = $isEdit ? 'updated' : 'added';
$success['message'] = translate('subscription_' . $text . '_successfuly', $i18n);
if ($logoError !== '') $success['logo_warning'] = $logoError;

// The old Logo and variant are never removed before the SQL and new files
// commit. The cleanup helper skips files still used by a clone or another
// account and retains a private retry record for a failed unlink.
if ($isEdit && $logo !== '') {
    $oldFiles = [];
    if ($oldLogo !== null && $oldLogo !== $logo) $oldFiles[] = $oldLogo;
    if ($oldLogoVariant !== null && $oldLogoVariant !== $logoVariant) $oldFiles[] = $oldLogoVariant;
    $cleanup = dialCleanupPreviousLogoFiles($db, $oldFiles, $logosDir);
    if (($cleanup['retry_count'] > 0 || $cleanup['unscheduled_count'] > 0) && $logoError === '') {
        $cleanupWarnings = $cleanup['unscheduled_count'] > 0
            ? ['en'=>'Previous Logo cleanup failed. Check the server log.',
               'zh_cn'=>'旧 Logo 清理失败，请查看服务器日志。',
               'zh_tw'=>'舊 Logo 清理失敗，請查看伺服器日誌。']
            : ['en'=>'Previous Logo cleanup will be retried.',
               'zh_cn'=>'旧 Logo 清理失败，稍后会重试。',
               'zh_tw'=>'舊 Logo 清理失敗，稍後會重試。'];
        $success['logo_warning'] = $cleanupWarnings[$lang] ?? $cleanupWarnings['en'];
    }
}

header('Content-Type: application/json');
echo json_encode($success);
$db->close();
