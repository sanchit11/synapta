<?php

require_once(dirname(__FILE__, 5) . "/globals.php");

$pid = (int)($GLOBALS['pid'] ?? $_SESSION['pid'] ?? 0);

if (!$pid) {
    return;
}

// Patient Info
$patientData = sqlQuery(
    "SELECT
        fname,
        lname,
        DOB,
        sex,
        pubpid,
        TIMESTAMPDIFF(YEAR, DOB, CURDATE()) AS age
     FROM patient_data
     WHERE pid = ?",
    [$pid]
);

if (!$patientData) {
    return;
}

// Current Encounter
$encounter = $GLOBALS['encounter'] ?? $_SESSION['encounter'] ?? 0;

$encounterData = [];

if ($encounter) {

    $encounterData = sqlQuery(
        "SELECT
            date
         FROM form_encounter
         WHERE encounter = ?
         AND pid = ?",
        [$encounter, $pid]
    );
}

// Variables
$patientName =
    trim(
        ($patientData['lname'] ?? '') .
        ', ' .
        ($patientData['fname'] ?? '')
    );

$patientInitials =
    strtoupper(
        substr($patientData['fname'] ?? '', 0, 1) .
        substr($patientData['lname'] ?? '', 0, 1)
    );

$dob = !empty($patientData['DOB'])
    ? date('m/d/Y', strtotime($patientData['DOB']))
    : '';

$age = $patientData['age'] ?? '';

$sex = !empty($patientData['sex'])
    ? substr($patientData['sex'], 0, 1)
    : '';

$mrn = $patientData['pubpid'] ?? '';

$encDate = !empty($encounterData['date'])
    ? date('M d, Y', strtotime($encounterData['date']))
    : '';

?>

<link rel="stylesheet"
href="<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-physician-dashboard/public/css/dashboard.css">

<div id="synapta-patient-bar" style="display:flex; gap:10px;">

    <!-- Avatar -->
    <div class="syd-pt-avatar">
        <?php echo htmlspecialchars($patientInitials); ?>
    </div>

    <!-- Patient Info -->
    <div class="syd-pt-info">

        <div class="syd-pt-name">
            <?php echo htmlspecialchars($patientName); ?>
        </div>

        <div class="syd-pt-meta">

            DOB:
            <?php echo $dob; ?>

            &nbsp;·&nbsp;

            <?php echo $age . $sex; ?>

            &nbsp;·&nbsp;

            MRN:
            <?php echo htmlspecialchars($mrn); ?>

            <?php if ($encDate): ?>

                &nbsp;·&nbsp;

                Encounter:
                <?php echo $encDate; ?>

            <?php endif; ?>

        </div>

    </div>

</div>