<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/dial_categories.php';
require_once '../../includes/dial_category_i18n.php';
dial_category_authorize($lang);

$action = $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'merge':
            $sourceId = dial_category_positive_id($_POST['sourceId'] ?? null);
            $targetId = dial_category_positive_id($_POST['targetId'] ?? null);
            if ($sourceId === null || $targetId === null) {
                throw new DialCategoryError('invalid_request');
            }
            $movedCount = dial_category_merge($db, (int) $userId, $sourceId, $targetId);
            dial_category_send(true, 'category_merged', $lang, ['movedCount' => $movedCount]);
            break;

        case 'starter':
            if (($_POST['confirm'] ?? null) !== '1') {
                throw new DialCategoryError('invalid_request');
            }
            $deleteIds = $_POST['deleteIds'] ?? [];
            $expectedNames = $_POST['expectedNames'] ?? [];
            if (!is_array($deleteIds) || !is_array($expectedNames)) {
                throw new DialCategoryError('stale_selection');
            }
            $selected = [];
            foreach ($deleteIds as $rawId) {
                $id = dial_category_positive_id($rawId);
                if ($id === null || !isset($expectedNames[$id]) || !is_string($expectedNames[$id])) {
                    throw new DialCategoryError('stale_selection');
                }
                $selected[] = ['id' => $id, 'expectedName' => $expectedNames[$id]];
            }
            $result = dial_category_apply_starter($db, (int) $userId, $selected);
            dial_category_send(true, 'starter_applied', $lang, $result);
            break;

        default:
            throw new DialCategoryError('invalid_action');
    }
} catch (DialCategoryError $error) {
    dial_category_send(false, $error->getMessage(), $lang);
}
$db->close();
