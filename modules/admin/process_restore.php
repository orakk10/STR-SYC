<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: admin_archive.php?status=error');
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: admin_archive.php?status=error');
    exit();
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();

    $student = $conn->prepare('SELECT * FROM archived_students WHERE id = ? FOR UPDATE');
    $student->execute([$id]);
    $row = $student->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $conn->rollBack();
        header('Location: admin_archive.php?status=error');
        exit();
    }

    $sectionId = $row['section_id'] !== null ? (int)$row['section_id'] : null;
    if ($sectionId !== null) {
        $sectionExists = $conn->prepare('SELECT id FROM sections WHERE id = ?');
        $sectionExists->execute([$sectionId]);
        if (!$sectionExists->fetchColumn()) {
            $sectionId = null;
        }
    }

    $existingUserId = false;
    if (!empty($row['original_id'])) {
        $existingUser = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $existingUser->execute([(int)$row['original_id']]);
        $existingUserId = $existingUser->fetchColumn();
    }

    if ($existingUserId !== false) {
        $restoreUser = $conn->prepare("
            UPDATE users
            SET lrn = :lrn, username = :username, full_name = :full_name,
                role = 'student', section_id = :section_id
            WHERE id = :id
        ");
        $restoreUser->execute([
            'lrn' => $row['lrn'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'section_id' => $sectionId,
            'id' => (int)$existingUserId
        ]);
    } else {
        $username = trim((string)($row['username'] ?: $row['lrn']));
        if ($username === '') {
            throw new RuntimeException('The archived record has no LRN or username to restore.');
        }

        $originalId = !empty($row['original_id']) ? (int)$row['original_id'] : null;
        $restoreUser = $conn->prepare("
            INSERT INTO users (id, lrn, username, full_name, role, section_id, password, reset_requested, require_reset)
            VALUES (:id, :lrn, :username, :full_name, 'student', :section_id, :password, 0, 1)
        ");
        $restoreUser->execute([
            'id' => $originalId,
            'lrn' => $row['lrn'],
            'username' => $username,
            'full_name' => $row['full_name'] ?: $username,
            'section_id' => $sectionId,
            'password' => password_hash($username, PASSWORD_DEFAULT)
        ]);

        $profileStmt = $conn->prepare("
            INSERT INTO student_profiles (user_id, gender)
            VALUES (:user_id, 'N/A')
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");
        $profileStmt->execute(['user_id' => $originalId ?: (int)$conn->lastInsertId()]);
    }

    $deleteArchive = $conn->prepare('DELETE FROM archived_students WHERE id = ?');
    $deleteArchive->execute([$id]);

    $conn->commit();
    header('Location: admin_archive.php?status=restored');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Student restore failed: ' . $e->getMessage());
    header('Location: admin_archive.php?status=error');
    exit();
}
