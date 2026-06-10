<?php
/**
 * Synapta Patient Intake Form - Comprehensive Save Handler
 * Saves patient data to multiple tables: patient_data, employer_data, history_data, lists, insurance_data
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @copyright Copyright (c) 2026
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Set JSON response header FIRST - BEFORE ANY OUTPUT
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
    $db = new mysqli('localhost', 'root', '', 'synaptaemr');

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

    // Validate required fields
    $required_fields = ['fname', 'lname', 'DOB', 'sex', 'phone_cell', 'email'];
    foreach ($required_fields as $field) {
        if (empty($data[$field] ?? null)) {
            throw new Exception("Required field missing: $field");
        }
    }

    // ============================================
    // 1. INSERT INTO patient_data
    // ============================================

    $newdata = [];

    // Identity & Demographics
    if (!empty($data['fname'])) $newdata['fname'] = ucwords(trim($data['fname']));
    if (!empty($data['lname'])) $newdata['lname'] = ucwords(trim($data['lname']));
    if (!empty($data['mname'])) $newdata['mname'] = ucwords(trim($data['mname']));
    if (!empty($data['suffix'])) $newdata['suffix'] = trim($data['suffix']);
    if (!empty($data['title'])) $newdata['title'] = trim($data['title']);
    if (!empty($data['preferred_name'])) $newdata['preferred_name'] = trim($data['preferred_name']);
    if (!empty($data['birth_fname'])) $newdata['birth_fname'] = trim($data['birth_fname']);
    if (!empty($data['birth_mname'])) $newdata['birth_mname'] = trim($data['birth_mname']);
    if (!empty($data['birth_lname'])) $newdata['birth_lname'] = trim($data['birth_lname']);

    // DOB
    if (!empty($data['DOB'])) {
        $newdata['DOB'] = date('Y-m-d', strtotime($data['DOB']));
    }

    // Gender/Sex
    if (!empty($data['sex'])) $newdata['sex'] = trim($data['sex']);
    if (!empty($data['gender_identity'])) $newdata['gender_identity'] = trim($data['gender_identity']);
    if (!empty($data['pronoun'])) $newdata['pronoun'] = trim($data['pronoun']);
    if (!empty($data['sexual_orientation'])) $newdata['sexual_orientation'] = trim($data['sexual_orientation']);

    // Contact
    if (!empty($data['street'])) $newdata['street'] = trim($data['street']);
    if (!empty($data['street_line_2'])) $newdata['street_line_2'] = trim($data['street_line_2']);
    if (!empty($data['city'])) $newdata['city'] = trim($data['city']);
    if (!empty($data['state'])) $newdata['state'] = trim($data['state']);
    if (!empty($data['postal_code'])) $newdata['postal_code'] = trim($data['postal_code']);
    $newdata['country_code'] = !empty($data['country_code']) ? trim($data['country_code']) : 'USA';
    if (!empty($data['county'])) $newdata['county'] = trim($data['county']);

    // Phone/Email
    if (!empty($data['phone_home'])) $newdata['phone_home'] = trim($data['phone_home']);
    if (!empty($data['phone_cell'])) $newdata['phone_cell'] = trim($data['phone_cell']);
    if (!empty($data['phone_biz'])) $newdata['phone_biz'] = trim($data['phone_biz']);
    if (!empty($data['email'])) $newdata['email'] = trim($data['email']);
    if (!empty($data['email_alternate'])) $newdata['email_alternate'] = trim($data['email_alternate']);
    if (!empty($data['phone_preferred_method'])) $newdata['phone_preferred_method'] = trim($data['phone_preferred_method']);

    // Emergency Contact
    if (!empty($data['mothersname'])) $newdata['mothersname'] = trim($data['mothersname']);
    if (!empty($data['emergency_contact_name'])) $newdata['emergency_contact_name'] = trim($data['emergency_contact_name']);
    if (!empty($data['emergency_contact_relationship'])) $newdata['emergency_contact_relationship'] = trim($data['emergency_contact_relationship']);
    if (!empty($data['emergency_contact_phone'])) $newdata['emergency_contact_phone'] = trim($data['emergency_contact_phone']);

    // Demographics
    if (!empty($data['race'])) $newdata['race'] = trim($data['race']);
    if (!empty($data['ethnicity'])) $newdata['ethnicity'] = trim($data['ethnicity']);
    if (!empty($data['language'])) $newdata['language'] = trim($data['language']);
    if (!empty($data['religion'])) $newdata['religion'] = trim($data['religion']);
    if (!empty($data['nationality_country'])) $newdata['nationality_country'] = trim($data['nationality_country']);
    if (!empty($data['interpreter_needed'])) $newdata['interpreter_needed'] = trim($data['interpreter_needed']);
    if (!empty($data['interpreter_dialect_notes'])) $newdata['interpreter_dialect_notes'] = trim($data['interpreter_dialect_notes']);
    if (!empty($data['household_size'])) $newdata['household_size'] = intval($data['household_size']);
    if (!empty($data['family_size'])) $newdata['family_size'] = trim($data['family_size']);
    if (!empty($data['monthly_income'])) $newdata['monthly_income'] = trim($data['monthly_income']);

    // Healthcare
    if (!empty($data['primary_care_provider'])) $newdata['primary_care_provider'] = intval($data['primary_care_provider']);
    if (!empty($data['provider_since_date'])) {
        $newdata['provider_since_date'] = date('Y-m-d', strtotime($data['provider_since_date']));
    }
    if (!empty($data['referring_provider'])) $newdata['referring_provider'] = trim($data['referring_provider']);
    if (!empty($data['preferred_pharmacy'])) $newdata['preferred_pharmacy'] = trim($data['preferred_pharmacy']);
    if (!empty($data['patient_category'])) $newdata['patient_category'] = trim($data['patient_category']);

    // Communication Preferences
    if (!empty($data['allow_voice_message'])) $newdata['allow_voice_message'] = trim($data['allow_voice_message']);
    if (!empty($data['voice_message_with'])) $newdata['voice_message_with'] = trim($data['voice_message_with']);
    if (!empty($data['allow_mail_message'])) $newdata['allow_mail_message'] = trim($data['allow_mail_message']);
    if (!empty($data['allow_sms_tex