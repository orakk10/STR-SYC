<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status_code, array $response): void
{
    http_response_code($status_code);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

$identifier = $_POST['username'] ?? '';
if (!is_string($identifier)) {
    respond(400, ['status' => 'error', 'message' => 'Enter a valid Username or LRN.']);
}

$identifier = trim($identifier);
if ($identifier === '' || strlen($identifier) > 50) {
    respond(400, ['status' => 'error', 'message' => 'Enter a valid Username or LRN.']);
}

require_once __DIR__ . '/../config/database.php';
$db = getDBConnection();
$stmt = $db->prepare("
    UPDATE users
    SET reset_requested = 1
    WHERE (username = :username OR lrn = :lrn)
      AND role <> 'super_admin'
");
$stmt->execute([
    'username' => $identifier,
    'lrn' => $identifier,
]);

respond(200, [
    'status' => 'success',
    'message' => 'If the account exists, its password reset request has been sent to an administrator.',
]);
