<?php

function dial_category_message(string $code, string $lang): string
{
    static $messages = [
        'en' => [
            'category_added' => 'Category added.',
            'category_saved' => 'Category saved.',
            'category_removed' => 'Category removed.',
            'order_saved' => 'Category order saved.',
            'category_merged' => 'Categories merged.',
            'starter_applied' => 'Starter categories added.',
            'invalid_name' => 'Enter a category name with up to 60 characters.',
            'foreign_category' => 'That category is unavailable.',
            'last_category' => 'Keep at least one category.',
            'immutable_category' => 'The default category cannot be removed.',
            'category_in_use' => 'This category has subscriptions. Merge it first.',
            'stale_order' => 'Categories changed in another tab. Reload and try again.',
            'stale_selection' => 'The selected categories changed. Reload the preview.',
            'same_category' => 'Choose two different categories.',
            'write_failed' => 'Could not save categories. Try again.',
            'invalid_request' => 'Check the submitted category fields.',
            'invalid_action' => 'Unknown category action.',
            'invalid_method' => 'Use a POST request.',
            'invalid_csrf' => 'Security token expired. Reload the page.',
            'session_expired' => 'Please sign in again.',
            'demo_read_only' => 'The public demo is read-only.',
        ],
        'zh_cn' => [
            'category_added' => '已新增分类。', 'category_saved' => '已保存分类。',
            'category_removed' => '已删除分类。', 'order_saved' => '已保存分类顺序。',
            'category_merged' => '已合并分类。', 'starter_applied' => '已加入初始分类。',
            'invalid_name' => '请输入不超过 60 个字符的分类名称。',
            'foreign_category' => '无法使用该分类。', 'last_category' => '请至少保留一个分类。',
            'immutable_category' => '默认分类不能删除。',
            'category_in_use' => '此分类仍有订阅，请先合并。',
            'stale_order' => '其他标签页修改了分类，请刷新后重试。',
            'stale_selection' => '所选分类已变化，请刷新预览。',
            'same_category' => '请选择两个不同的分类。',
            'write_failed' => '分类保存失败，请重试。',
            'invalid_request' => '请检查提交的分类信息。',
            'invalid_action' => '未知的分类操作。',
            'invalid_method' => '请使用 POST 请求。',
            'invalid_csrf' => '安全令牌已过期，请刷新页面。',
            'session_expired' => '请重新登录。',
            'demo_read_only' => '公开演示仅供浏览。',
        ],
        'zh_tw' => [
            'category_added' => '已新增分類。', 'category_saved' => '已儲存分類。',
            'category_removed' => '已刪除分類。', 'order_saved' => '已儲存分類順序。',
            'category_merged' => '已合併分類。', 'starter_applied' => '已加入初始分類。',
            'invalid_name' => '請輸入不超過 60 個字元的分類名稱。',
            'foreign_category' => '無法使用該分類。', 'last_category' => '請至少保留一個分類。',
            'immutable_category' => '預設分類不能刪除。',
            'category_in_use' => '此分類仍有訂閱，請先合併。',
            'stale_order' => '其他分頁修改了分類，請重新整理後再試。',
            'stale_selection' => '所選分類已變更，請重新整理預覽。',
            'same_category' => '請選擇兩個不同的分類。',
            'write_failed' => '分類儲存失敗，請重試。',
            'invalid_request' => '請檢查提交的分類資訊。',
            'invalid_action' => '未知的分類操作。',
            'invalid_method' => '請使用 POST 請求。',
            'invalid_csrf' => '安全權杖已過期，請重新整理頁面。',
            'session_expired' => '請重新登入。',
            'demo_read_only' => '公開展示僅供瀏覽。',
        ],
    ];
    return $messages[$lang][$code] ?? $messages['en'][$code] ?? $messages['en']['write_failed'];
}

function dial_category_send(bool $success, string $code, string $lang, array $extra = [], int $status = 200): void
{
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'code' => $code,
        'message' => dial_category_message($code, $lang),
    ], $extra), JSON_UNESCAPED_UNICODE);
}

function dial_category_authorize(string $lang): void
{
    header('Content-Type: application/json; charset=UTF-8');
    require_once __DIR__ . '/../libs/csrf.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dial_category_send(false, 'invalid_method', $lang, [], 405);
        exit;
    }
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($csrf) || !verify_csrf_token($csrf)) {
        dial_category_send(false, 'invalid_csrf', $lang, [], 403);
        exit;
    }
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        dial_category_send(false, 'session_expired', $lang, [], 401);
        exit;
    }
    require_once __DIR__ . '/validate_endpoint.php';
    if (getenv('DEMO_MODE') === '1') {
        dial_category_send(false, 'demo_read_only', $lang, [], 403);
        exit;
    }
    // Wallos opens SQLite before session_start(). Concurrent prefetch requests
    // can hold SQLite read locks while waiting for this session's file lock.
    // Releasing the session before BEGIN IMMEDIATE prevents that lock cycle.
    session_write_close();
}

function dial_category_positive_id($value): ?int
{
    if (!is_string($value) && !is_int($value)) return null;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function dial_category_copy(string $language): array
{
    static $copy = [
        'en' => [
            'title' => 'Categories', 'eyebrow' => 'YOUR SYSTEM',
            'intro' => 'Keep each subscription in a place that makes sense to you.',
            'manage' => 'Manage categories', 'name' => 'Category name',
            'add' => 'Add category', 'add_placeholder' => 'New category name',
            'save' => 'Save', 'move_up' => 'Move up', 'move_down' => 'Move down',
            'drag' => 'Drag to reorder', 'merge' => 'Merge',
            'merge_target' => 'Move subscriptions to', 'merge_confirm' => 'Merge categories',
            'merge_help' => 'All subscriptions in this category move to the selected category. The old category is then removed.',
            'delete' => 'Delete', 'delete_confirm' => 'Delete empty category',
            'delete_help' => 'This empty category will be removed.',
            'starter' => 'Use starter categories', 'starter_confirm' => 'Apply starter categories',
            'starter_help' => 'Preview the four starter names. Choose any empty categories to remove; none are selected automatically.',
            'starter_missing' => 'Names to add', 'starter_remove' => 'Empty categories to remove',
            'starter_none' => 'All starter names already exist.',
            'readonly' => 'This public demo is read-only. Install Wallos Dial to manage your own categories.',
            'fixed' => 'Protected default', 'cancel' => 'Cancel',
            'count' => 'Subscriptions', 'actions' => 'Actions',
            'saved' => 'Saved.', 'install' => 'Installation guide',
            'starter_unavailable' => 'Starter categories are available when this account has no subscriptions.',
        ],
        'zh_cn' => [
            'title' => '分类管理', 'eyebrow' => '你的订阅系统',
            'intro' => '按你的使用方式整理每一项订阅。',
            'manage' => '管理分类', 'name' => '分类名称',
            'add' => '新增分类', 'add_placeholder' => '新分类名称',
            'save' => '保存', 'move_up' => '上移', 'move_down' => '下移',
            'drag' => '拖动排序', 'merge' => '合并',
            'merge_target' => '将订阅移至', 'merge_confirm' => '确认合并分类',
            'merge_help' => '此分类的全部订阅将移到目标分类，原分类随后删除。',
            'delete' => '删除', 'delete_confirm' => '删除空分类',
            'delete_help' => '此空分类将被删除。',
            'starter' => '使用初始分类', 'starter_confirm' => '加入初始分类',
            'starter_help' => '预览四个初始名称。可自行勾选要删除的空分类；默认不勾选。',
            'starter_missing' => '将新增的名称', 'starter_remove' => '可删除的空分类',
            'starter_none' => '四个初始分类均已存在。',
            'readonly' => '公开演示仅供浏览。安装 Wallos Dial 后即可管理自己的分类。',
            'fixed' => '受保护的默认分类', 'cancel' => '取消',
            'count' => '订阅数', 'actions' => '操作',
            'saved' => '已保存。', 'install' => '安装说明',
            'starter_unavailable' => '此账户没有订阅时，才能加入初始分类。',
        ],
        'zh_tw' => [
            'title' => '分類管理', 'eyebrow' => '你的訂閱系統',
            'intro' => '依照你的使用方式整理每一項訂閱。',
            'manage' => '管理分類', 'name' => '分類名稱',
            'add' => '新增分類', 'add_placeholder' => '新分類名稱',
            'save' => '儲存', 'move_up' => '上移', 'move_down' => '下移',
            'drag' => '拖曳排序', 'merge' => '合併',
            'merge_target' => '將訂閱移至', 'merge_confirm' => '確認合併分類',
            'merge_help' => '此分類的全部訂閱將移至目標分類，原分類隨後刪除。',
            'delete' => '刪除', 'delete_confirm' => '刪除空分類',
            'delete_help' => '此空分類將被刪除。',
            'starter' => '使用初始分類', 'starter_confirm' => '加入初始分類',
            'starter_help' => '預覽四個初始名稱。可自行勾選要刪除的空分類；預設不勾選。',
            'starter_missing' => '將新增的名稱', 'starter_remove' => '可刪除的空分類',
            'starter_none' => '四個初始分類均已存在。',
            'readonly' => '公開展示僅供瀏覽。安裝 Wallos Dial 後即可管理自己的分類。',
            'fixed' => '受保護的預設分類', 'cancel' => '取消',
            'count' => '訂閱數', 'actions' => '操作',
            'saved' => '已儲存。', 'install' => '安裝說明',
            'starter_unavailable' => '此帳戶沒有訂閱時，才能加入初始分類。',
        ],
    ];
    $language = isset($copy[$language]) ? $language : 'en';
    $selected = $copy[$language];
    foreach (['invalid_name', 'foreign_category', 'category_in_use',
              'immutable_category', 'last_category', 'stale_order',
              'stale_selection', 'demo_read_only', 'write_failed',
              'same_category', 'invalid_request'] as $code) {
        $selected[$code] = dial_category_message($code, $language);
    }
    return $selected;
}
