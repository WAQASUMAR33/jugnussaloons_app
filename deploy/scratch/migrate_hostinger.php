<?php

// Load Laravel Bootstrap
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Set Hostinger MySQL Config
config([
    'database.default' => 'mysql',
    'database.connections.mysql.host' => '194.59.164.56',
    'database.connections.mysql.port' => '3306',
    'database.connections.mysql.database' => 'u312978252_jugnusaloon',
    'database.connections.mysql.username' => 'u312978252_jugnusaloon',
    'database.connections.mysql.password' => 'DildilPakistan786_786_waqas',
]);

echo "Running migrations on Hostinger MySQL database...\n";
$exitCode = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo \Illuminate\Support\Facades\Artisan::output();

echo "\nRunning database seeders on Hostinger MySQL database...\n";
$exitCodeSeed = \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
echo \Illuminate\Support\Facades\Artisan::output();

echo "\nHOSTINGER_MIGRATION_COMPLETE\n";
