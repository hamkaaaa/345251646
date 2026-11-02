<?php

// Forward Vercel requests to Laravel application with serverless configuration

// 1. Prepare writable storage directories in /tmp for serverless environment
$dirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/bootstrap/cache',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 2. Setup SQLite database in /tmp
$tmpDb = '/tmp/database.sqlite';
$seedDb = dirname(__DIR__) . '/database/database.sqlite';

if (!file_exists($tmpDb) || filesize($tmpDb) === 0) {
    if (file_exists($seedDb) && filesize($seedDb) > 0) {
        copy($seedDb, $tmpDb);
    } else {
        touch($tmpDb);
    }
}

// 3. Set serverless environment defaults
$defaultEnv = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:XG8d2Lq3Z2U4r1O9Y7vF8tqR91ZkWm4jK3lT8hA2bC8=',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $tmpDb,
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'stderr',
    'ASSET_URL' => '/',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'APP_CONFIG_CACHE' => '/tmp/bootstrap/cache/config.php',
    'APP_EVENTS_CACHE' => '/tmp/bootstrap/cache/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/bootstrap/cache/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/bootstrap/cache/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/bootstrap/cache/services.php',
];

foreach ($defaultEnv as $key => $value) {
    if (!getenv($key)) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// 4. Register the Composer autoloader
require dirname(__DIR__) . '/vendor/autoload.php';

// 5. Bootstrap Laravel
/** @var \Illuminate\Foundation\Application $app */
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';

// Point storage to /tmp/storage
$app->useStoragePath('/tmp/storage');

// Auto-migrate if newly created empty database
if (filesize($tmpDb) === 0) {
    try {
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--force' => true]);
    } catch (\Throwable $e) {
        error_log('Database init warning: ' . $e->getMessage());
    }
}

// 6. Handle the incoming request
$app->handleRequest(\Illuminate\Http\Request::capture());
