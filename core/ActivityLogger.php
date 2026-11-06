<?php

class ActivityLogger {
    /**
     * Record a system activity or audit log.
     *
     * @param PDO         $conn          Active database connection
     * @param int         $userId        ID of the user performing the action
     * @param string      $action        Description or identifier of the action (e.g., 'UPDATE_GRADE')
     * @param string|null $affectedTable Name of the table being modified (optional)
     * @param mixed       $details       Array or string of extra details to store as JSON (optional)
     * @return bool                      Returns true on success, false on failure
     */
    public static function log($conn, int $userId, string $action, ?string $affectedTable = null, $details = null): bool {
        try {
            $jsonDetails = null;
            if ($details !== null) {
                // If an array or object is passed, convert it to JSON
                $jsonDetails = is_array($details) || is_object($details) 
                    ? json_encode($details) 
                    : (string) $details;
            }

            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, affected_table, details, created_at)
                VALUES (:user_id, :action, :table, :details, NOW())
            ");

            return $stmt->execute([
                'user_id' => $userId,
                'action'  => $action,
                'table'   => $affectedTable,
                'details' => $jsonDetails
            ]);

        } catch (Exception $e) {
            // Log error internally to prevent breaking the main request workflow
            error_log("ActivityLogger Error: " . $e->getMessage());
            return false;
        }
    }
}