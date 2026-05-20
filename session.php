<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $studentId = trim((string) ($_GET['studentId'] ?? ''));
    $token = trim((string) ($_GET['sessionToken'] ?? ''));
    $role = trim((string) ($_GET['role'] ?? ''));

    if ($role === 'guru') {
        json_response(['success' => true]);
    }

    if ($studentId === '' || $token === '') {
        json_response(['success' => false, 'message' => 'Sesi tidak lengkap.'], 401);
    }

    $pdo = get_pdo();
    $student = validate_student_session($pdo, $studentId, $token);
    if (!$student) {
      json_response(['success' => false, 'message' => 'Sesi sudah tidak aktif.'], 401);
    }

    $update = $pdo->prepare(
        'UPDATE students SET active_session_at = NOW()
         WHERE student_id = :student_id AND active_session_token = :token'
    );
    $update->execute([
        ':student_id' => $studentId,
        ':token' => $token,
    ]);

    // include userId mapping
    $user = fetch_user_by_student_id($pdo, $studentId);
    $resp = format_student_user($student);
    $resp['userId'] = $user ? (int) ($user['id'] ?? $user['ID'] ?? 0) : null;

    json_response([
        'success' => true,
        'user' => $resp,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
