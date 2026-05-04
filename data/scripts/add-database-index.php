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
 *       [--algorithm INPLACE] [--lock NONE]
 *
 * Usage (batch of indexes via JSON file, one ALTER per array element):
 *   php add-database-index.php --batch /path/to/indexes.json JSON format:
 *   [{"table":"value","index":"type","columns":"`type`"}, ...]
 *
 * Exit code 0 always (background script — non-zero would not surface).
 */

$opts = getopt('', ['table::', 'index::', 'columns::', 'algorithm::', 'lock::', 'batch::']);

openlog('omeka-common-add-index', LOG_PID | LOG_ODELAY, LOG_USER);

$fail = function (string $msg): void {
    syslog(LOG_ERR, $msg);
    closelog();
    exit(0);
};

$omekaPath = realpath(__DIR__ . '/../../../..');
if (!$omekaPath) {
    $fail('Cannot resolve Omeka root.');
}
$dbIni = $omekaPath . '/config/database.ini';
if (!is_readable($dbIni)) {
    $fail("Cannot read database config: $dbIni");
}

$db = parse_ini_file($dbIni);
if (!$db) {
    $fail("Cannot parse $dbIni");
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
    $fail('Database connection failed: ' . $e->getMessage());
}

// Build index list.
$indexes = [];
if (!empty($opts['batch'])) {
    if (!is_readable($opts['batch'])) {
        $fail('Batch file not readable: ' . $opts['batch']);
    }
    $json = file_get_contents($opts['batch']);
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        $fail('Batch file is not valid JSON: ' . $opts['batch']);
    }
    foreach ($decoded as $entry) {
        if (empty($entry['table']) || empty($entry['index']) || empty($entry['columns'])) {
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
    $fail('Missing args: pass --batch <file> or --table --index --columns.');
}

$algorithm = preg_replace('/[^A-Z]/', '', strtoupper($opts['algorithm'] ?? 'INPLACE'));
$lock = preg_replace('/[^A-Z]/', '', strtoupper($opts['lock'] ?? 'NONE'));

$added = [];
$skipped = [];
$failed = [];

foreach ($indexes as $idx) {
    $table = str_replace('`', '', (string) $idx['table']);
    $index = str_replace('`', '', (string) $idx['index']);
    $columns = (string) $idx['columns'];
    $ref = "$table/$index";

    try {
        $exists = $pdo->query(sprintf(
            "SHOW INDEX FROM `%s` WHERE `Key_name` = %s",
            $table,
            $pdo->quote($index)
        ))->fetchColumn();
    } catch (\Throwable $e) {
        $failed[] = $ref . ' (' . $e->getMessage() . ')';
        continue;
    }
    if ($exists) {
        $skipped[] = $ref;
        continue;
    }

    $sql = sprintf(
        'ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=%s, LOCK=%s',
        $table, $index, $columns, $algorithm, $lock
    );
    try {
        $pdo->exec($sql);
        $added[] = $ref;
    } catch (\Throwable $e) {
        try {
            $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
            $added[] = $ref;
        } catch (\Throwable $e2) {
            $failed[] = $ref . ' (' . $e2->getMessage() . ')';
        }
    }
}

// Cleanup batch file once processed.
if (!empty($opts['batch']) && is_writable($opts['batch'])) {
    @unlink($opts['batch']);
}

$summary = sprintf(
    'add-database-index: %d added, %d skipped, %d failed.',
    count($added),
    count($skipped),
    count($failed)
);
if ($failed) {
    syslog(LOG_ERR, $summary . ' Failed: ' . implode('; ', $failed));
} else {
    syslog(LOG_INFO, $summary . ($added ? ' Added: ' . implode(', ', $added) : ''));
}
closelog();

exit(0);
