<?php

function ensurePromotionVerificationTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecr_promotion_verifications_by_assignment (
            student_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            verified_by BIGINT UNSIGNED NOT NULL,
            verified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, assignment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
