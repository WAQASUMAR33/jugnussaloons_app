<?php
try {
    $pdo = new PDO(
        'mysql:host=194.59.164.56;port=3306;dbname=u312978252_jugnusaloon',
        'u312978252_jugnusaloon',
        'DildilPakistan786_786_waqas',
        [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
    echo "CONNECTED_SUCCESSFULLY\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables count: " . count($tables) . "\n";
    print_r($tables);
} catch (Exception $e) {
    echo "CONNECTION_ERROR: " . $e->getMessage() . "\n";
}
