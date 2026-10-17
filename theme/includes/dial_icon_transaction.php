<?php
declare(strict_types=1);

require_once __DIR__ . '/dial_icon_repository.php';

/** Return null only when the older Wallos form sends no Dial fields at all. */
function dialSubmittedIconPreference(SQLite3 $db, int $userId, array $post): ?array
{
    $fields = [
        'source'=>'dial_icon_source', 'catalog_key'=>'dial_icon_catalog_key',
        'asset_id'=>'dial_icon_asset_id', 'presentation'=>'dial_icon_presentation',
        'treatment'=>'dial_icon_treatment', 'scale'=>'dial_icon_scale',
    ];
    $input = [];
    $any = false;
    foreach ($fields as $key=>$postKey) {
        $value = $post[$postKey] ?? '';
        if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('icon_source_invalid');
        if ($value !== '') $any = true;
        $input[$key] = $value;
    }
    if (!$any) return null;
    if ($input['source'] === '') throw new InvalidArgumentException('icon_source_invalid');
    if ($input['presentation'] === '') $input['presentation'] = 'plain';
    if ($input['treatment'] === '') $input['treatment'] = 'color';
    if ($input['scale'] === '') $input['scale'] = 100;
    return dialIconValidatePreference($db, $userId, $input);
}

/** Keep uploaded files on the Logo mount while denying access to nginx's user. */
function dialCreateLogoStageDirectory(string $logosDir): string
{
    $logosDir = rtrim($logosDir, '/');
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $path = $logosDir . '/.dial-stage-' . bin2hex(random_bytes(16));
        if (@mkdir($path, 0700)) {
            @chmod($path, 0700);
            return $path;
        }
    }
    throw new RuntimeException('logo_stage_failed');
}

/** Both files are on one mount; hard linking atomically refuses a collision. */
function dialMoveStagedLogoFiles(array $stagedFiles, array &$createdFinals): void
{
    foreach ($stagedFiles as $file) {
        $temporary = $file['temporary'] ?? null;
        $final = $file['final'] ?? null;
        if (!is_string($temporary) || !is_string($final) || !is_file($temporary) ||
            is_link($temporary) || file_exists($final) || is_link($final)) {
            throw new RuntimeException('logo_move_failed');
        }
        if (!@chmod($temporary, 0644) || !@link($temporary, $final)) {
            throw new RuntimeException('logo_move_failed');
        }
        $createdFinals[] = $final;
        if (!@unlink($temporary)) {
            throw new RuntimeException('logo_move_failed');
        }
    }
}

function dialRemoveNewLogoFiles(array $stagedFiles, array $createdFinals): void
{
    foreach ($stagedFiles as $file) {
        $path = $file['temporary'] ?? null;
        if (is_string($path) && (is_file($path) || is_link($path))) @unlink($path);
    }
    foreach ($createdFinals as $path) {
        if (is_string($path) && (is_file($path) || is_link($path))) @unlink($path);
    }
}

/** Read the Logo currently committed under this transaction's write lock. */
function dialCurrentLogoFiles(SQLite3 $db, int $userId, int $subscriptionId): array
{
    $row = dialIconOne($db,
        'SELECT logo,logo_variant FROM subscriptions WHERE id=:id AND user_id=:user',
        [':id'=>$subscriptionId, ':user'=>$userId]);
    if (!$row) throw new InvalidArgumentException('subscription_not_owned');
    return $row;
}

function dialDiscardLogoStageDirectory(string $stageDir): void
{
    if (!preg_match('/\A\.dial-stage-[a-f0-9]{32}\z/D', basename($stageDir)) || !is_dir($stageDir) || is_link($stageDir)) {
        return;
    }
    foreach (scandir($stageDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $stageDir . '/' . $entry;
        if (is_file($path) || is_link($path)) @unlink($path);
    }
    @rmdir($stageDir);
}

/**
 * The caller stages the unchanged upstream Logo transform before calling this.
 * Every database write and final file placement either commits together or is
 * reversed. A missing preference leaves an existing choice untouched on edit.
 */
function dialWriteSubscriptionWithIcon(
    SQLite3 $db, int $userId, callable $writeWallosRow, ?array $preference,
    array $stagedFiles, ?callable $fault = null
): int {
    $begun = false;
    $createdFinals = [];
    try {
        if ($preference !== null) $preference = dialIconValidatePreference($db, $userId, $preference);
        if ($fault !== null) $fault('after_stage');
        if (!@$db->exec('BEGIN IMMEDIATE')) throw new RuntimeException('subscription_transaction_failed');
        $begun = true;
        $id = $writeWallosRow($db);
        if (!is_int($id) || $id <= 0) throw new RuntimeException('subscription_write_failed');
        dialMoveStagedLogoFiles($stagedFiles, $createdFinals);
        if ($fault !== null) $fault('after_move');
        if ($preference !== null) dialIconSavePreference($db, $userId, $id, $preference);
        if ($fault !== null) $fault('before_commit');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('subscription_commit_failed');
        $begun = false;
        return $id;
    } catch (Throwable $error) {
        if ($begun) @$db->exec('ROLLBACK');
        dialRemoveNewLogoFiles($stagedFiles, $createdFinals);
        throw $error;
    }
}

function dialLogoIsReferenced(SQLite3 $db, string $filename): bool
{
    $statement = $db->prepare(<<<'SQL'
SELECT 1 FROM subscriptions WHERE logo=:name OR logo_variant=:name
UNION ALL SELECT 1 FROM payment_methods WHERE icon=:name LIMIT 1
SQL);
    if (!$statement) throw new RuntimeException('logo_cleanup_query_failed');
    if (!$statement->bindValue(':name', $filename, SQLITE3_TEXT)) throw new RuntimeException('logo_cleanup_query_failed');
    $result = $statement->execute();
    if (!$result) throw new RuntimeException('logo_cleanup_query_failed');
    return (bool) $result->fetchArray(SQLITE3_ASSOC);
}

function dialRequireLogoCleanup(): void
{
    if (function_exists('deleteLogoFileIfUnused')) return;
    $path = __DIR__ . '/logo_cleanup.php';
    // Unit tests mount the Dial source at /app while the pinned upstream helper
    // remains in the image's /var/www/html tree.
    if (!is_file($path)) $path = '/var/www/html/includes/logo_cleanup.php';
    require_once $path;
}

/** Persist a failed postcommit cleanup for the next authenticated write. */
function dialQueueLogoCleanup(string $logosDir, string $filename): bool
{
    $queue = rtrim($logosDir, '/') . '/.dial-logo-cleanup-retry.jsonl';
    $line = json_encode(['filename'=>$filename, 'at'=>gmdate('c')], JSON_THROW_ON_ERROR) . "\n";
    $previousUmask = umask(0077);
    try {
        $written = @file_put_contents($queue, $line, FILE_APPEND | LOCK_EX);
    } finally {
        umask($previousUmask);
    }
    if ($written === false) {
        error_log('Dial logo cleanup retry queue could not be written for ' . $filename);
        return false;
    }
    @chmod($queue, 0600);
    return true;
}

/** Failed unlink must not change a committed row. */
function dialCleanupPreviousLogoFiles(
    SQLite3 $db, array $oldFiles, string $logosDir, ?callable $delete = null
): array {
    $delete ??= static function(SQLite3 $db, string $filename, string $dir): void {
        dialRequireLogoCleanup();
        deleteLogoFileIfUnused($db, $filename, $dir);
    };
    $retryCount = 0;
    $unscheduledCount = 0;
    foreach (array_unique($oldFiles) as $oldFile) {
        $filename = basename((string) $oldFile);
        if ($filename === '' || $filename === '.' || $filename === '..') continue;
        $path = rtrim($logosDir, '/') . '/' . $filename;
        if (!is_file($path)) continue;
        try {
            if (dialLogoIsReferenced($db, $filename)) continue;
            $delete($db, $filename, $logosDir);
            if (!is_file($path)) continue;
        } catch (Throwable $error) {
            error_log('Dial logo cleanup failed for ' . $filename . ': ' . $error->getMessage());
        }
        if (dialQueueLogoCleanup($logosDir, $filename)) $retryCount++;
        else $unscheduledCount++;
    }
    return ['retry_count'=>$retryCount, 'unscheduled_count'=>$unscheduledCount];
}

/** Retry deferred cleanup without touching files still referenced by a clone. */
function dialRetryLogoCleanup(SQLite3 $db, string $logosDir): void
{
    $queue = rtrim($logosDir, '/') . '/.dial-logo-cleanup-retry.jsonl';
    if (!is_file($queue)) return;
    $handle = @fopen($queue, 'c+');
    if (!$handle) return;
    try {
        if (!flock($handle, LOCK_EX)) return;
        rewind($handle);
        $lines = stream_get_contents($handle) ?: '';
        $remaining = [];
        foreach (explode("\n", trim($lines)) as $line) {
            if ($line === '') continue;
            $record = json_decode($line, true);
            $filename = is_array($record) ? ($record['filename'] ?? '') : '';
            if (!is_string($filename) || $filename === '' || basename($filename) !== $filename) continue;
            $path = rtrim($logosDir, '/') . '/' . $filename;
            if (!is_file($path) || dialLogoIsReferenced($db, $filename)) continue;
            try {
                dialRequireLogoCleanup();
                deleteLogoFileIfUnused($db, $filename, $logosDir);
            } catch (Throwable $error) {
                error_log('Dial logo retry failed for ' . $filename . ': ' . $error->getMessage());
            }
            if (is_file($path)) $remaining[] = $line;
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $remaining ? implode("\n", $remaining) . "\n" : '');
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function dialCloneReferenceOwned(SQLite3 $db, string $table, int $userId, $id): bool
{
    if ($id === null || $id === '') return true;
    if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id <= 0) return false;
    $sql = match($table) {
        'currencies', 'categories', 'household', 'subscriptions' =>
            "SELECT id FROM $table WHERE id=:id AND user_id=:user",
        'payment_methods' => 'SELECT id FROM payment_methods WHERE id=:id AND (user_id=:user OR user_id=0 OR user_id IS NULL)',
        default => throw new InvalidArgumentException('clone_reference_invalid'),
    };
    return dialIconOne($db, $sql, [':id'=>(int) $id, ':user'=>$userId]) !== null;
}

function dialCloneSubscriptionWithIcon(SQLite3 $db, int $userId, int $sourceId, ?callable $fault = null): int
{
    if (!@$db->exec('BEGIN IMMEDIATE')) throw new RuntimeException('subscription_transaction_failed');
    try {
        $source = dialIconOne($db,
            'SELECT * FROM subscriptions WHERE id=:id AND user_id=:user',
            [':id'=>$sourceId, ':user'=>$userId]);
        if (!$source) throw new InvalidArgumentException('subscription_not_owned');
        foreach ([
            'currency_id'=>'currencies', 'category_id'=>'categories',
            'payer_user_id'=>'household', 'payment_method_id'=>'payment_methods',
            'replacement_subscription_id'=>'subscriptions',
        ] as $column=>$table) {
            if (($column === 'currency_id' && empty($source[$column])) ||
                !dialCloneReferenceOwned($db, $table, $userId, $source[$column] ?? null)) {
                throw new InvalidArgumentException('clone_reference_not_owned');
            }
        }
        $columns = [
            'name','logo','logo_variant','logo_text_color','price','currency_id',
            'next_payment','auto_renew','start_date','cycle','frequency','notes',
            'payment_method_id','payer_user_id','category_id','notify','url','inactive',
            'notify_days_before','user_id','cancellation_date','replacement_subscription_id',
        ];
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO subscriptions (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $statement = $db->prepare($sql);
        if (!$statement) throw new RuntimeException('subscription_clone_failed');
        foreach ($columns as $column) {
            $value = $column === 'user_id' ? $userId : ($source[$column] ?? null);
            $type = $value === null ? SQLITE3_NULL : (is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT : SQLITE3_TEXT));
            if (!$statement->bindValue(':' . $column, $value, $type)) throw new RuntimeException('subscription_clone_failed');
        }
        if (!$statement->execute()) throw new RuntimeException('subscription_clone_failed');
        $id = (int) $db->lastInsertRowID();
        if ($id <= 0) throw new RuntimeException('subscription_clone_failed');
        $preference = dialIconGetPreference($db, $userId, $sourceId);
        if ($preference !== null) dialIconSavePreference($db, $userId, $id, $preference);
        if ($fault !== null) $fault('before_commit');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('subscription_commit_failed');
        return $id;
    } catch (Throwable $error) {
        @$db->exec('ROLLBACK');
        throw $error;
    }
}
