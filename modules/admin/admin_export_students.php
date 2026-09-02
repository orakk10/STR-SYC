<?php
session_start();
require_once 'db_config.php';

if ($_SESSION['role'] !== 'admin') { exit("Unauthorized"); }

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Master_Student_List.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['LRN', 'Full_Name', 'Section_ID', 'Role']);


$query = "SELECT username, full_name, section_id, role FROM users WHERE role = 'student'";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);