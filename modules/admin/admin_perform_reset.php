<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function respond(int $status_code, array $response): void
{
    http_response_code($status_code);
    echo json_encode($response);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(401, ['status' => 'error', 'message' => 'Unauthorized.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    respond(403, ['status' => 'error', 'message' => 'Invalid request token. Refresh the page and try again.']);
}

$request = json_decode(file_get_contents('php://input'), true);
$user_id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
if ($user_id === false || $user_id === null || $user_id < 1) {
    respond(400, ['status' => 'error', 'message' => 'Invalid account.']);
}

$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
$temporary_password = '';
for ($i = 0, $length = strlen($alphabet); $i < 20; $i++) {
    $temporary_password .= $alphabet[random_int(0, $length - 1)];
}

require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();
$stmt = $conn->prepare("
    UPDATE users
    SET password = :password, reset_requested = 0, require_reset = 1
    WHERE id = :id AND role <> 'super_admin'
");
$stmt->execute([
    ':password' => password_hash($temporary_password, PASSWORD_DEFAULT),
    ':id' => $user_id,
]);

if ($stmt->rowCount() !== 1) {
    respond(404, ['status' => 'error', 'message' => 'Account not found or cannot be reset.']);
}

respond(200, [
    'status' => 'success',
    'temporary_password' => $temporary_password,
]);
