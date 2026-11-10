CREATE TABLE IF NOT EXISTS `resource_archives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_type` enum('strand','subject','section','assignment','schedule') NOT NULL,
  `resource_id` int(11) NOT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_resource_archives_resource` (`resource_type`,`resource_id`),
  KEY `fk_resource_archives_archived_by` (`archived_by`),
  CONSTRAINT `fk_resource_archives_archived_by`
    FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
