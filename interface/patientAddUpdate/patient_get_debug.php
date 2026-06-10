<?php
/**
 * Patient Data Retrieval - DEBUG VERSION
 * Shows exactly what's happening
 */

// Allow public access
$ignoreAuth = true;

// Set JSON response header
header('Content-Type: application/json');

// Show ALL errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Get PID from query parameter
    $pid = isset($_GET['pid']) ? intval($_GET['pid']) : null;

    if (empty($pid)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Patient ID (pid) is required']);
        exit;
    }

    // Direct database connection
    $db = new mysqli('localhost', 'root', '', 'synaptaemr');

    if ($db->connect_error) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'details' => $db->connect_error
        ]);
        exit;
    }

    $db->set_charset("utf8mb4");

    // FETCH PATIENT DATA
    $query = "SELECT * FROM patient_data WHERE pid = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Prepare failed',
            'details' => $db->error
        ]);
        exit;
    }

    $stmt->bind_param("i", $pid);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Execute failed',
            'details' => $stmt->error
        ]);
        exit;
    }

    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();

    if (!$patient_data) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Patient with ID $pid not found"
        ]);
        exit;
    }

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'pid' => $pid,
        'patient_data' => $patient_data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
