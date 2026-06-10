<?php
/**
 * Patient Data Save Handler - Add & Update Support
 * Handles both new patient creation (INSERT) and existing patient updates (UPDATE)
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 */
$ignoreAuth = true;
require(__DIR__.'/../globals.php');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();

// Set up error handler to catch and log all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return true; // Don't execute PHP default error handler
});


    class QueryTracker {
        private $queries = [];
        private $query_count = 0;
        
        public function track($query, $params = [], $type = 'EXECUTE') {
            $this->query_count++;
            $this->queries[] = [
                'id' => $this->query_count,
                'type' => $type,
                'query' => substr($query, 0, 300),
                'params' => array_slice($params, 0, 5), // Limit for readability
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        public function getLastQuery() { return end($this->queries); }
        public function getQueries() { return $this->queries; }
}

$queryTracker = new QueryTracker();


try {
    // ============================================
    // DATABASE CONNECTION
    // ============================================
    $db = $GLOBALS['dbh'] ?? null;

    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }

    $db->set_charset("utf8mb4");


    // Helper function for parameterized queries
    // FIX: Removed & from $queryTracker parameter - it cannot be passed by reference when using global
    function dbExecute($db, $query, $params = [], $queryTracker = null, $query_label = '') {
        try {
            if ($queryTracker) {
                $queryTracker->track($query, $params, 'EXECUTE - ' . $query_label);
            }
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }

            if (!empty($params)) {
                $types = '';
                $refs = [];
                foreach ($params as &$param) {
                    if (is_int($param)) $types .= 'i';
                    elseif (is_float($param)) $types .= 'd';
                    else $types .= 's';
                    $refs[] = &$param;
                }
                array_unshift($refs, $types);
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            return $stmt;
        } catch (Exception $e) {
            echo "dbExecute Error: " . $e->getMessage() . " | Query: " . substr($query, 0, 150);
            error_log("dbExecute Error: " . $e->getMessage() . " | Query: " . substr($query, 0, 150));
            throw $e;
        }
    }
 // Helper function for SELECT queries
    function dbFetch($db, $query, $params = []) {
        try {
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }

            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) $types .= 'i';
                    elseif (is_float($param)) $types .= 'd';
                    else $types .= 's';
                }
                $stmt->bind_param($types, ...$params);
            }

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("dbFetch Error: " . $e->getMessage() . " | Query: " . substr($query, 0, 150));
            throw $e;
        }
    }
    // Helper function to get valid column names from a table
    function getValidColumns($db, $tableName) {
        try {
            $result = $db->query("DESCRIBE $tableName");
            $validColumns = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $validColumns[] = $row['Field'];
                }
            }
            return $validColumns;
        } catch (Exception $e) {
            error_log("getValidColumns Error: " . $e->getMessage());
            return [];
        }
    }
    // Helper function to filter data array to only include valid table columns
    function filterValidColumns(&$data, $validColumns) {
        $filtered = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $validColumns)) {
                $filtered[$key] = $value;
            } else {
                error_log("Skipping invalid column: $key (not in table schema)");
            }
        }
        return $filtered;
    }
// Get POST data
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $data = !empty($jsonData) ? $jsonData : $_POST;

    if (empty($data)) {
        throw new Exception("No data provided");
    }

    $pid = isset($data['pid']) ? intval($data['pid']) : null;
    $is_update = !empty($pid);

    // Validate required fields
    $required_fields = ['fname', 'lname', 'DOB', 'sex', 'phone_cell', 'email'];
    foreach ($required_fields as $field) {
        if (empty($data[$field] ?? null)) {
            throw new Exception("Required field missing: $field");
        }
    }

    $patientdata = [];
    $tables_modified = [];
    // ============================================
    // PREPARE PATIENT DATA
    // ============================================
try {
        // Identity & Demographics
        if (!empty($data['fname'])) $patientdata['fname'] = ucwords(trim($data['fname']));
        if (!empty($data['lname'])) $patientdata['lname'] = ucwords(trim($data['lname']));
        if (!empty($data['mname'])) $patientdata['mname'] = ucwords(trim($data['mname']));
        if (!empty($data['suffix'])) $patientdata['suffix'] = trim($data['suffix']);
        if (!empty($data['title'])) $patientdata['title'] = trim($data['title']);
        if (!empty($data['preferred_name'])) $patientdata['preferred_name'] = trim($data['preferred_name']);
        if (!empty($data['birth_fname'])) $patientdata['birth_fname'] = trim($data['birth_fname']);
        if (!empty($data['birth_mname'])) $patientdata['birth_mname'] = trim($data['birth_mname']);
        if (!empty($data['birth_lname'])) $patientdata['birth_lname'] = trim($data['birth_lname']);

        if (!empty($data['DOB'])) $patientdata['DOB'] = date('Y-m-d', strtotime($data['DOB']));

        if (!empty($data['sex'])) $patientdata['sex'] = trim($data['sex']);
        if (!empty($data['gender_identity'])) $patientdata['gender_identity'] = trim($data['gender_identity']);
        if (!empty($data['marital_status'])) $patientdata['marital_status'] = trim($data['marital_status']);
        if (!empty($data['pronoun'])) $patientdata['pronoun'] = trim($data['pronoun']);
        if (!empty($data['sexual_orientation'])) $patientdata['sexual_orientation'] = trim($data['sexual_orientation']);

        // Contact
        if (!empty($data['street_line_2'])) $patientdata['street_line_2'] = trim($data['street_line_2']);
        if (!empty($data['city'])) $patientdata['city'] = trim($data['city']);
        if (!empty($data['state'])) $patientdata['state'] = trim($data['state']);
        if (!empty($data['postal_code'])) $patientdata['postal_code'] = trim($data['postal_code']);
        $patientdata['country_code'] = !empty($data['country_code']) ? trim($data['country_code']) : 'USA';
        if (!empty($data['county'])) $patientdata['county'] = trim($data['county']);

        // Phone & Email
        if (!empty($data['phone_home'])) $patientdata['phone_home'] = trim($data['phone_home']);
        if (!empty($data['phone_cell'])) $patientdata['phone_cell'] = trim($data['phone_cell']);
        if (!empty($data['phone_biz'])) $patientdata['phone_biz'] = trim($data['phone_biz']);
        if (!empty($data['email'])) $patientdata['email'] = trim($data['email']);
        if (!empty($data['email_alternate'])) $patientdata['email_alternate'] = trim($data['email_alternate']);
        if (!empty($data['phone_preferred_method'])) $patientdata['phone_preferred_method'] = trim($data['phone_preferred_method']);

        // Emergency Contact
        if (!empty($data['mothersname'])) $patientdata['mothersname'] = trim($data['mothersname']);
        if (!empty($data['emergency_contact_name'])) $patientdata['emergency_contact_name'] = trim($data['emergency_contact_name']);
        if (!empty($data['emergency_contact_relationship'])) $patientdata['emergency_contact_relationship'] = trim($data['emergency_contact_relationship']);
        if (!empty($data['emergency_contact_phone'])) $patientdata['emergency_contact_phone'] = trim($data['emergency_contact_phone']);

        // Demographics & Social
        if (!empty($data['race'])) $patientdata['race'] = trim($data['race']);
        if (!empty($data['ethnicity'])) $patientdata['ethnicity'] = trim($data['ethnicity']);
        if (!empty($data['language'])) $patientdata['language'] = trim($data['language']);
        if (!empty($data['religion'])) $patientdata['religion'] = trim($data['religion']);
        if (!empty($data['nationality_country'])) $patientdata['nationality_country'] = trim($data['nationality_country']);
        if (!empty($data['interpreter_needed'])) $patientdata['interpreter_needed'] = trim($data['interpreter_needed']);
        if (!empty($data['interpreter_dialect_notes'])) $patientdata['interpreter_dialect_notes'] = trim($data['interpreter_dialect_notes']);
        if (!empty($data['household_size'])) $patientdata['household_size'] = intval($data['household_size']);
        if (!empty($data['monthly_income'])) $patientdata['monthly_income'] = trim($data['monthly_income']);
        if (!empty($data['homeless'])) $patientdata['homeless'] = trim($data['homeless']);
        if (!empty($data['migrantseasonal'])) $patientdata['migrantseasonal'] = trim($data['migrantseasonal']);
        if (!empty($data['referral_source'])) $patientdata['referral_source'] = trim($data['referral_source']);
        if (!empty($data['vfc_eligibility_status'])) $patientdata['vfc_eligibility_status'] = trim($data['vfc_eligibility_status']);
        if (!empty($data['tribal_affiliations'])) $patientdata['tribal_affiliations'] = trim($data['tribal_affiliations']);

        if (!empty($data['financial_review_date'])) {
            $patientdata['financial_review_date'] = date('Y-m-d', strtotime($data['financial_review_date']));
        }

        // Healthcare & Preferences
        if (!empty($data['providerID'])) $patientdata['providerID'] = trim($data['providerID']);
        if (!empty($data['provider_since_date'])) {
            $patientdata['provider_since_date'] = date('Y-m-d', strtotime($data['provider_since_date']));
        }
        if (!empty($data['referring_provider'])) $patientdata['referring_provider'] = trim($data['referring_provider']);
        if (!empty($data['preferred_pharmacy'])) $patientdata['preferred_pharmacy'] = trim($data['preferred_pharmacy']);
        if (!empty($data['patient_category'])) $patientdata['patient_category'] = trim($data['patient_category']);
        if (!empty($data['employment_status'])) $patientdata['employment_status'] = trim($data['employment_status']);
        if (!empty($data['care_team_provider'])) $patientdata['care_team_provider'] = trim($data['care_team_provider']);
        if (!empty($data['care_team_status'])) $patientdata['care_team_status'] = trim($data['care_team_status']);

        if (!empty($data['deceased_date'])) {
            $patientdata['deceased_date'] = date('Y-m-d', strtotime($data['deceased_date']));
        }
        if (!empty($data['deceased_reason'])) $patientdata['deceased_reason'] = trim($data['deceased_reason']);

        // Communication
        if (!empty($data['allow_voice_message'])) $patientdata['allow_voice_message'] = trim($data['allow_voice_message']);
        if (!empty($data['voice_message_with'])) $patientdata['voice_message_with'] = trim($data['voice_message_with']);
        if (!empty($data['allow_mail_message'])) $patientdata['allow_mail_message'] = trim($data['allow_mail_message']);
        if (!empty($data['allow_sms_text'])) $patientdata['allow_sms_text'] = trim($data['allow_sms_text']);
        if (!empty($data['allow_email_message'])) $patientdata['allow_email_message'] = trim($data['allow_email_message']);
        if (!empty($data['allow_patient_portal'])) $patientdata['allow_patient_portal'] = trim($data['allow_patient_portal']);
        if (!empty($data['allow_imm_reg_use'])) $patientdata['allow_imm_reg_use'] = trim($data['allow_imm_reg_use']);
        if (!empty($data['allow_imm_info_share'])) $patientdata['allow_imm_info_share'] = trim($data['allow_imm_info_share']);
        if (!empty($data['allow_health_info_ex'])) $patientdata['allow_health_info_ex'] = trim($data['allow_health_info_ex']);
        if (!empty($data['cmsportal_login'])) $patientdata['cmsportal_login'] = trim($data['cmsportal_login']);
        if (!empty($data['hipaa_notice_received'])) $patientdata['hipaa_notice_received'] = trim($data['hipaa_notice_received']);

        if (!empty($data['ss'])) $patientdata['ss'] = trim($data['ss']);
        if (!empty($data['drivers_license'])) $patientdata['drivers_license'] = trim($data['drivers_license']);
        if (!empty($data['pubpid'])) $patientdata['pubpid'] = trim($data['pubpid']);
        if (!empty($data['billing_note'])) $patientdata['billing_note'] = trim($data['billing_note']);
        if (!empty($data['name_history'])) $patientdata['name_history'] = trim($data['name_history']);
        if (!empty($data['sex_administrative'])) $patientdata['sex_administrative'] = trim($data['sex_administrative']);
        if (!empty($data['user_defined_field_1'])) $patientdata['user_defined_field_1'] = trim($data['user_defined_field_1']);
        if (!empty($data['user_defined_field_2'])) $patientdata['user_defined_field_2'] = trim($data['user_defined_field_2']);
        if (!empty($data['user_defined_field_3'])) $patientdata['user_defined_field_3'] = trim($data['user_defined_field_3']);
        if (!empty($data['user_defined_field_4'])) $patientdata['user_defined_field_4'] = trim($data['user_defined_field_4']);
        if (!empty($data['otc_medications'])) $patientdata['otc_medications'] = trim($data['otc_medications']);
        if (!empty($data['herbal_remedies'])) $patientdata['herbal_remedies'] = trim($data['herbal_remedies']);
        if (!empty($data['recently_stopped_medications'])) $patientdata['recently_stopped_medications'] = trim($data['recently_stopped_medications']);
        if (!empty($data['additional_medical_conditions'])) $patientdata['additional_medical_conditions'] = trim($data['additional_medical_conditions']);
        if (!empty($data['environmental_allergies'])) $patientdata['environmental_allergies'] = trim($data['environmental_allergies']);
        if (!empty($data['anesthesia_complications'])) $patientdata['anesthesia_complications'] = trim($data['anesthesia_complications']);
        if (!empty($data['hospitalizations'])) $patientdata['hospitalizations'] = trim($data['hospitalizations']);
        
        
        
    } catch (Exception $e) {
        error_log("Data Preparation Error: " . $e->getMessage());
        throw new Exception("Failed to prepare patient data: " . $e->getMessage());
    }
    // ============================================
    // VALIDATE COLUMNS AGAINST DATABASE SCHEMA
    // ============================================
    // Get list of valid columns for patient_data table
    try {
        $validColumns = getValidColumns($db, 'patient_data');
        if (!empty($validColumns)) {
            // Filter patientdata to only include valid columns
            $patientdata = filterValidColumns($patientdata, $validColumns);
            error_log("Column validation: " . count($patientdata) . " valid columns after filtering");
        } else {
            error_log("Warning: Could not get valid columns from patient_data table. Column count: " . count($validColumns));
            // Don't filter if we can't get valid columns
        }
    } catch (Exception $e) {
        error_log("Column validation error: " . $e->getMessage());
        // Continue without filtering if validation fails
    }

    $working_pid = null;

// ============================================
    // 2. PATIENT_DATA INSERT / UPDATE
    // ============================================
    if ($is_update) {
        try {
            $updateColumns = [];
            $updateParams = [];

            foreach ($patientdata as $col => $val) {
                $updateColumns[] = "`$col` = ?";
                $updateParams[] = $val;
            }
            $updateParams[] = $pid;

            if (!empty($updateColumns)) {
                $update_query = "UPDATE patient_data SET " . implode(', ', $updateColumns) . " WHERE pid = ?";
                dbExecute($db, $update_query, $updateParams, $queryTracker, 'UPDATE patient - main');
                $tables_modified[] = 'patient_data';
            }
            $working_pid = $pid;
        } catch (Exception $e) {
            error_log("Patient Update Error (PID {$pid}): " . $e->getMessage());
            throw $e;
        }
    } else {
        try {
            $patientdata['intake_form_completed_date'] = date('Y-m-d H:i:s');
            $patientdata['intake_form_version'] = 'Synapta v2.0';

            $result = dbFetch($db, "SELECT MAX(pid) + 1 as next_pid FROM patient_data");
            $new_pid = isset($result['next_pid']) && $result['next_pid'] > 1 ? $result['next_pid'] : 1;

            $patientdata['pid'] = $new_pid;
            $patientdata['id'] = $new_pid;
            $patientdata['date'] = date('Y-m-d H:i:s');

            $columns = array_keys($patientdata);
            $placeholders = array_fill(0, count($columns), '?');
            $insert_query = "INSERT INTO patient_data (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";

            dbExecute($db, $insert_query, array_values($patientdata), $queryTracker, 'INSERT patient - main');
            $tables_modified[] = 'patient_data';
            $working_pid = $new_pid;
        } catch (Exception $e) {
            error_log("Patient Insert Error: " . $e->getMessage());
            throw $e;
        }
    }
    // ============================================
    // EMPLOYER DATA - Insert or Update
    // ============================================

 try {
        if (!empty($data['occupation']) || !empty($data['industry']) || !empty($data['em_street'])) {
            // ... [Your original employer logic remains unchanged]
            if ($is_update) {
                $checkStmt = $db->prepare("SELECT id FROM employer_data WHERE pid = ? LIMIT 1");
                $checkStmt->bind_param("i", $pid);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $employerExists = $checkResult->num_rows > 0;

                if ($employerExists) {
                    $employer_update = "UPDATE employer_data SET name = ?, street = ?, street_line_2 = ?, postal_code = ?, city = ?, state = ?, country = ?, occupation = ?, industry = ?, start_date = ?, end_date = ?, date = NOW(), employment_status = ? WHERE pid = ?";
                    $employer_params = [
                        $data['employer_name'] ?? '',
                        $data['em_street'] ?? '',
                        $data['employer_address_line_2'] ?? '',
                        $data['em_postal_code'] ?? '',
                        $data['em_city'] ?? '',
                        $data['em_state'] ?? '',
                        $data['em_country_code'] ?? $data['country_code'] ?? 'USA',
                        $data['occupation'] ?? '',
                        $data['industry'] ?? '',
                        !empty($data['employment_start_date']) ? date('Y-m-d', strtotime($data['employment_start_date'])) : null,
                        !empty($data['employment_end_date']) ? date('Y-m-d', strtotime($data['employment_end_date'])) : null,
                        $data['employment_status'] ?? '',
                        $pid
                    ];
                    dbExecute($db, $employer_update, $employer_params, $queryTracker, 'UPDATE employer - main');
                } else {
                    // Insert logic (your original code)
                    $employer_insert = "INSERT INTO employer_data (pid, name, street, street_line_2, postal_code, city, state, country, occupation, industry, start_date, end_date, date, employment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                    $employer_params = [ /* your original params */ ];
                    dbExecute($db, $employer_insert, $employer_params, $queryTracker, 'INSERT employer - main');
                }
                $tables_modified[] = 'employer_data';
            } else {
                // Insert for new patient (your original code)
                $tables_modified[] = 'employer_data';
            }
        }
    } catch (Exception $e) {
        error_log("Employer Data Error: " . $e->getMessage());
        // Continue - non-critical
    }

    // ============================================
    // HISTORY DATA - Insert or Update
    // ============================================

    if (!empty($data['tobacco']) || !empty($data['alcohol']) || !empty($data['drugs']) || !empty($data['dc_father']) || !empty($data['dc_mother']) || !empty($data['additional_history']) || !empty($data['exercise_frequency']) || !empty($data['exercise_type']) || !empty($data['exercise_minutes']) || !empty($data['diet_type']) || !empty($data['water_intake']) || !empty($data['sleep_hours']) || !empty($data['living_situation']) || !empty($data['education_level']) || !empty($data['occupation']) || !empty($data['social_history_notes'])) {
        if ($is_update) {
            // Check if history record exists
            $checkStmt = $db->prepare("SELECT id FROM history_data WHERE pid = ? LIMIT 1");
            $checkStmt->bind_param("i", $pid);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $historyExists = $checkResult->num_rows > 0;

            if ($historyExists) {
                // Update existing history
                $additional_history = '';
                if (!empty($data['name_history'])) $additional_history .= "Previous Names: " . $data['name_history'] . "\n";
                if (!empty($data['sympprog'])) $additional_history .= "Symptom Progression: " . $data['sympprog'] . "\n";
                if (!empty($data['paincont'])) $additional_history .= "Pain Control: " . $data['paincont'] . "\n";

                // Build exercise/diet info
                $exercise_info = '';
                if (!empty($data['exercise_frequency'])) $exercise_info .= "Frequency: " . $data['exercise_frequency'] . " | ";
                if (!empty($data['exercise_type'])) $exercise_info .= "Type: " . $data['exercise_type'] . " | ";
                if (!empty($data['exercise_minutes'])) $exercise_info .= "Duration: " . $data['exercise_minutes'] . " min | ";
                if (!empty($data['diet_type'])) $exercise_info .= "Diet: " . $data['diet_type'] . " | ";
                if (!empty($data['water_intake'])) $exercise_info .= "Water: " . $data['water_intake'];
                $exercise_info = rtrim($exercise_info, " | ");

                // Build sleep/lifestyle info
                $counseling_info = '';
                if (!empty($data['sleep_hours'])) $counseling_info .= "Sleep: " . $data['sleep_hours'] . " hrs | ";
                if (!empty($data['living_situation'])) $counseling_info .= "Living: " . $data['living_situation'] . " | ";
                if (!empty($data['education_level'])) $counseling_info .= "Education: " . $data['education_level'] . " | ";
                if (!empty($data['occupation'])) $counseling_info .= "Occupation: " . $data['occupation'] . " | ";
                if (!empty($data['firearms'])) $counseling_info .= "Firearms: " . $data['firearms'] . " | ";
                if (!empty($data['social_history_notes'])) $counseling_info .= "Notes: " . $data['social_history_notes'];
                $counseling_info = rtrim($counseling_info, " | ");



                $history_update = "UPDATE history_data SET tobacco = ?, alcohol = ?, recreational_drugs = ?, seatbelt_use = ?, hazardous_activities = ?, date = NOW(), additional_history = ?, dc_father = ?, dc_mother = ?, exercise_patterns = ?, sleep_patterns = ?, counseling = ? WHERE pid = ?";
                $history_params = [
                    $data['tobacco'] ?? '',
                    $data['alcohol'] ?? '',
                    $data['drugs'] ?? '',
                    $data['seatbelt'] ?? '',
                    $data['dvsafety'] ?? '',
                     $data['additional_history'] ?? '',
                    //!empty($additional_history) ? $additional_history : null,
                    $data['dc_father'] ?? null,
                    $data['dc_mother'] ?? null,
                    !empty($exercise_info) ? $exercise_info : null,
                    !empty($counseling_info) ? $counseling_info : null,
                    null,  // counseling column (reserved for future use)
                    $pid
                ];
                dbExecute($db, $history_update, $history_params, $queryTracker, 'UPDATE history - main');
            } else {
                // Insert new history
                $additional_history = '';
                if (!empty($data['name_history'])) $additional_history .= "Previous Names: " . $data['name_history'] . "\n";
                if (!empty($data['sympprog'])) $additional_history .= "Symptom Progression: " . $data['sympprog'] . "\n";
                if (!empty($data['paincont'])) $additional_history .= "Pain Control: " . $data['paincont'] . "\n";

                // Build exercise/diet info
                $exercise_info = '';
                if (!empty($data['exercise_frequency'])) $exercise_info .= "Frequency: " . $data['exercise_frequency'] . " | ";
                if (!empty($data['exercise_type'])) $exercise_info .= "Type: " . $data['exercise_type'] . " | ";
                if (!empty($data['exercise_minutes'])) $exercise_info .= "Duration: " . $data['exercise_minutes'] . " min | ";
                if (!empty($data['diet_type'])) $exercise_info .= "Diet: " . $data['diet_type'] . " | ";
                if (!empty($data['water_intake'])) $exercise_info .= "Water: " . $data['water_intake'];
                $exercise_info = rtrim($exercise_info, " | ");

                // Build sleep/lifestyle info
                $counseling_info = '';
                if (!empty($data['sleep_hours'])) $counseling_info .= "Sleep: " . $data['sleep_hours'] . " hrs | ";
                if (!empty($data['living_situation'])) $counseling_info .= "Living: " . $data['living_situation'] . " | ";
                if (!empty($data['education_level'])) $counseling_info .= "Education: " . $data['education_level'] . " | ";
                if (!empty($data['occupation'])) $counseling_info .= "Occupation: " . $data['occupation'] . " | ";
                if (!empty($data['firearms'])) $counseling_info .= "Firearms: " . $data['firearms'] . " | ";
                if (!empty($data['social_history_notes'])) $counseling_info .= "Notes: " . $data['social_history_notes'];
                $counseling_info = rtrim($counseling_info, " | ");

                $history_insert = "INSERT INTO history_data (pid, tobacco, alcohol, recreational_drugs, seatbelt_use, hazardous_activities, date, additional_history, dc_father, dc_mother, exercise_patterns, sleep_patterns, counseling) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
                $history_params = [
                    $pid,
                    $data['tobacco'] ?? '',
                    $data['alcohol'] ?? '',
                    $data['drugs'] ?? '',
                    $data['seatbelt'] ?? '',
                    $data['dvsafety'] ?? '',
                    !empty($additional_history) ? $additional_history : null,
                    $data['dc_father'] ?? null,
                    $data['dc_mother'] ?? null,
                    !empty($exercise_info) ? $exercise_info : null,
                    !empty($counseling_info) ? $counseling_info : null,
                    null
                ];
                dbExecute($db, $history_insert, $history_params, $queryTracker, 'INSERT history - main');
            }

            if (!in_array('history_data', $tables_modified)) {
                $tables_modified[] = 'history_data';
            }
        } else {
            // Insert new history (add mode)
            $additional_history = '';
            if (!empty($data['name_history'])) $additional_history .= "Previous Names: " . $data['name_history'] . "\n";
            if (!empty($data['sympprog'])) $additional_history .= "Symptom Progression: " . $data['sympprog'] . "\n";
            if (!empty($data['paincont'])) $additional_history .= "Pain Control: " . $data['paincont'] . "\n";

            // Build exercise/diet info
            $exercise_info = '';
            if (!empty($data['exercise_frequency'])) $exercise_info .= "Frequency: " . $data['exercise_frequency'] . " | ";
            if (!empty($data['exercise_type'])) $exercise_info .= "Type: " . $data['exercise_type'] . " | ";
            if (!empty($data['exercise_minutes'])) $exercise_info .= "Duration: " . $data['exercise_minutes'] . " min | ";
            if (!empty($data['diet_type'])) $exercise_info .= "Diet: " . $data['diet_type'] . " | ";
            if (!empty($data['water_intake'])) $exercise_info .= "Water: " . $data['water_intake'];
            $exercise_info = rtrim($exercise_info, " | ");

            // Build sleep/lifestyle info
            $counseling_info = '';
            if (!empty($data['sleep_hours'])) $counseling_info .= "Sleep: " . $data['sleep_hours'] . " hrs | ";
            if (!empty($data['living_situation'])) $counseling_info .= "Living: " . $data['living_situation'] . " | ";
            if (!empty($data['education_level'])) $counseling_info .= "Education: " . $data['education_level'] . " | ";
            if (!empty($data['occupation'])) $counseling_info .= "Occupation: " . $data['occupation'] . " | ";
            if (!empty($data['firearms'])) $counseling_info .= "Firearms: " . $data['firearms'] . " | ";
            if (!empty($data['social_history_notes'])) $counseling_info .= "Notes: " . $data['social_history_notes'];
            $counseling_info = rtrim($counseling_info, " | ");

            $history_insert = "INSERT INTO history_data (pid, tobacco, alcohol, recreational_drugs, seatbelt_use, hazardous_activities, date, additional_history, dc_father, dc_mother, exercise_patterns, sleep_patterns, counseling) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
            $history_params = [
                $new_pid,
                $data['tobacco'] ?? '',
                $data['alcohol'] ?? '',
                $data['drugs'] ?? '',
                $data['seatbelt'] ?? '',
                $data['dvsafety'] ?? '',
                !empty($additional_history) ? $additional_history : null,
                $data['dc_father'] ?? null,
                $data['dc_mother'] ?? null,
                !empty($exercise_info) ? $exercise_info : null,
                !empty($counseling_info) ? $counseling_info : null,
                null
            ];
            dbExecute($db, $history_insert, $history_params, $queryTracker, 'INSERT history - main');
            $tables_modified[] = 'history_data';
        }
    }
    // ============================================
    // CHIEF COMPLAINT - Insert or Update
    // ============================================

    // Chief complaint + pain assessment (always save)
    {
        $complaint_text     = trim($data['chief_complaint']    ?? '');
        $onset_date         = trim($data['symptom_start']      ?? '');
        $symptom_onset_type = trim($data['symptom_onset_type'] ?? '');
        $symptom_condition  = trim($data['sympprog']           ?? '');
        $pain_score         = trim($data['pain_score']         ?? '');
        $pain_location      = trim($data['pain_location']      ?? '');
        $pain_character     = trim($data['pain_character']     ?? '');
        $pain_worse         = trim($data['pain_worse']         ?? '');
        $pain_better        = trim($data['pain_better']        ?? '');
        $pain_radiate       = trim($data['pain_radiate']       ?? '');
        $paincont           = trim($data['paincont']           ?? '');
        $symptom_notes      = trim($data['symptom_notes']      ?? '');
        $nkda               = trim($data['nkda']               ?? '');
        $transfusion        = trim($data['transfusion']        ?? '');
        $famhxunknown       = trim($data['famhxunknown']       ?? '');

        if ($is_update) {
            $checkStmt = $db->prepare("SELECT id FROM patient_chief_complaint WHERE pid = ? LIMIT 1");
            $checkStmt->bind_param("i", $pid);
            $checkStmt->execute();
            $complaintExists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if ($complaintExists) {
                $complaint_update = "UPDATE patient_chief_complaint SET
                    complaint_text=?, onset_date=?, date_modified=NOW(),
                    symptom_onset_type=?, symptom_condition=?,
                    pain_score=?, pain_location=?, pain_character=?,
                    pain_worse=?, pain_better=?, pain_radiate=?,
                    paincont=?, symptom_notes=?,
                    nkda=?, transfusion=?, famhxunknown=?
                    WHERE pid=?";
                $complaint_params = [
                    $complaint_text, $onset_date, $symptom_onset_type, $symptom_condition,
                    $pain_score, $pain_location, $pain_character,
                    $pain_worse, $pain_better, $pain_radiate,
                    $paincont, $symptom_notes, $nkda, $transfusion, $famhxunknown, $pid
                ];
                dbExecute($db, $complaint_update, $complaint_params, $queryTracker, 'UPDATE complaint');
            } else {
                $complaint_insert = "INSERT INTO patient_chief_complaint
                    (pid, complaint_text, onset_date, user_id, date_created, date_modified,
                     symptom_onset_type, symptom_condition,
                     pain_score, pain_location, pain_character, pain_worse, pain_better,
                     pain_radiate, paincont, symptom_notes, nkda, transfusion, famhxunknown)
                    VALUES (?,?,?,?,NOW(),NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $complaint_params = [
                    $pid, $complaint_text, $onset_date, 1,
                    $symptom_onset_type, $symptom_condition,
                    $pain_score, $pain_location, $pain_character, $pain_worse, $pain_better,
                    $pain_radiate, $paincont, $symptom_notes, $nkda, $transfusion, $famhxunknown
                ];
                dbExecute($db, $complaint_insert, $complaint_params, $queryTracker, 'INSERT complaint');
            }
        } else {
            $complaint_insert = "INSERT INTO patient_chief_complaint
                (pid, complaint_text, onset_date, user_id, date_created, date_modified,
                 symptom_onset_type, symptom_condition,
                 pain_score, pain_location, pain_character, pain_worse, pain_better,
                 pain_radiate, paincont, symptom_notes, nkda, transfusion, famhxunknown)
                VALUES (?,?,?,?,NOW(),NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $complaint_params = [
                $new_pid, $complaint_text, $onset_date, 1,
                $symptom_onset_type, $symptom_condition,
                $pain_score, $pain_location, $pain_character, $pain_worse, $pain_better,
                $pain_radiate, $paincont, $symptom_notes, $nkda, $transfusion, $famhxunknown
            ];
            dbExecute($db, $complaint_insert, $complaint_params, $queryTracker, 'INSERT complaint');
        }

        if (!in_array('patient_chief_complaint', $tables_modified)) {
            $tables_modified[] = 'patient_chief_complaint';
        }
    }
    // ============================================
    // INSURANCE DATA
    // ============================================

    $working_pid = $is_update ? $pid : $new_pid;

    // Handle primary insurance
    if (!empty($data['insurance_provider'])
        && trim($data['insurance_provider']) !== ''
        && trim($data['insurance_provider']) !== '-- None --') {

    $exists = false;

    if ($is_update) {
        $exists = dbFetch($db,
            "SELECT id FROM insurance_data WHERE pid=? AND insurance_sequence=1 AND type='primary'",
            [$working_pid]
        );
    }

    if ($exists && $is_update) {

        // UPDATE params: matches SET columns exactly (provider, type, plan_name, policy_number,
        // group_number, date, copay_amount, accept_assignment, insurance_sequence,
        // subscriber_fname, subscriber_mname, subscriber_lname, subscriber_relationship,
        // subscriber_DOB, subscriber_sex, subscriber_ss, subscriber_phone,
        // subscriber_city, subscriber_state, subscriber_address_line_1,
        // subscriber_address_line_2, subscriber_postal_code, subscriber_street,
        // subscriber_employer, subscriber_employer_city, subscriber_employer_state,
        // subscriber_employer_postal_code, subscriber_employer_country, subscriber_country)
        // + WHERE pid  → 30 values, 30 ?s
        $params = [
            $data['insurance_provider'] ?? '',
            'primary',
            $data['plan_name'] ?? '',
            $data['policy_number'] ?? '',
            $data['group_number'] ?? '',
            !empty($data['date_start']) ? date('Y-m-d', strtotime($data['date_start'])) : null,
            $data['copay_amount'] ?? null,
            $data['accept_assignment'] ?? '',
            1,
            $data['subscriber_fname'] ?? '',
            $data['subscriber_mname'] ?? '',
            $data['subscriber_lname'] ?? '',
            $data['subscriber_relationship'] ?? '',
            !empty($data['subscriber_dob']) ? date('Y-m-d', strtotime($data['subscriber_dob'])) : null,
            $data['subscriber_sex'] ?? '',
            $data['subscriber_ssn'] ?? '',
            $data['subscriber_phone'] ?? '',
            $data['subscriber_city'] ?? '',
            $data['subscriber_state'] ?? '',
            $data['subscriber_address_line_1'] ?? '',
            $data['subscriber_address_line_2'] ?? '',
            $data['subscriber_postal_code'] ?? '',
            $data['subscriber_street'] ?? '',
            $data['subscriber_employer'] ?? '',
            $data['se_city'] ?? '',
            $data['se_state'] ?? '',
            $data['se_postal_code'] ?? '',
            $data['se_country_code'] ?? '',
            $data['subscriber_country_code'] ?? '',
            $working_pid  // WHERE pid=?
        ];

        $sql = "UPDATE insurance_data SET
            provider=?, type=?, plan_name=?, policy_number=?, group_number=?,
            date=?, copay_amount=?, accept_assignment=?, insurance_sequence=?,
            subscriber_fname=?, subscriber_mname=?, subscriber_lname=?, subscriber_relationship=?,
            subscriber_DOB=?, subscriber_sex=?, subscriber_ss=?, subscriber_phone=?,
            subscriber_city=?, subscriber_state=?, subscriber_address_line_1=?,
            subscriber_address_line_2=?, subscriber_postal_code=?, subscriber_street=?,
            subscriber_employer=?, subscriber_employer_city=?, subscriber_employer_state=?,
            subscriber_employer_postal_code=?, subscriber_employer_country=?, subscriber_country=?
        WHERE pid=? AND insurance_sequence=1 AND type='primary'";

        dbExecute($db, $sql, $params, $queryTracker, 'UPDATE insurance - primary');

    } else {

        // INSERT params: pid, type, provider, plan_name, policy_number, group_number,
        // date, copay_amount, accept_assignment, insurance_sequence,
        // subscriber_fname...subscriber_country  → 30 values, 30 ?s
        $params = [
            $working_pid,
            'primary',
            $data['insurance_provider'] ?? '',
            $data['plan_name'] ?? '',
            $data['policy_number'] ?? '',
            $data['group_number'] ?? '',
            !empty($data['date_start']) ? date('Y-m-d', strtotime($data['date_start'])) : null,
            $data['copay_amount'] ?? null,
            $data['accept_assignment'] ?? '',
            1,
            $data['subscriber_fname'] ?? '',
            $data['subscriber_mname'] ?? '',
            $data['subscriber_lname'] ?? '',
            $data['subscriber_relationship'] ?? '',
            !empty($data['subscriber_dob']) ? date('Y-m-d', strtotime($data['subscriber_dob'])) : null,
            $data['subscriber_sex'] ?? '',
            $data['subscriber_ssn'] ?? '',
            $data['subscriber_phone'] ?? '',
            $data['subscriber_city'] ?? '',
            $data['subscriber_state'] ?? '',
            $data['subscriber_address_line_1'] ?? '',
            $data['subscriber_address_line_2'] ?? '',
            $data['subscriber_postal_code'] ?? '',
            $data['subscriber_street'] ?? '',
            $data['subscriber_employer'] ?? '',
            $data['se_city'] ?? '',
            $data['se_state'] ?? '',
            $data['se_postal_code'] ?? '',
            $data['se_country_code'] ?? '',
            $data['subscriber_country_code'] ?? ''
        ];

        $sql = "INSERT INTO insurance_data (
            pid,type,provider,plan_name,policy_number,group_number,
            date,copay_amount,accept_assignment,insurance_sequence,
            subscriber_fname,subscriber_mname,subscriber_lname,subscriber_relationship,
            subscriber_DOB,subscriber_sex,subscriber_ss,subscriber_phone,
            subscriber_city,subscriber_state,subscriber_address_line_1,
            subscriber_address_line_2,subscriber_postal_code,subscriber_street,
            subscriber_employer,subscriber_employer_city,subscriber_employer_state,
            subscriber_employer_postal_code,subscriber_employer_country,subscriber_country
        ) VALUES (
            ?,?,?,?,?,?,
            ?,?,?,?,?,?,
            ?,?,?,?,?,?,
            ?,?,?,?,?,?,
            ?,?,?,?,?,?
        )";

        dbExecute($db, $sql, $params, $queryTracker, 'INSERT insurance - primary');
    }
}
    // Handle secondary insurance
    if (!empty($data['insurance_provider_secondary'])
        && trim($data['insurance_provider_secondary']) !== ''
        && trim($data['insurance_provider_secondary']) !== '-- None --') {

    $exists = false;

    if ($is_update) {
        $exists = dbFetch($db,
            "SELECT id FROM insurance_data WHERE pid=? AND insurance_sequence=2",
            [$working_pid]
        );
    }

    if ($exists && $is_update) {

        // UPDATE params: provider, type, plan_name, policy_number, group_number,
        // date, copay_amount, accept_assignment, subscriber_lname,
        // subscriber_relationship + WHERE pid  → 11 values, 11 ?s
        $params = [
            $data['insurance_provider_secondary'] ?? '',
            'secondary',
            $data['plan_name_secondary'] ?? '',
            $data['policy_number_secondary'] ?? '',
            $data['group_number_secondary'] ?? '',
            !empty($data['date_start_secondary']) ? date('Y-m-d', strtotime($data['date_start_secondary'])) : null,
            $data['copay_amount_secondary'] ?? null,
            $data['accept_assignment_secondary'] ?? '',
            $data['subscriber_name_secondary'] ?? '',
            $data['subscriber_relationship_secondary'] ?? '',
            $working_pid  // WHERE pid=?
        ];

        $sql = "UPDATE insurance_data SET
            provider=?, type=?, plan_name=?, policy_number=?, group_number=?,
            date=?, copay_amount=?, accept_assignment=?,
            subscriber_lname=?, subscriber_relationship=?
        WHERE pid=? AND insurance_sequence=2";

        dbExecute($db, $sql, $params, $queryTracker, 'UPDATE insurance - secondary');

    } else {

        // INSERT params: pid, type, provider, plan_name, policy_number, group_number,
        // date, copay_amount, accept_assignment, insurance_sequence,
        // subscriber_lname, subscriber_relationship  → 12 values, 12 ?s
        $params = [
            $working_pid,
            'secondary',
            $data['insurance_provider_secondary'] ?? '',
            $data['plan_name_secondary'] ?? '',
            $data['policy_number_secondary'] ?? '',
            $data['group_number_secondary'] ?? '',
            !empty($data['date_start_secondary']) ? date('Y-m-d', strtotime($data['date_start_secondary'])) : null,
            $data['copay_amount_secondary'] ?? null,
            $data['accept_assignment_secondary'] ?? '',
            2,
            $data['subscriber_name_secondary'] ?? '',
            $data['subscriber_relationship_secondary'] ?? ''
        ];

        $sql = "INSERT INTO insurance_data (
            pid,type,provider,plan_name,policy_number,group_number,
            date,copay_amount,accept_assignment,insurance_sequence,
            subscriber_lname,subscriber_relationship
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";

        dbExecute($db, $sql, $params, $queryTracker, 'INSERT insurance - secondary');
    }
}
    // Handle tertiary insurance
    if (!empty($data['insurance_provider_tertiary'])
        && trim($data['insurance_provider_tertiary']) !== ''
        && trim($data['insurance_provider_tertiary']) !== '-- None --') {

    $exists = false;

    if ($is_update) {
        $exists = dbFetch($db,
            "SELECT id FROM insurance_data WHERE pid=? AND insurance_sequence=3",
            [$working_pid]
        );
    }

    if ($exists && $is_update) {

        // UPDATE params: provider, type, plan_name, policy_number, group_number,
        // date, copay_amount, accept_assignment + WHERE pid  → 9 values, 9 ?s
        $params = [
            $data['insurance_provider_tertiary'] ?? '',
            'tertiary',
            $data['plan_name_tertiary'] ?? '',
            $data['policy_number_tertiary'] ?? '',
            $data['group_number_tertiary'] ?? '',
            !empty($data['date_start_tertiary']) ? date('Y-m-d', strtotime($data['date_start_tertiary'])) : null,
            $data['copay_amount_tertiary'] ?? null,
            $data['accept_assignment_tertiary'] ?? '',
            $working_pid  // WHERE pid=?
        ];

        $sql = "UPDATE insurance_data SET
            provider=?, type=?, plan_name=?, policy_number=?, group_number=?,
            date=?, copay_amount=?, accept_assignment=?
        WHERE pid=? AND insurance_sequence=3";

        dbExecute($db, $sql, $params, $queryTracker, 'UPDATE insurance - tertiary');

    } else {

        // INSERT params: pid, type, provider, plan_name, policy_number, group_number,
        // date, copay_amount, accept_assignment, insurance_sequence,
        // subscriber_lname, subscriber_relationship  → 12 values, 12 ?s
        $params = [
            $working_pid,
            'tertiary',
            $data['insurance_provider_tertiary'] ?? '',
            $data['plan_name_tertiary'] ?? '',
            $data['policy_number_tertiary'] ?? '',
            $data['group_number_tertiary'] ?? '',
            !empty($data['date_start_tertiary']) ? date('Y-m-d', strtotime($data['date_start_tertiary'])) : null,
            $data['copay_amount_tertiary'] ?? null,
            $data['accept_assignment_tertiary'] ?? '',
            3,
            '',  // subscriber_lname
            ''   // subscriber_relationship
        ];

        $sql = "INSERT INTO insurance_data (
            pid,type,provider,plan_name,policy_number,group_number,
            date,copay_amount,accept_assignment,insurance_sequence,
            subscriber_lname,subscriber_relationship
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";

        dbExecute($db, $sql, $params, $queryTracker, 'INSERT insurance - tertiary');
    }
}
    // ============================================
    // CHOICES & PREFERENCES DATA
    // ============================================

    // Check if there's any choices & preferences data to save
    $choices_fields = [
         'provider_since_date', 'referring_provider', 'preferred_pharmacy',
        'hipaa_notice_received', 'allow_voice_message', 'voice_message_with', 'allow_mail_message',
        'allow_sms_text', 'allow_email_message', 'allow_patient_portal', 'allow_imm_reg_use',
        'allow_imm_info_share', 'allow_health_info_ex', 'cmsportal_login',
        'imm_reg_status', 'imm_reg_stat_effdate', 'publicity_code', 'publ_code_eff_date',
        'protect_indicator', 'prot_indi_effdate', 'care_team_provider', 'care_team_status', 'patient_category'
    ];

    $has_choices_data = false;
    $choices_data = [];
    foreach ($choices_fields as $field) {
        if (!empty($data[$field])) {
            $has_choices_data = true;
            $choices_data[$field] = $data[$field];
        }
    }

    if ($has_choices_data) {
        if ($is_update) {
            // Check if choices preferences record exists
            $checkStmt = $db->prepare("SELECT id FROM patient_choices_preferences WHERE pid = ? LIMIT 1");
            $checkStmt->bind_param("i", $working_pid);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $choicesExists = $checkResult->num_rows > 0;

            if ($choicesExists) {
                // Update existing choices preferences
                $updateCols = [];
                $updateParams = [];
                foreach ($choices_data as $col => $val) {
                    if ($col === 'provider_since_date' || $col === 'imm_reg_stat_effdate' || $col === 'publ_code_eff_date' || $col === 'prot_indi_effdate') {
                        // Handle date fields
                        $updateCols[] = "`$col` = ?";
                        $updateParams[] = !empty($val) ? date('Y-m-d', strtotime($val)) : null;
                    } else {
                        $updateCols[] = "`$col` = ?";
                        $updateParams[] = trim($val);
                    }
                }
                $updateCols[] = "`date_modified` = NOW()";
                // NOTE: date_modified = NOW() has NO placeholder, so don't add a parameter for it

                // Build the SQL with placeholders first
                $set_clause = implode(', ', $updateCols);
                $update_sql = "UPDATE patient_choices_preferences SET " . $set_clause . " WHERE pid = ?";

                // Now add the WHERE parameter
                $updateParams[] = $working_pid;

                // Safety check: count placeholders in SET clause (should match number of update params - 1 for pid)
                $placeholder_count = substr_count($set_clause, '?');
                $param_count_without_pid = count($updateParams) - 1;

                if ($placeholder_count === $param_count_without_pid) {
                    dbExecute($db, $update_sql, $updateParams, $queryTracker, 'UPDATE choices - main');
                } else {
                    throw new Exception("Parameter mismatch for patient_choices_preferences UPDATE: {$placeholder_count} placeholders but {$param_count_without_pid} parameters provided");
                }
            } else {
                // Insert new choices preferences
                $insert_cols = ['pid'];
                $insert_vals = ['?'];
                $insert_params = [$working_pid];

                foreach ($choices_data as $col => $val) {
                    $insert_cols[] = "`$col`";
                    $insert_vals[] = "?";
                    if ($col === 'provider_since_date' || $col === 'imm_reg_stat_effdate' || $col === 'publ_code_eff_date' || $col === 'prot_indi_effdate') {
                        $insert_params[] = !empty($val) ? date('Y-m-d', strtotime($val)) : null;
                    } else {
                        $insert_params[] = trim($val);
                    }
                }

                $insert_sql = "INSERT INTO patient_choices_preferences (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_vals) . ")";
                dbExecute($db, $insert_sql, $insert_params, $queryTracker, 'INSERT choices - main'); 
            }

            if (!in_array('patient_choices_preferences', $tables_modified)) {
                $tables_modified[] = 'patient_choices_preferences';
            }
        } else {
            // Insert new choices preferences (add mode)
            $insert_cols = ['pid'];
            $insert_vals = ['?'];
            $insert_params = [$working_pid];

            foreach ($choices_data as $col => $val) {
                $insert_cols[] = "`$col`";
                $insert_vals[] = "?";
                if ($col === 'provider_since_date' || $col === 'imm_reg_stat_effdate' || $col === 'publ_code_eff_date' || $col === 'prot_indi_effdate') {
                    $insert_params[] = !empty($val) ? date('Y-m-d', strtotime($val)) : null;
                } else {
                    $insert_params[] = trim($val);
                }
            }

            $insert_sql = "INSERT INTO patient_choices_preferences (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_vals) . ")";
            dbExecute($db, $insert_sql, $insert_params, $queryTracker, 'INSERT choices - main'); 
            $tables_modified[] = 'patient_choices_preferences';
        }
    }
    // ============================================
    // LISTS DATA - Medical Problems, Medications, Allergies, Surgeries, Family History
    // ============================================

    // For update mode, delete old lists entries and add new ones
    if ($is_update) {
        $delete_types = ['condition', 'medication', 'drug_allergy', 'food_allergy', 'surgery', 'family_history'];
        foreach ($delete_types as $type) {
            dbExecute($db, "DELETE FROM lists WHERE pid = ? AND type = ?", [$working_pid, $type], $queryTracker, 'delete from lists');
        }
    }

    // Medical Conditions
    if (!empty($data['conditions']) && is_array($data['conditions'])) {
        foreach ($data['conditions'] as $condition) {
            $condition = trim($condition);
            if (!empty($condition)) {
                dbExecute(
                    $db,
                    "INSERT INTO lists (pid, type, title, activity, date, user, groupname, verification) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)",
                    [$working_pid, 'condition', $condition, 1, 'admin', 'Default', 'unconfirmed'],
                    $queryTracker,
                    'insert into lists'
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
        }
    }
    // Medications
    if (!empty($data['medications']) && is_array($data['medications'])) {
        foreach ($data['medications'] as $med) {
            if (!empty($med['name'])) {
                $comments = '';
                if (!empty($med['dose'])) $comments .= "Dose: {$med['dose']}";
                if (!empty($med['frequency'])) $comments .= (!empty($comments) ? ', ' : '') . "Frequency: {$med['frequency']}";
                if (!empty($med['route'])) $comments .= (!empty($comments) ? ', ' : '') . "Route: {$med['route']}";
                if (!empty($med['prescriber'])) $comments .= (!empty($comments) ? ', ' : '') . "Prescriber: {$med['prescriber']}";

                dbExecute(
                    $db,
                    "INSERT INTO lists (pid, type, title, activity, comments, date, user, groupname) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)",
                    [$working_pid, 'medication', $med['name'], 1, $comments, 'admin', 'Default'],
                    $queryTracker,
                    "insert into lists - medications"
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
        }
    }
    // Drug Allergies
    if (!empty($data['allergies_drug']) && is_array($data['allergies_drug'])) {
        foreach ($data['allergies_drug'] as $allergy) {
            if (!empty($allergy['name'])) {
                dbExecute(
                    $db,
                    "INSERT INTO lists (pid, type, title, reaction, severity_al, activity, date, user, groupname, verification) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)",
                    [$working_pid, 'drug_allergy', $allergy['name'], $allergy['reaction'] ?? '', $allergy['severity'] ?? '', 1, 'admin', 'Default', 'unconfirmed'],
                    $queryTracker,
                    "insert into lists - drug allergies"
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
        }
    }
    // Food Allergies
    if (!empty($data['allergies_food']) && is_array($data['allergies_food'])) {
        foreach ($data['allergies_food'] as $allergy) {
            if (!empty($allergy['name'])) {
                dbExecute(
                    $db,
                    "INSERT INTO lists (pid, type, title, reaction, severity_al, activity, date, user, groupname, verification) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)",
                    [$working_pid, 'food_allergy', $allergy['name'], $allergy['reaction'] ?? '', $allergy['severity'] ?? '', 1, 'admin', 'Default', 'unconfirmed'],
                    $queryTracker,
                    "insert into lists - food allergies"
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
        }
    }
    // Surgical History
    if (!empty($data['surgeries']) && is_array($data['surgeries'])) {
        foreach ($data['surgeries'] as $surgery) {
            if (!empty($surgery['procedure'])) {
                $comments = '';
                if (!empty($surgery['year'])) $comments .= "Year: {$surgery['year']}";
                if (!empty($surgery['facility'])) $comments .= (!empty($comments) ? ', ' : '') . "Facility: {$surgery['facility']}";
                if (!empty($surgery['complications'])) $comments .= (!empty($comments) ? ', ' : '') . "Complications: {$surgery['complications']}";

                dbExecute(
                    $db,
                    "INSERT INTO lists (pid, type, title, activity, comments, date, user, groupname) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)",
                    [$working_pid, 'surgery', $surgery['procedure'], 1, $comments, 'admin', 'Default'],
                    $queryTracker,
                    "inserting surgery {$surgery['procedure']} for patient {$working_pid}"
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
        }
    }
    // Family Health History
    if (!empty($data['family_history']) && is_array($data['family_history'])) {
        foreach ($data['family_history'] as $fam_history) {
            if (!empty($fam_history['condition'])) {
                $comments = '';
                if (!empty($fam_history['relationship'])) $comments .= "Relationship: {$fam_history['relationship']}";
                if (!empty($fam_history['age'])) $comments .= (!empty($comments) ? ', ' : '') . "Age: {$fam_history['age']}";

                dbExecute(
                    $db,
                    "INSERT INTO lists (pid, type, title, activity, comments, date, user, groupname) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)",
                    [$working_pid, 'family_history', $fam_history['condition'], 1, $comments, 'admin', 'Default'],
                    $queryTracker,
                    "insert family history for patient {$working_pid}"
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
        }
    }
    // ============================================
    // REVIEW OF SYSTEMS (ROS)
    // ============================================
    if (!empty($data['ros_symptoms']) && is_array($data['ros_symptoms'])) {
        $ros_value = implode('|', array_map('trim', $data['ros_symptoms']));

        $checkStmt = $db->prepare("SELECT id FROM patient_review_of_systems WHERE pid = ? LIMIT 1");
        $checkStmt->bind_param("i", $working_pid);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $rosExists = $checkResult->num_rows > 0;
        $checkStmt->close();

        if ($rosExists) {
            dbExecute($db, "UPDATE patient_review_of_systems SET symptoms = ?, date_modified = NOW() WHERE pid = ?",
                [$ros_value, $working_pid], $queryTracker, 'UPDATE review_of_systems');
        } else {
            dbExecute($db, "INSERT INTO patient_review_of_systems (pid, symptoms, date_created, date_modified) VALUES (?, ?, NOW(), NOW())",
                [$working_pid, $ros_value], $queryTracker, 'INSERT review_of_systems');
        }
        if (!in_array('patient_review_of_systems', $tables_modified)) {
            $tables_modified[] = 'patient_review_of_systems';
        }
    } elseif ($is_update) {
        // Clear ROS on update if no symptoms were submitted
        @$db->query("UPDATE patient_review_of_systems SET symptoms = '', date_modified = NOW() WHERE pid = " . intval($working_pid));
    }

    // ============================================
    // RELATED PERSONS
    // ============================================

     if ($is_update) {
                    @$db->query("DELETE FROM patient_related_persons WHERE pid = " . intval($working_pid));
                }
    if (!empty($data['related_persons']) && is_array($data['related_persons'])) {
        try {
            // Check if table exists
            $table_check = @$db->query("SHOW TABLES LIKE 'patient_related_persons'");
            if ($table_check && $table_check->num_rows > 0) {
                // Delete existing related persons in edit mode
                if ($is_update) {
                    @$db->query("DELETE FROM patient_related_persons WHERE pid = " . intval($working_pid));
                }

                // Insert each related person using dbExecute
                $user_id = 1;
                $now = date('Y-m-d H:i:s');

                foreach ($data['related_persons'] as $person) {
                    if (!empty($person['full_name'])) {
                        $full_name = trim($person['full_name'] ?? '');
                        $relationship = trim($person['relationship'] ?? '');
                        $sex = trim($person['sex'] ?? '');
                        $phone = trim($person['phone'] ?? '');
                        $email = trim($person['email'] ?? '');
                        $city = trim($person['city'] ?? '');

                        // Use consistent dbExecute helper
                        $insert_sql = "INSERT INTO patient_related_persons (pid, full_name, relationship, sex, phone, email, city, user_id, date_created, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insert_params = [$working_pid, $full_name, $relationship, $sex, $phone, $email, $city, $user_id, $now, $now];

                        dbExecute($db, $insert_sql, $insert_params, $queryTracker, 'INSERT related persons - main'); 
                    }
                }

                if (!in_array('patient_related_persons', $tables_modified)) {
                    $tables_modified[] = 'patient_related_persons';
                }
            }
        } catch (Exception $e) {
            error_log("Related persons processing error: " . $e->getMessage());
            // Continue processing, don't fail the whole request
        }
    }

// ============================================
// PATIENT SOCIAL HISTORY - Save to separate table
// ============================================

if (!empty($data['tobacco']) ||                    // ✅ FIXED
    !empty($data['alcohol']) ||                    // ✅ FIXED
    !empty($data['drugs']) ||                      // ✅ FIXED
    !empty($data['exercise_frequency']) ||
    !empty($data['living_situation']) ||
    !empty($data['education_level']) ||
    !empty($data['dvsafety']) ||                   // ✅ FIXED (was domestic_violence_safe)
    !empty($data['seatbelt']) ||                   // ✅ FIXED (was seatbelt_use)
    !empty($data['firearms']) ||                   // ✅ FIXED (was firearms_in_home)
    !empty($data['social_history_notes'])) {

    // Collect all social history data
    $social_history = [
        'pid' => $working_pid,
        'tobacco_status' => $data['tobacco'] ?? null,
        'tobacco_product_type' => $data['tobacco_product_type'] ?? null,
        'tobacco_amount' => $data['tobacco_amount'] ?? null,
        'tobacco_years' => $data['tobacco_years'] ?? null,
        'tobacco_quit_date' => !empty($data['tobacco_quit_date']) ? date('Y-m-d', strtotime($data['tobacco_quit_date'])) : null,
        'tobacco_cessation_interest' => $data['tobacco_cessation_interest'] ?? null,
        
        'alcohol_status' => $data['alcohol'] ?? null,
        'alcohol_drinks_per_week' => !empty($data['alcohol_drinks_per_week']) ? intval($data['alcohol_drinks_per_week']) : null,
        'alcohol_type' => $data['alcohol_type'] ?? null,
        'audit_score' => $data['audit'] ?? null,
        
        'drug_status' => $data['drugs'] ?? null,
        'substances_used' => $data['substances_used'] ?? null,
        'drug_frequency' => $data['drug_frequency'] ?? null,
        'drug_treatment_interest' => $data['drug_treatment_interest'] ?? null,
        
        'exercise_frequency' => $data['exercise_frequency'] ?? null,
        'exercise_type' => $data['exercise_type'] ?? null,
        'exercise_minutes' => !empty($data['exercise_minutes']) ? intval($data['exercise_minutes']) : null,
        'diet_type' => $data['diet_type'] ?? null,
        'water_intake' => $data['water_intake'] ?? null,
        'sleep_hours' => !empty($data['sleep_hours']) ? intval($data['sleep_hours']) : null,
        
        'living_situation' => $data['living_situation'] ?? null,
        'education_level' => $data['education_level'] ?? null,
        'occupation_category' => $data['occupation_category'] ?? null,
        'domestic_violence_safe' => $data['dvsafety'] ?? null,
        'seatbelt_use' => $data['seatbelt'] ?? null,
        'firearms_in_home' => $data['firearms'] ?? null,
        
        'social_history_notes' => $data['social_history_notes'] ?? null,
        'created_by' => $user_id ?? null
    ];

    // [Rest of UPDATE/INSERT code remains the same]

    // Check if social history record exists for this patient
    $checkStmt = $db->prepare("SELECT id FROM patient_social_history WHERE pid = ? LIMIT 1");
    $checkStmt->bind_param("i", $working_pid);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $socialHistoryExists = $checkResult->num_rows > 0;
    $checkStmt->close();

    if ($socialHistoryExists) {
        // UPDATE existing record
        $updateSql = "UPDATE patient_social_history SET 
            tobacco_status = ?,
            tobacco_product_type = ?,
            tobacco_amount = ?,
            tobacco_years = ?,
            tobacco_quit_date = ?,
            tobacco_cessation_interest = ?,
            alcohol_status = ?,
            alcohol_drinks_per_week = ?,
            alcohol_type = ?,
            audit_score = ?,
            drug_status = ?,
            substances_used = ?,
            drug_frequency = ?,
            drug_treatment_interest = ?,
            exercise_frequency = ?,
            exercise_type = ?,
            exercise_minutes = ?,
            diet_type = ?,
            water_intake = ?,
            sleep_hours = ?,
            living_situation = ?,
            education_level = ?,
            occupation = ?,
            domestic_violence_safe = ?,
            seatbelt_use = ?,
            firearms_in_home = ?,
            social_history_notes = ?,
            date_modified = NOW(),
            created_by = ?
            WHERE pid = ?";

        $updateParams = [
            $social_history['tobacco_status'],
            $social_history['tobacco_product_type'],
            $social_history['tobacco_amount'],
            $social_history['tobacco_years'],
            $social_history['tobacco_quit_date'],
            $social_history['tobacco_cessation_interest'],
            $social_history['alcohol_status'],
            $social_history['alcohol_drinks_per_week'],
            $social_history['alcohol_type'],
            $social_history['audit_score'],
            $social_history['drug_status'],
            $social_history['substances_used'],
            $social_history['drug_frequency'],
            $social_history['drug_treatment_interest'],
            $social_history['exercise_frequency'],
            $social_history['exercise_type'],
            $social_history['exercise_minutes'],
            $social_history['diet_type'],
            $social_history['water_intake'],
            $social_history['sleep_hours'],
            $social_history['living_situation'],
            $social_history['education_level'],
            $social_history['occupation_category'],
            $social_history['domestic_violence_safe'],
            $social_history['seatbelt_use'],
            $social_history['firearms_in_home'],
            $social_history['social_history_notes'],
            $social_history['created_by'],
            $working_pid
        ];

        dbExecute($db, $updateSql, $updateParams, $queryTracker, 'UPDATE patient_social_history');

    } else {
        // INSERT new record
        $insertSql = "INSERT INTO patient_social_history (
            pid,
            tobacco_status,
            tobacco_product_type,
            tobacco_amount,
            tobacco_years,
            tobacco_quit_date,
            tobacco_cessation_interest,
            alcohol_status,
            alcohol_drinks_per_week,
            alcohol_type,
            audit_score,
            drug_status,
            substances_used,
            drug_frequency,
            drug_treatment_interest,
            exercise_frequency,
            exercise_type,
            exercise_minutes,
            diet_type,
            water_intake,
            sleep_hours,
            living_situation,
            education_level,
            occupation,
            domestic_violence_safe,
            seatbelt_use,
            firearms_in_home,
            social_history_notes,
            created_by,
            date_created
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $insertParams = [
            $working_pid,
            $social_history['tobacco_status'],
            $social_history['tobacco_product_type'],
            $social_history['tobacco_amount'],
            $social_history['tobacco_years'],
            $social_history['tobacco_quit_date'],
            $social_history['tobacco_cessation_interest'],
            $social_history['alcohol_status'],
            $social_history['alcohol_drinks_per_week'],
            $social_history['alcohol_type'],
            $social_history['audit_score'],
            $social_history['drug_status'],
            $social_history['substances_used'],
            $social_history['drug_frequency'],
            $social_history['drug_treatment_interest'],
            $social_history['exercise_frequency'],
            $social_history['exercise_type'],
            $social_history['exercise_minutes'],
            $social_history['diet_type'],
            $social_history['water_intake'],
            $social_history['sleep_hours'],
            $social_history['living_situation'],
            $social_history['education_level'],
            $social_history['occupation_category'],
            $social_history['domestic_violence_safe'],
            $social_history['seatbelt_use'],
            $social_history['firearms_in_home'],
            $social_history['social_history_notes'],
            $social_history['created_by']
        ];

        dbExecute($db, $insertSql, $insertParams, $queryTracker, 'INSERT patient_social_history');
    }

    if (!in_array('patient_social_history', $tables_modified)) {
        $tables_modified[] = 'patient_social_history';
    }
}

    // ============================================
    // DEMOGRAPHICS & SOCIAL DATA
    // ============================================

    // Check if there's any demographics & social data to save
    $demographics_fields = [
        'primary_language', 'ethnicity', 'race', 'nationality_country', 'interpreter_needed', 'interpreter_dialect_notes',
        'financial_review_date', 'monthly_income', 'household_size', 'homeless', 'migrantseasonal', 'referral_source',
        'vfc_eligibility_status', 'religion', 'tribal_affiliations'
    ];

    $has_demographics_data = false;
    $demographics_data = [];
    foreach ($demographics_fields as $field) {
        if (!empty($data[$field])) {
            $has_demographics_data = true;
            $demographics_data[$field] = $data[$field];
        }
    }

    if ($has_demographics_data) {
        if ($is_update) {
            // Check if demographics record exists
            $checkStmt = $db->prepare("SELECT id FROM patient_demographics_social WHERE pid = ? LIMIT 1");
            $checkStmt->bind_param("i", $working_pid);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $demographicsExists = $checkResult->num_rows > 0;

            if ($demographicsExists) {
                // Update existing demographics
                $updateCols = [];
                $updateParams = [];
                foreach ($demographics_data as $col => $val) {
                    if ($col === 'financial_review_date') {
                        // Handle date fields
                        $updateCols[] = "`$col` = ?";
                        $updateParams[] = !empty($val) ? date('Y-m-d', strtotime($val)) : null;
                    } else if ($col === 'household_size') {
                        // Handle numeric fields
                        $updateCols[] = "`$col` = ?";
                        $updateParams[] = !empty($val) ? intval($val) : null;
                    } else {
                        $updateCols[] = "`$col` = ?";
                        $updateParams[] = trim($val);
                    }
                }
                $updateCols[] = "`date_modified` = NOW()";
                // NOTE: date_modified = NOW() has NO placeholder, so don't add a parameter for it

                // Build the SQL with placeholders first
                $set_clause = implode(', ', $updateCols);
                $update_sql = "UPDATE patient_demographics_social SET " . $set_clause . " WHERE pid = ?";

                // Now add the WHERE parameter
                $updateParams[] = $working_pid;

                // Safety check: count placeholders in SET clause
                $placeholder_count = substr_count($set_clause, '?');
                $param_count_without_pid = count($updateParams) - 1;

                if ($placeholder_count === $param_count_without_pid) {
                    dbExecute($db, $update_sql, $updateParams, $queryTracker, 'UPDATE demographics - main');
                } else {
                    throw new Exception("Parameter mismatch for patient_demographics_social UPDATE: {$placeholder_count} placeholders but {$param_count_without_pid} parameters provided");
                }
            } else {
                // Insert new demographics
                $insert_cols = ['pid'];
                $insert_vals = ['?'];
                $insert_params = [$working_pid];

                foreach ($demographics_data as $col => $val) {
                    $insert_cols[] = "`$col`";
                    $insert_vals[] = "?";
                    if ($col === 'financial_review_date') {
                        $insert_params[] = !empty($val) ? date('Y-m-d', strtotime($val)) : null;
                    } else if ($col === 'household_size') {
                        $insert_params[] = !empty($val) ? intval($val) : null;
                    } else {
                        $insert_params[] = trim($val);
                    }
                }

                $insert_sql = "INSERT INTO patient_demographics_social (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_vals) . ")";
                dbExecute($db, $insert_sql, $insert_params, $queryTracker, 'INSERT demographics - main'); 
            }

            if (!in_array('patient_demographics_social', $tables_modified)) {
                $tables_modified[] = 'patient_demographics_social';
            }
        } else {
            // Insert new demographics (add mode)
            $insert_cols = ['pid'];
            $insert_vals = ['?'];
            $insert_params = [$working_pid];

            foreach ($demographics_data as $col => $val) {
                $insert_cols[] = "`$col`";
                $insert_vals[] = "?";
                if ($col === 'financial_review_date') {
                    $insert_params[] = !empty($val) ? date('Y-m-d', strtotime($val)) : null;
                } else if ($col === 'household_size') {
                    $insert_params[] = !empty($val) ? intval($val) : null;
                } else {
                    $insert_params[] = trim($val);
                }
            }

            $insert_sql = "INSERT INTO patient_demographics_social (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_vals) . ")";
            dbExecute($db, $insert_sql, $insert_params, $queryTracker, 'INSERT demographics - main'); 
            $tables_modified[] = 'patient_demographics_social';
        }
    }

    // ============================================
    // PATIENT CONSENT & AUTHORIZATION
    // ============================================

    if (!empty($data['consent_to_treatment']) ||
        !empty($data['financial_responsibility_consent']) ||
        !empty($data['digital_communications_consent']) ||
        !empty($data['signature_printed_name']) ||
        !empty($data['signature_date']) ||
        !empty($data['signature_relationship']) ||
        !empty($data['signature_digital']) ||
        !empty($data['consentAuthorization_referral_source']) ||
        !empty($data['referring_provider_name'])) {

        // Check if consent record exists for this patient
        $checkStmt = $db->prepare("SELECT id FROM patient_consent_authorization WHERE pid = ? LIMIT 1");
        $checkStmt->bind_param("i", $working_pid);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $consentExists = $checkResult->num_rows > 0;
        $checkStmt->close();

        if ($consentExists) {
            // UPDATE existing record
            $updateSql = "UPDATE patient_consent_authorization SET
                consent_to_treatment = ?,
                financial_responsibility_consent = ?,
                digital_communications_consent = ?,
                signature_printed_name = ?,
                signature_date = ?,
                signature_relationship = ?,
                signature_digital = ?,
                referral_source = ?,
                referring_provider_name = ?,
                date_modified = NOW()
                WHERE pid = ?";

            $updateParams = [
                isset($data['consent_to_treatment']) && $data['consent_to_treatment'] ? 1 : 0,
                isset($data['financial_responsibility_consent']) && $data['financial_responsibility_consent'] ? 1 : 0,
                isset($data['digital_communications_consent']) && $data['digital_communications_consent'] ? 1 : 0,
                $data['signature_printed_name'] ?? null,
                !empty($data['signature_date']) ? date('Y-m-d', strtotime($data['signature_date'])) : null,
                $data['signature_relationship'] ?? null,
                $data['signature_digital'] ?? null,
                $data['consentAuthorization_referral_source'] ?? null,
                $data['referring_provider_name'] ?? null,
                $working_pid
            ];

            dbExecute($db, $updateSql, $updateParams, $queryTracker, 'UPDATE patient_consent_authorization');
        } else {
            // INSERT new record
            $insertSql = "INSERT INTO patient_consent_authorization (
                pid,
                consent_to_treatment,
                financial_responsibility_consent,
                digital_communications_consent,
                signature_printed_name,
                signature_date,
                signature_relationship,
                signature_digital,
                referral_source,
                referring_provider_name,
                created_by,
                date_created
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $insertParams = [
                $working_pid,
                isset($data['consent_to_treatment']) && $data['consent_to_treatment'] ? 1 : 0,
                isset($data['financial_responsibility_consent']) && $data['financial_responsibility_consent'] ? 1 : 0,
                isset($data['digital_communications_consent']) && $data['digital_communications_consent'] ? 1 : 0,
                $data['signature_printed_name'] ?? null,
                !empty($data['signature_date']) ? date('Y-m-d', strtotime($data['signature_date'])) : null,
                $data['signature_relationship'] ?? null,
                $data['signature_digital'] ?? null,
                $data['consentAuthorization_referral_source'] ?? null,
                $data['referring_provider_name'] ?? null,
                $user_id ?? null
            ];

            dbExecute($db, $insertSql, $insertParams, $queryTracker, 'INSERT patient_consent_authorization');
        }

        if (!in_array('patient_consent_authorization', $tables_modified)) {
            $tables_modified[] = 'patient_consent_authorization';
        }
    }

    // Clear output buffer and return JSON
    ob_end_clean();

    echo json_encode([
        'success' => true,
        'status' => $is_update ? 'updated' : 'created',
        'mode' => $is_update ? 'update' : 'add',
        'message' => $is_update ? 'Patient information updated successfully' : 'Patient intake form submitted successfully',
        'pid' => $working_pid,
        'timestamp' => date('Y-m-d H:i:s'),
        'tables_updated' => $tables_modified
    ]);

} catch (Exception $e) {
    // Clear output buffer to prevent any HTML from being sent
    @ob_end_clean();

        $last_query = $queryTracker->getLastQuery();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'last_query_attempted' => $last_query,
            'all_tracked_queries' => $queryTracker->getQueries()
        ]
    ]);

    error_log("Patient save error: " . $e->getMessage());
} catch (Throwable $t) {
    // Catch any other errors (PHP 7+)
    @ob_end_clean();

            $last_query = $queryTracker->getLastQuery();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => ($t->getMessage())?$t->getMessage():$last_query,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'last_query_attempted' => $last_query,
            'all_tracked_queries' => $queryTracker->getQueries()
        ]
    ]);

    error_log("Patient save throwable error: " . $t->getMessage());
} finally {
    // Restore error handler
    restore_error_handler();
}

?>