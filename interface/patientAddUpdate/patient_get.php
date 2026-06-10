<?php
$ignoreAuth = true;
require(__DIR__.'/../globals.php');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

// Get PID
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;

if ($pid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid PID']);
    exit;
}

// Connect to database
$db = $GLOBALS['dbh'] ?? null;

if ($db->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $db->connect_error]);
    exit;
}

$db->set_charset("utf8mb4");

// Initialize response
$response = [
    'success' => true,
    'pid' => $pid,
    'patient_data' => null,
    'employer_data' => null,
    'history_data' => null,
    'lists_data' => [],
    'social_history_data' => null,
    'insurance_data' => [],
    'chief_complaint_data' => null,
    'choices_preferences_data' => null,
    'demographics_social_data' => null,
    'consent_authorization_data' => null
];

try {
    // 1. Get patient data (REQUIRED)
    $result = $db->query("SELECT * FROM patient_data WHERE pid = " . intval($pid));
    if ($result && $result->num_rows > 0) {
        $response['patient_data'] = cleanUtf8($result->fetch_assoc());
    }

    // 2. Get employer data (OPTIONAL)
    $result = $db->query("SELECT * FROM employer_data WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['employer_data'] = cleanUtf8($result->fetch_assoc());
    }

    // 3. Get history data (OPTIONAL)
    $result = $db->query("SELECT * FROM history_data WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['history_data'] = cleanUtf8($result->fetch_assoc());
    }

    // 4. Get social history data (OPTIONAL) - NEW CODE
    $result = @$db->query("SELECT * FROM patient_social_history WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['social_history_data'] = cleanUtf8($result->fetch_assoc());

        
    }

    // 4. Get lists data (OPTIONAL) - organize by type
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
        while ($row = $result->fetch_assoc()) {
            $row = cleanUtf8($row);
            $type = isset($row['type']) ? strtolower($row['type']) : 'medication';

            // Categorize by type
            if ($type === 'drug_allergy' || strpos($type, 'drug') !== false) {
                $lists_organized['allergies_drug'][] = $row;
            } elseif ($type === 'food_allergy' || (strpos($type, 'allerg') !== false && strpos($type, 'food') !== false)) {
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
    }
    $response['lists_data'] = $lists_organized;

    // 5. Get insurance data (OPTIONAL)
    $result = $db->query("SELECT * FROM insurance_data WHERE pid = " . intval($pid) . " ORDER BY insurance_sequence ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response['insurance_data'][] = cleanUtf8($row);
        }
    }

    // 6. Get chief complaint data (OPTIONAL)
    $result = @$db->query("SELECT * FROM patient_chief_complaint WHERE pid = " . intval($pid) . " ORDER BY created_at DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['chief_complaint_data'] = cleanUtf8($result->fetch_assoc());
    }

    // 7. Get choices & preferences data (OPTIONAL)
    $result = @$db->query("SELECT * FROM patient_choices_preferences WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['choices_preferences_data'] = cleanUtf8($result->fetch_assoc());
    }

    // 8. Get demographics & social data (OPTIONAL)
    $result = @$db->query("SELECT * FROM patient_demographics_social WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['demographics_social_data'] = cleanUtf8($result->fetch_assoc());
    }

    // 9. Get consent and authorization data (OPTIONAL)
    $result = @$db->query("SELECT * FROM patient_consent_authorization WHERE pid = " . intval($pid) . " LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $response['consent_authorization_data'] = cleanUtf8($result->fetch_assoc());
    }

    $db->close();

    echo json_encode($response);

} catch (Exception $e) {
    $db->close();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
