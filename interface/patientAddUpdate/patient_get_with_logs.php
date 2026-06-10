<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log all errors to a file
$log_file = __DIR__ . '/patient_get_logs.txt';
ini_set('error_log', $log_file);

// Start logging
$log_entries = [];

function log_msg($msg) {
    global $log_file, $log_entries;
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $msg";
    $log_entries[] = $entry;
    file_put_contents($log_file, $entry . "\n", FILE_APPEND);
}

log_msg("=== REQUEST STARTED ===");
log_msg("PID Parameter: " . (isset($_GET['pid']) ? $_GET['pid'] : 'NOT SET'));
log_msg("Method: " . $_SERVER['REQUEST_METHOD']);

// Get PID
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
log_msg("Converted PID to intval: $pid");

if ($pid <= 0) {
    log_msg("ERROR: Invalid PID ($pid)");
    echo json_encode(['success' => false, 'error' => 'Invalid PID', 'logs' => $log_entries]);
    exit;
}

// Function to clean invalid UTF-8 characters
function cleanUtf8($data) {
    if (is_array($data)) {
        $clean = [];
        foreach ($data as $key => $value) {
            $clean[$key] = cleanUtf8($value);
        }
        return $clean;
    } elseif (is_string($data)) {
        return @iconv('UTF-8', 'UTF-8//IGNORE', $data);
    }
    return $data;
}

// Connect to database
log_msg("Connecting to database...");
$db = new mysqli('localhost', 'root', '', 'synaptaemr');

if ($db->connect_error) {
    $error_msg = "Connection failed: " . $db->connect_error;
    log_msg("ERROR: $error_msg");
    echo json_encode(['success' => false, 'error' => $error_msg, 'logs' => $log_entries]);
    exit;
}

log_msg("Database connected successfully");
$db->set_charset("utf8mb4");
log_msg("Charset set to utf8mb4");

// Initialize response
$response = [
    'success' => true,
    'pid' => $pid,
    'patient_data' => null,
    'employer_data' => null,
    'history_data' => null,
    'lists_data' => [],
    'insurance_data' => [],
    'chief_complaint_data' => null
];

try {
    // 1. Get patient data (REQUIRED)
    log_msg("Fetching patient_data for pid=$pid...");
    $result = $db->query("SELECT * FROM patient_data WHERE pid = " . intval($pid));
    if (!$result) {
        throw new Exception("Query failed: " . $db->error);
    }
    log_msg("Query executed, rows: " . $result->num_rows);
    if ($result && $result->num_rows > 0) {
        $response['patient_data'] = cleanUtf8($result->fetch_assoc());
        log_msg("Patient data fetched successfully");
    } else {
        log_msg("No patient data found for pid=$pid");
    }

    // 2. Get employer data (OPTIONAL)
    log_msg("Fetching employer_data for pid=$pid...");
    $result = $db->query("SELECT * FROM employer_data WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result) {
        if ($result->num_rows > 0) {
            $response['employer_data'] = cleanUtf8($result->fetch_assoc());
            log_msg("Employer data fetched successfully");
        } else {
            log_msg("No employer data found");
        }
    } else {
        log_msg("WARNING: employer_data query failed: " . $db->error);
    }

    // 3. Get history data (OPTIONAL)
    log_msg("Fetching history_data for pid=$pid...");
    $result = $db->query("SELECT * FROM history_data WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result) {
        if ($result->num_rows > 0) {
            $response['history_data'] = cleanUtf8($result->fetch_assoc());
            log_msg("History data fetched successfully");
        } else {
            log_msg("No history data found");
        }
    } else {
        log_msg("WARNING: history_data query failed: " . $db->error);
    }

    // 4. Get lists data (OPTIONAL) - organize by type
    log_msg("Fetching lists for pid=$pid...");
    $lists_organized = [
        'medications' => [],
        'allergies_drug' => [],
        'allergies_food' => [],
        'surgeries' => [],
        'family_history' => [],
        'conditions' => []
    ];

    $result = $db->query("SELECT * FROM lists WHERE pid = " . intval($pid));
    if ($result) {
        $list_count = $result->num_rows;
        log_msg("Lists query returned $list_count rows");
        while ($row = $result->fetch_assoc()) {
            $row = cleanUtf8($row);
            $type = isset($row['type']) ? strtolower($row['type']) : 'medication';

            // Categorize by type
            if (strpos($type, 'drug') !== false || (strpos($type, 'allerg') !== false && strpos($type, 'food') === false)) {
                $lists_organized['allergies_drug'][] = $row;
            } elseif (strpos($type, 'allerg') !== false && strpos($type, 'food') !== false) {
                $lists_organized['allergies_food'][] = $row;
            } elseif (strpos($type, 'med') !== false) {
                $lists_organized['medications'][] = $row;
            } elseif (strpos($type, 'surg') !== false) {
                $lists_organized['surgeries'][] = $row;
            } elseif (strpos($type, 'family') !== false || strpos($type, 'fam') !== false) {
                $lists_organized['family_history'][] = $row;
            } elseif (strpos($type, 'cond') !== false) {
                $lists_organized['conditions'][] = $row;
            } else {
                $lists_organized['medications'][] = $row;
            }
        }
        log_msg("Lists organized: " . json_encode(array_map('count', $lists_organized)));
    } else {
        log_msg("WARNING: lists query failed: " . $db->error);
    }
    $response['lists_data'] = $lists_organized;

    // 5. Get insurance data (OPTIONAL)
    log_msg("Fetching insurance_data for pid=$pid...");
    $result = $db->query("SELECT * FROM insurance_data WHERE pid = " . intval($pid) . " ORDER BY insurance_sequence ASC");
    if ($result) {
        $ins_count = $result->num_rows;
        log_msg("Insurance query returned $ins_count rows");
        while ($row = $result->fetch_assoc()) {
            $response['insurance_data'][] = cleanUtf8($row);
        }
    } else {
        log_msg("WARNING: insurance_data query failed: " . $db->error);
    }

    // 6. Get chief complaint data (OPTIONAL)
    log_msg("Fetching patient_chief_complaint for pid=$pid...");
    $result = @$db->query("SELECT * FROM patient_chief_complaint WHERE pid = " . intval($pid) . " ORDER BY created_at DESC LIMIT 1");
    if ($result) {
        if ($result->num_rows > 0) {
            $response['chief_complaint_data'] = cleanUtf8($result->fetch_assoc());
            log_msg("Chief complaint data fetched successfully");
        } else {
            log_msg("No chief complaint data found");
        }
    } else {
        log_msg("WARNING: chief_complaint query failed: " . $db->error);
    }

    $db->close();
    log_msg("Database connection closed");

    $response['logs'] = $log_entries;
    log_msg("=== REQUEST COMPLETED SUCCESSFULLY ===");
    echo json_encode($response);

} catch (Exception $e) {
    $error_msg = $e->getMessage();
    log_msg("EXCEPTION: $error_msg");
    $db->close();
    echo json_encode([
        'success' => false,
        'error' => $error_msg,
        'logs' => $log_entries
    ]);
}
?>
