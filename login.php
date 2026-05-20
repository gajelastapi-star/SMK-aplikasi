<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $username = normalize_username((string) ($payload['username'] ?? ''));
    $password = trim((string) ($payload['password'] ?? ''));

    if ($username === '' || $password === '') {
        json_response(['success' => false, 'message' => 'Username dan password wajib diisi.'], 422);
    }

    if ($username === normalize_username(ADMIN_USERNAME) && $password === ADMIN_PASSWORD) {
        $pdo = get_pdo();
        // ensure admin user exists in users table
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => ADMIN_USERNAME]);
        $admin = $stmt->fetch();
        if (!$admin) {
            $ins = $pdo->prepare('INSERT INTO users (username, password, name, role) VALUES (:username, :password, :name, :role)');
            $ins->execute([':username' => ADMIN_USERNAME, ':password' => ADMIN_PASSWORD, ':name' => 'PENGAWAS ADMIN', ':role' => 'admin']);
            $adminId = (int) $pdo->lastInsertId();
        } else {
            $adminId = (int) ($admin['id'] ?? $admin['ID'] ?? 0);
        }

        json_response([
            'success' => true,
            'user' => [
                'role' => 'guru',
                'id' => 'GURU-01',
                'userId' => $adminId,
                'username' => ADMIN_USERNAME,
                'name' => 'PENGAWAS ADMIN',
                'class' => 'STAF PENGAJAR',
                'jurusan' => 'ADMIN',
                'absenNo' => '-',
                'avatar' => 'char-live-admin',
                'photo' => '',
                'lastAttendancePhoto' => '',
                'bio' => 'DASHBOARD MONITORING SMKN 2 SRAGEN',
            ],
        ]);
    }

    $pdo = get_pdo();
    $student = fetch_student_by_username($pdo, $username);

    if (!$student || $student['password'] !== $password) {
        json_response(['success' => false, 'message' => 'Username atau password tidak valid.'], 401);
    }

    $session = lock_student_session($pdo, $student['student_id']);
    $student['active_session_token'] = $session['token'];

    // ensure users row and get id
    $user = fetch_or_create_user_for_student($pdo, $student);
    $userId = isset($user['id']) ? (int) $user['id'] : (int) ($user['ID'] ?? 0);

    $resp = format_student_user($student);
    $resp['userId'] = $userId;
    json_response([
        'success' => true,
        'user' => $resp,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
