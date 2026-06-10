<?php
/**
 * Setup script to create patient_demographics_social table
 * Run this once to set up the database table
 * Access: http://localhost/synaptaEMR-old/interface/patientAddUpdate/setup_demographics_social_table.php
 */

header('Content-Type: application/json; charset=utf-8');

try {
    // Connect to database
    $db = new mysqli('localhost', 'root', '', 'synaptaemr');

    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }

    $db->set_charset("utf8mb4");

    // SQL to create the table
    $sql = "CREATE TABLE IF NOT EXISTS `patient_demographics_social` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pid` int(11) NOT NULL COMMENT 'Patient ID (Foreign Key to patient_data)',
      `uuid` varchar(36) DEFAULT NULL,

      -- Race, Ethnicity & Language
      `primary_language` varchar(50) DEFAULT NULL,
      `ethnicity` varchar(50) DEFAULT NULL,
      `race` varchar(100) DEFAULT NULL,
      `nationality_country` varchar(100) DEFAULT NULL,
      `interpreter_needed` varchar(3) DEFAULT NULL,
      `interpreter_dialect_notes` text DEFAULT NULL,

      -- Financial & Social Factors
      `financial_review_date` date DEFAULT NULL,
      `monthly_income` varchar(50) DEFAULT NULL,
      `household_size` int(2) DEFAULT NULL,
      `homeless` varchar(255) DEFAULT NULL,
      `migrantseasonal` varchar(255) DEFAULT NULL,
      `referral_source` varchar(100) DEFAULT NULL,
      `vfc_eligibility_status` varchar(50) DEFAULT NULL,
      `religion` varchar(50) DEFAULT NULL,
      `tribal_affiliations` varchar(255) DEFAULT NULL,

      -- Metadata
      `date` datetime DEFAULT CURRENT_TIMESTAMP,
      `date_modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

      PRIMARY KEY (`id`),
      UNIQUE KEY `pid_unique` (`pid`),
      KEY `idx_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Execute the query
    if ($db->query($sql) === TRUE) {
        $db->close();
        echo json_encode([
            'success' => true,
            'message' => 'Table patient_demographics_social created successfully!',
            'status' => 'ready'
        ]);
    } else {
        throw new Exception("Error creating table: " . $db->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
