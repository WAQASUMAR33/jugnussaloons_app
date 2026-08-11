<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config([
    'database.default' => 'mysql',
    'database.connections.mysql.host' => '194.59.164.56',
    'database.connections.mysql.port' => '3306',
    'database.connections.mysql.database' => 'u312978252_jugnusaloon',
    'database.connections.mysql.username' => 'u312978252_jugnusaloon',
    'database.connections.mysql.password' => 'DildilPakistan786_786_waqas',
]);

echo "Seeding Hostinger database...\n";
\Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
echo \Illuminate\Support\Facades\Artisan::output() . "\n";

$usersCount = \App\Models\User::count();
$rolesCount = \App\Models\Role::count();

echo "SEEDED_SUCCESSFULLY: Users={$usersCount}, Roles={$rolesCount}\n";
