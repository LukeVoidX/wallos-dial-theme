<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/dial_categories.php';
require_once '../../includes/dial_category_i18n.php';
dial_category_authorize($lang);

$action = $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'add':
            // The existing Settings button sends no name and must still add Category.
            $rawName = array_key_exists('name', $_POST) ? $_POST['name'] : null;
            if ($rawName !== null && !is_string($rawName)) {
                throw new DialCategoryError('invalid_name');
            }
            $categoryId = dial_category_add($db, (int) $userId, $rawName);
            dial_category_send(true, 'category_added', $lang, ['categoryId' => $categoryId]);
            break;

        case 'edit':
            $id = dial_category_positive_id($_POST['categoryId'] ?? null);
            $name = $_POST['name'] ?? null;
            if ($id === null || !is_string($name)) {
                throw new DialCategoryError('invalid_request');
            }
            dial_category_rename($db, (int) $userId, $id, $name);
            dial_category_send(true, 'category_saved', $lang);
            break;

        case 'delete':
            $id = dial_category_positive_id($_POST['categoryId'] ?? null);
            if ($id === null) throw new DialCategoryError('invalid_request');
            dial_category_delete_empty($db, (int) $userId, $id);
            dial_category_send(true, 'category_removed', $lang);
            break;

        case 'sort':
            $rawIds = $_POST['categoryIds'] ?? null;
            if (!is_array($rawIds)) throw new DialCategoryError('stale_order');
            $ids = [];
            foreach ($rawIds as $rawId) {
                $id = dial_category_positive_id($rawId);
                if ($id === null) throw new DialCategoryError('stale_order');
                $ids[] = $id;
            }
            dial_category_sort($db, (int) $userId, $ids);
            dial_category_send(true, 'order_saved', $lang);
            break;

        default:
            throw new DialCategoryError('invalid_action');
    }
} catch (DialCategoryError $error) {
    dial_category_send(false, $error->getMessage(), $lang);
}
$db->close();
