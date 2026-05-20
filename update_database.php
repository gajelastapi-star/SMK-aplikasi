<?php

declare(strict_types=1);

require_once __DIR__ . '/api/bootstrap.php';

try {
    $pdo = get_pdo();

    // Cek apakah tabel system_settings sudah ada
    $tables = $pdo->query("SHOW TABLES LIKE 'system_settings'")->fetchAll();
    if (!$tables) {
        echo "Membuat tabel system_settings...\n";

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS system_settings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        // Insert default settings
        $pdo->exec(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES
            ('allow_outside_absence', 'false')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        echo "Tabel system_settings berhasil dibuat.\n";
    } else {
        echo "Tabel system_settings sudah ada.\n";
    }

    // Test query untuk memastikan tabel berfungsi
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM system_settings');
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    echo "Pengaturan saat ini:\n";
    foreach ($settings as $key => $value) {
        echo "- $key: $value\n";
    }

    echo "\nDatabase update berhasil!\n";

} catch (Throwable $e) {
    echo 'Update gagal: ' . $e->getMessage() . "\n";
    exit(1);
}