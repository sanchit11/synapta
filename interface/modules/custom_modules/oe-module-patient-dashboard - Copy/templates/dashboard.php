<?php
/**
 * Synapta Patient Portal Dashboard Template
 *
 * Variables injected by DashboardController::render():
 *  @var array  $patient       Patient demographics
 *  @var array  $appointments  Upcoming appointments
 *  @var array  $past_visits   Past completed encounters
 *  @var array  $messages      Secure portal messages
 *  @var array  $medications   Active medication list
 *  @var array  $lab_results   Recent lab results
 *  @var array  $billing       Billing summary
 *  @var array  $care_plan     Care plan goals
 *  @var string $module_path   Public asset base URL
 *
 * Security: all dynamic output is run through attr() / text() helpers below.
 */

// ── Tiny output-escaping helpers ────────────────────────────────────────────
if (!function_exists('syn_e')) {
    function syn_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function syn_h(string $s): string { return syn_e($s); }
}

// ── Derived values ───────────────────────────────────────────────────────────
$ptName      = syn_e($patient['fname'] ?? 'Patient');
$ptInitials  = syn_e($patient['initials'] ?? 'P');
$ptDOB       = syn_e($patient['DOB'] ?? '');
$ptMRN       = syn_e($patient['pubpid'] ?? '');
$ptIns       = syn_e($patient['insurance_name'] ?? 'Insurance');
$ptPCP       = syn_e($patient['pcp_name'] ?? '—');

$unreadCount = count(array_filter($onsite_messages, fn($m) => !empty($m['is_unread'])));
$outstanding = number_format((float)($billing['outstanding'] ?? 0), 2);
$nextAppt    = $appointments[0] ?? null;
$goalsDone   = count(array_filter($care_plan, fn($g) => ($g['progress'] ?? 0) >= 100));
$goalsTotal  = count($care_plan);
$planPct     = $goalsTotal > 0 ? round(($goalsDone / $goalsTotal) * 100) : 0;

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Patient Portal — <?= $ptName ?></title>
    <link rel="stylesheet" href="<?= syn_e($module_path) ?>/css/synapta-portal.css" />
</head>

<body class="syn-body">

    <div class="syn-app">

        <!-- ══════════════════════════════════════════════════════════════
       TOP BAR
  ═══════════════════════════════════════════════════════════════ -->
        <header class="syn-topbar">
            <div class="syn-t-brand">
                <!-- Logo / clinic name -->
                <svg width="200" height="38" viewBox="0 0 420 80" xmlns="http://www.w3.org/2000/svg"
                    aria-label="Synapta">
                    <line x1="40" y1="29" x2="40" y2="16" stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="51" y1="35" x2="63" y2="28" stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="51" y1="45" x2="63" y2="52" stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="40" y1="51" x2="40" y2="64" stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="29" y1="45" x2="17" y2="52" stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="29" y1="35" x2="17" y2="28" stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round" />
                    <circle cx="40" cy="40" r="10" fill="#1D9E75" />
                    <circle cx="40" cy="40" r="6" fill="#085041" />
                    <rect x="37.5" y="33.5" width="5" height="13" rx="1" fill="white" opacity="0.6" />
                    <rect x="34" y="37" width="12" height="5" rx="1" fill="white" opacity="0.6" />
                    <circle cx="40" cy="12" r="5" fill="#1D9E75" />
                    <circle cx="40" cy="12" r="2.5" fill="#085041" />
                    <circle cx="67" cy="25" r="5" fill="#7F77DD" />
                    <circle cx="67" cy="25" r="2.5" fill="#3C3489" />
                    <circle cx="67" cy="55" r="5" fill="#1D9E75" />
                    <circle cx="67" cy="55" r="2.5" fill="#085041" />
                    <circle cx="40" cy="68" r="5" fill="#7F77DD" />
                    <circle cx="40" cy="68" r="2.5" fill="#3C3489" />
                    <circle cx="13" cy="55" r="5" fill="#1D9E75" />
                    <circle cx="13" cy="55" r="2.5" fill="#085041" />
                    <circle cx="13" cy="25" r="5" fill="#7F77DD" />
                    <circle cx="13" cy="25" r="2.5" fill="#3C3489" />
                    <line x1="82" y1="16" x2="82" y2="64" stroke="#D3D1C7" stroke-width="0.5" />
                    <text x="96" y="47" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="500"
                        font-size="30" letter-spacing="-0.3">
                        <tspan fill="#0F6E56">Syn</tspan>
                        <tspan fill="#534AB7">apta</tspan>
                    </text>
                    <text x="96" y="63" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="400"
                        font-size="8.5" letter-spacing="1.6" fill="#888780">AI · EHR · HEALTHCARE INTELLIGENCE</text>
                </svg>
            </div>

            <!-- Patient identity strip -->
            <div class="syn-t-pt">
                <div class="syn-pt-av"><?= $ptInitials ?></div>
                <div>
                    <div class="syn-pt-name">
                        <?= $greeting ?>, <?= $ptName ?> 👋
                    </div>
                    <div class="syn-pt-meta">
                        <?php if ($ptDOB): ?>DOB: <?= $ptDOB ?> ·<?php endif; ?>
                        <?php if ($ptMRN): ?>MRN: <?= $ptMRN ?> ·<?php endif; ?>
                        <?php if ($ptIns): ?><?= $ptIns ?> ·<?php endif; ?>
                        <?php if ($ptPCP): ?>PCP: <?= $ptPCP ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top-bar actions -->
            <div class="syn-t-acts">
                <button class="syn-hero-btn syn-ghost" onclick="openModal('triage')">🩺 Symptom Check</button>
                <?php if ($nextAppt && !empty($nextAppt['is_tele'])): ?>
                <button class="syn-tbtn syn-tbtn-ai">
                    <span class="syn-dlive"></span> Join Tele-Visit
                </button>
                <?php endif; ?>
                <button class="syn-tbtn syn-tbtn-pri" onclick="synOpenApptModal()">📅 Book Visit</button>

                <div class="t-prov" id="profileMenu">
                    <div class="prov-trigger">
                        <div class="prov-av"><?=$ptInitials;?></div>
                        <div class="prov-name"><?= $ptName ?></div>
                        <span class="prov-arrow">▼</span>
                    </div>

                    <div class="prov-dropdown">
                        <a href="<?= $GLOBALS['web_root'] ?>/portal/logout.php" class="prov-dropdown-item">
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- ══════════════════════════════════════════════════════════════
       SIDEBAR NAV
  ═══════════════════════════════════════════════════════════════ -->
        <nav class="syn-sidebar">
            <div class="syn-ni syn-on" data-tab="dashboard" title="Home">🏠
                <span class="syn-ntip">Home</span>
            </div>
            <div class="syn-ni" data-tab="appointments" title="Appointments">📅
                <span class="syn-ntip">Appointments</span>
            </div>
            <div class="syn-ni" data-tab="messages" title="Messages">
                <?php if ($unreadCount > 0): ?>
                <span class="syn-nb"><?= min($unreadCount, 9) ?></span>
                <?php endif; ?>
                💬<span class="syn-ntip">Messages</span>
            </div>
            <div class="syn-ni" data-tab="records" title="Records">📋
                <span class="syn-ntip">Records &amp; Results</span>
            </div>
            <div class="syn-nsep"></div>
            <div class="syn-ni" data-tab="medications" title="Medications">💊
                <span class="syn-ntip">Medications</span>
            </div>
            <div class="syn-ni" data-tab="billing" title="Billing">💰
                <span class="syn-ntip">Billings </span>
            </div>
            <div class="syn-ni" onclick="synOpenIntake()" title="Intake Form">📝
                <span class="syn-ntip">Intake Form</span>
            </div>
            <div class="syn-nsep"></div>
            <div class="syn-ni syn-nbot">
                <a href="<?= $GLOBALS['web_root'] ?>/portal/account/account.php" title="Settings">⚙️</a>
                <span class="syn-ntip">Settings</span>
            </div>
        </nav>

        <!-- ══════════════════════════════════════════════════════════════
       MAIN CONTENT AREA
  ═══════════════════════════════════════════════════════════════ -->
        <main class="syn-main">

            <!-- Sub-tabs strip -->
            <div class="syn-sstrip">
                <div class="syn-stabs">
                    <button class="syn-stab syn-on" data-tab="dashboard">🏠 Home</button>
                    <button class="syn-stab" data-tab="appointments">📅 Appointments</button>
                    <button class="syn-stab" data-tab="messages">
                        💬 Messages
                        <?php if ($unreadCount > 0): ?>
                        <span class="syn-badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="syn-stab" data-tab="health">📈 Health Trends</button>
                    <button class="syn-stab" data-tab="records">📋 Records & Results</button>
                    <!-- <button class="syn-stab" data-tab="medications">💊 Medications</button> -->
                    <button class="syn-stab" data-tab="billing">💰 Billings</button>

                    <button class="syn-sbtn" style="font-weight:600; border:0px;" onclick="synOpenIntake()">
                        📝 Intake Form
                    </button>
                </div>
            </div>

            <!-- ════════════════════  HOME  ════════════════════ -->
            <div id="syn-tab-dashboard" class="syn-tpanel syn-on">
                <div class="syn-tscroll">

                    <!-- Hero greeting banner -->
                    <div class="syn-hero syn-fade">
                        <div>
                            <div class="syn-hero-lbl">✦ Synapta · Today's Health Snapshot</div>
                            <div class="syn-hero-ttl">
                                <?= $greeting ?>, <?= $ptName ?>.
                                <?php if ($nextAppt): ?>
                                Your next appointment is
                                <strong><?= syn_e($nextAppt['date']) ?></strong>
                                with <?= syn_e($nextAppt['provider'] ?? 'your provider') ?>.
                                <?php else: ?>
                                You have no upcoming appointments scheduled.
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="syn-hero-actions">

                            <button class="syn-hero-btn" onclick="synOpenApptModal()">📅 Book Visit</button>

                            <button class="syn-stab syn-hero-btn syn-ghost" data-tab="messages">
                                💬 Messages
                                <?php if ($unreadCount > 0): ?>
                                <span class="syn-badge"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>

                    <!-- Stats row -->
                    <div class="syn-four-col">
                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">Next Appointment</div>
                            <?php if ($nextAppt): ?>
                            <div class="syn-stat-val syn-p"><?= syn_e(date('M d', strtotime($nextAppt['date']))) ?>
                            </div>
                            <div class="syn-stat-sub"><?= syn_e($nextAppt['provider'] ?? '') ?></div>
                            <?php else: ?>
                            <div class="syn-stat-val">None</div>
                            <div class="syn-stat-sub">No upcoming visits</div>
                            <?php endif; ?>
                        </div>

                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">Care Plan Progress</div>
                            <div
                                class="syn-stat-val <?= $planPct >= 80 ? 'syn-g' : ($planPct >= 50 ? 'syn-t' : 'syn-a') ?>">
                                <?= $planPct ?>%
                            </div>
                            <div class="syn-stat-sub"><?= $goalsDone ?> of <?= $goalsTotal ?> goals</div>
                        </div>

                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">Unread Messages</div>
                            <div class="syn-stat-val <?= $unreadCount > 0 ? 'syn-a' : 'syn-g' ?>">
                                <?= $unreadCount ?>
                            </div>
                            <div class="syn-stat-sub">
                                <?= $unreadCount === 0 ? 'All caught up' : 'New messages waiting' ?>
                            </div>
                        </div>

                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">Outstanding Balance</div>
                            <div class="syn-stat-val <?= $outstanding > 0 ? 'syn-a' : 'syn-g' ?>">
                                $<?= $outstanding ?>
                            </div>
                            <div class="syn-stat-sub">
                                <?= $outstanding > 0 ? 'Payment due' : 'No balance due' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Triage + Trends row -->
                    <div class="two-col">
                        <div class="triage-card fade">
                            <div class="triage-lbl">✦ AI Symptom Triage · Phase 3</div>
                            <div class="triage-ttl">Not feeling well? Get guidance in 60 seconds</div>
                            <div class="triage-meta">Synapta's guided questionnaire helps you decide whether to book a
                                visit, message your team, or seek urgent care. Not a diagnosis — guidance only.</div>
                            <button class="triage-btn" onclick="openModal('triage')">Start Symptom Check →</button>
                        </div>


                        <div class="trend-slider">



                            <div class="trend-slides">

                                <!-- Slide 1 : Home BP -->
                                <div class="trend-slide active">
                                    <div class="trend-card fade">

                                        <div class="trend-lbl trend-header">
                                            <span> 📈 Home Blood Pressure · Last 60 days </span>

                                            <div class="trend-controls">
                                                <button class="trend-btn" onclick="changeTrend(-1)">❮</button>
                                                <button class="trend-btn" onclick="changeTrend(1)">❯</button>
                                            </div>
                                        </div>

                                        <!-- Trend Header -->
                                        <div class="trend-ttl">

                                            <span>Trend</span>

                                            <span class="trend-val"
                                                style="color:<?= htmlspecialchars($home_bp['color'] ?? '#C77A0A') ?>;">

                                                <?= htmlspecialchars($home_bp['formatted'] ?? 'N/A') ?>

                                                <?php if (($home_bp['status'] ?? '') == 'High') { ?>
                                                ↑
                                                <?php } ?>

                                            </span>

                                        </div>

                                        <!-- Graph -->
                                        <svg class="trend-svg" viewBox="0 0 300 60" preserveAspectRatio="none">

                                            <defs>

                                                <linearGradient id="bpgrad" x1="0" y1="0" x2="0" y2="1">

                                                    <stop offset="0%"
                                                        stop-color="<?= htmlspecialchars($home_bp['color'] ?? '#C77A0A') ?>"
                                                        stop-opacity="0.3" />

                                                    <stop offset="100%"
                                                        stop-color="<?= htmlspecialchars($home_bp['color'] ?? '#C77A0A') ?>"
                                                        stop-opacity="0" />

                                                </linearGradient>

                                            </defs>

                                            <!-- Area -->
                                            <path
                                                d="M0,40 L25,38 L50,42 L75,35 L100,30 L125,32 L150,28 L175,25 L200,22 L225,28 L250,18 L275,15 L300,12 L300,60 L0,60 Z"
                                                fill="url(#bpgrad)" />

                                            <!-- Line -->
                                            <polyline
                                                points="0,40 25,38 50,42 75,35 100,30 125,32 150,28 175,25 200,22 225,28 250,18 275,15 300,12"
                                                fill="none"
                                                stroke="<?= htmlspecialchars($home_bp['color'] ?? '#C77A0A') ?>"
                                                stroke-width="2" />

                                            <!-- Goal Line -->
                                            <line x1="0" y1="20" x2="300" y2="20" stroke="#1D9E75" stroke-width="1"
                                                stroke-dasharray="3,3" opacity="0.5" />

                                            <!-- Goal Text -->
                                            <text x="298" y="18" font-size="7" fill="#1D9E75" text-anchor="end"
                                                font-weight="700">

                                                Goal &lt;130/80

                                            </text>

                                        </svg>

                                        <!-- Footer -->
                                        <div class="trend-foot">

                                            <span>Last Reading</span>

                                            <span>
                                                <?= htmlspecialchars($home_bp['date'] ?? '') ?>
                                            </span>

                                            <span>
                                                <?= htmlspecialchars($home_bp['status'] ?? '') ?>
                                            </span>

                                        </div>

                                    </div>
                                </div>

                                <!-- Slide 2 : Glucose -->
                                <div class="trend-slide">
                                    <div class="trend-card fade">

                                        <div class="trend-lbl trend-header">
                                            <span>
                                                🩸 Glucose (CGM avg)</span>

                                            <div class="trend-controls">
                                                <button class="trend-btn" onclick="changeTrend(-1)">❮</button>
                                                <button class="trend-btn" onclick="changeTrend(1)">❯</button>
                                            </div>


                                        </div>

                                        <div class="trend-ttl"><span>14 days</span><span class="trend-val"
                                                style="color:var(--green);">128 mg/dL</span></div>
                                        <svg class="trend-svg" viewBox="0 0 300 60" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="bg2" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#0F6E56" stop-opacity="0.25" />
                                                    <stop offset="100%" stop-color="#0F6E56" stop-opacity="0" />
                                                </linearGradient>
                                            </defs>
                                            <path
                                                d="M0,28 L25,30 L50,25 L75,32 L100,28 L125,22 L150,30 L175,26 L200,28 L225,24 L250,22 L275,25 L300,22 L300,60 L0,60 Z"
                                                fill="url(#bg2)" />
                                            <polyline
                                                points="0,28 25,30 50,25 75,32 100,28 125,22 150,30 175,26 200,28 225,24 250,22 275,25 300,22"
                                                fill="none" stroke="#0F6E56" stroke-width="2" />
                                        </svg>
                                        <div class="trend-foot"><span>Time in range: 78%</span><span
                                                style="color:var(--green);">Goal &gt; 70%</span></div>
                                    </div>
                                </div>

                            </div>

                            <!-- Slide 3 : Weight -->
                            <div class="trend-slide">
                                <div class="trend-card fade">
                                    <div class="trend-lbl trend-header">
                                        <span>⚖️ Weight </span>

                                        <div class="trend-controls">
                                            <button class="trend-btn" onclick="changeTrend(-1)">❮</button>
                                            <button class="trend-btn" onclick="changeTrend(1)">❯</button>
                                        </div>
                                    </div>

                                    <!-- Weight Value -->
                                    <div class="trend-ttl">

                                        <span>90 days</span>

                                        <span class="trend-val" style="color:var(--green);">

                                            <?= htmlspecialchars($weight_trend['weight'] ?? '0') ?> lb

                                        </span>

                                    </div>

                                    <!-- Graph -->
                                    <svg class="trend-svg" viewBox="0 0 300 60" preserveAspectRatio="none">

                                        <defs>

                                            <linearGradient id="wt2" x1="0" y1="0" x2="0" y2="1">

                                                <stop offset="0%" stop-color="#1D9E75" stop-opacity="0.25" />

                                                <stop offset="100%" stop-color="#1D9E75" stop-opacity="0" />

                                            </linearGradient>

                                        </defs>

                                        <!-- Area -->
                                        <path
                                            d="M0,15 L30,18 L60,17 L90,22 L120,25 L150,28 L180,30 L210,32 L240,35 L270,38 L300,40 L300,60 L0,60 Z"
                                            fill="url(#wt2)" />

                                        <!-- Line -->
                                        <polyline
                                            points="0,15 30,18 60,17 90,22 120,25 150,28 180,30 210,32 240,35 270,38 300,40"
                                            fill="none" stroke="#1D9E75" stroke-width="2" />

                                    </svg>

                                    <!-- Footer -->
                                    <div class="trend-foot">

                                        <span>

                                            Last Updated:
                                            <?= htmlspecialchars($weight_trend['date'] ?? '') ?>

                                        </span>

                                        <span style="color:var(--green);">

                                            On track to goal

                                        </span>

                                    </div>

                                </div>


                            </div>



                        </div>



                    </div>

                    <!-- Upcoming appointments card -->
                    <div class="syn-two-col">
                        <div class="syn-card syn-fade">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">📅 Upcoming Appointments</div>
                            </div>
                            <div class="syn-card-body">
                                <?php if (empty($appointments)): ?>
                                <p class="syn-empty">No upcoming appointments.
                                    <a href="#" onclick="synOpenApptModal();return false;">Book
                                        one →</a>
                                </p>
                                <?php else: ?>
                                <?php foreach (array_slice($appointments, 0, 3) as $appt): ?>
                                <?php
                  $apptDate  = date('M', strtotime($appt['date']));
                  $apptDay   = date('d',  strtotime($appt['date']));
                  $apptTime  = substr($appt['time'] ?? '', 0, 5);
                  $isTele    = !empty($appt['is_tele']);
                  ?>
                                <div class="syn-appt <?= $isTele ? 'syn-tele' : '' ?>">
                                    <div class="syn-appt-date <?= $isTele ? 'syn-tele-date' : '' ?>">
                                        <div class="syn-appt-d-mo"><?= syn_e($apptDate) ?></div>
                                        <div class="syn-appt-d-day"><?= syn_e($apptDay) ?></div>
                                        <div class="syn-appt-d-time"><?= syn_e($apptTime) ?></div>
                                    </div>
                                    <div class="syn-appt-info">
                                        <div class="syn-appt-ttl">
                                            <?= syn_e($appt['title'] ?? 'Appointment') ?>
                                            <?php if ($isTele): ?>
                                            <span class="syn-appt-tag syn-tag-tele">📹 Telehealth</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="syn-appt-meta"><?= syn_e($appt['provider'] ?? '') ?></div>
                                        <div class="syn-appt-loc"><?= syn_e($appt['location'] ?? '') ?></div>
                                    </div>
                                    <div class="syn-appt-actions">
                                        <?php if ($isTele): ?>
                                        <button class="syn-minibtn syn-tele-btn">Join</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="syn-card-foot">
                                <a href="#" data-tab-link="appointments">View all →</a>
                            </div>
                        </div>

                        <!-- Recent messages card -->

                        <!-- Recent Inbox Messages Card -->
                        <div class="syn-card syn-fade">

                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">📨 Recent Inbox Messages</div>
                            </div>

                            <div class="syn-card-body syn-no-pad">

                                <?php
        /*
        |--------------------------------------------------------------------------
        | FILTER ONLY INBOX MESSAGES
        |--------------------------------------------------------------------------
        */

        $recentInboxMessages = array_values(array_filter(
            $onsite_messages ?? [],
            fn($m) => ($m['direction'] ?? '') === 'in'
        ));
        ?>

                                <?php if (empty($recentInboxMessages)): ?>

                                <p class="syn-empty syn-pad">
                                    No inbox messages yet.
                                </p>

                                <?php else: ?>

                                <?php foreach (array_slice($recentInboxMessages, 0, 4) as $msg): ?>

                                <?php $isUnread = !empty($msg['unread']); ?>

                                <div class="syn-msg <?= $isUnread ? 'syn-unread' : '' ?>" onclick="synOpenMessage(
               <?= (int)($msg['id'] ?? 0) ?>,
                '<?= syn_e(addslashes($msg['sender_name'] ?? 'Care Team')) ?>',
                '<?= syn_e(addslashes($msg['subject'] ?? '(no subject)')) ?>',
                '<?= syn_e(addslashes(nl2br(strip_tags($msg['body'] ?? '')))) ?>',
                '<?= syn_e($msg['date'] ?? '') ?>',
                <?= ($isUnread && !$isOut) ? 'true' : 'false' ?>
             )">

                                    <!-- Avatar -->
                                    <div class="syn-msg-av">

                                        <?= syn_e(
                    strtoupper(
                        substr($msg['sender_name'] ?? 'C', 0, 2)
                    )
                ) ?>

                                    </div>

                                    <!-- Content -->
                                    <div class="syn-msg-content">

                                        <div class="syn-msg-from">

                                            <?= syn_e($msg['sender_name'] ?? 'Care Team') ?>

                                            <span class="syn-msg-time">

                                                <?= syn_e(
                            date(
                                'M d, g:i a',
                                strtotime($msg['date'] ?? 'now')
                            )
                        ) ?>

                                            </span>

                                        </div>

                                        <div class="syn-msg-subj">

                                            <?= syn_e($msg['subject'] ?? '(no subject)') ?>

                                            <?php if (!empty($msg['mtype'])): ?>
                                            <span class="syn-om-tag">
                                                <?= syn_e($msg['mtype']) ?>
                                            </span>
                                            <?php endif; ?>

                                        </div>

                                        <div class="syn-msg-snip">

                                            <?= syn_e(
                        mb_strimwidth(
                            strip_tags($msg['body'] ?? ''),
                            0,
                            100,
                            '…'
                        )
                    ) ?>

                                        </div>

                                    </div>

                                    <!-- Unread Dot -->
                                    <?php if ($isUnread): ?>

                                    <div class="syn-msg-dot"></div>

                                    <?php endif; ?>

                                </div>

                                <?php endforeach; ?>

                                <?php endif; ?>

                            </div>

                            <div class="syn-card-foot">

                                <a href="#" data-tab-link="messages">

                                    View all →

                                </a>

                            </div>

                        </div>
                        <div class="syn-card syn-fade" style="display:none;">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">💬 Recent Messages</div>
                            </div>
                            <div class="syn-card-body syn-no-pad">
                                <?php if (empty($messages)): ?>
                                <p class="syn-empty syn-pad">No messages yet.</p>
                                <?php else: ?>
                                <?php foreach (array_slice($messages, 0, 4) as $msg): ?>
                                <div class="syn-msg <?= !empty($msg['unread']) ? 'syn-unread' : '' ?>">
                                    <div class="syn-msg-av">
                                        <?= syn_e(strtoupper(substr($msg['sender'] ?? 'S', 0, 2))) ?>
                                    </div>
                                    <div class="syn-msg-content">
                                        <div class="syn-msg-from">
                                            <?= syn_e($msg['sender'] ?? 'Staff') ?>
                                            <span class="syn-msg-time"><?= syn_e($msg['date'] ?? '') ?></span>
                                        </div>
                                        <div class="syn-msg-subj"><?= syn_e($msg['subject'] ?? '(no subject)') ?></div>
                                        <div class="syn-msg-snip">
                                            <?= syn_e(mb_strimwidth(strip_tags($msg['body'] ?? ''), 0, 100, '…')) ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($msg['unread'])): ?>
                                    <div class="syn-msg-dot"></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="syn-card-foot">
                                <a href="#" data-tab-link="messages">View all →</a>
                            </div>
                        </div>


                    </div>

                    <!-- Care plan goals -->
                    <div class="syn-two-col">
                    <?php if (!empty($care_plan)): ?>
                    <div class="syn-card syn-fade">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">🎯 Care Plan Goals</div>
                        </div>

                        <div class="syn-card-body">

                            <?php foreach ($care_plan as $plan): ?>

                            <div class="syn-careplan-item">

                                <div class="syn-careplan-top">
                                    <div class="syn-careplan-code">
                                        <?= syn_e($plan['code'] ?? '') ?>
                                    </div>

                                    <div class="syn-careplan-date">
                                        <?= !empty($plan['date']) ? date('d M Y', strtotime($plan['date'])) : '-' ?>
                                    </div>
                                </div>

                                <div class="syn-careplan-title">
                                    <?= syn_e($plan['codetext'] ?? '') ?>
                                </div>

                                <div class="syn-careplan-desc">
                                    <?= nl2br(syn_e($plan['description'] ?? '')) ?>
                                </div>

                                <div class="syn-careplan-meta">
                                    <span>
                                        <strong>User:</strong>
                                        <?= syn_e($plan['user'] ?? '-') ?>
                                    </span>
                                    

                                    <span>
                                        <strong>Encounter:</strong>
                                        <?= syn_e($plan['encounter_name'] ?? '-') ?> <small>(<?= syn_e($plan['encounter_date'] ?? '-') ?>)</small>
                                    </span>
                                    
                                </div>

                                <?php if (!empty($plan['care_plan_type']) || !empty($plan['plan_status'])): ?>
                                <div class="syn-careplan-tags">

                                    <?php if (!empty($plan['care_plan_type'])): ?>
                                    <span class="syn-tag syn-tag-blue">
                                        <?= syn_e($plan['care_plan_type']) ?>
                                    </span>
                                    <?php endif; ?>

                                    <?php if (!empty($plan['plan_status'])): ?>
                                    <span class="syn-tag syn-tag-green">
                                        <?= syn_e($plan['plan_status']) ?>
                                    </span>
                                    <?php endif; ?>

                                </div>
                                <?php endif; ?>

                            </div>

                            <?php endforeach; ?>

                        </div>
                    </div>
                    <?php endif; ?>
                                   
                    <!-- ── Payment History ───────────────────────────────────────── -->
                        <div class="syn-card syn-fade">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">Payment History</div>
                                <?php if (!empty($billing['history'])): ?>
                                <span class="syn-card-hd-sub">
                                    <?= count($billing['history']) ?>
                                    transaction<?= count($billing['history']) !== 1 ? 's' : '' ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="syn-card-body syn-no-pad">
                                <?php if (empty($billing['history'])): ?>
                                <div class="syn-empty-state">
                                    <div class="syn-empty-icon">📋</div>
                                    <div class="syn-empty-ttl">No payment history</div>
                                    <div class="syn-empty-sub">Completed payments will appear here</div>
                                </div>
                                  <?php else: ?>
                                  <?php foreach ($billing['history'] as $h): ?>
                                  <?php
                                    $paidDate  = !empty($h['paid_date'])
                                                ? date('M d, Y', strtotime($h['paid_date']))
                                                : '';
                                    $encDate   = !empty($h['enc_date'])
                                                ? date('M d, Y', strtotime($h['enc_date']))
                                                : $paidDate;
                                    $isPatient = ((int)($h['payer_type'] ?? 0) === 0);
                                    $payerLbl  = $isPatient ? 'Patient payment' : 'Insurance payment';
                                    ?>
                                <div class="syn-bill2-row">
                                    <div class="syn-bill2-info">
                                        <div class="syn-bill2-ttl">
                                            <?= syn_e($encDate) ?>
                                            <?php if (!empty($h['description'])): ?>
                                            — <?= syn_e($h['description']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="syn-bill2-sub">
                                            Paid <?= syn_e($paidDate) ?>
                                            · <?= $payerLbl ?>
                                        </div>
                                    </div>
                                    <div class="syn-bill2-right">
                                        <div class="syn-bill2-amt syn-bill2-paid">
                                            $<?= number_format((float)($h['amount'] ?? 0), 2) ?> ✓
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>                 
 </div>
                </div><!-- /syn-tscroll -->
            </div><!-- /dashboard panel -->

            <!-- ════════════════════  APPOINTMENTS  ════════════════════ -->
            <div id="syn-tab-appointments" class="syn-tpanel">
                <div class="syn-tscroll">
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">Appointments</div>
                            <div class="syn-page-sub">Your upcoming and past visits</div>
                        </div>
                        <button class="syn-sbtn syn-on-teal" onclick="synOpenApptModal()">+ Book New</button>

                    </div>

                    <div class="syn-card syn-fade">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">Upcoming</div>
                        </div>
                        <div class="syn-card-body">
                            <?php if (empty($appointments)): ?>
                            <p class="syn-empty">No upcoming appointments.</p>
                            <?php else: ?>
                            <?php foreach ($appointments as $appt): ?>
                            <?php $isTele = !empty($appt['is_tele']); ?>
                            <div class="syn-appt <?= $isTele ? 'syn-tele' : '' ?>">
                                <div class="syn-appt-date <?= $isTele ? 'syn-tele-date' : '' ?>">
                                    <div class="syn-appt-d-mo"><?= syn_e(date('M', strtotime($appt['date']))) ?></div>
                                    <div class="syn-appt-d-day"><?= syn_e(date('d', strtotime($appt['date']))) ?></div>
                                    <div class="syn-appt-d-time"><?= syn_e(substr($appt['time'] ?? '', 0, 5)) ?></div>
                                </div>
                                <div class="syn-appt-info">
                                    <div class="syn-appt-ttl">
                                        <?= syn_e($appt['title'] ?? 'Appointment') ?>
                                        <?php if ($isTele): ?>
                                        <span class="syn-appt-tag syn-tag-tele">📹 Telehealth</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syn-appt-meta"><?= syn_e($appt['provider'] ?? '') ?></div>
                                    <div class="syn-appt-loc"><?= syn_e($appt['location'] ?? '') ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($past_visits)): ?>
                    <div class="syn-card syn-fade" style="margin-top:12px;">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">Recent Visits</div>
                        </div>
                        <div class="syn-card-body">
                            <?php foreach ($past_visits as $visit): ?>
                            <div class="syn-appt" style="opacity:.85;">
                                <div class="syn-appt-date">
                                    <div class="syn-appt-d-mo"><?= syn_e(date('M', strtotime($visit['date']))) ?></div>
                                    <div class="syn-appt-d-day"><?= syn_e(date('d', strtotime($visit['date']))) ?></div>
                                    <div class="syn-appt-d-time"><?= syn_e(substr($visit['time'] ?? '', 0, 5)) ?></div>
                                </div>
                                <div class="syn-appt-info">
                                    <div class="syn-appt-ttl"><?= syn_e($visit['reason'] ?? 'Office Visit') ?></div>
                                    <div class="syn-appt-meta"><?= syn_e($visit['provider'] ?? '') ?></div>
                                    <div class="syn-appt-loc"><?= syn_e($visit  ['location'] ?? '') ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ════════════════════  MESSAGES  ════════════════════ -->
            <div id="syn-tab-messages" class="syn-tpanel">
                <div class="syn-tscroll">

                    <!-- Page header -->
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">Secure Messages</div>
                            <div class="syn-page-sub">Private communication with your care team</div>
                        </div>
                        <button class="syn-sbtn syn-compose-btn" onclick="synOpenCompose()"
                            style="background:var(--syn-teal);color:#fff;border-color:var(--syn-teal);font-weight:600;">
                            ✉ New Message
                        </button>
                    </div>

                    <?php
/*
|--------------------------------------------------------------------------
| PREPARE DATA
|--------------------------------------------------------------------------
*/

$onsiteAll = $onsite_messages ?? [];

$onsiteIn = array_filter(
    $onsiteAll,
    fn($m) => ($m['direction'] ?? '') === 'in'
);

$onsiteOut = array_filter(
    $onsiteAll,
    fn($m) => ($m['direction'] ?? '') === 'out'
);

$onsiteUnread = count(array_filter(
    $onsiteIn,
    fn($m) => !empty($m['unread'])
));
?>

                    <!-- =========================================================
     PORTAL MAIL TABS
========================================================= -->
                    <div class="syn-msg-tabs">

                        <!-- INBOX -->
                        <button class="syn-portal-tab" data-portaltab="inbox">

                            📨 Inbox

                            <?php if ($onsiteUnread > 0): ?>
                            <span class="syn-badge">
                                <?= $onsiteUnread ?>
                            </span>
                            <?php endif; ?>

                        </button>



                        <!-- SENT -->
                        <button class="syn-portal-tab" data-portaltab="sent">

                            📤 Sent

                            <?php if (!empty($onsiteOut)): ?>
                            <span class="syn-badge syn-badge-grey">
                                <?= count($onsiteOut) ?>
                            </span>
                            <?php endif; ?>

                        </button>



                        <!-- ALL -->
                        <button class="syn-portal-tab syn-on" data-portaltab="all">

                            🏥 All

                            <?php if (!empty($onsiteAll)): ?>
                            <span class="syn-badge syn-badge-grey">
                                <?= count($onsiteAll) ?>
                            </span>
                            <?php endif; ?>

                        </button>

                    </div>



                    <!-- =========================================================
     INBOX TAB
========================================================= -->
                    <div id="syn-portaltab-inbox" class="syn-portal-panel syn-card syn-fade">

                        <div class="syn-card-hd">

                            <div class="syn-card-ttl">
                                📨 Inbox

                                <?php if ($onsiteUnread > 0): ?>
                                <span class="syn-badge" style="margin-left:8px;">
                                    <?= $onsiteUnread ?> unread
                                </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($onsiteIn)): ?>
                            <span class="syn-card-hd-sub">
                                <?= count($onsiteIn) ?>
                                message<?= count($onsiteIn) !== 1 ? 's' : '' ?>
                            </span>
                            <?php endif; ?>

                        </div>

                        <div class="syn-card-body syn-no-pad">

                            <?php if (empty($onsiteIn)): ?>

                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">📨</div>
                                <div class="syn-empty-ttl">No inbox messages</div>
                                <div class="syn-empty-sub">
                                    Received portal mail will appear here
                                </div>
                            </div>

                            <?php else: ?>

                            <?php foreach ($onsiteIn as $msg): ?>

                            <?php $isUnread = !empty($msg['unread']); ?>

                            <div class="syn-msg-row <?= $isUnread ? 'syn-unread' : '' ?>"
                                data-om-id="<?= (int)($msg['id'] ?? 0) ?>" onclick="synOpenMessage(<?= (int)($msg['id'] ?? 0) ?>,
    '<?= syn_e(addslashes($msg['sender_name'] ?? 'Care Team')) ?>',
    '<?= syn_e(addslashes($msg['subject'] ?? '(no subject)')) ?>',
    '<?= syn_e(addslashes(nl2br(strip_tags($msg['body'] ?? '')))) ?>',
    '<?= syn_e($msg['date'] ?? '') ?>',
    <?= $isUnread ? 'true' : 'false' ?>
             )">

                                <div class="syn-msg-av syn-av-in">
                                    <?= syn_e(strtoupper(substr($msg['sender_name'] ?? 'C', 0, 2))) ?>
                                </div>

                                <div class="syn-msg-content">

                                    <div class="syn-msg-row-top">

                                        <span class="syn-msg-from <?= $isUnread ? 'syn-fw700' : '' ?>">
                                            <?= syn_e($msg['sender_name'] ?? 'Care Team') ?>
                                        </span>

                                        <span class="syn-msg-time">
                                            <?= syn_e(date('M d, g:i a', strtotime($msg['date'] ?? 'now'))) ?>
                                        </span>

                                    </div>

                                    <div class="syn-msg-subj <?= $isUnread ? 'syn-fw600' : '' ?>">

                                        <?= syn_e($msg['subject'] ?? '(no subject)') ?>

                                        <?php if (!empty($msg['mtype'])): ?>
                                        <span class="syn-om-tag">
                                            <?= syn_e($msg['mtype']) ?>
                                        </span>
                                        <?php endif; ?>

                                    </div>

                                    <div class="syn-msg-snip">

                                        <?= syn_e(
                        mb_strimwidth(
                            strip_tags($msg['body'] ?? ''),
                            0,
                            120,
                            '…'
                        )
                    ) ?>

                                    </div>

                                </div>

                                <div class="syn-msg-right">

                                    <?php if ($isUnread): ?>
                                    <div class="syn-unread-dot"></div>
                                    <?php endif; ?>

                                    <div class="syn-msg-chev">›</div>

                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- =========================================================
     SENT TAB
========================================================= -->
                    <div id="syn-portaltab-sent" class="syn-portal-panel syn-card syn-fade" style="display:none;">

                        <div class="syn-card-hd">

                            <div class="syn-card-ttl">
                                📤 Sent Messages
                            </div>

                            <?php if (!empty($onsiteOut)): ?>
                            <span class="syn-card-hd-sub">
                                <?= count($onsiteOut) ?>
                                message<?= count($onsiteOut) !== 1 ? 's' : '' ?>
                            </span>
                            <?php endif; ?>

                        </div>

                        <div class="syn-card-body syn-no-pad">

                            <?php if (empty($onsiteOut)): ?>

                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">📤</div>
                                <div class="syn-empty-ttl">No sent messages</div>
                                <div class="syn-empty-sub">
                                    Messages you send will appear here
                                </div>
                            </div>

                            <?php else: ?>

                            <?php foreach ($onsiteOut as $msg): ?>

                            <div class="syn-msg-row" onclick="synOpenMessage(
                <?= (int)($msg['id'] ?? 0) ?>,
                'You → <?= syn_e(addslashes($msg['recipient_name'] ?? 'Care Team')) ?>',
                '<?= syn_e(addslashes($msg['subject'] ?? '(no subject)')) ?>',
                '<?= syn_e(addslashes(nl2br(strip_tags($msg['body'] ?? '')))) ?>',
                '<?= syn_e($msg['date'] ?? '') ?>',
                <?= ($isUnread && !$isOut) ? 'true' : 'false' ?>

                
             )">

                                <div class="syn-msg-av syn-av-out">
                                    <?= syn_e(strtoupper(substr($msg['recipient_name'] ?? 'C', 0, 2))) ?>
                                </div>

                                <div class="syn-msg-content">

                                    <div class="syn-msg-row-top">

                                        <span class="syn-msg-from">
                                            To:
                                            <?= syn_e($msg['recipient_name'] ?? 'Care Team') ?>
                                        </span>

                                        <span class="syn-msg-time">
                                            <?= syn_e(date('M d, g:i a', strtotime($msg['date'] ?? 'now'))) ?>
                                        </span>

                                    </div>

                                    <div class="syn-msg-subj">

                                        <?= syn_e($msg['subject'] ?? '(no subject)') ?>

                                        <?php if (!empty($msg['mtype'])): ?>
                                        <span class="syn-om-tag">
                                            <?= syn_e($msg['mtype']) ?>
                                        </span>
                                        <?php endif; ?>

                                    </div>

                                    <div class="syn-msg-snip">

                                        <?= syn_e(
                        mb_strimwidth(
                            strip_tags($msg['body'] ?? ''),
                            0,
                            120,
                            '…'
                        )
                    ) ?>

                                    </div>

                                </div>

                                <div class="syn-msg-right">

                                    <span class="syn-sent-tag">Sent</span>

                                    <div class="syn-msg-chev">›</div>

                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- =========================================================
     ALL TAB
========================================================= -->
                    <div id="syn-portaltab-all" class="syn-portal-panel syn-on syn-card syn-fade" style="display:none;">

                        <div class="syn-card-hd">

                            <div class="syn-card-ttl">
                                🏥 Portal Mail

                                <?php if ($onsiteUnread > 0): ?>
                                <span class="syn-badge" style="margin-left:8px;">
                                    <?= $onsiteUnread ?> unread
                                </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($onsiteAll)): ?>
                            <span class="syn-card-hd-sub">
                                <?= count($onsiteAll) ?>
                                message<?= count($onsiteAll) !== 1 ? 's' : '' ?>
                            </span>
                            <?php endif; ?>

                        </div>

                        <div class="syn-card-body syn-no-pad">

                            <?php if (empty($onsiteAll)): ?>

                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">🏥</div>
                                <div class="syn-empty-ttl">No portal mail</div>
                                <div class="syn-empty-sub">
                                    Messages sent through the portal mail system appear here
                                </div>
                            </div>

                            <?php else: ?>

                            <?php foreach ($onsiteAll as $msg): ?>

                            <?php
        $isUnread = !empty($msg['unread']);

        $isOut = ($msg['direction'] ?? '') === 'out';

        $displayName = $isOut
            ? ($msg['recipient_name'] ?? 'Care Team')
            : ($msg['sender_name'] ?? 'Care Team');

        $prefix = $isOut ? 'You → ' : '';
        ?>

                            <div class="syn-msg-row <?= ($isUnread && !$isOut) ? 'syn-unread' : '' ?>"
                                data-om-id="<?= (int)($msg['id'] ?? 0) ?>" onclick="synOpenMessage(
                <?= (int)($msg['id'] ?? 0) ?>,
                '<?= syn_e(addslashes($prefix . $displayName)) ?>',
                '<?= syn_e(addslashes($msg['subject'] ?? '(no subject)')) ?>',
                '<?= syn_e(addslashes(nl2br(strip_tags($msg['body'] ?? '')))) ?>',
                '<?= syn_e($msg['date'] ?? '') ?>',
                <?= ($isUnread && !$isOut) ? 'true' : 'false' ?>
             )">

                                <!-- AVATAR -->
                                <div class="syn-msg-av <?= $isOut ? 'syn-av-out' : 'syn-av-in' ?>">
                                    <?= syn_e(strtoupper(substr($displayName, 0, 2))) ?>
                                </div>

                                <!-- CONTENT -->
                                <div class="syn-msg-content">

                                    <div class="syn-msg-row-top">

                                        <span class="syn-msg-from <?= ($isUnread && !$isOut) ? 'syn-fw700' : '' ?>">

                                            <?= syn_e($prefix . $displayName) ?>

                                        </span>

                                        <span class="syn-msg-time">
                                            <?= syn_e(date('M d, g:i a', strtotime($msg['date'] ?? 'now'))) ?>
                                        </span>

                                    </div>

                                    <div class="syn-msg-subj <?= ($isUnread && !$isOut) ? 'syn-fw600' : '' ?>">

                                        <?= syn_e($msg['subject'] ?? '(no subject)') ?>

                                        <?php if (!empty($msg['mtype'])): ?>
                                        <span class="syn-om-tag">
                                            <?= syn_e($msg['mtype']) ?>
                                        </span>
                                        <?php endif; ?>

                                    </div>

                                    <div class="syn-msg-snip">

                                        <?= syn_e(
                        mb_strimwidth(
                            strip_tags($msg['body'] ?? ''),
                            0,
                            120,
                            '…'
                        )
                    ) ?>

                                    </div>

                                </div>

                                <!-- RIGHT -->
                                <div class="syn-msg-right">

                                    <?php if ($isUnread && !$isOut): ?>
                                    <div class="syn-unread-dot"></div>
                                    <?php endif; ?>

                                    <?php if ($isOut): ?>
                                    <span class="syn-sent-tag">Sent</span>
                                    <?php endif; ?>

                                    <div class="syn-msg-chev">›</div>

                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- Inbox / Sent sub-tabs -->
                    <div class="syn-msg-tabs">
                        <button class="syn-msg-tab syn-on" data-msgtab="inbox">
                            📥 Alerts

                        </button>
                        <button class="syn-msg-tab" data-msgtab="reminder">
                            📤 Reminders

                        </button>
                        <button class="syn-msg-tab" data-msgtab="recalls">
                            📤 Recalls

                        </button>


                    </div>

                    <!-- ── INBOX ─────────────────────────────────────────── -->
                    <div id="syn-msgtab-inbox" class="syn-msg-panel syn-on syn-card syn-fade">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">📥 Alerts

                            </div>
                            <?php if (!empty($messages)): ?>
                            <span class="syn-card-hd-sub"><?= count($messages) ?>
                                message<?= count($messages) !== 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="syn-card-body syn-no-pad">
                            <?php if (empty($messages)): ?>
                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">📭</div>
                                <div class="syn-empty-ttl">Your inbox is empty</div>
                                <div class="syn-empty-sub">Messages from your care team will appear here</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <?php $isUnread = !empty($msg['unread']); ?>
                            <div class="syn-msg-row <?= $isUnread ? 'syn-unread' : '' ?>"
                                data-msg-id="<?= (int)($msg['id'] ?? 0) ?>">
                                <div class="syn-msg-av syn-av-in">
                                    <?= syn_e(strtoupper(substr($msg['sender'] ?? 'C', 0, 2))) ?>
                                </div>
                                <div class="syn-msg-content">
                                    <div class="syn-msg-row-top">
                                        <span class="syn-msg-from <?= $isUnread ? 'syn-fw700' : '' ?>">
                                            <?= syn_e($msg['sender'] ?? 'Care Team') ?>
                                        </span>
                                        <span
                                            class="syn-msg-time"><?= syn_e(date('M d, g:i a', strtotime($msg['date'] ?? 'now'))) ?></span>
                                    </div>
                                    <div class="syn-msg-subj <?= $isUnread ? 'syn-fw600' : '' ?>">
                                        <?= syn_e($msg['subject'] ?? '(no subject)') ?>
                                    </div>
                                    <div class="syn-msg-snip">
                                        <?= syn_e(mb_strimwidth(strip_tags($msg['body'] ?? ''), 0, 120, '…')) ?>
                                    </div>
                                </div>
                                <div class="syn-msg-right">
                                    <?php if ($isUnread): ?>
                                    <div class="syn-unread-dot"></div>
                                    <?php endif; ?>
                                    <div class="syn-msg-chev">›</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Reminder ──────────────────────────────────────────── -->
                    <!-- =========================================================
     REMINDERS TAB
========================================================= -->
                    <div id="syn-msgtab-reminder" class="syn-msg-panel syn-card syn-fade" style="display:none;">

                        <div class="syn-card-hd">

                            <div class="syn-card-ttl">

                                ⏰ Reminders

                                <?php
            $pendingCount = count(array_filter(
                $reminders ?? [],
                fn($r) => empty($r['is_processed'])
            ));
            ?>

                                <?php if ($pendingCount > 0): ?>
                                <span class="syn-badge" style="margin-left:8px;">
                                    <?= $pendingCount ?> pending
                                </span>
                                <?php endif; ?>

                            </div>

                            <?php if (!empty($reminders)): ?>
                            <span class="syn-card-hd-sub">

                                <?= count($reminders) ?>

                                reminder<?= count($reminders) !== 1 ? 's' : '' ?>

                            </span>
                            <?php endif; ?>

                        </div>

                        <div class="syn-card-body syn-no-pad">

                            <?php if (empty($reminders)): ?>

                            <!-- EMPTY STATE -->
                            <div class="syn-empty-state">

                                <div class="syn-empty-icon">⏰</div>

                                <div class="syn-empty-ttl">
                                    No reminders
                                </div>

                                <div class="syn-empty-sub">
                                    Upcoming reminders from your care team will appear here
                                </div>

                            </div>

                            <?php else: ?>

                            <?php foreach ($reminders as $reminder): ?>

                            <?php
        $isProcessed = !empty($reminder['is_processed']);

        $isPriority = !empty($reminder['is_priority']);

        $status = $reminder['status'] ?? 'Pending';
        ?>

                            <div class="syn-msg-row <?= !$isProcessed ? 'syn-unread' : '' ?>" onclick="synOpenMessage(
                <?= (int)($msg['id'] ?? 0) ?>,
                '<?= syn_e(addslashes($reminder['sender_name'] ?? 'Care Team')) ?>',
                'Reminder',
                '<?= syn_e(addslashes(nl2br(strip_tags($reminder['dr_message_text'] ?? '')))) ?>',
                '<?= syn_e($reminder['dr_message_sent_date'] ?? '') ?>',
                <?= ($isUnread && !$isOut) ? 'true' : 'false' ?>
             )">

                                <!-- AVATAR -->
                                <div class="syn-msg-av <?= $isProcessed ? 'syn-av-out' : 'syn-av-in' ?>">

                                    <?= syn_e(
                    strtoupper(
                        substr(
                            $reminder['sender_name'] ?? 'C',
                            0,
                            2
                        )
                    )
                ) ?>

                                </div>

                                <!-- CONTENT -->
                                <div class="syn-msg-content">

                                    <div class="syn-msg-row-top">

                                        <span class="syn-msg-from <?= !$isProcessed ? 'syn-fw700' : '' ?>">

                                            <?= syn_e($reminder['sender_name'] ?? 'Care Team') ?>

                                        </span>

                                        <span class="syn-msg-time">

                                            <?= syn_e(
                            date(
                                'M d, Y',
                                strtotime(
                                    $reminder['dr_message_due_date'] ?? 'now'
                                )
                            )
                        ) ?>

                                        </span>

                                    </div>

                                    <!-- SUBJECT -->
                                    <div class="syn-msg-subj <?= !$isProcessed ? 'syn-fw600' : '' ?>">

                                        Reminder

                                        <?php if ($isPriority): ?>
                                        <span class="syn-om-tag" style="background:#ffe5e5;color:#c62828;">

                                            High Priority

                                        </span>
                                        <?php endif; ?>

                                        <span class="syn-om-tag">

                                            <?= syn_e($status) ?>

                                        </span>

                                    </div>

                                    <!-- MESSAGE -->
                                    <div class="syn-msg-snip">

                                        <?= syn_e(
                        mb_strimwidth(
                            strip_tags(
                                $reminder['dr_message_text'] ?? ''
                            ),
                            0,
                            120,
                            '…'
                        )
                    ) ?>

                                    </div>

                                </div>

                                <!-- RIGHT -->
                                <div class="syn-msg-right">

                                    <?php if (!$isProcessed): ?>

                                    <div class="syn-unread-dot"></div>

                                    <?php else: ?>

                                    <span class="syn-sent-tag">
                                        Done
                                    </span>

                                    <?php endif; ?>

                                    <div class="syn-msg-chev">›</div>

                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- =========================================================
     RECALLS TAB
========================================================= -->
                    <div id="syn-msgtab-recalls" class="syn-msg-panel syn-card syn-fade" style="display:none;">

                        <div class="syn-card-hd">

                            <div class="syn-card-ttl">

                                📅 Recalls

                                <?php
            $upcomingRecallCount = count(array_filter(
                $recalls ?? [],
                fn($r) => !empty($r['is_upcoming'])
            ));
            ?>

                                <?php if ($upcomingRecallCount > 0): ?>
                                <span class="syn-badge" style="margin-left:8px;">

                                    <?= $upcomingRecallCount ?> upcoming

                                </span>
                                <?php endif; ?>

                            </div>

                            <?php if (!empty($recalls)): ?>

                            <span class="syn-card-hd-sub">

                                <?= count($recalls) ?>

                                recall<?= count($recalls) !== 1 ? 's' : '' ?>

                            </span>

                            <?php endif; ?>

                        </div>

                        <div class="syn-card-body syn-no-pad">

                            <?php if (empty($recalls)): ?>

                            <!-- EMPTY -->
                            <div class="syn-empty-state">

                                <div class="syn-empty-icon">📅</div>

                                <div class="syn-empty-ttl">
                                    No recalls found
                                </div>

                                <div class="syn-empty-sub">
                                    Upcoming recalls and follow-up visits appear here
                                </div>

                            </div>

                            <?php else: ?>

                            <?php foreach ($recalls as $recall): ?>

                            <?php
        $status = $recall['status'] ?? 'Upcoming';

        $isUpcoming = !empty($recall['is_upcoming']);
        ?>

                            <div class="syn-msg-row <?= $isUpcoming ? 'syn-unread' : '' ?>" onclick="synOpenMessage(
                0,
                '<?= syn_e(addslashes($recall['provider_name'] ?? 'Care Team')) ?>',
                'Recall Appointment',
                '<?= syn_e(addslashes($recall['r_reason'] ?? 'Scheduled recall appointment')) ?>',
                '<?= syn_e($recall['r_eventDate'] ?? '') ?>',
                false
             )">

                                <!-- AVATAR -->
                                <div class="syn-msg-av <?= $isUpcoming ? 'syn-av-in' : 'syn-av-out' ?>">

                                    <?= syn_e(
                    strtoupper(
                        substr(
                            $recall['provider_name'] ?? 'C',
                            0,
                            2
                        )
                    )
                ) ?>

                                </div>

                                <!-- CONTENT -->
                                <div class="syn-msg-content">

                                    <div class="syn-msg-row-top">

                                        <span class="syn-msg-from <?= $isUpcoming ? 'syn-fw700' : '' ?>">

                                            <?= syn_e($recall['provider_name'] ?? 'Care Team') ?>

                                        </span>

                                        <span class="syn-msg-time">

                                            <?= syn_e(
                            date(
                                'M d, Y',
                                strtotime(
                                    $recall['r_eventDate'] ?? 'now'
                                )
                            )
                        ) ?>

                                        </span>

                                    </div>

                                    <!-- SUBJECT -->
                                    <div class="syn-msg-subj <?= $isUpcoming ? 'syn-fw600' : '' ?>">

                                        Recall Appointment

                                        <span class="syn-om-tag">

                                            <?= syn_e($status) ?>

                                        </span>

                                    </div>

                                    <!-- MESSAGE -->
                                    <div class="syn-msg-snip">

                                        <?= syn_e(
                        mb_strimwidth(
                            strip_tags(
                                $recall['r_reason']
                                    ?? 'Scheduled recall appointment'
                            ),
                            0,
                            120,
                            '…'
                        )
                    ) ?>

                                    </div>

                                </div>

                                <!-- RIGHT -->
                                <div class="syn-msg-right">

                                    <?php if ($isUpcoming): ?>

                                    <div class="syn-unread-dot"></div>

                                    <?php else: ?>

                                    <span class="syn-sent-tag">
                                        Completed
                                    </span>

                                    <?php endif; ?>

                                    <div class="syn-msg-chev">›</div>

                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div><!-- /syn-tscroll -->
            </div><!-- /messages panel -->

            <!-- ════════════════════════════════════════════════════
         COMPOSE MESSAGE MODAL
    ═══════════════════════════════════════════════════════ -->
            <div id="syn-compose-overlay" class="syn-modal-overlay" style="display:none;"
                onclick="synCloseCompose(event)">
                <div class="syn-modal" onclick="event.stopPropagation()">

                    <!-- Modal header -->
                    <div class="syn-modal-hd">
                        <div class="syn-modal-ttl">✉ Compose Message</div>
                        <button class="syn-modal-close" onclick="synCloseCompose()" aria-label="Close">✕</button>
                    </div>

                    <!-- Modal body -->
                    <div class="syn-modal-body">
                        <div id="syn-compose-success" class="syn-compose-success" style="display:none;">
                            <div class="syn-success-icon">✅</div>
                            <div class="syn-success-ttl">Message Sent!</div>
                            <div class="syn-success-sub">Your care team will respond as soon as possible.</div>
                        </div>

                        <form id="syn-compose-form" onsubmit="synSendMessage(event)">

                            <!-- To field -->
                            <div class="syn-field">
                                <label class="syn-label" for="syn-msg-to">To</label>
                                <select id="syn-msg-to" name="to_user" class="syn-select" required>
                                    <option value="">— Select recipient —</option>
                                    <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?=$staff['username']; ?>">
                                        <?= syn_e($staff['name'] ?? '') ?>
                                        <?php if (!empty($staff['specialty'])): ?>
                                        — <?= syn_e($staff['specialty']) ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Subject field -->
                            <div class="syn-field">
                                <label class="syn-label" for="syn-msg-subject">Subject</label>
                                <input type="text" id="syn-msg-subject" name="subject" class="syn-input"
                                    placeholder="e.g. Question about my prescription" maxlength="200" required />
                            </div>

                            <!-- Body field -->
                            <div class="syn-field">
                                <label class="syn-label" for="syn-msg-body">Message</label>
                                <textarea id="syn-msg-body" name="body" class="syn-textarea" rows="6"
                                    placeholder="Type your message here…" maxlength="4000" required></textarea>
                                <div class="syn-char-count"><span id="syn-char-n">0</span> / 4000</div>
                            </div>

                            <!-- Error display -->
                            <div id="syn-compose-error" class="syn-compose-error" style="display:none;"></div>

                            <!-- Actions -->
                            <div class="syn-modal-foot">
                                <button type="button" class="syn-modal-cancel"
                                    onclick="synCloseCompose()">Cancel</button>
                                <button type="submit" id="syn-send-btn" class="syn-modal-send">
                                    <span id="syn-send-label">Send Message ✉</span>
                                    <span id="syn-send-spin" style="display:none;">Sending…</span>
                                </button>
                            </div>

                        </form>
                    </div><!-- /modal-body -->

                </div><!-- /syn-modal -->
            </div><!-- /compose overlay -->


            <!-- Appointment Modal -->
            <div id="syn-appt-modal" class="syn-msg-modal-wrap" style="display:none;">

                <div class="syn-msg-modal">

                    <div class="syn-msg-head">
                        <h3>📅 Schedule Appointment</h3>

                        <button type="button" class="syn-msg-close" onclick="synCloseApptModal()">
                            ×
                        </button>
                    </div>

                    <div class="syn-msg-body">

                        <iframe id="syn-appt-frame" src="about:blank" style="
                    width:100%;
                    height:700px;
                    border:none;
                    border-radius:12px;
                ">
                        </iframe>

                    </div>

                </div>

            </div>

            <!-- ════════════════════════════════════════════════════
         VIEW MESSAGE MODAL
    ═══════════════════════════════════════════════════════ -->
            <div id="syn-view-overlay" class="syn-modal-overlay" style="display:none;" onclick="synCloseView(event)">
                <div class="syn-modal" onclick="event.stopPropagation()">
                    <div class="syn-modal-hd">
                        <div class="syn-modal-ttl" id="syn-view-subject">Message</div>
                        <button class="syn-modal-close" onclick="synCloseView()" aria-label="Close">✕</button>
                    </div>
                    <div class="syn-modal-body">
                        <div class="syn-view-meta">
                            <span class="syn-view-from" id="syn-view-from"></span>
                            <span class="syn-view-date" id="syn-view-date"></span>
                        </div>
                        <div class="syn-view-body" id="syn-view-body"></div>
                        <div class="syn-modal-foot">
                            <button type="button" class="syn-modal-cancel" onclick="synCloseView()">Close</button>
                            <button type="button" class="syn-modal-send" onclick="synReplyTo()">Reply ↩</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CSRF token for JS (rendered server-side, read by JS) -->
            <input type="hidden" id="syn-csrf-token"
                value="<?= syn_e(\OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken() ?? '') ?>" />
            <input type="hidden" id="syn-msg-handler-url" value="<?= syn_e($module_path) ?>/message_handler.php" />

            <!-- ════════════════════  Health Tread  ════════════════════ -->
            <div id="syn-tab-health" class="syn-tpanel">
                <div class="syn-tscroll">
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">Health Trends</div>
                            <!-- <div class="syn-page-sub">Synced from Apple Health, Omron BP cuff, Dexcom CGM — last sync 4
                                min ago
                            </div> -->
                        </div>
                        <!-- <a href="<?= $GLOBALS['web_root'] ?>/portal/messaging/messages.php" class="syn-sbtn"
                            style="border-color:var(--syn-teal);color:var(--syn-teal);">
                            ↻ Sync Now
                        </a> -->
                    </div>

                    <div class="three-col">

                        <?php $avgBP = $this->getAverageBP(); ?>

                        <div class="trend-card fade">

                            <!-- Title -->
                            <div class="trend-lbl">
                                📈 Blood Pressure (avg)
                            </div>

                            <!-- Average BP Value -->
                            <div class="trend-ttl">

                                <span>30 days</span>

                                <span class="trend-val" style="color:var(--amber);">

                                    <?= htmlspecialchars($avgBP['formatted'] ?? 'N/A') ?>

                                </span>

                            </div>

                            <!-- Graph -->
                            <svg class="trend-svg" viewBox="0 0 300 60" preserveAspectRatio="none">

                                <defs>

                                    <linearGradient id="bp2" x1="0" y1="0" x2="0" y2="1">

                                        <stop offset="0%" stop-color="#C77A0A" stop-opacity="0.25" />

                                        <stop offset="100%" stop-color="#C77A0A" stop-opacity="0" />

                                    </linearGradient>

                                </defs>

                                <!-- Background area -->
                                <path
                                    d="M0,40 L25,38 L50,42 L75,35 L100,30 L125,32 L150,28 L175,25 L200,22 L225,28 L250,18 L275,15 L300,12 L300,60 L0,60 Z"
                                    fill="url(#bp2)" />

                                <!-- Line -->
                                <polyline
                                    points="0,40 25,38 50,42 75,35 100,30 125,32 150,28 175,25 200,22 225,28 250,18 275,15 300,12"
                                    fill="none" stroke="#C77A0A" stroke-width="2" />

                            </svg>

                            <!-- Footer -->
                            <div class="trend-foot">

                                <span>
                                    Trending ↓ improving
                                </span>

                                <span style="color:var(--green);">

                                    ▼
                                    <?= htmlspecialchars($avgBP['systolic'] ?? '0') ?>/<?= htmlspecialchars($avgBP['diastolic'] ?? '0') ?>

                                </span>

                            </div>

                        </div>
                        <div class="trend-card fade">
                            <div class="trend-lbl">🩸 Glucose (CGM avg)</div>
                            <div class="trend-ttl"><span>14 days</span><span class="trend-val"
                                    style="color:var(--green);">128 mg/dL</span></div>
                            <svg class="trend-svg" viewBox="0 0 300 60" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="bg2" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#0F6E56" stop-opacity="0.25" />
                                        <stop offset="100%" stop-color="#0F6E56" stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <path
                                    d="M0,28 L25,30 L50,25 L75,32 L100,28 L125,22 L150,30 L175,26 L200,28 L225,24 L250,22 L275,25 L300,22 L300,60 L0,60 Z"
                                    fill="url(#bg2)" />
                                <polyline
                                    points="0,28 25,30 50,25 75,32 100,28 125,22 150,30 175,26 200,28 225,24 250,22 275,25 300,22"
                                    fill="none" stroke="#0F6E56" stroke-width="2" />
                            </svg>
                            <div class="trend-foot"><span>Time in range: 78%</span><span
                                    style="color:var(--green);">Goal &gt; 70%</span></div>
                        </div>
                        <div class="trend-card fade">

                            <!-- Title -->
                            <div class="trend-lbl">
                                ⚖️ Weight
                            </div>

                            <!-- Weight Value -->
                            <div class="trend-ttl">

                                <span>90 days</span>

                                <span class="trend-val" style="color:var(--green);">

                                    <?= htmlspecialchars($weight_trend['weight'] ?? '0') ?> lb

                                </span>

                            </div>

                            <!-- Graph -->
                            <svg class="trend-svg" viewBox="0 0 300 60" preserveAspectRatio="none">

                                <defs>

                                    <linearGradient id="wt2" x1="0" y1="0" x2="0" y2="1">

                                        <stop offset="0%" stop-color="#1D9E75" stop-opacity="0.25" />

                                        <stop offset="100%" stop-color="#1D9E75" stop-opacity="0" />

                                    </linearGradient>

                                </defs>

                                <!-- Area -->
                                <path
                                    d="M0,15 L30,18 L60,17 L90,22 L120,25 L150,28 L180,30 L210,32 L240,35 L270,38 L300,40 L300,60 L0,60 Z"
                                    fill="url(#wt2)" />

                                <!-- Line -->
                                <polyline
                                    points="0,15 30,18 60,17 90,22 120,25 150,28 180,30 210,32 240,35 270,38 300,40"
                                    fill="none" stroke="#1D9E75" stroke-width="2" />

                            </svg>

                            <!-- Footer -->
                            <div class="trend-foot">

                                <span>

                                    Last Updated:
                                    <?= htmlspecialchars($weight_trend['date'] ?? '') ?>

                                </span>

                                <span style="color:var(--green);">

                                    On track to goal

                                </span>

                            </div>

                        </div>
                    </div>

                    <!-- ------------------------------------------------ -->
                    <?php
                      $vitals = $vitals_summary['current'] ?? [];
                      $history = $vitals_summary['history'] ?? [];
                      $encounter = $vitals_summary['encounter'] ?? [];
                      ?>
                    <div class="syn-card syn-fade">

                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">
                                🩺 Latest Vitals
                            </div>
                        </div>

                        <div class="syn-card-body">

                            <div class="syn-section-label">
                                Current Encounter —
                                <?= date('M d, Y', strtotime($encounter['date'] ?? '')) ?>
                            </div>



                            <div class="syn-vgrid">

                                <div class="syn-vi">
                                    <div class="syn-vi-lbl">BP</div>
                                    <div class="syn-vi-val">
                                        <?= $vitals['bps'] ?>/<?= $vitals['bpd'] ?>
                                        <small class="vi-unit">mmHg</small>
                                    </div>
                                </div>

                                <div class="syn-vi">
                                    <div class="syn-vi-lbl">HR</div>
                                    <div class="syn-vi-val">
                                        <?= round($vitals['pulse']) ?>
                                        <small class="vi-unit">bpm</small>
                                    </div>
                                </div>

                                <div class="syn-vi">
                                    <div class="syn-vi-lbl">SpO₂</div>
                                    <div class="syn-vi-val">
                                        <?= round($vitals['oxygen_saturation']) ?>%
                                        <small class="vi-unit">Room air</small>
                                    </div>
                                </div>

                                <div class="syn-vi">
                                    <div class="syn-vi-lbl">Temp</div>
                                    <div class="syn-vi-val">
                                        <?= round($vitals['temperature']) ?>
                                        <small class="vi-unit">Oral</small>
                                    </div>
                                </div>

                                <div class="syn-vi">
                                    <div class="syn-vi-lbl">RR</div>
                                    <div class="syn-vi-val">
                                        <?= round($vitals['respiration']) ?>
                                        <small class="vi-unit">breaths/min</small>
                                    </div>
                                </div>

                                <div class="syn-vi">
                                    <div class="syn-vi-lbl">BMI</div>
                                    <div class="syn-vi-val">
                                        <?= number_format((float)$vitals['BMI'], 1) ?>
                                        <small class="vi-unit">kg/m²</small>
                                    </div>
                                </div>

                            </div>

                            <?php

$points = '';
$circles = '';
$labels = '';
$dates = '';

$startX = 45;
$gap = 38;

// Fixed BP range
$minBP = 60;
$maxBP = 180;

$topY = 10;
$bottomY = 60;
$height = $bottomY - $topY;

/**
 * Convert BP value to SVG Y coordinate
 */
function bpToY($bp, $minBP, $maxBP, $bottomY, $height)
{
    return $bottomY - (
        (($bp - $minBP) / ($maxBP - $minBP))
        * $height
    );
}

// Build graph
foreach ($history as $i => $h) {

    $x = $startX + ($i * $gap);

    $bp = (int)($h['bps'] ?? 0);

    $y = bpToY(
        $bp,
        $minBP,
        $maxBP,
        $bottomY,
        $height
    );

    $points .= "{$x},{$y} ";

    // Color based on BP
    $color = '#0F6E56';

    if ($bp >= 140) {
        $color = '#A32D2D';
    } elseif ($bp >= 130) {
        $color = '#C77A0A';
    }

    // Point
    $circles .= "
        <circle
            cx='{$x}'
            cy='{$y}'
            r='4'
            fill='{$color}' />
    ";

    // BP label
    $labels .= "
        <text
            x='{$x}'
            y='" . ($y - 8) . "'
            text-anchor='middle'
            font-size='8'
            fill='{$color}'
            font-weight='700'>
            {$bp}
        </text>
    ";

    // Date label
    $dates .= "
        <text
            x='{$x}'
            y='80'
            text-anchor='middle'
            font-size='7'
            fill='#888780'>
            " . date('M y', strtotime($h['date'])) . "
        </text>
    ";
}

// Reference line at 130
$line130Y = bpToY(
    130,
    $minBP,
    $maxBP,
    $bottomY,
    $height
);

?>

                            <div class="syn-section-label">
                                Blood Pressure Systolic — Last <?= count($history) ?> Visits
                            </div>

                            <?php if (count($history) > 0): ?>

                            <svg viewBox="0 0 280 90" xmlns="http://www.w3.org/2000/svg"
                                style="width:100%;height:90px;overflow:visible;">

                                <!-- Grid -->

                                <line x1="30" y1="10" x2="270" y2="10" stroke="#D3D1C7" stroke-width="0.5"
                                    stroke-dasharray="3,3" />

                                <line x1="30" y1="35" x2="270" y2="35" stroke="#D3D1C7" stroke-width="0.5"
                                    stroke-dasharray="3,3" />

                                <line x1="30" y1="60" x2="270" y2="60" stroke="#D3D1C7" stroke-width="0.5"
                                    stroke-dasharray="3,3" />

                                <!-- 130 reference line -->

                                <line x1="30" y1="<?= $line130Y ?>" x2="270" y2="<?= $line130Y ?>" stroke="#0F6E56"
                                    stroke-width="1" stroke-dasharray="4,3" opacity="0.6" />

                                <text x="272" y="<?= $line130Y + 3 ?>" font-size="7" fill="#0F6E56">
                                    130
                                </text>

                                <!-- Trend line -->

                                <?php if (count($history) > 1): ?>
                                <polyline points="<?= trim($points) ?>" fill="none" stroke="#A32D2D" stroke-width="2.5"
                                    stroke-linejoin="round" stroke-linecap="round" />
                                <?php endif; ?>

                                <!-- Dots -->
                                <?= $circles ?>

                                <!-- BP values -->
                                <?= $labels ?>

                                <!-- Dates -->
                                <?= $dates ?>

                                <!-- Bottom axis -->

                                <line x1="30" y1="68" x2="270" y2="68" stroke="#D3D1C7" stroke-width="1" />

                            </svg>

                            <?php else: ?>

                            <div class="text-muted">
                                No blood pressure readings available.
                            </div>

                            <?php endif; ?>

                            <div class="syn-section-label">
                                Past Visit Readings
                            </div>

                            <?php foreach (array_reverse($history) as $h): ?>

                            <div class="syn-vital-row">

                                <div>
                                    <?= date('M d, Y', strtotime($h['date'])) ?>
                                </div>

                                <div>
                                    <?= $h['bps'] ?>/<?= $h['bpd'] ?>
                                </div>

                                <div>
                                    HR <?= $h['pulse'] ?>
                                </div>

                                <div>
                                    SpO₂ <?= $h['oxygen_saturation'] ?>%
                                </div>

                            </div>

                            <?php endforeach; ?>

                        </div>
                    </div>
                    <!-- ------------------------------------------------ -->
                </div>
            </div>

            <!-- ════════════════════  RECORDS  ════════════════════ -->
            <div id="syn-tab-records" class="syn-tpanel">
                <div class="syn-tscroll">

                    <!-- Page header -->
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">Records &amp; Results</div>
                            <div class="syn-page-sub">
                                Health snapshot · Lab results · Allergies · Problems · Immunizations · Reports
                            </div>
                        </div>
                        <a href="<?= syn_e($GLOBALS['web_root']) ?>/portal/patient/ehr_report.php" class="syn-sbtn"
                            style="border-color:var(--syn-teal);color:var(--syn-teal);">
                            ⬇ Download Records
                        </a>
                    </div>

                    <!-- Sub-tabs -->
                    <div class="syn-msg-tabs" style="margin-bottom:14px;">
                        <button class="syn-msg-tab syn-on" data-rectab="labs">🧪 Labs</button>
                        <button class="syn-msg-tab" data-rectab="problems">🩺 Problems</button>
                        <button class="syn-msg-tab" data-rectab="allergies">⚠️ Allergies</button>
                        <button class="syn-msg-tab" data-rectab="immunizations">💉 Immunizations</button>
                        <button class="syn-msg-tab" data-rectab="reports">📑 Reports</button>
                    </div>

                    <!-- ── LAB RESULTS ──────────────────────────────────────── -->
                    <div id="syn-rectab-labs" class="syn-rec-panel syn-card syn-fade">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">🧪 Lab Results</div>
                            <?php if (!empty($lab_results)): ?>
                            <span class="syn-card-hd-sub">
                                <?= count($lab_results) ?> result<?= count($lab_results) !== 1 ? 's' : '' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="syn-card-body">
                            <?php if (empty($lab_results)): ?>
                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">🧪</div>
                                <div class="syn-empty-ttl">No lab results on file</div>
                                <div class="syn-empty-sub">Your lab results will appear here when available</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($lab_results as $lab): ?>
                            <?php
                $abnormal = strtolower($lab['abnormal'] ?? '');
                $valClass = ($abnormal === '' || $abnormal === 'n')
                          ? 'syn-norm'
                          : ($abnormal === 'h' || $abnormal === 'l' ? 'syn-high' : 'syn-crit');
                ?>
                            <div class="syn-result-row">
                                <div class="syn-r-icon">🧪</div>
                                <div class="syn-r-name">
                                    <div class="syn-r-ttl"><?= syn_e($lab['name'] ?? '') ?></div>
                                    <div class="syn-r-sub"><?= syn_e($lab['date'] ?? '') ?></div>
                                </div>
                                <div>
                                    <div class="syn-r-val <?= $valClass ?>">
                                        <?= syn_e($lab['value'] ?? '') ?> <?= syn_e($lab['units'] ?? '') ?>
                                    </div>
                                    <div class="syn-r-status">
                                        <?= ($abnormal && $abnormal !== 'n') ? syn_e(strtoupper($abnormal)) : 'Normal' ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── PROBLEMS ─────────────────────────────────────────── -->
                    <div id="syn-rectab-problems" class="syn-rec-panel syn-card syn-fade" style="display:none;">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">🩺 Active Problems</div>
                            <?php if (!empty($problems)): ?>
                            <span class="syn-card-hd-sub">
                                <?= count($problems) ?> condition<?= count($problems) !== 1 ? 's' : '' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="syn-card-body">
                            <?php if (empty($problems)): ?>
                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">🩺</div>
                                <div class="syn-empty-ttl">No active problems on file</div>
                                <div class="syn-empty-sub">Active diagnoses and conditions will appear here</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($problems as $prob): ?>
                            <div class="syn-hs-row">
                                <div class="syn-hs-icon syn-hs-problem">🩺</div>
                                <div class="syn-hs-info">
                                    <div class="syn-hs-ttl"><?= syn_e($prob['name'] ?? '') ?></div>
                                    <div class="syn-hs-sub">
                                        <?php if (!empty($prob['code'])): ?>
                                        <span class="syn-hs-code"><?= syn_e($prob['code']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($prob['onset_date'])): ?>
                                        Since <?= syn_e(date('M Y', strtotime($prob['onset_date']))) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($prob['notes'])): ?>
                                        · <?= syn_e($prob['notes']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="syn-hs-status syn-hs-active">Active</span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── ALLERGIES ─────────────────────────────────────────── -->
                    <div id="syn-rectab-allergies" class="syn-rec-panel syn-card syn-fade" style="display:none;">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">⚠️ Allergies</div>
                            <?php if (!empty($allergies)): ?>
                            <span class="syn-card-hd-sub" style="color:var(--syn-amber);font-weight:600;">
                                <?= count($allergies) ?> allerg<?= count($allergies) !== 1 ? 'ies' : 'y' ?> on file
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="syn-card-body">
                            <?php if (empty($allergies)): ?>
                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">✅</div>
                                <div class="syn-empty-ttl">No allergies on file</div>
                                <div class="syn-empty-sub">Any recorded allergies will appear here</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($allergies as $allergy): ?>
                            <?php
                $sev      = strtolower($allergy['severity'] ?? '');
                $sevClass = $sev === 'severe'   ? 'syn-hs-severe'
                          : ($sev === 'moderate' ? 'syn-hs-moderate' : 'syn-hs-mild');
                $sevLabel = ucfirst($sev) ?: 'Unknown';
                ?>
                            <div class="syn-hs-row">
                                <div class="syn-hs-icon syn-hs-allergy">⚠️</div>
                                <div class="syn-hs-info">
                                    <div class="syn-hs-ttl"><?= syn_e($allergy['name'] ?? '') ?></div>
                                    <div class="syn-hs-sub">
                                        <?php if (!empty($allergy['reaction'])): ?>
                                        Reaction: <?= syn_e($allergy['reaction']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="syn-hs-status <?= $sevClass ?>">
                                    <?= syn_e($sevLabel) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── IMMUNIZATIONS ─────────────────────────────────────── -->
                    <div id="syn-rectab-immunizations" class="syn-rec-panel syn-card syn-fade" style="display:none;">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">💉 Immunizations</div>
                            <?php if (!empty($immunizations)): ?>
                            <span class="syn-card-hd-sub">
                                <?= count($immunizations) ?> record<?= count($immunizations) !== 1 ? 's' : '' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="syn-card-body">
                            <?php if (empty($immunizations)): ?>
                            <div class="syn-empty-state">
                                <div class="syn-empty-icon">💉</div>
                                <div class="syn-empty-ttl">No immunization records</div>
                                <div class="syn-empty-sub">Your vaccination history will appear here</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($immunizations as $imm): ?>
                            <div class="syn-hs-row">
                                <div class="syn-hs-icon syn-hs-imm">💉</div>
                                <div class="syn-hs-info">
                                    <div class="syn-hs-ttl">
                                        <?= syn_e($imm['vaccine_name'] ?? ('CVX ' . ($imm['cvx_code'] ?? ''))) ?>
                                    </div>
                                    <div class="syn-hs-sub">
                                        <?php if (!empty($imm['administered_date'])): ?>
                                        <?= syn_e(date('M d, Y', strtotime($imm['administered_date']))) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($imm['administered_by'])): ?>
                                        · <?= syn_e($imm['administered_by']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($imm['lot_number'])): ?>
                                        · Lot: <?= syn_e($imm['lot_number']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="syn-hs-status syn-hs-active">✓ Given</span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── MEDICAL REPORTS ───────────────────────────────────── -->
                    <?php
        // Convenience variables from reports_config
        $rCfg      = $reports_config ?? [];
        $ccdaOk    = !empty($rCfg['ccda_ok']);
        $canReport = !empty($rCfg['allow_custom_report']);
        $canDocs   = !empty($rCfg['allow_doc_download']);
        $csrfTok   = $rCfg['csrf_token'] ?? '';
        $wr        = $GLOBALS['web_root'] ?? '';
        ?>
                    <div id="syn-rectab-reports" class="syn-rec-panel syn-fade" style="display:none;">

                        <!-- Reports menu card -->
                        <div class="syn-card" id="syn-reports-menu">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">📑 Medical Reports</div>
                            </div>
                            <div class="syn-card-body syn-no-pad">

                                <?php if ($ccdaOk): ?>
                                <!-- View Summary of Care (CCDA) -->
                                <a href="<?= syn_e($wr) ?>/ccdaservice/ccda_gateway.php?action=view&csrf_token_form=<?= urlencode($csrfTok) ?>"
                                    target="_blank" rel="noopener" class="syn-report-row">
                                    <div class="syn-report-icon"
                                        style="background:var(--syn-teal-l);color:var(--syn-teal-d);">
                                        📋
                                    </div>
                                    <div class="syn-report-info">
                                        <div class="syn-report-ttl">View Summary of Care</div>
                                        <div class="syn-report-sub">Opens your C-CDA clinical summary in a new tab</div>
                                    </div>
                                    <div class="syn-report-arrow">›</div>
                                </a>

                                <!-- Download Summary of Care (CCDA) -->
                                <a href="<?= syn_e($wr) ?>/ccdaservice/ccda_gateway.php?action=dl&csrf_token_form=<?= urlencode($csrfTok) ?>"
                                    class="syn-report-row">
                                    <div class="syn-report-icon"
                                        style="background:var(--syn-purple-l);color:var(--syn-purple);">
                                        ⬇
                                    </div>
                                    <div class="syn-report-info">
                                        <div class="syn-report-ttl">Download Summary of Care</div>
                                        <div class="syn-report-sub">Download your C-CDA clinical summary as a file</div>
                                    </div>
                                    <div class="syn-report-arrow">›</div>
                                </a>
                                <?php endif; ?>

                                <?php if ($canReport): ?>
                                <!-- Customized Medical History Report -->
                                <div class="syn-report-row syn-report-row-btn" onclick="synOpenReportFrame('syn-custom-report-wrap',
  '<?= syn_e($wr) ?>/portal/report/portal_patient_report.php?pid=<?= (int)($_SESSION['pid'] ?? 0) ?>',
  this)">
                                    <div class="syn-report-icon"
                                        style="background:var(--syn-teal-l);color:var(--syn-teal-d);">
                                        📊
                                    </div>
                                    <div class="syn-report-info">
                                        <div class="syn-report-ttl">Customized Medical History Report</div>
                                        <div class="syn-report-sub">Generate a personalised medical history summary
                                        </div>
                                    </div>
                                    <div class="syn-report-arrow" id="syn-custom-report-chev">›</div>
                                </div>
                                <!-- Inline iframe for custom report -->
                                <div id="syn-custom-report-wrap" class="syn-report-iframe-wrap" style="display:none;">
                                    <div style="position:relative;min-height:60px;">
                                        <div id="syn-custom-report-loader" class="syn-intake-loader">
                                            <div class="syn-intake-spinner"></div>
                                            <div class="syn-intake-loader-txt">Generating report…</div>
                                        </div>
                                        <iframe id="syn-custom-report-frame" src="about:blank"
                                            style="width:100%;height:600px;border:none;display:block;"
                                            title="Customized Medical History Report"
                                            onload="synIframeLoaded('syn-custom-report-loader')">
                                        </iframe>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($canDocs): ?>
                                <!-- Download Medical Record Documents -->
                                <div class="syn-report-row syn-report-row-btn" onclick="synOpenReportFrame('syn-doc-download-wrap',
                     '<?= syn_e($wr) ?>/portal/get_patient_documents.php',
                     this)">
                                    <div class="syn-report-icon"
                                        style="background:var(--syn-gold-l);color:var(--syn-gold);">
                                        🗂
                                    </div>
                                    <div class="syn-report-info">
                                        <div class="syn-report-ttl">Download Medical Record Documents</div>
                                        <div class="syn-report-sub">Download a zip of your clinical documents</div>
                                    </div>
                                    <div class="syn-report-arrow" id="syn-doc-download-chev">›</div>
                                </div>
                                <!-- Inline iframe for doc download -->
                                <div id="syn-doc-download-wrap" class="syn-report-iframe-wrap" style="display:none;">
                                    <div style="position:relative;min-height:60px;">
                                        <div id="syn-doc-download-loader" class="syn-intake-loader">
                                            <div class="syn-intake-spinner"></div>
                                            <div class="syn-intake-loader-txt">Loading documents…</div>
                                        </div>
                                        <iframe id="syn-doc-download-frame" src="about:blank"
                                            style="width:100%;height:500px;border:none;display:block;"
                                            title="Download Medical Record Documents"
                                            onload="synIframeLoaded('syn-doc-download-loader')">
                                        </iframe>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!$ccdaOk && !$canReport && !$canDocs): ?>
                                <div class="syn-empty-state">
                                    <div class="syn-empty-icon">📑</div>
                                    <div class="syn-empty-ttl">No reports available</div>
                                    <div class="syn-empty-sub">
                                        Medical reports have not been enabled for your portal.<br>
                                        Please contact your care team for assistance.
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div><!-- /card-body -->
                        </div><!-- /reports menu card -->

                    </div><!-- /syn-rectab-reports -->

                </div><!-- /syn-tscroll -->
            </div><!-- /records panel -->

            <!-- ════════════════════  MEDICATIONS  ════════════════════ -->
            <div id="syn-tab-medications" class="syn-tpanel">
                <div class="syn-tscroll">
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">Medications</div>
                            <div class="syn-page-sub">Your current prescription list</div>
                        </div>
                        <a href="<?= $GLOBALS['web_root'] ?>/portal/messaging/messages.php?tofacility=1&subject=Refill+Request"
                            class="syn-sbtn" style="border-color:var(--syn-teal);color:var(--syn-teal);">
                            💊 Request Refill
                        </a>
                    </div>

                    <div class="syn-card syn-fade">
                        <div class="syn-card-hd">
                            <div class="syn-card-ttl">Active Medications</div>
                        </div>
                        <div class="syn-card-body">
                            <?php if (empty($medications)): ?>
                            <p class="syn-empty">No active medications on file.</p>
                            <?php else: ?>
                            <?php foreach ($medications as $med): ?>
                            <div class="syn-med-row">
                                <div class="syn-med-pill">💊</div>
                                <div class="syn-med-info">
                                    <div class="syn-med-name"><?= syn_e($med['drug'] ?? '') ?></div>
                                    <div class="syn-med-dose"><?= syn_e($med['dosage'] ?? '') ?></div>
                                </div>
                                <a href="<?= $GLOBALS['web_root'] ?>/portal/messaging/messages.php?tofacility=1&subject=Refill+Request:+<?= urlencode($med['drug'] ?? '') ?>"
                                    class="syn-med-refill">Request Refill →</a>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════  BILLING  ════════════════════ -->
            <div id="syn-tab-billing" class="syn-tpanel">
                <div class="syn-tscroll">


                    <!-- Page header -->
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">Bills &amp; Payments</div>
                            <div class="syn-page-sub">
                                <?php
                                  $subParts = [];
                                  if (!empty($billing['insurance_name'])) {
                                      $subParts[] = syn_e($billing['insurance_name']);
                                  }
                                  $subParts[] = 'Online statements';
                                  $subParts[] = 'Secure payments';
                                  echo implode(' · ', $subParts);
                                  ?>
                            </div>
                        </div>

                    </div>

                    <!-- ── 3 stat tiles ──────────────────────────────────────────── -->

                    <!-- Billing sub-tabs -->
<div class="syn-three-col">

                        <!-- Outstanding Balance -->
                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">Outstanding Balance</div>
                            <?php $outstanding = (float)($billing['outstanding'] ?? 0); ?>
                            <div class="syn-stat-val <?= $outstanding > 0 ? 'syn-a' : 'syn-g' ?>">
                                $<?= number_format($outstanding, 2) ?>
                            </div>
                            <div class="syn-stat-sub">
                                <?= $outstanding > 0 ? 'Payment due' : 'Paid in full ✓' ?>
                            </div>
                        </div>

                        <!-- YTD Out-of-Pocket -->
                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">YTD Out-of-Pocket</div>
                            <div class="syn-stat-val">
                                $<?= number_format((float)($billing['ytd_oop'] ?? 0), 2) ?>
                            </div>
                            <div class="syn-stat-sub">
                                <?= date('Y') ?> patient payments
                            </div>
                        </div>

                        <!-- YTD Saved by Insurance -->
                        <div class="syn-stat syn-fade">
                            <div class="syn-stat-lbl">YTD Saved by Insurance</div>
                            <div class="syn-stat-val syn-g">
                                $<?= number_format((float)($billing['ytd_ins_saved'] ?? 0), 2) ?>
                            </div>
                            <div class="syn-stat-sub">
                                <?= syn_e($billing['insurance_name'] ?? 'Insurance') ?>
                            </div>
                        </div>

                    </div>
                    
                    <div class="syn-two-col">
                        <!-- ── Open Statements ───────────────────────────────────────── -->
                        <div class="syn-card syn-fade">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">Open Statements</div>
                                <?php
                                    $openCount = count(array_filter(
                                        $billing['open_statements'] ?? [],
                                        fn($s) => !$s['is_paid']
                                    ));
                                    if ($openCount > 0): ?>
                                <span class="syn-card-hd-sub" style="color:var(--syn-amber);font-weight:600;">
                                    <?= $openCount ?> unpaid
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="syn-card-body syn-no-pad">
                                <?php if (empty($billing['open_statements'])): ?>
                                <div class="syn-empty-state">
                                    <div class="syn-empty-icon">🎉</div>
                                    <div class="syn-empty-ttl">No open statements</div>
                                    <div class="syn-empty-sub">You have no outstanding balances</div>
                                </div>
                                <?php else: ?>
                                <?php foreach ($billing['open_statements'] as $stmt): ?>
                                <?php
                                    $isPaid   = $stmt['is_paid'];
                                    $fee      = (float)($stmt['fee']          ?? 0);
                                    $insPaid  = (float)($stmt['ins_paid']     ?? 0);
                                    $patResp  = (float)($stmt['patient_resp'] ?? 0);
                                    $encDate  = !empty($stmt['date'])
                                                ? date('M d, Y', strtotime($stmt['date']))
                                                : '';
                                    $code     = !empty($stmt['code'])
                                                ? ' (' . syn_e($stmt['code']) . ')'
                                                : '';
                                    ?>
                                <div class="syn-bill2-row">
                                    <div class="syn-bill2-info">
                                        <div class="syn-bill2-ttl">
                                            <?=$stmt['billing_id'];?> <?= syn_e($encDate) ?>
                                            <?php if (!empty($stmt['description'])): ?>
                                            — <?= syn_e($stmt['description']) ?>
                                            <?php endif; ?>
                                            <?= $code ?>
                                        </div>
                                        <div class="syn-bill2-sub">
                                            Charged $<?= number_format($fee, 2) ?>
                                            <?php if ($insPaid > 0): ?>
                                            · Insurance paid $<?= number_format($insPaid, 2) ?>
                                            <?php endif; ?>
                                            <?php if ($isPaid): ?>
                                            · No patient responsibility
                                            <?php else: ?>
                                            · Patient responsibility
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="syn-bill2-right">
                                        <?php if ($isPaid): ?>
                                        <div class="syn-bill2-amt syn-bill2-paid">
                                            $<?= number_format($patResp, 2) ?> ✓
                                        </div>
                                        <span class="syn-bill2-status syn-bill2-status-paid">Paid in full</span>
                                        <?php else: ?>
                                        <div class="syn-bill2-amt syn-bill2-due">
                                            $<?= number_format($patResp, 2) ?>
                                        </div>
                                        <a href="<?= syn_e($GLOBALS['web_root']) ?>/portal/patient/patientportal.php"
                                            class="syn-bill2-pay">Pay Now</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ── Payment History ───────────────────────────────────────── -->
                        <div class="syn-card syn-fade">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">Payment History</div>
                                <?php if (!empty($billing['history'])): ?>
                                <span class="syn-card-hd-sub">
                                    <?= count($billing['history']) ?>
                                    transaction<?= count($billing['history']) !== 1 ? 's' : '' ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="syn-card-body syn-no-pad">
                                <?php if (empty($billing['history'])): ?>
                                <div class="syn-empty-state">
                                    <div class="syn-empty-icon">📋</div>
                                    <div class="syn-empty-ttl">No payment history</div>
                                    <div class="syn-empty-sub">Completed payments will appear here</div>
                                </div>
                                  <?php else: ?>
                                  <?php foreach ($billing['history'] as $h): ?>
                                  <?php
                                    $paidDate  = !empty($h['paid_date'])
                                                ? date('M d, Y', strtotime($h['paid_date']))
                                                : '';
                                    $encDate   = !empty($h['enc_date'])
                                                ? date('M d, Y', strtotime($h['enc_date']))
                                                : $paidDate;
                                    $isPatient = ((int)($h['payer_type'] ?? 0) === 0);
                                    $payerLbl  = $isPatient ? 'Patient payment' : 'Insurance payment';
                                    ?>
                                <div class="syn-bill2-row">
                                    <div class="syn-bill2-info">
                                        <div class="syn-bill2-ttl">
                                            <?= syn_e($encDate) ?>
                                            <?php if (!empty($h['description'])): ?>
                                            — <?= syn_e($h['description']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="syn-bill2-sub">
                                            Paid <?= syn_e($paidDate) ?>
                                            · <?= $payerLbl ?>
                                        </div>
                                    </div>
                                    <div class="syn-bill2-right">
                                        <div class="syn-bill2-amt syn-bill2-paid">
                                            $<?= number_format((float)($h['amount'] ?? 0), 2) ?> ✓
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ══ LEDGER panel (iframe → pat_ledger.php) ══ -->
                    <div>
                        <div class="syn-card syn-fade" style="overflow:hidden;">
                            <div class="syn-card-hd">
                                <div class="syn-card-ttl">📋 Patient Billing Summary</div>

                            </div>
                            <div style="position:relative;min-height:80px;">
                                <div id="syn-ledger-loader" class="syn-intake-loader">
                                    <div class="syn-intake-spinner"></div>
                                    <div class="syn-intake-loader-txt">Loading ledger…</div>
                                </div>
                                <iframe id="syn-ledger-frame"
                                    src="<?= syn_e($GLOBALS['web_root']) ?>/portal/report/pat_ledger.php?pid=<?= (int)($_SESSION['pid'] ?? 0) ?>"
                                    style="width:100%;height:700px;border:none;display:block;"
                                    title="Patient Billing Ledger" onload="synIframeLoaded('syn-ledger-loader')">
                                </iframe>
                            </div>
                        </div>
                    </div><!-- /syn-billtab-ledger -->

                    

                    

                </div><!-- /syn-tscroll -->
            </div><!-- /billing panel -->


            <!-- ════════════════════  INTAKE FORM  ════════════════════ -->
            <div id="syn-tab-intake" class="syn-tpanel">
                <div class="syn-tscroll syn-intake-scroll">

                    <!-- Page header -->
                    <div class="syn-page-hd">
                        <div>
                            <div class="syn-page-ttl">📝 Intake Form</div>
                            <div class="syn-page-sub">
                                Please complete your intake information below. Your progress is saved automatically.
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button class="syn-sbtn" style="border-color:var(--syn-teal);color:var(--syn-teal);"
                                onclick="synReloadIntake()" title="Reload form">
                                ↺ Reload
                            </button>
                            <button class="syn-sbtn"
                                style="background:var(--syn-teal);color:#fff;border-color:var(--syn-teal);font-weight:600;"
                                onclick="synOpenIntake()">
                                ⤢ Full Screen
                            </button>
                        </div>
                    </div>

                    <!-- iframe wrapper -->
                    <div class="syn-intake-wrap syn-card syn-fade">
                        <div id="syn-intake-loader" class="syn-intake-loader">
                            <div class="syn-intake-spinner"></div>
                            <div class="syn-intake-loader-txt">Loading intake form…</div>
                        </div>
                        <iframe id="syn-intake-frame" class="syn-intake-frame" src="about:blank"
                            data-src="<?= syn_e($GLOBALS['webroot'] ?? $GLOBALS['web_root']) ?>/interface/patientAddUpdate/synapta_form.php?pid=<?= (int)($_SESSION['pid'] ?? 0) ?>"
                            title="Intake Form" frameborder="0" allowfullscreen onload="synIntakeLoaded()">
                        </iframe>
                    </div>

                    <!-- Helper notice -->
                    <div class="syn-intake-notice">
                        🔒 Your information is encrypted and securely stored in your medical record.
                        If you have trouble viewing the form,
                        <a href="<?= syn_e($GLOBALS['webroot'] ?? $GLOBALS['web_root']) ?>/interface/patientAddUpdate/synapta_form.php?pid=<?= (int)($_SESSION['pid'] ?? 0) ?>"
                            target="_blank" rel="noopener">open it in a new tab →</a>
                    </div>

                </div><!-- /syn-tscroll -->
            </div><!-- /intake panel -->

            <!-- ════════════════════════════════════════════════════
         INTAKE FULLSCREEN MODAL
    ═══════════════════════════════════════════════════════ -->
            <div id="syn-intake-overlay" class="syn-modal-overlay syn-intake-modal-overlay" style="display:none;"
                onclick="synCloseIntakeFullscreen(event)">
                <div class="syn-intake-modal" onclick="event.stopPropagation()">
                    <div class="syn-modal-hd">
                        <div class="syn-modal-ttl">📝 Intake Form</div>
                        <div style="display:flex;gap:8px;">
                            <small>
                                If you have trouble viewing the form,
                                <a href="<?= syn_e($GLOBALS['webroot'] ?? $GLOBALS['web_root']) ?>/interface/patientAddUpdate/synapta_form.php?pid=<?= (int)($_SESSION['pid'] ?? 0) ?>"
                                    target="_blank" rel="noopener">open it in a new tab →</a>
                            </small>
                            <button class="syn-modal-close" onclick="synCloseIntakeFullscreen()"
                                aria-label="Close">✕</button>
                        </div>
                    </div>
                    <div class="syn-intake-modal-body">
                        <iframe id="syn-intake-modal-frame" class="syn-intake-frame syn-intake-modal-frame"
                            src="about:blank" title="Intake Form Fullscreen" frameborder="0">
                        </iframe>
                    </div>
                </div>


                <!-- ════════════════════════════════════════════════════
         APPOINTMENT BOOKING MODAL
         Loads add_edit_event_user.php in an iframe —
         same auth pattern as the rest of the portal.
    ═══════════════════════════════════════════════════════ -->
                <div id="syn-appt-modal" class="syn-modal-overlay syn-appt-modal" style="display:none;"
                    onclick="synCloseAppt(event)">
                    <div class="syn-appt-modal" onclick="event.stopPropagation()">

                        <!-- Modal header -->
                        <div class="syn-modal-hd">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span style="font-size:18px;">📅</span>
                                <div class="syn-modal-ttl">Book an Appointment</div>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <a href="<?= syn_e($GLOBALS['web_root']) ?>/interface/modules/custom_modules/oe-module-patient-dashboard/public/appt_wrapper.php?patid=<?= (int)($_SESSION['pid'] ?? 0) ?>"
                                    target="_blank" rel="noopener" class="syn-minibtn" title="Open in new tab">⤢ New
                                    Tab</a>
                                <button class="syn-modal-close" onclick="synCloseAppt()" aria-label="Close">✕</button>
                            </div>
                        </div>

                        <!-- iframe body -->
                        <div class="syn-appt-modal-body">
                            <!-- Loading spinner -->
                            <div id="syn-appt-loader" class="syn-intake-loader">
                                <div class="syn-intake-spinner"></div>
                                <div class="syn-intake-loader-txt">Loading appointment form…</div>
                            </div>

                            <iframe id="syn-appt-frame" src="about:blank"
                                data-src="<?= syn_e($GLOBALS['web_root']) ?>/interface/modules/custom_modules/oe-module-patient-dashboard/public/appt_wrapper.php?patid=<?= (int)($_SESSION['pid'] ?? 0) ?>"
                                class="syn-appt-frame" title="Book Appointment" frameborder="0"
                                onload="synApptFrameLoaded()">
                            </iframe>
                        </div>

                    </div><!-- /syn-appt-modal -->
                </div><!-- /syn-appt-overlay -->
            </div>



        </main><!-- /syn-main -->

        <!-- ══════════════════════════════════════════════════════════════
       RIGHT-RAIL — CARE HIGHLIGHTS
  ═══════════════════════════════════════════════════════════════ -->
        <aside class="syn-ai-panel">
            <div class="syn-ai-hd">
                <div class="syn-ai-ttl">✦ Synapta · AI Health Insights</div>
            </div>
            <div class="syn-ai-body">

                <div class="ai-pv">
                    <div class="ai-lbl">✦ AI Care Highlights · Today</div>
                    <div class="ai-item">
                        <div class="ai-dot dot-cr"></div>
                        <div><strong>Tele-visit in 47 minutes</strong> with Dr. Hadden — <span class="nxt">Test
                                connection now ›</span></div>
                    </div>
                    <div class="ai-item">
                        <div class="ai-dot dot-wa"></div>
                        <div>Home BP averaging <strong>142/88</strong> — above 130/80 goal. <span class="nxt">View trend
                                ›</span></div>
                    </div>
                    <div class="ai-item">
                        <div class="ai-dot dot-ok"></div>
                        <div><strong>A1c improved</strong> to 7.2% (from 7.8%) — keep it up</div>
                    </div>
                    <div class="ai-item">
                        <div class="ai-dot dot-inf"></div>
                        <div>Sleep averaging 6.2 hr — sleep coach: <span class="nxt">Read tips ›</span></div>
                    </div>
                    <div class="ai-item">
                        <div class="ai-dot dot-inf"></div>
                        <div>Care plan adherence: <strong>82%</strong> · 7 of 9 goals on track</div>
                    </div>
                </div>

                <div class="notif warn">
                    <div class="notif-icon">💊</div>
                    <div class="notif-content">
                        <div class="notif-ttl">Refill due in 8 days</div>
                        <div class="notif-body">Metformin 1000mg — Synapta will auto-request the refill on Apr 30 unless
                            you opt out.</div>
                        <span class="notif-cta" onclick="toast('Auto-refill confirmed','var(--teal)')">Confirm
                            auto-refill →</span>
                    </div>
                </div>

                <div class="notif info">
                    <div class="notif-icon">🩺</div>
                    <div class="notif-content">
                        <div class="notif-ttl">Preventive care due</div>
                        <div class="notif-body">Mammogram screening overdue by 6 months. Synapta can find a slot near
                            you.</div>
                        <span class="notif-cta ai" onclick="openModal('book')">Book screening →</span>
                    </div>
                </div>

                <!-- Upcoming appointment alert -->
                <?php if ($nextAppt): ?>
                <div class="syn-notif <?= !empty($nextAppt['is_tele']) ? 'syn-info' : '' ?>">
                    <div class="syn-notif-icon"><?= !empty($nextAppt['is_tele']) ? '📹' : '📅' ?></div>
                    <div class="syn-notif-content">
                        <div class="syn-notif-ttl">Next Appointment</div>
                        <div class="syn-notif-body">
                            <?= syn_e(date('D M d', strtotime($nextAppt['date']))) ?>
                            at <?= syn_e(substr($nextAppt['time'] ?? '', 0, 5)) ?>
                            — <?= syn_e($nextAppt['provider'] ?? '') ?>
                        </div>
                        <?php if (!empty($nextAppt['is_tele'])): ?>
                        <span class="syn-notif-cta">Join tele-visit →</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Unread messages alert -->
                <?php if ($unreadCount > 0): ?>
                <div class="syn-notif syn-warn">
                    <div class="syn-notif-icon">💬</div>
                    <div class="syn-notif-content">
                        <div class="syn-notif-ttl"><?= $unreadCount ?> Unread Message<?= $unreadCount > 1 ? 's' : '' ?>
                        </div>
                        <div class="syn-notif-body">You have messages from your care team.</div>
                        <a href="#" data-tab-link="messages" class="syn-notif-cta">Read messages →</a>
                    </div>
                </div>
                <?php endif; ?>



            </div>
        </aside>

    </div><!-- /syn-app -->
    <script>
    const patientId = <?= (int)($_SESSION['pid'] ?? 0) ?>;
    </script>
    <script src="<?= syn_e($module_path) ?>/js/synapta-portal.js"></script>
</body>

</html>