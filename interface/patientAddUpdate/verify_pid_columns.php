<?php
/**
 * PID Column Verification Script
 * Checks if PID (patient ID) column exists in all patient-related tables
 * This ensures referential integrity for patient data queries
 */

$db = new mysqli('localhost', 'root', 'root', 'synaptaemr');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$db->set_charset("utf8mb4");

// Tables that should have PID column
$expected_tables = [
    'patient_data' => 'Primary patient demographics',
    'employer_data' => 'Employment information',
    'history_data' => 'Medical/social history',
    'lists' => 'Medical data (allergies, medications, conditions)',
    'patient_chief_complaint' => 'Chief complaint and symptoms',
    'insurance_data' => 'Insurance information'
];

echo "============================================================\n";
echo "PID COLUMN VERIFICATION REPORT\n";
echo "============================================================\n\n";

$all_good = true;
$results = [];

foreach ($expected_tables as $table => $description) {
    echo "Checking: $table ($description)\n";
    echo "---\n";

    // Check if table exists
    $check_table = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = 'synaptaemr'
                    AND TABLE_NAME = '$table'";
    $table_result = $db->query($check_table);

    if ($table_result->num_rows == 0) {
        echo "❌ TABLE DOES NOT EXIST\n\n";
        $all_good = false;
        continue;
    }

    // Check if PID column exists
    $check_pid = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = 'synaptaemr'
                  AND TABLE_NAME = '$table'
                  AND COLUMN_NAME = 'pid'";

    $result = $db->query($check_pid);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ PID column exists\n";
        echo "   Type: " . $row['COLUMN_TYPE'] . "\n";
        echo "   Nullable: " . ($row['IS_NULLABLE'] == 'YES' ? 'Yes' : 'No') . "\n";
        echo "   Index: " . ($row['COLUMN_KEY'] ? 'Yes (' . $row['COLUMN_KEY'] . ')' : 'No') . "\n";

        $results[$table] = [
            'exists' => true,
            'type' => $row['COLUMN_TYPE'],
            'nullable' => $row['IS_NULLABLE'],
            'index' => $row['COLUMN_KEY']
        ];
    } else {
        echo "❌ PID column MISSING\n";
        $all_good = false;
        $results[$table] = ['exists' => false];
    }

    // Check for any index on PID
    $check_index = "SELECT INDEX_NAME, SEQ_IN_INDEX
                    FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = 'synaptaemr'
                    AND TABLE_NAME = '$table'
                    AND COLUMN_NAME = 'pid'";

    $index_result = $db->query($check_index);
    if ($index_result->num_rows > 0) {
        echo "   ✅ Index exists on PID\n";
    } else {
        echo "   ⚠️  No index on PID (performance warning)\n";
    }

    echo "\n";
}

echo "============================================================\n";
echo "SUMMARY\n";
echo "============================================================\n\n";

if ($all_good) {
    echo "✅ ALL CHECKS PASSED\n\n";
    echo "All patient-related tables have PID columns.\n";
    echo "You can now query patient data like:\n\n";
    echo "-- Get all allergies for a patient\n";
    echo "SELECT * FROM lists WHERE pid = 123 AND type = 'allergy';\n\n";
    echo "-- Get all medications for a patient\n";
    echo "SELECT * FROM lists WHERE pid = 123 AND type = 'medication';\n\n";
    echo "-- Get patient's chief complaint\n";
    echo "SELECT * FROM patient_chief_complaint WHERE pid = 123;\n\n";
    echo "-- Get patient's employment history\n";
    echo "SELECT * FROM employer_data WHERE pid = 123;\n\n";
    echo "-- Get patient's medical history\n";
    echo "SELECT * FROM history_data WHERE pid = 123;\n\n";
} else {
    echo "❌ SOME CHECKS FAILED\n\n";
    echo "Missing PID columns detected. Run migrations to add them.\n";
}

echo "============================================================\n";
echo "DETAILED RESULTS\n";
echo "============================================================\n\n";

foreach ($results as $table => $info) {
    echo "$table: ";
    if ($info['exists']) {
        echo "✅ (Type: " . $info['type'] . ", Index: " . ($info['index'] ?: 'None') . ")\n";
    } else {
        echo "❌ (PID column missing)\n";
    }
}

// Now show sample queries
echo "\n============================================================\n";
echo "SAMPLE QUERIES FOR PATIENT DATA RETRIEVAL\n";
echo "============================================================\n\n";

echo "1. GET COMPLETE PATIENT PROFILE:\n";
echo "---\n";
echo "SELECT \n";
echo "    pd.pid, pd.fname, pd.lname, pd.DOB, pd.email, pd.phone_cell,\n";
echo "    (SELECT GROUP_CONCAT(title SEPARATOR ', ') FROM lists WHERE pid = pd.pid AND type = 'medical_problem') as conditions,\n";
echo "    (SELECT GROUP_CONCAT(title SEPARATOR ', ') FROM lists WHERE pid = pd.pid AND type = 'allergy') as allergies,\n";
echo "    (SELECT GROUP_CONCAT(title SEPARATOR ', ') FROM lists WHERE pid = pd.pid AND type = 'medication') as medications\n";
echo "FROM patient_data pd\n";
echo "WHERE pd.pid = 123;\n\n";

echo "2. GET PATIENT'S ALLERGIES:\n";
echo "---\n";
echo "SELECT title, reaction, severity_al FROM lists \n";
echo "WHERE pid = 123 AND type = 'allergy';\n\n";

echo "3. GET PATIENT'S MEDICATIONS:\n";
echo "---\n";
echo "SELECT title, comments FROM lists \n";
echo "WHERE pid = 123 AND type = 'medication';\n\n";

echo "4. GET PATIENT'S MEDICAL CONDITIONS:\n";
echo "---\n";
echo "SELECT title FROM lists \n";
echo "WHERE pid = 123 AND type = 'medical_problem';\n\n";

echo "5. GET ALL DATA FOR A PATIENT (ALL TABLES):\n";
echo "---\n";
echo "-- Patient Demographics\n";
echo "SELECT * FROM patient_data WHERE pid = 123;\n";
echo "-- Employment\n";
echo "SELECT * FROM employer_data WHERE pid = 123;\n";
echo "-- Medical History\n";
echo "SELECT * FROM history_data WHERE pid = 123;\n";
echo "-- Chief Complaint\n";
echo "SELECT * FROM patient_chief_complaint WHERE pid = 123;\n";
echo "-- All Medical Data\n";
echo "SELECT * FROM lists WHERE pid = 123;\n";
echo "-- Insurance\n";
echo "SELECT * FROM insurance_data WHERE pid = 123;\n\n";

echo "============================================================\n";

$db->close();
?>
