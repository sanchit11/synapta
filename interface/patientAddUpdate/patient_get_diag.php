<?php
header('Content-Type: application/json');

$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$diag = [];

// Step 1: Check PID
$diag[] = "PID: " . $pid;

if ($pid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid PID', 'debug' => $diag]);
    exit;
}

// Step 2: Connect
$db = new mysqli('localhost', 'root', '', 'synaptaemr');
$diag[] = "DB Connect: " . ($db->connect_error ? 'FAILED: ' . $db->connect_error : 'OK');

if ($db->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB Connection failed', 'debug' => $diag]);
    exit;
}

// Step 3: Query
$query = "SELECT * FROM patient_data WHERE pid = $pid";
$diag[] = "Query: $query";

$result = $db->query($query);
$diag[] = "Query Result: " . ($result ? 'OK' : 'FAILED: ' . $db->error);

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Query failed: ' . $db->error, 'debug' => $diag]);
    exit;
}

// Step 4: Fetch
$num_rows = $result->num_rows;
$diag[] = "Num rows: $num_rows";

if ($num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'Patient not found', 'debug' => $diag, 'num_rows' => $num_rows]);
    exit;
}

$patient_data = $result->fetch_assoc();
$diag[] = "Fetch: OK, got " . count($patient_data) . " fields";

$db->close();

// Step 5: Return
$output = [
    'success' => true,
    'pid' => $pid,
    'patient_data' => $patient_data,
    'debug' => $diag
];

echo json_encode($output);
?>
