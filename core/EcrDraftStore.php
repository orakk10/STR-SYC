<?php

function ensureEcrDraftTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecr_score_drafts (
            user_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            term VARCHAR(10) NOT NULL,
            draft_data LONGTEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, assignment_id, term)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
