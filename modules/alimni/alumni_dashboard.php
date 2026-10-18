<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'alumni') {
    header("Location: ../../manifest/login.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT u.full_name, u.username, a.batch_year, st.strand_name, s.grade_level, s.section_name
    FROM users u
    LEFT JOIN archived_students a ON a.original_id = u.id
    LEFT JOIN strands st ON st.id = a.strand_id
    LEFT JOIN sections s ON s.id = a.section_id
    WHERE u.id = :user_id AND u.role = 'alumni'
    LIMIT 1
");
$stmt->execute([':user_id' => (int) $_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header("Location: ../../manifest/login.php");
    exit();
}

$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? 'N/A'), ENT_QUOTES, 'UTF-8');
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Portal | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .alumni-container {
            max-width: 600px;
            margin: 80px auto;
            padding: 0 20px;
        }

        .portal-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .portal-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .portal-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .portal-header p {
            opacity: 0.8;
            margin-top: 8px;
            font-size: 0.95rem;
        }

        .portal-body {
            padding: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #f1f5f9;
        }

        .info-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-value {
            display: block;
            font-size: 1.1rem;
            color: #1e293b;
            font-weight: 500;
        }

        .archive-notice {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            padding: 15px;
            border-radius: 8px;
            color: #92400e;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .btn-logout {
            display: block;
            width: 100%;
            text-align: center;
            background: #e2e8f0;
            color: #475569;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #cbd5e1;
            color: #1e293b;
        }

        .brand-footer {
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

    <div class="alumni-container">
        <div class="portal-card">
            <header class="portal-header">
                <h1>Welcome, <?php echo $escape($user_data['full_name']); ?>!</h1>
                <p>Alumni Account Access</p>
            </header>

            <div class="portal-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Graduation Year</span>
                        <span class="info-value"><?php echo $escape($user_data['batch_year']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo $escape($user_data['username']); ?></span>
                    </div>
                    <?php if (!empty($user_data['strand_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">Strand</span>
                            <span class="info-value"><?php echo $escape($user_data['strand_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($user_data['section_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">Last Section</span>
                            <span class="info-value"><?php echo $escape($user_data['section_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="archive-notice">
                    <strong>Note:</strong> Your account is currently in <strong>Archive Mode</strong>. Profile modifications are restricted. For official transcript requests or record corrections, please contact the School Registrar.
                </div>

                <a href="../../manifest/logout.php" class="btn-logout">Secure Logout</a>
            </div>
        </div>
        
        <div class="brand-footer">
            STRAND-SYNC | Student Record Management System
        </div>
    </div>

</body>
</html>