<?php
/**
 * Setup script to create patient_choices_preferences table
 * Run this once to set up the database table
 * Access: http://localhost/synaptaEMR-old/interface/patientAddUpdate/setup_choices_table.php
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
    $sql = "CREATE TABLE IF NOT EXISTS `patient_choices_preferences` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pid` int(11) NOT NULL COMMENT 'Patient ID (Foreign Key to patient_data)',
      `uuid` varchar(36) DEFAULT NULL COMMENT 'Unique identifier',

      -- Provider & Pharmacy
      `primary_care_provider` varchar(255) DEFAULT NULL COMMENT 'Primary care provider name or ID',
      `provider_since_date` date DEFAULT NULL COMMENT 'Date patient started with primary provider',
      `referring_provider` varchar(255) DEFAULT NULL COMMENT 'Referring provider information',
      `preferred_pharmacy` text DEFAULT NULL COMMENT 'Preferred pharmacy name and address',

      -- Communication Permissions
      `hipaa_notice_received` varchar(3) DEFAULT NULL COMMENT 'Yes/No - HIPAA notice received',
      `allow_voice_message` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow voice messages',
      `voice_message_with` varchar(255) DEFAULT NULL COMMENT 'Name of person to leave message with',
      `allow_mail_message` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow mail communication',
      `allow_sms_text` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow SMS/text messages',
      `allow_email_message` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow email communication',
      `allow_patient_portal` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow patient portal access',
      `allow_imm_reg_use` varchar(50) DEFAULT NULL COMMENT 'Yes/No/Opted Out - Immunization registry use',
      `allow_imm_info_share` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow immunization info sharing',
      `allow_health_info_ex` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Allow health info exchange',
      `cmsportal_login` varchar(255) DEFAULT NULL COMMENT 'CMS Blue Button login information',

      -- Registry & Compliance
      `imm_reg_status` varchar(50) DEFAULT NULL COMMENT 'Active/Inactive/Opted Out - Immunization registry status',
      `imm_reg_stat_effdate` date DEFAULT NULL COMMENT 'Immunization registry status effective date',
      `publicity_code` varchar(50) DEFAULT NULL COMMENT 'Unrestricted/Restricted - Publicity code',
      `publ_code_eff_date` date DEFAULT NULL COMMENT 'Publicity code effective date',
      `protect_indicator` varchar(3) DEFAULT NULL COMMENT 'Yes/No - Protection indicator',
      `prot_indi_effdate` date DEFAULT NULL COMMENT 'Protection indicator effective date',
      `care_team_provider` varchar(255) DEFAULT NULL COMMENT 'Care team provider name',
      `care_team_status` varchar(50) DEFAULT NULL COMMENT 'Active/Inactive - Care team status',
      `patient_category` varchar(50) DEFAULT NULL COMMENT 'Patient category/group assignment',

      -- Metadata
      `date` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation date',
      `date_modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification date',

      PRIMARY KEY (`id`),
      UNIQUE KEY `pid_unique` (`pid`),
      KEY `idx_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Patient choices, preferences, and privacy settings'";

    // Execute the query
    if ($db->query($sql) === TRUE) {
        $db->close();
        echo json_encode([
            'success' => true,
            'message' => 'Table patient_choices_preferences created successfully!',
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
