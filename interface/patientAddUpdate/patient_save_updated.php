<?php
/**
 * Patient Data Save Handler - Add & Update Support
 * Handles both new patient creation (INSERT) and existing patient updates (UPDATE)
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @copyright Copyright (c) 2026
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Set JSON response header FIRST - BEFORE ANY OUTPUT
$ignoreAuth = true;
require(__DIR__.'/../globals.php');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Disable error display to prevent HTML from mixing with JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any unwanted output
ob_start();

try {
    // Direct database connection
    $db = $GLOBALS['dbh'] ?? null;

    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }

    $db->set_charset("utf8mb4");

    // Helper function for parameterized queries
    function dbExecute($db, $query, $params = []) {
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        return $stmt;
    }

    // Helper function for SELECT queries
    function dbFetch($db, $query, $params = []) {
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Get POST data
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $data = !empty($jsonData) ? $jsonData : $_POST;

    if (empty($data)) {
        throw new Exception("No data provided");
    }

    // Check if we're updating an existing patient or creating a new one
    $pid = isset($data['pid']) ? intval($data['pid']) : null;
    $is_update = !empty($pid);

    // Validate required fields
    $required_fields = ['fname', 'lname', 'DOB', 'sex', 'phone_cell', 'email'];
    foreach ($required_fields as $field) {
        if (empty($data[$field] ?? null)) {
            throw new Exception("Required field missing: $field");
        }
    }

    // ============================================
    // PREPARE PATIENT DATA
    // ============================================

    $patientdata = [];

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

    // DOB
    if (!empty($data['DOB'])) {
        $patientdata['DOB'] = date('Y-m-d', strtotime($data['DOB']));
    }

    // Gender/Sex
    if (!empty($data['sex'])) $patientdata['sex'] = trim($data['sex']);
    if (!empty($data['gender_identity'])) $patientdata['gender_identity'] = trim($data['gender_identity']);
    if (!empty($data['pronoun'])) $patientdata['pronoun'] = trim($data['pronoun']);
    if (!empty($data['sexual_orientation'])) $patientdata['sexual_orientation'] = trim($data['sexual_orientation']);

    // Contact
    if (!empty($data['street'])) $patientdata['street'] = trim($data['street']);
    if (!empty($data['street_line_2'])) $patientdata['street_line_2'] = trim($data['street_line_2']);
    if (!empty($data['city'])) $patientdata['city'] = trim($data['city']);
    if (!empty($data['state'])) $patientdata['state'] = trim($data['state']);
    if (!empty($data['postal_code'])) $patientdata['postal_code'] = trim($data['postal_code']);
    $patientdata['country_code'] = !empty($data['country_code']) ? trim($data['country_code']) : 'USA';
    if (!empty($data['county'])) $patientdata['county'] = trim($data['county']);

    // Phone/Email
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

    // Demographics
    if (!empty($data['race'])) $patientdata['race'] = trim($data['race']);
    if (!empty($data['ethnicity'])) $patientdata['ethnicity'] = trim($data['ethnicity']);
    if (!empty($data['language'])) $patientdata['language'] = trim($data['language']);
    if (!empty($data['religion'])) $patientdata['religion'] = trim($data['religion']);
    if (!empty($data['nationality_country'])) $patientdata['nationality_country'] = trim($data['nationality_country']);
    if (!empty($data['interpreter_needed'])) $patientdata['interpreter_needed'] = trim($data['interpreter_needed']);
    if (!empty($data['interpreter_dialect_notes'])) $patientdata['interpreter_dialect_notes'] = trim($data['interpreter_dialect_notes']);
    if (!empty($data['household_size'])) $patientdata['household_size'] = intval($data['household_size']);
    if (!empty($data['family_size'])) $patientdata['family_size'] = trim($data['family_size']);
    if (!empty($data['monthly_income'])) $patientdata['monthly_income'] = trim($data['monthly_income']);

    // Healthcare
    if (!empty($data['providerID'])) $patientdata['providerID'] = intval($data['providerID']);
    if (!empty($data['provider_since_date'])) {
        $patientdata['provider_since_date'] = date('Y-m-d', strtotime($data['provider_since_date']));
    }
    if (!empty($data['referring_provider'])) $patientdata['referring_provider'] = trim($data['referring_provider']);
    if (!empty($data['preferred_pharmacy'])) $patientdata['preferred_pharmacy'] = trim($data['preferred_pharmacy']);
    if (!empty($data['patient_category'])) $patientdata['patient_category'] = trim($data['patient_category']);

    // Communication Preferences
    if (!empty($data['allow_voice_message'])) $patientdata['allow_voice_message'] = trim($data['allow_voice_message']);
    if (!empty($data['voice_message_with'])) $patientdata['voice_message_with'] = trim($data['voice_message_with']);
    if (!empty($data['allow_mail_message'])) $patientdata['allow_mail_message'] = trim($data['allow_mail_message']);
    if (!empty($data['allow_sms_text'])) $patientdata['allow_sms_text'] = trim($data['allow_sms_text']);
    if (!empty($data['allow_email_message'])) $patientdata['allow_email_message'] = trim($data['allow_email_message']);
    if (!empty($data['allow_patient_portal_notification'])) $patientdata['allow_patient_portal_notification'] = trim($data['allow_patient_portal_notification']);
    if (!empty($data['allow_patient_portal'])) $patientdata['allow_patient_portal'] = trim($data['allow_patient_portal']);
    if (!empty($data['allow_imm_reg_use'])) $patientdata['allow_imm_reg_use'] = trim($data['allow_imm_reg_use']);
    if (!empty($data['allow_imm_info_share'])) $patientdata['allow_imm_info_share'] = trim($data['allow_imm_info_share']);
    if (!empty($data['allow_health_info_ex'])) $patientdata['allow_health_info_ex'] = trim($data['allow_health_info_ex']);
    if (!empty($data['cmsportal_login'])) $patientdata['cmsportal_login'] = trim($data['cmsportal_login']);
    if (!empty($data['hipaa_notice_received'])) $patientdata['hipaa_notice_received'] = trim($data['hipaa_notice_received']);

    // HIPAA
    if (!empty($data['hipaa_mail'])) $patientdata['hipaa_mail'] = trim($data['hipaa_mail']);
    if (!empty($data['hipaa_voice'])) $patientdata['hipaa_voice'] = trim($data['hipaa_voice']);
    if (!empty($data['hipaa_notice'])) $patientdata['hipaa_notice'] = trim($data['hipaa_notice']);
    $patientdata['hipaa_allowsms'] = !empty($data['hipaa_allowsms']) ? trim($data['hipaa_allowsms']) : 'NO';
    $patientdata['hipaa_allowemail'] = !empty($data['hipaa_allowemail']) ? trim($data['hipaa_allowemail']) : 'NO';
    if (!empty($data['hipaa_notice_received_date'])) {
        $patientdata['hipaa_notice_received_date'] = date('Y-m-d', strtotime($data['hipaa_notice_received_date']));
    }

    // Other
    if (!empty($data['ss'])) $patientdata['ss'] = trim($data['ss']);
    if (!empty($data['drivers_license'])) $patientdata['drivers_license'] = trim($data['drivers_license']);
    if (!empty($data['pubpid'])) $patientdata['pubpid'] = trim($data['pubpid']);
    if (!empty($data['billing_note'])) $patientdata['billing_note'] = trim($data['billing_note']);
    if (!empty($data['name_history'])) $patientdata['name_history'] = trim($data['name_history']);
    if (!empty($data['financial'])) $patientdata['financial'] = trim($data['financial']);

    $tables_modified = [];

    // ============================================
    // MODE 1: UPDATE EXISTING PATIENT
    // ============================================

    if ($is_update) {
        // Update patient_data table
        $updateColumns = [];
        $updateParams = [];

        foreach ($patientdata as $col => $val) {
            $updateColumns[] = "`$col` = ?";
            $updateParams[] = $val;
        }

        $updateParams[] = $pid; // Add PID to WHERE clause

        if (!empty($updateColumns)) {
            $update_query = "UPDATE patient_data SET " . implode(', ', $updateColumns) . " WHERE pid = ?";
            dbExecute($db, $update_query, $updateParams);
            $tables_modified[] = 'patient_data';
        }

        $new_pid = $pid;

    } else {
        // ============================================
        // MODE 2: CREATE NEW PATIENT (INSERT)
        // ============================================

        // Intake tracking
        $patientdata['intake_form_completed_date'] = date('Y-m-d H:i:s');
        $patientdata['intake_form_version'] = 'Synapta v2.0';

        // Get new PID
        $result = dbFetch($db, "SELECT MAX(pid) + 1 as next_pid FROM patient_data");
        $new_pid = isset($result['next_pid']) && $result['next_pid'] > 1 ? $result['next_pid'] : 1;
        $patientdata['pid'] = $new_pid;
        $patientdata['id'] = $new_pid;
        $patientdata['date'] = date('Y-m-d H:i:s');

        // Insert to patient_data
        $columns = array_keys($patientdata);
        $placeholders = array_fill(0, count($columns), '?');
        $columnsList = implode('`, `', $columns);
        $insert_query = "INSERT INTO patient_data (`" . $columnsList . "`) VALUES (" . implode(', ', $placeholders) . ")";
        dbExecute($db, $insert_query, array_values($patientdata));
        $tables_modified[] = 'patient_data';
    }

    // ============================================
    // EMPLOYER DATA - Insert or Update
    // ============================================

    if (!empty($data['occupation']) || !empty($data['industry']) || !empty($data['em_street'])) {
        if ($is_update) {
            // Check if employer record exists
            $checkStmt = $db->prepare("SELECT id FROM employer_data WHERE pid = ? LIMIT 1");
            $checkStmt->bind_param("i", $pid);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $employerExists = $checkResult->num_rows > 0;

            if ($employerExists) {
                // Update existing employer data
                $employer_update = "UPDATE employer_data SET name = ?, street = ?, street_line_2 = ?, postal_code = ?, city = ?, state = ?, country = ?, occupation = ?, industry = ?, start_date = ?, end_date = ?, date = NOW() WHERE pid = ?";
                $employer_params = [
                    '',
                    $data['em_street'] ?? '',
                    $data['employer_address_line_2'] ?? '',
                    $data['em_postal_code'] ?? '',
                    $data['em_city'] ?? '',
                    $data['em_state'] ?? '',
                    $data['country_code'] ?? 'USA',
                    $data['occupation'] ?? '',
                    $data['industry'] ?? '',
                    !empty($data['employment_start_date']) ? date('Y-m-d', strtotime($data['employment_start_date'])) : null,
                    !empty($data['employment_end_date']) ? date('Y-m-d', strtotime($data['employment_end_date'])) : null,
                    $pid
                ];
                dbExecute($db, $employer_update, $employer_params);
            } else {
                // Insert new employer data
                $employer_insert = "INSERT INTO employer_data (pid, name, street, street_line_2, postal_code, city, state, country, occupation, industry, start_date, end_date, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $employer_params = [
                    $pid,
                    '',
                    $data['em_street'] ?? '',
                    $data['employer_address_line_2'] ?? '',
                    $data['em_postal_code'] ?? '',
                    $data['em_city'] ?? '',
                    $data['em_state'] ?? '',
                    $data['country_code'] ?? 'USA',
                    $data['occupation'] ?? '',
                    $data['industry'] ?? '',
                    !empty($data['employment_start_date']) ? date('Y-m-d', strtotime($data['employment_start_date'])) : null,
                    !empty($data['employment_end_date']) ? date('Y-m-d', strtotime($data['employment_end_date'])) : null
                ];
                dbExecute($db, $employer_insert, $employer_params);
            }

            if (!in_array('employer_data', $tables_modified)) {
                $tables_modified[] = 'employer_data';
            }
        } else {
            // Insert new employer data (add mode)
            $employer_insert = "INSERT INTO employer_data (pid, name, street, street_line_2, postal_code, city, state, country, occupation, industry, start_date, end_date, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $employer_params = [
                $new_pid,
                '',
                $data['em_street'] ?? '',
                $data['employer_address_line_2'] ?? '',
                $data['em_postal_code'] ?? '',
                $data['em_city'] ?? '',
                $data['em_state'] ?? '',
                $data['country_code'] ?? 'USA',
                $data['occupation'] ?? '',
                $data['industry'] ?? '',
                !empty($data['employment_start_date']) ? date('Y-m-d', strtotime($data['employment_start_date'])) : null,
                !empty($data['employment_end_date']) ? date('Y-m-d', strtotime($data['employment_end_date'])) : null
            ];
            dbExecute($db, $employer_insert, $employer_params);
            $tables_modified[] = 'employer_data';
        }
    }

    // ============================================
    // HISTORY DATA - Insert or Update
    // ============================================

    if (!empty($data['tobacco']) || !empty($data['alcohol']) || !empty($data['drugs'])) {
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

                $history_update = "UPDATE history_data SET tobacco = ?, alcohol = ?, recreational_drugs = ?, seatbelt_use = ?, hazardous_activities = ?, date = NOW(), additional_history = ? WHERE pid = ?";
                $history_params = [
                    $data['tobacco'] ?? '',
                    $data['alcohol'] ?? '',
                    $data['drugs'] ?? '',
                    $data['seatbelt'] ?? '',
                    $data['dvsafety'] ?? '',
                    !empty($additional_history) ? $additional_history : null,
                    $pid
                ];
                dbExecute($db, $history_update, $history_params);
            } else {
                // Insert new history
                $additional_history = '';
                if (!empty($data['name_history'])) $additional_history .= "Previous Names: " . $data['name_history'] . "\n";
                if (!empty($data['sympprog'])) $additional_history .= "Symptom Progression: " . $data['sympprog'] . "\n";
                if (!empty($data['paincont'])) $additional_history .= "Pain Control: " . $data['paincont'] . "\n";

                $history_insert = "INSERT INTO history_data (pid, tobacco, alcohol, recreational_drugs, seatbelt_use, hazardous_activities, date, additional_history) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
                $history_params = [
                    $pid,
                    $data['tobacco'] ?? '',
                    $data['alcohol'] ?? '',
                    $data['drugs'] ?? '',
                    $data['seatbelt'] ?? '',
                    $data['dvsafety'] ?? '',
                    !empty($additional_history) ? $additional_history : null
                ];
                dbExecute($db, $history_insert, $history_params);
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

            $history_insert = "INSERT INTO history_data (pid, tobacco, alcohol, recreational_drugs, seatbelt_use, hazardous_activities, date, additional_history) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
            $history_params = [
                $new_pid,
                $data['tobacco'] ?? '',
                $data['alcohol'] ?? '',
                $data['drugs'] ?? '',
                $data['seatbelt'] ?? '',
                $data['dvsafety'] ?? '',
                !empty($additional_history) ? $additional_history : null
            ];
            dbExecute($db, $history_insert, $history_params);
            $tables_modified[] = 'history_data';
        }
    }

    // ============================================
    // CHIEF COMPLAINT - Insert or Update
    // ============================================

    if (!empty($data['chief_complaint']) || !empty($data['symptom_start'])) {
        $complaint_text = !empty($data['chief_complaint']) ? trim($data['chief_complaint']) : '';
        $onset_date = !empty($data['symptom_start']) ? trim($data['symptom_start']) : '';

        if (!empty($complaint_text) || !empty($onset_date)) {
            if ($is_update) {
                // Check if chief complaint exists
                $checkStmt = $db->prepare("SELECT id FROM patient_chief_complaint WHERE pid = ? LIMIT 1");
                $checkStmt->bind_param("i", $pid);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $complaintExists = $checkResult->num_rows > 0;

                if ($complaintExists) {
                    // Update existing complaint
                    $complaint_update = "UPDATE patient_chief_complaint SET complaint_text = ?, onset_date = ?, date_modified = NOW() WHERE pid = ?";
                    $complaint_params = [$complaint_text, $onset_date, $pid];
                    dbExecute($db, $complaint_update, $complaint_params);
                } else {
                    // Insert new complaint
                    $complaint_insert = "INSERT INTO patient_chief_complaint (pid, user_id, complaint_text, onset_date, date_created, date_modified) VALUES (?, ?, ?, ?, NOW(), NOW())";
                    $complaint_params = [$pid, 1, $complaint_text, $onset_date];
                    dbExecute($db, $complaint_insert, $complaint_params);
                }
            } else {
                // Insert new complaint (add mode)
                $complaint_insert = "INSERT INTO patient_chief_complaint (pid, user_id, complaint_text, onset_date, date_created, date_modified) VALUES (?, ?, ?, ?, NOW(), NOW())";
                $complaint_params = [$new_pid, 1, $complaint_text, $onset_date];
                dbExecute($db, $complaint_insert, $complaint_params);
            }

            if (!in_array('patient_chief_complaint', $tables_modified)) {
                $tables_modified[] = 'patient_chief_complaint';
            }
        }
    }

    // ============================================
    // LISTS DATA - Medical Problems, Medications, Allergies, Surgeries, Family History
    // ============================================

    $working_pid = $is_update ? $pid : $new_pid;

    // For update mode, delete old lists entries and add new ones
    if ($is_update) {
        $delete_types = ['medical_problem', 'medication', 'allergy', 'procedure', 'family_history'];
        foreach ($delete_types as $type) {
            dbExecute($db, "DELETE FROM lists WHERE pid = ? AND type = ?", [$working_pid, $type]);
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
                    [$working_pid, 'medical_problem', $condition, 1, 'admin', 'Default', 'unconfirmed']
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
                    [$working_pid, 'medication', $med['name'], 1, $comments, 'admin', 'Default']
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
                    [$working_pid, 'allergy', $allergy['name'], $allergy['reaction'] ?? '', $allergy['severity'] ?? '', 1, 'admin', 'Default', 'unconfirmed']
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
                    [$working_pid, 'allergy', $allergy['name'], $allergy['reaction'] ?? '', $allergy['severity'] ?? '', 1, 'admin', 'Default', 'unconfirmed']
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
                    [$working_pid, 'procedure', $surgery['procedure'], 1, $comments, 'admin', 'Default']
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
                    [$working_pid, 'family_history', $fam_history['condition'], 1, $comments, 'admin', 'Default']
                );
            }
        }
        if (!in_array('lists', $tables_modified)) {
            $tables_modified[] = 'lists';
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
    ob_end_clean();

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    error_log("Patient save error: " . $e->getMessage());
}
?>
