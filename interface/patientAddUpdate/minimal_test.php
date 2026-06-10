<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html');

echo "Step 1: Check PHP version<br>";
echo phpversion() . "<br><br>";

echo "Step 2: Connect to database<br>";
$db = @new mysqli('localhost', 'root', '', 'synaptaemr');
if ($db->connect_error) {
    echo "ERROR: " . $db->connect_error . "<br>";
} else {
    echo "OK: Connected<br>";
}

echo "Step 3: Query for pid=7<br>";
if ($result = $db->query("SELECT * FROM patient_data WHERE pid=7")) {
    echo "OK: Query executed<br>";
    if ($row = $result->fetch_assoc()) {
        echo "OK: Found patient<br>";
        echo "Name: " . $row['fname'] . " " . $row['lname'] . "<br>";
    } else {
        echo "ERROR: No patient found<br>";
    }
} else {
    echo "ERROR: " . $db->error . "<br>";
}

echo "Step 4: Convert to JSON<br>";
$test = array('success' => true, 'data' => $row);
$json = json_encode($test);
if ($json === false) {
    echo "ERROR: " . json_last_error_msg() . "<br>";
} else {
    echo "OK: JSON created<br>";
    echo "Length: " . strlen($json) . " bytes<br>";
}
?>
