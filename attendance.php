<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $role = trim((string) ($_GET['role'] ?? ''));
        $pdo = get_pdo();
        if ($role === 'guru' || $role === 'admin') {
            $stmt = $pdo->query('SELECT * FROM attendance_reports ORDER BY timestamp DESC LIMIT 80');
            $rows = $stmt->fetchAll();
        } elseif ($role === 'siswa') {
            $studentId = trim((string) ($_GET['studentId'] ?? ''));
            if ($studentId === '') {
                json_response(['success' => false, 'message' => 'Student ID wajib diisi.'], 422);
            }
            $stmt = $pdo->prepare('SELECT * FROM attendance_reports WHERE student_id = :student_id ORDER BY timestamp DESC LIMIT 80');
            $stmt->execute([':student_id' => $studentId]);
            $rows = $stmt->fetchAll();
        } else {
            json_response(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }
        $reports = array_map(static function (array $row): array {
            return [
                'studentId' => $row['student_id'],
                'name' => $row['name'],
                'class' => $row['class_name'],
                'jurusan' => $row['jurusan'],
                'absenNo' => $row['absen_no'],
                'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
                'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
                'photo' => $row['photo'] ?? '',
                'status' => $row['status'],
                'activeUntil' => $row['active_until'] !== null ? (int) $row['active_until'] : null,
                'timestamp' => (int) $row['timestamp'],
            ];
        }, $rows);

        $stmt = $pdo->query(
            'SELECT student_id FROM students
             WHERE active_session_at >= NOW() - INTERVAL 120 SECOND'
        );
        $onlineStudentIds = array_map(
            static fn(array $row): string => (string) $row['student_id'],
            $stmt->fetchAll()
        );

        json_response([
            'success' => true,
            'reports' => $reports,
            'onlineStudentIds' => $onlineStudentIds,
        ]);
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $studentId = trim((string) ($payload['studentId'] ?? ''));
        $token = trim((string) ($payload['sessionToken'] ?? ''));
        if ($studentId === '') {
            json_response(['success' => false, 'message' => 'Student ID wajib diisi.'], 422);
        }

        $pdo = get_pdo();
        $student = null;
        if ($token !== '') {
            $student = validate_student_session($pdo, $studentId, $token);
        }

        if (!$student) {
            $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :student_id LIMIT 1');
            $stmt->execute([':student_id' => $studentId]);
            $student = $stmt->fetch();
        }

        if (!$student) {
            json_response(['success' => false, 'message' => 'Sesi pengguna tidak valid atau data siswa tidak ditemukan.'], 401);
        }

        // Ensure corresponding users row exists and attach its id
        $user = fetch_or_create_user_for_student($pdo, $student);
        $userId = isset($user['id']) ? (int) $user['id'] : (int) ($user['ID'] ?? 0);

        $name = trim((string) ($payload['name'] ?? $student['name'] ?? ''));
        $className = trim((string) ($payload['class'] ?? $student['class_name'] ?? ''));
        $jurusan = trim((string) ($payload['jurusan'] ?? $student['jurusan'] ?? ''));
        $absenNo = trim((string) ($payload['absenNo'] ?? $student['absen_no'] ?? ''));
        $lat = is_numeric($payload['lat'] ?? null) ? (float) $payload['lat'] : null;
        $lng = is_numeric($payload['lng'] ?? null) ? (float) $payload['lng'] : null;
        $photo = trim((string) ($payload['photo'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'ACTIVE')) ?: 'ACTIVE';
        $activeUntil = is_numeric($payload['activeUntil'] ?? null) ? (int) $payload['activeUntil'] : null;
        $timestamp = is_numeric($payload['timestamp'] ?? null) ? (int) $payload['timestamp'] : time() * 1000;

        $stmt = $pdo->prepare(
            'INSERT INTO attendance_reports (
                student_id,
                user_id,
                absen_no,
                name,
                class_name,
                jurusan,
                lat,
                lng,
                photo,
                status,
                active_until,
                timestamp
            ) VALUES (
                :student_id,
                :user_id,
                :absen_no,
                :name,
                :class_name,
                :jurusan,
                :lat,
                :lng,
                :photo,
                :status,
                :active_until,
                :timestamp
            ) ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                absen_no = VALUES(absen_no),
                name = VALUES(name),
                class_name = VALUES(class_name),
                jurusan = VALUES(jurusan),
                lat = VALUES(lat),
                lng = VALUES(lng),
                photo = VALUES(photo),
                status = VALUES(status),
                active_until = VALUES(active_until),
                timestamp = VALUES(timestamp)'
        );

        $stmt->execute([
            ':student_id' => $studentId,
            ':user_id' => $userId > 0 ? $userId : null,
            ':absen_no' => $absenNo,
            ':name' => $name,
            ':class_name' => $className,
            ':jurusan' => $jurusan,
            ':lat' => $lat,
            ':lng' => $lng,
            ':photo' => $photo,
            ':status' => $status,
            ':active_until' => $activeUntil,
            ':timestamp' => $timestamp,
        ]);

        json_response(['success' => true]);
    }

    json_response(['success' => false, 'message' => 'Metode tidak didukung.'], 405);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
