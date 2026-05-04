<?php
/**
 * Standalone PHP script that adds database indexes in background.
 *
 * Designed to be spawned via shell `&` from the parent Omeka request so the
 * caller (typically long ALTER TABLE statements on large tables) does not block
 * the admin upgrade page.
 *
 * Self-contained: no Omeka bootstrap, no Composer autoload, no module autoload
 * race. Reads config/database.ini directly and uses PDO.
 *
 * Usage (single index):
 *   php add-database-index.php \
 *       --table value \
 *       --index type \
 *       --columns "`type`" \
 *       [--algorithm INPLACE] [--lock NONE] [--log /path/to/log]
 *
 * Usage (batch of indexes via JSON file, one ALTER per array element):
 *   php add-database-index.php --batch /path/to/indexes.json [--log ...] JSON
 *   format: [{"table":"value","index":"type","columns":"`type`"}, ...]
 *
 * Exit code 0 always (background script — non-zero would not surface). Status
 * is appended to the log file.
 */

$opts = getopt('', ['table::', 'index::', 'columns::', 'algorithm::', 'lock::', 'log::', 'batch::']);

$omekaPath = realpath(__DIR__ . '/../../../..');
if (!$omekaPath) {
    fwrite(STDERR, "Cannot resolve Omeka root from " . __DIR__ . "\n");
    exit(0);
}
$dbIni = $omekaPath . '/config/database.ini';
if (!is_readable($dbIni)) {
    fwrite(STDERR, "Cannot read database config: $dbIni\n");
    exit(0);
}
$logFile = $opts['log'] ?? ($omekaPath . '/logs/common-add-index.log');

$logLine = function (string $msg) use ($logFile): void {
    $line = sprintf("[%s] [pid %d] %s\n", date('Y-m-d H:i:s'), getmypid(), $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$db = parse_ini_file($dbIni);
if (!$db) {
    $logLine("Cannot parse $dbIni");
    exit(0);
}

if (!empty($db['unix_socket'])) {
    $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $db['unix_socket'], $db['dbname'] ?? '');
} else {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['dbname'] ?? '');
    if (!empty($db['port'])) {
        $dsn .= ';port=' . $db['port'];
    }
}

try {
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\Throwable $e) {
    $logLine("Connection failed: " . $e->getMessage());
    exit(0);
}

// Build index list.
$indexes = [];
if (!empty($opts['batch'])) {
    if (!is_readable($opts['batch'])) {
        $logLine("Batch file not readable: " . $opts['batch']);
        exit(0);
    }
    $json = file_get_contents($opts['batch']);
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        $logLine("Batch file is not valid JSON: " . $opts['batch']);
        exit(0);
    }
    foreach ($decoded as $entry) {
        if (empty($entry['table']) || empty($entry['index']) || empty($entry['columns'])) {
            $logLine("Skipping malformed batch entry: " . json_encode($entry));
            continue;
        }
        $indexes[] = $entry;
    }
} elseif (!empty($opts['table']) && !empty($opts['index']) && !empty($opts['columns'])) {
    $indexes[] = [
        'table' => $opts['table'],
        'index' => $opts['index'],
        'columns' => $opts['columns'],
    ];
} else {
    $logLine("Missing args: pass --batch <file> or --table --index --columns.");
    exit(0);
}

$algorithm = preg_replace('/[^A-Z]/', '', strtoupper($opts['algorithm'] ?? 'INPLACE'));
$lock = preg_replace('/[^A-Z]/', '', strtoupper($opts['lock'] ?? 'NONE'));

foreach ($indexes as $idx) {
    $table = str_replace('`', '', (string) $idx['table']);
    $index = str_replace('`', '', (string) $idx['index']);
    $columns = (string) $idx['columns'];

    try {
        $exists = $pdo->query(sprintf(
            "SHOW INDEX FROM `%s` WHERE `Key_name` = %s",
            $table,
            $pdo->quote($index)
        ))->fetchColumn();
    } catch (\Throwable $e) {
        $logLine("Pre-check failed for `$table`.`$index`: " . $e->getMessage());
        continue;
    }
    if ($exists) {
        $logLine("Index `$index` on `$table` already exists, skipping.");
        continue;
    }

    $sql = sprintf(
        'ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=%s, LOCK=%s',
        $table, $index, $columns, $algorithm, $lock
    );
    $logLine("Starting: $sql");
    $start = microtime(true);
    try {
        $pdo->exec($sql);
        $logLine(sprintf("Done `%s`.`%s` in %.2fs.", $table, $index, microtime(true) - $start));
    } catch (\Throwable $e) {
        $logLine("Online DDL failed for `$table`.`$index` (" . $e->getMessage() . "), retrying without ALGORITHM/LOCK.");
        try {
            $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
            $logLine(sprintf("Done `%s`.`%s` (no online DDL) in %.2fs.", $table, $index, microtime(true) - $start));
        } catch (\Throwable $e2) {
            $logLine("Failed `$table`.`$index`: " . $e2->getMessage());
        }
    }
}

// Cleanup batch file once processed.
if (!empty($opts['batch']) && is_writable($opts['batch'])) {
    @unlink($opts['batch']);
}

exit(0);
