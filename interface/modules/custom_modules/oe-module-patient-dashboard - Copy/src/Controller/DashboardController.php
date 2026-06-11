<?php

/**
 * DashboardController — Synapta Portal Module
 *
 * Retrieves live patient data from OpenEMR's database layer and
 * renders the Synapta dashboard HTML template.
 *
 * Data sources used:
 *  - patient_data           → demographics / MRN / PCP
 *  - openemr_postcalendar_events → upcoming appointments
 *  - pnotes                 → secure messages
 *  - lists (type=allergy,medication)  → meds
 *  - form_encounter         → recent visits
 *  - billing / ar_activity  → outstanding balance
 *
 * @package OpenEMR\Modules\PatientDashboard
 */

namespace OpenEMR\Modules\PatientDashboard\Controller;

class DashboardController
{
    /** @var int  Patient PID (from session, set by portal auth) */
    private int $pid;

    public function __construct()
    {
        // Portal auth stores the logged-in patient PID in $_SESSION['pid']
        
        
        $this->pid = (int)($_SESSION['pid'] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PUBLIC ENTRY POINT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch all data, build the view-model, include the template.
     */
    public function render(): void
    {
        if ($this->pid === 0) {
            // Not authenticated — let the portal handle the redirect
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | AJAX REQUESTS
    |--------------------------------------------------------------------------
    */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && !empty($_POST['action'])
    ) {

        $this->handleAjax();

        return;
    }

        $data = [
            'patient'      => $this->getPatientInfo(),
            'appointments' => $this->getUpcomingAppointments(),
            'past_visits'  => $this->getPastVisits(),
            'messages'     => $this->getMessages(),
            'sent_messages'=> $this->getSentMessages(),
            'staff_list'   => $this->getStaffList(),
            'medications'  => $this->getMedications(),
            'lab_results'  => $this->getLabResults(),
            'allergies'      => $this->getAllergies(),
            'problems'       => $this->getProblems(),
            'immunizations'  => $this->getImmunizations(),
            'reports_config' => $this->getReportsConfig(),
            'billing'      => $this->getBillingSummary(),
            'care_plan'    => $this->getCarePlanGoals(),
            'home_bp'    => $this->getHomeBP(),
            'average_bp'    => $this->getAverageBP(),
            'weight_trend'    => $this->getWeightTrend(),
            'onsite_messages' => $this->getOnsiteMessages(),
            'reminders' => $this->getReminders(),
            'recalls' => $this->getRecalls(),
            'vitals_summary' => $this->getVitalsSummary(),
            
           
            'module_path'  => $GLOBALS['web_root']
                              . '/interface/modules/custom_modules/oe-module-patient-dashboard/public',
        ];

        $templatePath = __DIR__ . '/../../templates/dashboard.php';
        if (!file_exists($templatePath)) {
            echo '<p class="text-danger">Synapta: dashboard template missing.</p>';
            return;
        }

        // Expose variables to the template via extract
        extract($data, EXTR_SKIP);
        require $templatePath;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DATA METHODS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Core patient demographics.
     *
     * @return array{fname:string, lname:string, DOB:string, pubpid:string,
     *               insurance_name:string, pcp_name:string, initials:string}
     */
    private function getPatientInfo(): array
    {
        $row = sqlQuery(
            "SELECT pd.fname, pd.lname, pd.DOB, pd.pubpid,
                    CONCAT(u.fname, ' ', u.lname) AS pcp_name,
                    ic.name AS insurance_name
             FROM   patient_data pd
             LEFT JOIN users  u  ON u.id   = pd.providerID
             LEFT JOIN insurance_data id2 ON id2.pid = pd.pid AND id2.type = 'primary'
             LEFT JOIN insurance_companies ic ON ic.id = id2.provider
             WHERE  pd.pid = ?",
            [$this->pid]
        );

        if (!$row) {
            return [
                'fname' => 'Patient', 'lname' => '', 'DOB' => '',
                'pubpid' => '', 'insurance_name' => '', 'pcp_name' => '',
                'initials' => 'P',
            ];
        }

        $initials = strtoupper(
            substr($row['fname'] ?? '', 0, 1) . substr($row['lname'] ?? '', 0, 1)
        );

        return array_merge($row, ['initials' => $initials]);
    }

    /**
     * Upcoming appointments (next 90 days), newest first.
     *
     * @return array<int, array{date:string, time:string, title:string,
     *                          provider:string, location:string, type:string}>
     */
    private function getUpcomingAppointments(): array
    {
        $res = sqlStatement(
            "SELECT e.pc_eventDate  AS date,
                    e.pc_startTime  AS time,
                    e.pc_title      AS title,
                    CONCAT(u.suffix, ' ', u.fname, ' ', u.lname) AS provider,
                    f.name          AS location,
                    e.pc_apptstatus AS status,
                    opc.pc_catname    AS category
             FROM   openemr_postcalendar_events e
             LEFT JOIN users     u ON u.id = e.pc_aid
             LEFT JOIN facility  f ON f.id = e.pc_facility
             INNER JOIN openemr_postcalendar_categories  opc ON opc.pc_catid = e.pc_catid
             WHERE  e.pc_pid         = ?
               AND  e.pc_eventDate  >= CURDATE()
               AND  e.pc_apptstatus NOT IN ('x','d')
             ORDER  BY e.pc_eventDate ASC, e.pc_startTime ASC
             LIMIT  10",
            [$this->pid]
        );

        $appts = [];
        while ($row = sqlFetchArray($res)) {
            $row['is_tele'] = stripos($row['category'] ?? '', 'tele') !== false
                           || stripos($row['title'] ?? '', 'tele') !== false;
            $appts[] = $row;
        }
        return $appts;
    }

    /**
     * Last 5 completed visits.
     *
     * @return array<int, array{date:string, reason:string, provider:string}>
     */
    private function getPastVisits(): array
    {
        $res = sqlStatement(
            "SELECT e.date, e.reason,
                    CONCAT(u.suffix, ' ', u.fname, ' ', u.lname) AS provider,
                    f.name          AS location
             FROM   form_encounter e
             LEFT JOIN users u ON u.id = e.provider_id
             LEFT JOIN facility  f ON f.id = e.facility_id
             WHERE  e.pid = ?
             ORDER  BY e.date DESC
             LIMIT  5",
            [$this->pid]
        );

        $visits = [];
        while ($row = sqlFetchArray($res)) {
            $visits[] = $row;
        }
        return $visits;
    }

    /**
     * Inbox messages (pnotes) — last 10, unread first.
     *
     * @return array<int, array{id:int, from:string, subject:string,
     *                          body:string, date:string, unread:bool}>
     */
    // private function getMessages(): array
    // {
    //     $res = sqlStatement(
    //         "SELECT id,
    //                 COALESCE(user, 'System') AS sender,
    //                 title   AS subject,
    //                 body,
    //                 date,
    //                 (activity = 0) AS is_unread
    //          FROM   pnotes
    //          WHERE  pid         = ?
    //            AND  assigned_to = 'portal-user'
    //          ORDER  BY activity ASC, date DESC
    //          LIMIT  10",
    //         [$this->pid]
    //     );

    //     $msgs = [];
    //     while ($row = sqlFetchArray($res)) {
    //         $row['unread'] = (bool)$row['is_unread'];
    //         $msgs[] = $row;
    //     }
    //     return $msgs;
    // }

    private function getMessages(): array
    {
        $res = sqlStatement(
            "SELECT pn.id,
                    COALESCE(CONCAT(u.fname, ' ', u.lname), pn.user, 'Care Team') AS sender,
                    pn.title   AS subject,
                    pn.body,
                    pn.date,
                    pn.message_status,
                    pn.portal_relation,
                    (pn.message_status = 'New' OR pn.activity = 1) AS is_unread
             FROM   pnotes pn
             LEFT JOIN users u ON u.username = pn.user
             WHERE  pn.pid = ?
               AND  pn.deleted != 1
               AND  (
                    pn.assigned_to = 'portal-user'
                 OR pn.portal_relation     = 'portal'
                 OR pn.message_status IN ('New','Sent','Read')
               )
             ORDER  BY pn.date DESC
             LIMIT  50",
            [$this->pid]
        );

        $msgs = [];
        while ($row = sqlFetchArray($res)) {
            $row['unread']    = (bool)$row['is_unread'];
            $row['direction'] = 'in';
            $msgs[] = $row;
        }
        return $msgs;
    }

    private function getSentMessages(): array
    {
        $res = sqlStatement(
            "SELECT pn.id,
                    COALESCE(CONCAT(u.fname, ' ', u.lname), pn.assigned_to, 'Care Team') AS recipient,
                    pn.title   AS subject,
                    pn.body,
                    pn.date,
                    pn.message_status
             FROM   pnotes pn
             LEFT JOIN users u ON u.username = pn.assigned_to
             WHERE  pn.pid        = ?
               AND  pn.deleted   != 1
               AND  pn.portal_relation     = 'portal-patient'
             ORDER  BY pn.date DESC
             LIMIT  50",
            [$this->pid]
        );

        $msgs = [];
        while ($row = sqlFetchArray($res)) {
            $row['direction'] = 'out';
            $msgs[] = $row;
        }
        return $msgs;
    }

     /**
     * List of staff / providers the patient can send a message to.
     */
    private function getStaffList(): array
    {
        $res = sqlStatement(
            "SELECT id, CONCAT(fname, ' ', lname) AS name, specialty,username
             FROM   users
             WHERE  active    = 1
               AND  portal_user = 1
               AND  username  != ''
               
             ORDER  BY lname ASC, fname ASC"
        );

        $staff = [];
        while ($row = sqlFetchArray($res)) {
            $staff[] = $row;
        }

        // Fallback — always include a generic "Care Team" option
        if (empty($staff)) {
            $staff[] = ['id' => 0, 'name' => 'Care Team', 'specialty' => ''];
        }

        return $staff;
    }


    /**
     * Active medications from the lists table.
     *
     * @return array<int, array{drug:string, dosage:string, route:string, refills:int}>
     */
    private function getMedications(): array
    {
        $res = sqlStatement(
            "SELECT title  AS drug,
                    comments AS dosage,
                    activity AS active,
                    modifydate AS last_updated
             FROM   lists
             WHERE  pid  = ?
               AND  type = 'medication'
               AND  activity = 1
             ORDER  BY title ASC
             LIMIT  15",
            [$this->pid]
        );

        $meds = [];
        while ($row = sqlFetchArray($res)) {
            $meds[] = $row;
        }
        return $meds;
    }

    /**
     * Recent lab results (last 10 ordered by date).
     *
     * @return array<int, array{name:string, value:string, units:string,
     *                          abnormal:string, date:string}>
     */
    private function getLabResults(): array
{
    $res = sqlStatement(
        "SELECT
            poc.procedure_name AS name,
            pr.result          AS value,
            pr.units,
            pr.abnormal,
            pr.date
         FROM procedure_order po
         INNER JOIN procedure_order_code poc
            ON poc.procedure_order_id = po.procedure_order_id
         INNER JOIN procedure_report rep
            ON rep.procedure_order_id = po.procedure_order_id
         INNER JOIN procedure_result pr
            ON pr.procedure_report_id = rep.procedure_report_id
         WHERE po.patient_id = ?
           AND pr.result <> ''
         ORDER BY pr.date DESC
         LIMIT 10",
        [$this->pid]
    );

    $labs = [];
    while ($row = sqlFetchArray($res)) {
        $labs[] = $row;
    }

    return $labs;
}

    /**
     * Outstanding billing balance.
     *
     * @return array{outstanding:float, statements:array}
     */
     private function getBillingSummary(): array
    {
        // ── Total charged to patient ──────────────────────────────────────
        $charged = sqlQuery(
            "SELECT COALESCE(SUM(b.fee), 0) AS total_fee
             FROM   billing b
             WHERE  b.pid      = ?
               AND  b.billed   = 1
               AND  b.activity = 1",
            [$this->pid]
        );
 
        // ── Total paid (from ar_activity — the payments table) ────────────
        // ar_activity links via pid + encounter
        $paid = sqlQuery(
            "SELECT COALESCE(SUM(ar.pay_amount), 0) AS total_paid
             FROM   ar_activity ar
             WHERE  ar.pid = ?
               AND  ar.deleted IS NULL",
            [$this->pid]
        );
 
        // ── Total insurance paid (adj_amount in ar_activity) ─────────────
        $insurancePaid = sqlQuery(
            "SELECT COALESCE(SUM(ar.adj_amount), 0) AS ins_paid
             FROM   ar_activity ar
             WHERE  ar.pid     = ?
               AND  ar.payer_type > 0
               AND  ar.deleted IS NULL",
            [$this->pid]
        );
 
        $totalFee      = (float)($charged['total_fee']    ?? 0);
        $totalPaid     = (float)($paid['total_paid']       ?? 0);
        $totalInsPaid  = (float)($insurancePaid['ins_paid'] ?? 0);
        $outstanding   = max(0, $totalFee - $totalPaid);
 
        // YTD figures (current calendar year)
        $year = date('Y');
 
        $ytdOop = sqlQuery(
            "SELECT COALESCE(SUM(ar.pay_amount), 0) AS oop
             FROM   ar_activity ar
             WHERE  ar.pid        = ?
               AND  ar.payer_type = 0
               AND  ar.deleted    IS NULL
               AND  YEAR(ar.post_time) = ?",
            [$this->pid, $year]
        );
 
        $ytdInsSaved = sqlQuery(
            "SELECT COALESCE(SUM(ar.pay_amount + ar.adj_amount), 0) AS saved
             FROM   ar_activity ar
             WHERE  ar.pid        = ?
               AND  ar.payer_type > 0
               AND  ar.deleted    IS NULL
               AND  YEAR(ar.post_time) = ?",
            [$this->pid, $year]
        );
 
        // Insurance name for subtitle
        $insRow = sqlQuery(
            "SELECT ic.name
             FROM   insurance_data id2
             JOIN   insurance_companies ic ON ic.id = id2.provider
             WHERE  id2.pid  = ?
               AND  id2.type = 'primary'
             LIMIT  1",
            [$this->pid]
        );
 
        // ── Open statements — unpaid billing lines grouped by encounter ───
        $openRes = sqlStatement(
            "SELECT
                b.encounter,
                b.id as billing_id,
                b.date,
                b.code,
                b.code_type,
                b.code_text,
                b.fee,
                b.modifier,
                fe.reason        AS visit_reason,
                fe.date          AS enc_date,
                COALESCE(
                    (SELECT SUM(ar2.pay_amount + ar2.adj_amount)
                     FROM   ar_activity ar2
                     WHERE  ar2.encounter = b.encounter
                       AND  ar2.pid       = b.pid
                       AND  ar2.deleted   IS NULL
                    ), 0
                ) AS total_paid_for_enc
             FROM   billing b
             LEFT JOIN form_encounter fe ON fe.encounter = b.encounter AND fe.pid = b.pid
             WHERE  b.pid      = ?
               AND  b.billed   = 1
               AND  b.activity = 1
             ORDER  BY b.date DESC
             LIMIT  20",
            [$this->pid]
        );
 
        $openStatements = [];
        $seenEnc        = [];
        while ($row = sqlFetchArray($openRes)) {
            $enc    = $row['encounter'];
            $fee    = (float)($row['fee'] ?? 0);
            $paid   = (float)($row['total_paid_for_enc'] ?? 0);
            $patResp = max(0, $fee - $paid);
            $billing_id=$row['billing_id'];
 
            // One row per encounter
          //  echo "<br>ts = ".$seenEnc[$enc];
            if (!isset($seenEnc[$enc])) {
                
                $seenEnc[$enc] = true;
                $date = !empty($row['enc_date']) ? $row['enc_date'] : $row['date'];
                $desc = $row['visit_reason'] ?? $row['code_text'] ?? 'Service';
                $code = trim(($row['code_type'] ?? '') . ' ' . ($row['code'] ?? ''));
 
                $openStatements[] = [
                    'billing_id'   => $billing_id,
                    'encounter'   => $enc,
                    'date'        => $date,
                    'description' => $desc,
                    'code'        => $code,
                    'fee'         => $fee,
                    'ins_paid'    => $paid,
                    'patient_resp'=> $patResp,
                    'is_paid'     => ($patResp <= 0),
                ];
            }
        }
 
        // ── Payment history — what has actually been paid ─────────────────
         $histRes = sqlStatement(
            "SELECT
                ar.pid,
                ar.encounter,
                ar.sequence_no,
                ar.post_time        AS paid_date,
                ar.pay_amount,
                ar.adj_amount,
                ar.payer_type,
                COALESCE(fe.reason, b.code_text, 'Service') AS description,
                fe.date             AS enc_date
             FROM   ar_activity ar
             LEFT JOIN form_encounter fe
                    ON fe.encounter = ar.encounter
                   AND fe.pid       = ar.pid
             LEFT JOIN billing b
                    ON b.encounter  = ar.encounter
                   AND b.pid        = ar.pid
                   AND b.activity   = 1
             WHERE  ar.pid        = ?
               AND  ar.pay_amount > 0
               AND  ar.deleted    IS NULL
             GROUP  BY ar.pid, ar.encounter, ar.sequence_no
             ORDER  BY ar.post_time DESC
             LIMIT  20",
            [$this->pid]
        );
 
        $history = [];
        while ($row = sqlFetchArray($histRes)) {
            $history[] = [
                'paid_date'   => $row['paid_date'],
                'enc_date'    => $row['enc_date'] ?? $row['paid_date'],
                'description' => $row['description'] ?? 'Service',
                'amount'      => (float)($row['pay_amount'] ?? 0),
                'payer_type'  => (int)($row['payer_type'] ?? 0), // 0=patient, >0=insurance
            ];
        }
 
        return [
            'outstanding'      => $outstanding,
            'ytd_oop'          => (float)($ytdOop['oop']        ?? 0),
            'ytd_ins_saved'    => (float)($ytdInsSaved['saved']  ?? 0),
            'insurance_name'   => $insRow['name'] ?? '',
            'open_statements'  => $openStatements,
            'history'          => $history,
            // Keep legacy key so existing home-tab stat tile still works
            'statements'       => $openStatements,
        ];
    }

    /**
     * Care plan goals from the extended care plan forms (form_care_plan).
     * Falls back gracefully if the table doesn't exist in the installation.
     *
     * @return array<int, array{goal:string, progress:int, status:string}>
     */
    private function getCarePlanGoals(): array
    {
        try {
            // $res = sqlStatement(
            //     "SELECT cp.goal     AS goal,
            //             cp.progress AS progress,
            //             cp.status   AS status
            //      FROM   form_care_plan cp
            //      JOIN   forms f ON f.form_id = cp.id AND f.formdir = 'care_plan'
            //      WHERE  f.pid    = ?
            //        AND  f.deleted = 0
            //      ORDER  BY cp.date DESC
            //      LIMIT  8",
            //     [$this->pid]
            // );

               $res = sqlStatement(
                "SELECT 
                    cp.*,
                    fe.reason AS encounter_name,
                    fe.encounter_type_description,
                    fe.date AS encounter_date
                FROM form_care_plan cp

                INNER JOIN forms f 
                    ON f.form_id = cp.id
                    AND f.formdir = 'care_plan'

                LEFT JOIN form_encounter fe
                    ON fe.encounter = cp.encounter
                    AND fe.pid = cp.pid

                WHERE f.pid = ?
                AND f.deleted = 0

                ORDER BY cp.date DESC",
                [$this->pid]

            );

            $goals = [];
            while ($row = sqlFetchArray($res)) {
                $goals[] = $row;
            }
            return $goals;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getHomeBP(): array
    {
        try {

            // Current patient id
            $pid = $_SESSION['pid'] ?? 0;

            if (empty($pid)) {
                return [];
            }

            // Fetch latest BP reading from last 60 days
            $bpData = sqlQuery("
                SELECT 
                    bps,
                    bpd,
                    date
                FROM form_vitals
                WHERE pid = ?
                AND bps IS NOT NULL
                AND bpd IS NOT NULL
                AND bps != ''
                AND bpd != ''
                AND date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                ORDER BY date DESC
                LIMIT 1
            ", [$pid]);

            if (empty($bpData)) {
                return [];
            }

            // Convert values
            $systolic  = (int)$bpData['bps'];
            $diastolic = (int)$bpData['bpd'];

            // BP status logic
            $bpStatus = 'Normal';
            $bpColor  = '#1D9E75';

            if ($systolic >= 140 || $diastolic >= 90) {
                $bpStatus = 'High';
                $bpColor  = '#C77A0A';
            } elseif ($systolic >= 130 || $diastolic >= 80) {
                $bpStatus = 'Elevated';
                $bpColor  = '#E6A700';
            }

            return [
                'systolic'   => $systolic,
                'diastolic'  => $diastolic,
                'date'       => date('M d, Y', strtotime($bpData['date'])),
                'status'     => $bpStatus,
                'color'      => $bpColor,
                'formatted'  => $systolic . '/' . $diastolic
            ];

        } catch (\Throwable $e) {

            error_log('Home BP Error: ' . $e->getMessage());

            return [];
        }
    }

    private function getAverageBP(): array
    {
        try {

            $pid = $_SESSION['pid'] ?? 0;

            if (empty($pid)) {
                return [];
            }

            $bpData = sqlQuery("
                SELECT 
                    AVG(CAST(bps AS DECIMAL(10,2))) AS avg_systolic,
                    AVG(CAST(bpd AS DECIMAL(10,2))) AS avg_diastolic
                FROM form_vitals
                WHERE pid = ?
                AND bps IS NOT NULL
                AND bpd IS NOT NULL
                AND bps != ''
                AND bpd != ''
                AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$pid]);

            if (empty($bpData)) {
                return [];
            }

            $systolic  = round($bpData['avg_systolic']);
            $diastolic = round($bpData['avg_diastolic']);

            return [
                'formatted' => $systolic . '/' . $diastolic,
                'systolic'  => $systolic,
                'diastolic' => $diastolic
            ];

        } catch (\Throwable $e) {

            error_log($e->getMessage());

            return [];
        }
    }

    private function getWeightTrend(): array
    {
        try {

            $pid = $_SESSION['pid'] ?? 0;

            if (empty($pid)) {
                return [];
            }

            $weightData = sqlQuery("
                SELECT 
                    weight,
                    date
                FROM form_vitals
                WHERE pid = ?
                AND weight > 0
                AND date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ORDER BY date DESC
                LIMIT 1
            ", [$pid]);

            if (empty($weightData)) {
                return [];
            }

            return [
                'weight' => round($weightData['weight'], 1),
                'date'   => date('M d, Y', strtotime($weightData['date']))
            ];

        } catch (\Throwable $e) {

            error_log($e->getMessage());

            return [];
        }
    }

    private function getOnsiteMessages(): array
    {
        $sender_id = $_SESSION['portal_username'];
        
 
       $res = sqlStatement(
    "SELECT om.id,
            om.date,
            om.title AS subject,
            om.body,
            om.sender_id,
            om.sender_name,
            om.recipient_id,
            om.recipient_name,
            om.message_status,
            om.mtype,
            om.mail_chain,
            om.reply_mail_chain,
            om.header,
            CASE
                WHEN om.sender_id = ? THEN 'out'
                ELSE 'in'
            END AS direction,
            (
                om.message_status = 'New'
                AND om.recipient_id = ?
            ) AS is_unread
     FROM onsite_mail om
     WHERE om.deleted != 1
       AND om.owner = ?
     ORDER BY om.date DESC
     LIMIT 100",
    [$sender_id, $sender_id, $sender_id]
);
 
        $msgs = [];
        while ($row = sqlFetchArray($res)) {
            $row['unread'] = (bool)$row['is_unread'];
            $msgs[] = $row;
        }
        return $msgs;
    }


    private function getReminders(): array
    {
        $pid = $_SESSION['pid'] ?? 0;

        if (empty($pid)) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                dr.dr_id,
                dr.dr_from_ID,
                dr.dr_message_text,
                dr.dr_message_sent_date,
                dr.dr_message_due_date,
                dr.pid,
                dr.message_priority,
                dr.message_processed,
                dr.processed_date,
                dr.dr_processed_by,

                u.fname,
                u.lname,
                u.username

            FROM dated_reminders dr

            LEFT JOIN users u
                ON u.id = dr.dr_from_ID

            WHERE dr.pid = ?

            ORDER BY
                dr.message_processed ASC,
                dr.dr_message_due_date ASC,
                dr.dr_message_sent_date DESC

            LIMIT 100",

            [$pid]
        );

        $reminders = [];

        while ($row = sqlFetchArray($res)) {

            $senderName = trim(
                ($row['fname'] ?? '') . ' ' .
                ($row['lname'] ?? '')
            );

            if (empty($senderName)) {
                $senderName = $row['username'] ?? 'Care Team';
            }

            $row['sender_name'] = $senderName;

            $row['is_processed'] = !empty($row['message_processed']);

            $row['is_priority'] = !empty($row['message_priority']);

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $dueDate = strtotime($row['dr_message_due_date']);

            if (!$row['is_processed']) {

                if ($dueDate < strtotime(date('Y-m-d'))) {
                    $row['status'] = 'Overdue';
                } else {
                    $row['status'] = 'Pending';
                }

            } else {

                $row['status'] = 'Completed';

            }

            $reminders[] = $row;
        }

        return $reminders;
    }

    private function getRecalls(): array
    {
        $pid = $_SESSION['pid'] ?? 0;

        if (empty($pid)) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                mr.r_ID,
                mr.r_PRACTID,
                mr.r_pid,
                mr.r_eventDate,
                mr.r_facility,
                mr.r_provider,
                mr.r_reason,
                mr.r_created,

                u.fname,
                u.lname,
                u.username

            FROM medex_recalls mr

            LEFT JOIN users u
                ON u.id = mr.r_provider

            WHERE mr.r_pid = ?

            ORDER BY
                mr.r_eventDate ASC,
                mr.r_created DESC

            LIMIT 100",

            [$pid]
        );

        $recalls = [];

        while ($row = sqlFetchArray($res)) {

            /*
            |--------------------------------------------------------------------------
            | PROVIDER NAME
            |--------------------------------------------------------------------------
            */

            $providerName = trim(
                ($row['fname'] ?? '') . ' ' .
                ($row['lname'] ?? '')
            );

            if (empty($providerName)) {
                $providerName = $row['username'] ?? 'Care Team';
            }

            $row['provider_name'] = $providerName;

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $eventDate = strtotime($row['r_eventDate']);
            $today = strtotime(date('Y-m-d'));

            if ($eventDate < $today) {

                $row['status'] = 'Past Due';

            } elseif ($eventDate == $today) {

                $row['status'] = 'Today';

            } else {

                $row['status'] = 'Upcoming';

            }

            /*
            |--------------------------------------------------------------------------
            | UPCOMING FLAG
            |--------------------------------------------------------------------------
            */

            $row['is_upcoming'] = ($eventDate >= $today);

            $recalls[] = $row;
        }

        return $recalls;
    }

    public function handleAjax(): void
    {
        $action = $_POST['action'] ?? '';

        switch ($action) {

            /*
            |--------------------------------------------------------------------------
            | MARK PORTAL MAIL AS READ
            |--------------------------------------------------------------------------
            */

            case 'mark_portal_read':

                $this->markPortalMailRead();

                break;
        }
    }

    private function markPortalMailRead(): void
    {
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {

            echo json_encode([
                'success' => false,
                'message' => 'Invalid message id'
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | SECURITY CHECK
        |--------------------------------------------------------------------------
        | Make sure patient owns this message
        |--------------------------------------------------------------------------
        */

        $exists = sqlQuery(
            "SELECT id
            FROM onsite_mail
            WHERE id = ?
            AND owner = ?",

            [
                $id,
                $_SESSION['portal_username']
            ]
        );

        if (empty($exists)) {

            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized'
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS
        |--------------------------------------------------------------------------
        */

        sqlStatement(
            "UPDATE onsite_mail
            SET message_status = 'Read'
            WHERE id = ?",

            [$id]
        );

        echo json_encode([
            'success' => true
        ]);

        exit;
    }

    // ── Allergies ────────────────────────────────────────────────────────────
    private function getAllergies(): array
    {
        $res = sqlStatement(
            "SELECT title       AS name,
                    comments    AS reaction,
                    severity_al AS severity,
                    modifydate  AS last_updated
             FROM   lists
             WHERE  pid      = ?
               AND  type     = 'allergy'
               AND  activity = 1
             ORDER  BY title ASC
             LIMIT  30",
            [$this->pid]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) { $rows[] = $r; }
        return $rows;
    }
 
    // ── Active Problems (diagnoses / conditions) ──────────────────────────────
    private function getProblems(): array
    {
        $res = sqlStatement(
            "SELECT title     AS name,
                    diagnosis AS code,
                    begdate   AS onset_date,
                    comments  AS notes
             FROM   lists
             WHERE  pid      = ?
               AND  type     = 'medical_problem'
               AND  activity = 1
             ORDER  BY begdate DESC
             LIMIT  30",
            [$this->pid]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) { $rows[] = $r; }
        return $rows;
    }
 
    // ── Immunizations ─────────────────────────────────────────────────────────
   // ── Immunizations ─────────────────────────────────────────────────────
    private function getImmunizations(): array
    {
        $res = sqlStatement(
            "SELECT im.cvx_code,
                    cd.code_text                  AS vaccine_name,
                    im.vis_date                   AS administered_date,
                    im.lot_number,
                    CONCAT(u.fname,' ',u.lname)   AS administered_by
             FROM   immunizations im
             LEFT JOIN codes cd ON cd.code = im.cvx_code AND cd.code_type = 'CVX'
             LEFT JOIN users  u ON u.id    = im.administered_by_id
             WHERE  im.patient_id         = ?
               AND  im.added_erroneously  = 0
             ORDER  BY im.vis_date DESC
             LIMIT  20",
            [$this->pid]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) { $rows[] = $r; }
        return $rows;
    }
 
    // ── Reports config — feature flags + CSRF token ───────────────────────
    // Mirrors what home.html.twig receives: ccdaOk, allow_custom_report,
    // portal_onsite_document_download, and a fresh CSRF token for CCDA links.
    private function getReportsConfig(): array
    {
        // CCDA: gateway file must exist AND ccda_to_send global must be enabled
        $ccdaGateway = $GLOBALS['webserver_root'] . '/ccdaservice/ccda_gateway.php';
        $ccdaOk      = file_exists($ccdaGateway)
                    && !empty($GLOBALS['ccda_to_send']);
 
        // Custom medical history report
        // Check flag AND confirm the file physically exists
        $reportFile        = ($GLOBALS['webserver_root'] ?? '') . '/portal/report/portal_patient_report.php';
        $allowCustomReport = file_exists($reportFile) && (
                            !empty($GLOBALS['portal_onsite_custom_report'])
                        || !empty($GLOBALS['portal_two_custom_report'])
                        || !empty($GLOBALS['allow_custom_report'])
                        || true   // file exists — always show if file is present
                        );
 
        // Document download (zip of patient documents)
        $allowDocDownload = !empty($GLOBALS['portal_onsite_document_download'])
                 || !empty($GLOBALS['portal_two_document_download']);
 
        // CSRF token for CCDA links (must be appended to the URL)
        $csrfToken = '';
        if (class_exists('\OpenEMR\Common\Csrf\CsrfUtils')) {
            $csrfToken = \OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken() ?? '';
        }
 
        return [
            'ccda_ok'            => $ccdaOk,
            'allow_custom_report'=> $allowCustomReport,
            'allow_doc_download' => $allowDocDownload,
            'csrf_token'         => $csrfToken,
        ];
    }

    private function getVitalsSummary(): array
    {
        // Current encounter
        $encounterRow = sqlQuery(
            "SELECT fe.date,
                    fe.reason,
                    fe.encounter,
                    CONCAT(u.fname,' ',u.lname) AS provider_name,
                    u.title AS provider_title
            FROM form_encounter fe
            LEFT JOIN users u
                ON u.id = fe.provider_id
            WHERE fe.pid = ?
            ORDER BY fe.date DESC
            LIMIT 1",
            [$this->pid]
        );

        // Latest vitals
        $vitals = sqlQuery(
            "SELECT bps,
                    bpd,
                    pulse,
                    temperature,
                    respiration,
                    weight,
                    height,
                    BMI,
                    oxygen_saturation,
                    date
            FROM form_vitals
            WHERE pid = ?
            AND activity = 1
            ORDER BY date DESC
            LIMIT 1",
            [$this->pid]
        );

        // Last 6 vitals
        $history = [];

        $res = sqlStatement(
            "SELECT bps,
                    bpd,
                    pulse,
                    oxygen_saturation,
                    date
            FROM form_vitals
            WHERE pid = ?
            AND activity = 1
            ORDER BY date DESC
            LIMIT 6",
            [$this->pid]
        );

        while ($row = sqlFetchArray($res)) {
            $history[] = $row;
        }

        $history = array_reverse($history);

        return [
            'encounter' => $encounterRow,
            'current'   => $vitals,
            'history'   => $history
        ];
    }
}
