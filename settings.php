<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // GET - Ambil pengaturan
    if ($method === 'GET') {
        $pdo = get_pdo();
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM system_settings');
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        json_response([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    // POST - Update pengaturan
    if ($method === 'POST') {
        $role = trim((string) ($_GET['role'] ?? ''));
        if ($role !== 'guru') {
            json_response(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $settingKey = trim((string) ($payload['setting_key'] ?? ''));
        $settingValue = trim((string) ($payload['setting_value'] ?? ''));

        if ($settingKey === '') {
            json_response(['success' => false, 'message' => 'Setting key wajib diisi.'], 422);
        }

        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = :value'
        );
        $stmt->execute([
            ':key' => $settingKey,
            ':value' => $settingValue,
        ]);

        json_response([
            'success' => true,
            'message' => 'Pengaturan berhasil disimpan.',
        ]);
    }

    json_response(['success' => false, 'message' => 'Method tidak didukung.'], 405);

} catch (PDOException $e) {
    error_log('Database error in settings.php: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Kesalahan database: ' . $e->getMessage()], 500);
} catch (Throwable $e) {
    error_log('Settings API Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);