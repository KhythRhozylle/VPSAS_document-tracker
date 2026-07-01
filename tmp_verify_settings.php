<?php
$dsn = 'mysql:host=127.0.0.1;port=3308;dbname=docu_tracker;charset=utf8mb4';
$user = 'docu_tracker_user';
$password = 'docu_tracker_password';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['campuses', 'document_types'] as $key) {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $key . ' => ' . ($row['setting_value'] ?? '(missing)') . "\n";
    }
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
