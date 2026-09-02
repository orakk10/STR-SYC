<?php
session_start();
require_once 'db_config.php';
if ($_SESSION['role'] !== 'admin') exit("Unauthorized");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Master_User_List.csv"');

$output = fopen('php://output', 'w');
// Column mapping
fputcsv($output, ['LRN_Username', 'Full_Name', 'Role', 'Section_ID']);

$query = "SELECT username, full_name, role, section_id FROM users WHERE role != 'admin'";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);