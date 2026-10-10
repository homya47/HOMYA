<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    $path = database_path('migrations/commerce/2026_10_09_000002_create_admin_tables.php');
    $db = DB::connection('commerce');
    if ($db->getDriverName() !== 'mariadb') {
        throw new RuntimeException;
    }
    $queries = $db->pretend(fn () => (require $path)->up());
    echo json_encode(['migration_sha256' => hash_file('sha256', $path), 'queries' => array_column($queries, 'query'), 'sql_executed' => false], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "SCHEMA_PLAN_REFUSED\n");
    exit(1);
}
