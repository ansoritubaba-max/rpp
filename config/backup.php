<?php

return [
    'mysql_dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_dump_timeout_seconds' => (int) env('MYSQLDUMP_TIMEOUT_SECONDS', 300),
    'mysql_client_binary' => env('MYSQL_CLIENT_BINARY', 'mysql'),
    'mysql_restore_timeout_seconds' => (int) env('MYSQL_RESTORE_TIMEOUT_SECONDS', 1800),
    'max_uncompressed_bytes' => (int) env('BACKUP_MAX_UNCOMPRESSED_BYTES', 2147483648),
];
