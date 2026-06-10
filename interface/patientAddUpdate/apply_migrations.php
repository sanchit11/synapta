<?php
/**
 * Database Migration Helper
 * Identifies and applies missing migrations to fix patient_data schema
 */

header('Content-Type: application/json');

try {
    // Connect to database
    $db = new mysqli('localhost', 'root', '', 'synaptaemr');

    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }

    $db->set_charset("utf8mb4");

    // Get current patient_data columns
    $result = $db->query("DESCRIBE patient_data");
    $currentColumns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $currentColumns[] = $row['Field'];
        }
    }

    // Expected columns that should exist in patient_data
    $expectedColumns = [
        'pid',
        'pubpid',
        'title',
        'fname',
        'lname',
        'mname',
        'DOB',
        'sex',
        'street',
        'postal_code',
        'city',
        'state',
        'country_code',
        'phone_home',
        'phone_biz',
        'phone_cell',
        'email',
        'email_direct',
        'emergency_name',
        'emergency_phone',
        'emergency_relation',
        'race',
        'ethnicity',
        'status',
        'contact_relationship',
        'hipaa_notice_received',
        'hipaa_notice_date',
        'date',
        'referrer',
        'referrer_phone',
        'employer',
        'insurance_id',
        'insurance_company',
        'insurance_plan_name',
        'insurance_group_number',
        'insurance_member_id',
        'date_of_last_visit',
        'providerID'
    ];

    // Find missing columns
    $missingColumns = [];
    foreach ($expectedColumns as $col) {
        if (!in_array($col, $currentColumns)) {
            $missingColumns[] = $col;
        }
    }

    echo json_encode([
        'status' => 'current_schema_analysis',
        'total_current_columns' => count($currentColumns),
        'total_expected_columns' => count($expectedColumns),
        'current_columns_sample' => array_slice($currentColumns, 0, 20),
        'missing_columns' => $missingColumns,
        'missing_count' => count($missingColumns),
        'next_step' => count($missingColumns) > 0 ? 'Apply migrations listed in /sql/ directory' : 'Schema is complete',
        'database' => 'synaptaemr',
        'table' => 'patient_data'
    ], JSON_PRETTY_PRINT);

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
?>
