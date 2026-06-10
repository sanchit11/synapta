<?php

/**
 * main.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Kevin Yeh <kevin.y@integralemr.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Ranganath Pathak <pathak@scrs1.org>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @copyright Copyright (c) 2016 Kevin Yeh <kevin.y@integralemr.com>
 * @copyright Copyright (c) 2016-2019 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2019 Ranganath Pathak <pathak@scrs1.org>
 * @copyright Copyright (c) 2024 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$sessionAllowWrite = true;
require_once(__DIR__ . '/../../globals.php');
require_once $GLOBALS['srcdir'] . '/ESign/Api.php';

require_once("$srcdir/pnotes.inc.php");

use ESign\Api;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Events\Main\Tabs\RenderEvent;
use OpenEMR\Menu\MainMenuRole;
use OpenEMR\Services\LogoService;
use OpenEMR\Services\ProductRegistrationService;
use OpenEMR\Telemetry\TelemetryService;
use Symfony\Component\Filesystem\Path;

const ENV_DISABLE_TELEMETRY = 'OPENEMR_DISABLE_TELEMETRY';

$logoService = new LogoService();
$menuLogo = $logoService->getLogo('core/menu/primary/');
// Registration status and options.
$productRegistration = new ProductRegistrationService();
$product_row = $productRegistration->getProductDialogStatus();
$allowRegisterDialog = $product_row['allowRegisterDialog'] ?? 0;
$allowTelemetry = $product_row['allowTelemetry'] ?? null; // for dialog
$allowEmail = $product_row['allowEmail'] ?? null; // for dialog

// Check if telemetry is disabled via environment variable
// Telemetry disable flag (set env var to: 1/true)
$val = getenv(ENV_DISABLE_TELEMETRY);
if ($val === false || $val === '') {
    $val = $_ENV[ENV_DISABLE_TELEMETRY] ?? $_SERVER[ENV_DISABLE_TELEMETRY] ?? null;
}
$disableTelemetry = ($val !== null) && filter_var($val, FILTER_VALIDATE_BOOLEAN);
if ($disableTelemetry) {
    $allowRegisterDialog = false;
    $allowTelemetry = false;
}

// If running unit tests, then disable the registration dialog
if ($_SESSION['testing_mode'] ?? false) {
    $allowRegisterDialog = false;
}
// If the user is not a super admin, then disable the registration dialog
if (!AclMain::aclCheckCore('admin', 'super')) {
    $allowRegisterDialog = false;
}

// Ensure token_main matches so this script can not be run by itself
//  If tokens do not match, then destroy the session and go back to log in screen
if (
    (empty($_SESSION['token_main_php'])) ||
    (empty($_GET['token_main'])) ||
    ($_GET['token_main'] != $_SESSION['token_main_php'])
) {
// Below functions are from auth.inc, which is included in globals.php
    authCloseSession();
    authLoginScreen(false);
}
// this will not allow copy/paste of the link to this main.php page or a refresh of this main.php page
//  (default behavior, however, this behavior can be turned off in the prevent_browser_refresh global)
if ($GLOBALS['prevent_browser_refresh'] > 1) {
    unset($_SESSION['token_main_php']);
}

$esignApi = new Api();
$twig = (new TwigContainer(null, $GLOBALS['kernel']))->getTwig();

 $user = $_SESSION['authUser'];

$messageCount = getPnotesByUser(
    "1",        // active messages
    "no",       // show only this user
    $user,      // username
    true        // count only
);



?>
<!DOCTYPE html>
<html>

<head>
    <title><?php echo text($openemr_name); ?></title>
    <style>
    .syd-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 18px 0 0px;
        height: 100%;
        border-right: 1px solid rgba(255, 255, 255, .07);
        flex-shrink: 0;
        font-family: system-ui, -apple-system, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
    }

    .syd-brand-name {
        font-size: 20px;
        font-weight: 500;
        letter-spacing: -0.3px;
    }

    .syd-brand-syn {
        color: #5DCAA5;
    }

    .syd-brand-apta {
        color: #AFA9EC;
    }
    </style>

    <script>
    // This is to prevent users from losing data by refreshing or backing out of OpenEMR.
    //  (default behavior, however, this behavior can be turned off in the prevent_browser_refresh global)
    <?php if ($GLOBALS['prevent_browser_refresh'] > 0) { ?>
    window.addEventListener('beforeunload', (event) => {
        if (!timed_out) {
            event.returnValue = <?php echo xlj('Recommend not leaving or refreshing or you may lose data.'); ?>;
        }
    });
    <?php } ?>

    <?php require($GLOBALS['srcdir'] . "/restoreSession.php"); ?>

    // Since this should be the parent window, this is to prevent calls to the
    // window that opened this window. For example when a new window is opened
    // from the Patient Flow Board or the Patient Finder.
    window.opener = null;
    window.name = "main";

    // This flag indicates if another window or frame is trying to reload the login
    // page to this top-level window.  It is set by javascript returned by auth.inc.php
    // and is checked by handlers of beforeunload events.
    var timed_out = false;
    // some globals to access using top.variable
    // note that 'let' or 'const' does not allow global scope here.
    // only use var
    var isPortalEnabled = "<?php echo $GLOBALS['portal_onsite_two_enable'] ?>";
    // Set the csrf_token_js token that is used in the below js/tabs_view_model.js script
    var csrf_token_js = <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>;
    var userDebug = <?php echo js_escape($GLOBALS['user_debug']); ?>;
    var webroot_url = <?php echo js_escape($web_root); ?>;
    var jsLanguageDirection = <?php echo js_escape($_SESSION['language_direction']); ?> ||
        'ltr';
    var jsGlobals = {};
    // used in tabs_view_model.js.
    jsGlobals.enable_group_therapy = <?php echo js_escape($GLOBALS['enable_group_therapy']); ?>;
    jsGlobals.languageDirection = jsLanguageDirection;
    jsGlobals.date_display_format = <?php echo js_escape($GLOBALS['date_display_format']); ?>;
    jsGlobals.time_display_format = <?php echo js_escape($GLOBALS['time_display_format']); ?>;
    jsGlobals.timezone = <?php echo js_escape($GLOBALS['gbl_time_zone'] ?? ''); ?>;
    jsGlobals.assetVersion = <?php echo js_escape($GLOBALS['v_js_includes']); ?>;
    var WindowTitleAddPatient = <?php echo($GLOBALS['window_title_add_patient_name'] ? 'true' : 'false'); ?>;
    var WindowTitleBase = <?php echo js_escape($openemr_name); ?>;
    const isSms = "<?php echo !empty($GLOBALS['oefax_enable_sms'] ?? null); ?>";
    const isFax = "<?php echo !empty($GLOBALS['oefax_enable_fax']) ?? null?>";
    const isServicesOther = (isSms || isFax);
    var telemetryEnabled = <?php echo js_escape((new TelemetryService())->isTelemetryEnabled()); ?>;

    /**
     * Async function to get session value from the server
     * Usage Example
     * let authUser;
     * let sessionPid = await top.getSessionValue('pid');
     * // If using then() method a promise is returned instead of the value.
     * await top.getSessionValue('authUser').then(function (auth) {
     *    authUser = auth;
     *    console.log('authUser', authUser);
     * });
     * console.log('session pid', sessionPid);
     * console.log('auth User', authUser);
     */
    async function getSessionValue(key) {
        restoreSession();
        let csrf_token_js = <?php echo js_escape(CsrfUtils::collectCsrfToken('default')); ?>;
        const config = {
            url: `${webroot_url}/library/ajax/set_pt.php?csrf_token_form=${csrf_token_js}`,
            method: 'POST',
            data: {
                mode: 'session_key',
                key: key
            }
        };
        try {
            const response = await $.ajax(config);
            restoreSession();
            return response;
        } catch (error) {
            throw error;
        }
    }

    function goRepeaterServices() {
        // Ensure send the skip_timeout_reset parameter to not count this as a manual entry in the
        // timing out mechanism in OpenEMR.

        // Send the skip_timeout_reset parameter to not count this as a manual entry in the
        // timing out mechanism in OpenEMR. Notify App for various portal and reminder alerts.
        // Combined portal and reminders ajax to fetch sjp 06-07-2020.
        // Incorporated timeout mechanism in 2021
        restoreSession();
        let request = new FormData;
        request.append("skip_timeout_reset", "1");
        request.append("isPortal", isPortalEnabled);
        request.append("isServicesOther", isServicesOther);
        request.append("isSms", isSms);
        request.append("isFax", isFax);
        request.append("csrf_token_form", csrf_token_js);
        fetch(webroot_url + "/library/ajax/dated_reminders_counter.php", {
            method: 'POST',
            credentials: 'same-origin',
            body: request
        }).then((response) => {
            if (response.status !== 200) {
                console.log('Reminders start failed. Status Code: ' + response.status);
                return;
            }
            return response.json();
        }).then((data) => {
            if (data.timeoutMessage && (data.timeoutMessage == 'timeout')) {
                // timeout has happened, so logout
                timeoutLogout();
            }
            if (isPortalEnabled) {
                let mail = data.mailCnt;
                let chats = data.chatCnt;
                let audits = data.auditCnt;
                let payments = data.paymentCnt;
                let total = data.total;
                let enable = ((1 * mail) + (1 * audits)); // payments are among audits.
                // Send portal counts to notification button model
                // Will turn off button display if no notification!
                app_view_model.application_data.user().portal(enable);
                if (enable > 0) {
                    app_view_model.application_data.user().portalAlerts(total);
                    app_view_model.application_data.user().portalAudits(audits);
                    app_view_model.application_data.user().portalMail(mail);
                    app_view_model.application_data.user().portalChats(chats);
                    app_view_model.application_data.user().portalPayments(payments);
                }
            }
            if (isServicesOther) {
                let sms = data.smsCnt;
                let fax = data.faxCnt;
                let total = data.serviceTotal;
                let enable = ((1 * sms) + (1 * fax));
                // Will turn off button display if no notification!
                app_view_model.application_data.user().servicesOther(enable);
                if (enable > 0) {
                    app_view_model.application_data.user().serviceAlerts(total);
                    app_view_model.application_data.user().smsAlerts(sms);
                    app_view_model.application_data.user().faxAlerts(fax);
                }
            }
            // Always send reminder count text to model
            app_view_model.application_data.user().messages(data.reminderText);
        }).catch(function(error) {
            console.log('Request failed', error);
        });

        // run background-services
        // delay 10 seconds to prevent both utility trigger at close to same time.
        // Both call globals so that is my concern.
        setTimeout(function() {
            restoreSession();
            request = new FormData;
            request.append("skip_timeout_reset", "1");
            request.append("ajax", "1");
            request.append("csrf_token_form", csrf_token_js);
            fetch(webroot_url + "/library/ajax/execute_background_services.php", {
                method: 'POST',
                credentials: 'same-origin',
                body: request
            }).then((response) => {
                if (response.status !== 200) {
                    console.log('Background Service start failed. Status Code: ' + response.status);
                }
            }).catch(function(error) {
                console.log('HTML Background Service start Request failed: ', error);
            });
        }, 10000);

        // auto run this function every 60 seconds
        var repeater = setTimeout("goRepeaterServices()", 60000);
    }

    function isEncounterLocked(encounterId) {
        <?php if ($esignApi->lockEncounters()) { ?>
        // If encounter locking is enabled, make a synchronous call (async=false) to check the
        // DB to see if the encounter is locked.
        // Call restore session, just in case
        // @TODO next clean up pass, turn into await promise then modify tabs_view_model.js L-309
        restoreSession();
        let url = webroot_url + "/interface/esign/index.php?module=encounter&method=esign_is_encounter_locked";
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                encounterId: encounterId
            },
            success: function(data) {
                encounter_locked = data;
            },
            dataType: 'json',
            async: false
        });
        return encounter_locked;
        <?php } else { ?>
        // If encounter locking isn't enabled then always return false
        return false;
        <?php } ?>
    }
    </script>

    <?php Header::setupHeader(['knockout', 'tabs-theme', 'i18next', 'hotkeys', 'i18formatting']); ?>
    <script>
    // set up global translations for js
    function setupI18n(lang_id) {
        restoreSession();
        return fetch(<?php echo js_escape($GLOBALS['webroot']) ?> + "/library/ajax/i18n_generator.php?lang_id=" +
            encodeURIComponent(lang_id) + "&csrf_token_form=" + encodeURIComponent(csrf_token_js), {
                credentials: 'same-origin',
                method: 'GET'
            }).then((response) => {
            if (response.status !== 200) {
                console.log('I18n setup failed. Status Code: ' + response.status);
                return [];
            }
            return response.json();
        })
    }

    setupI18n(<?php echo js_escape($_SESSION['language_choice']); ?>).then(translationsJson => {
        i18next.init({
            lng: 'selected',
            debug: false,
            nsSeparator: false,
            keySeparator: false,
            resources: {
                selected: {
                    translation: translationsJson
                }
            }
        });
    }).catch(error => {
        console.log(error.message);
    });

    /**
     * Assign and persist documents to portal patients
     * @var int patientId pid
     */
    function assignPatientDocuments(patientId) {
        let url = top.webroot_url + '/portal/import_template_ui.php?from_demo_pid=' + encodeURIComponent(patientId);
        dlgopen(url, 'pop-assignments', 'modal-lg', 850, '', '', {
            allowDrag: true,
            allowResize: true,
            sizeHeight: 'full',
        });
    }
    </script>

    <script src="js/custom_bindings.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/user_data_view_model.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/patient_data_view_model.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/therapy_group_data_view_model.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/tabs_view_model.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/application_view_model.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/frame_proxies.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/dialog_utils.js?v=<?php echo $v_js_includes; ?>"></script>
    <script src="js/shortcuts.js?v=<?php echo $v_js_includes; ?>"></script>

    <?php
    // Below code block is to prepare certain elements for deciding what links to show on the menu
    // prepare Ensora eRx globals that are used in creating the menu
    if ($GLOBALS['erx_enable']) {
        $newcrop_user_role_sql = sqlQuery("SELECT `newcrop_user_role` FROM `users` WHERE `username` = ?", [$_SESSION['authUser']]);
        $GLOBALS['newcrop_user_role'] = $newcrop_user_role_sql['newcrop_user_role'];
        if ($GLOBALS['newcrop_user_role'] === 'erxadmin') {
            $GLOBALS['newcrop_user_role_erxadmin'] = 1;
        }
    }

    // prepare track anything to be used in creating the menu
    $track_anything_sql = sqlQuery("SELECT `state` FROM `registry` WHERE `directory` = 'track_anything'");
    $GLOBALS['track_anything_state'] = ($track_anything_sql['state'] ?? 0);
    // prepare Issues popup link global that is used in creating the menu
    $GLOBALS['allow_issue_menu_link'] = (
        (AclMain::aclCheckCore('encounters', 'notes', '', 'write')
        || AclMain::aclCheckCore('encounters', 'notes_a', '', 'write'))
        && AclMain::aclCheckCore('patients', 'med', '', 'write')
    );

    // we use twig templates here so modules can customize some of these files
    // at some point we will twigify all of main.php so we can extend it.
    echo $twig->render("interface/main/tabs/tabs_template.html.twig", []);
    echo $twig->render("interface/main/tabs/menu_template.html.twig", []);
    // TODO: patient_data_template.php is a more extensive refactor that could be done in a future feature request but to not jeopardize 7.0.3 release we will hold off.
    ?>
    <?php require_once("templates/patient_data_template.php"); ?>
    <?php
    echo $twig->render("interface/main/tabs/therapy_group_template.html.twig", []);
    echo $twig->render("interface/main/tabs/user_data_template.html.twig", [
        'openemr_name' => $GLOBALS['openemr_name']
    ]);
    // Collect the menu then build it
    $menuMain = new MainMenuRole($GLOBALS['kernel']->getEventDispatcher());
    $menu_restrictions = $menuMain->getMenu();
    echo $twig->render("interface/main/tabs/menu_json.html.twig", ['menu_restrictions' => $menu_restrictions]);
    ?>
    <?php $userQuery = sqlQuery("select * from users where username = ?", [$_SESSION['authUser']]); ?>

    <script>
    <?php
        if ($_SESSION['default_open_tabs']) :
            // For now, only the first tab is visible, this could be improved upon by further customizing the list options in a future feature request
            $visible = "true";
            foreach ($_SESSION['default_open_tabs'] as $i => $tab) :
                $_unsafe_url = preg_replace('/(\?.*)/m', '', Path::canonicalize($fileroot . DIRECTORY_SEPARATOR . $tab['notes']));
                if (realpath($_unsafe_url) === false || !str_starts_with($_unsafe_url, (string) $fileroot)) {
                    unset($_SESSION['default_open_tabs'][$i]);
                    continue;
                }
                $url = json_encode($webroot . "/" . $tab['notes']);
                $target = json_encode($tab['option_id']);
                $label = json_encode(xl("Loading") . " " . $tab['title']);
                $loading = xlj("Loading");
                echo "app_view_model.application_data.tabs.tabsList.push(new tabStatus($label, $url, $target, $loading, true, $visible, false));\n";
                $visible = "false";
            endforeach;
        endif;
        ?>

    app_view_model.application_data.user(new user_data_view_model(<?php echo json_encode($_SESSION["authUser"])
            . ',' . json_encode($userQuery['fname'])
            . ',' . json_encode($userQuery['lname'])
            . ',' . json_encode($_SESSION['authProvider']); ?>));
    </script>
    <style>
    html,
    body {
        width: max-content;
        min-height: 100% !important;
        height: 100% !important;
    }

    #userdropdown.dropdown-menu {
        white-space: nowrap;
        /* prevents multi-line wrapping */
        min-width: max-content;
        /* expands to fit the widest item */
    }

    /* Allow flyout menus to extend outside navbar */
.navbar,
.navbar-collapse,
#mainMenu,
#mainMenu.collapse {
    overflow: visible !important;
}

/* Parent menu items */
#mainMenu li,
#mainMenu .dropdown,
#mainMenu .nav-item,
#mainMenu .menuLabel {
    position: relative !important;
}

/* Hide child menus initially */
#mainMenu ul ul,
#mainMenu .dropdown-menu,
#mainMenu .menuEntries {
    display: none !important;
    position: absolute !important;
    top: 0 !important;
    left: 100% !important;
    min-width: 220px !important;
    background: #fff !important;
    border: 1px solid #ccc !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
    z-index: 99999 !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Show submenu when hovering parent */
#mainMenu li:hover > ul,
#mainMenu li:hover > .dropdown-menu,
#mainMenu .dropdown:hover > .dropdown-menu,
#mainMenu .menuLabel:hover > .menuEntries {
    display: block !important;
}

/* Submenu items */
#mainMenu ul ul li,
#mainMenu .dropdown-menu li,
#mainMenu .menuEntries li {
    display: block !important;
    width: 100% !important;
    white-space: nowrap !important;
}

/* Links */
#mainMenu ul ul a,
#mainMenu .dropdown-menu a,
#mainMenu .menuEntries a {
    display: block !important;
    padding: 8px 12px !important;
    text-decoration: none !important;
    cursor: pointer;
}

/* Keep parent menu vertical */
#mainMenu > ul > li {
    position: relative !important;
    cursor: pointer;
}


    </style>
</head>

<body class="min-vw-100">
    <?php
    // fire off an event here
    if (!empty($GLOBALS['kernel']->getEventDispatcher())) {
        $dispatcher = $GLOBALS['kernel']->getEventDispatcher();
        $dispatcher->dispatch(new RenderEvent(), RenderEvent::EVENT_BODY_RENDER_PRE);
    }
    ?>
    <!-- Below iframe is to support logout, which needs to be run in an inner iframe to work as intended -->
    <iframe name="logoutinnerframe" id="logoutinnerframe"
        style="visibility:hidden; position:absolute; left:0; top:0; height:0; width:0; border:none;"
        src="about:blank"></iframe>
    <?php // mdsupport - app settings
    $disp_mainBox = '';
    if (isset($_SESSION['app1'])) {
        $rs = sqlquery(
            "SELECT title app_url FROM list_options WHERE activity=1 AND list_id=? AND option_id=?",
            ['apps', $_SESSION['app1']]
        );
        if ($rs['app_url'] != "main/main_screen.php") {
            echo '<iframe name="app1" src="../../' . attr($rs['app_url']) . '"
            style="position: absolute; left: 0; top: 0; height: 100%; width: 100%; border: none;" />';
            $disp_mainBox = 'style="display: none;"';
        }
    }
    ?>
    <div id="mainBox" <?php echo $disp_mainBox ?>>
        <nav class="navbar navbar-expand-xl navbar-dark bg-dark text-white py-0">
            <?php if ($GLOBALS['display_main_menu_logo'] === '1') : ?>
            <div class="syd-brand">
                <svg width="36" height="36" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-label="Synapta">
                    <line x1="40" y1="29" x2="40" y2="16" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round">
                    </line>
                    <line x1="51" y1="35" x2="63" y2="28" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round">
                    </line>
                    <line x1="51" y1="45" x2="63" y2="52" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round">
                    </line>
                    <line x1="40" y1="51" x2="40" y2="64" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round">
                    </line>
                    <line x1="29" y1="45" x2="17" y2="52" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round">
                    </line>
                    <line x1="29" y1="35" x2="17" y2="28" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round">
                    </line>
                    <circle cx="40" cy="40" r="10" fill="#1D9E75"></circle>
                    <circle cx="40" cy="40" r="6" fill="#04342C"></circle>
                    <rect x="37.5" y="33.5" width="5" height="13" rx="1" fill="#9FE1CB" opacity="0.8"></rect>
                    <rect x="34" y="37" width="12" height="5" rx="1" fill="#9FE1CB" opacity="0.8"></rect>
                    <circle cx="40" cy="12" r="5" fill="#5DCAA5"></circle>
                    <circle cx="40" cy="12" r="2.5" fill="#04342C"></circle>
                    <circle cx="67" cy="25" r="5" fill="#AFA9EC"></circle>
                    <circle cx="67" cy="25" r="2.5" fill="#26215C"></circle>
                    <circle cx="67" cy="55" r="5" fill="#5DCAA5"></circle>
                    <circle cx="67" cy="55" r="2.5" fill="#04342C"></circle>
                    <circle cx="40" cy="68" r="5" fill="#AFA9EC"></circle>
                    <circle cx="40" cy="68" r="2.5" fill="#26215C"></circle>
                    <circle cx="13" cy="55" r="5" fill="#5DCAA5"></circle>
                    <circle cx="13" cy="55" r="2.5" fill="#04342C"></circle>
                    <circle cx="13" cy="25" r="5" fill="#AFA9EC"></circle>
                    <circle cx="13" cy="25" r="2.5" fill="#26215C"></circle>
                </svg>
                <span class="syd-brand-name"><span class="syd-brand-syn">Syn</span><span
                        class="syd-brand-apta">apta</span></span>
            </div>

            <!-- <a class="navbar-brand" href="https://www.open-emr.org" title="OpenEMR < ?php echo xla("Website"); ?>" rel="noopener" target="_blank">
                    <img src="< ?php echo $menuLogo; ?>" class="d-inline-block align-middle" height="16" alt="< ?php echo xlt('Main Menu Logo'); ?>">
              </a> -->
            <?php endif; ?>
            <button class="navbar-toggler mr-auto" type="button" data-toggle="collapse" data-target="#mainMenu"
                aria-controls="mainMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#mainMenu"
                aria-controls="mainMenu" aria-expanded="false" aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse" id="mainMenu" data-bind="template: {name: 'menu-template', data: application_data}">
            </div>
            <div id="syd-navbar-patient" ></div>
          
            <div class="ml-auto d-flex align-items-center">

                <?php if ($GLOBALS['search_any_patient'] != 'none') : ?>
                <form name="frm_search_globals" class="form-inline">
                    <div class="input-group">
                        <input type="text" id="anySearchBox"
                            class="form-control-sm <?php echo $any_search_class ?> form-control" name="anySearchBox"
                            placeholder="<?php echo xla("Search by any demographics") ?>" autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" id="search_globals"
                                class="btn btn-sm btn-secondary <?php echo $search_globals_class ?>"
                                title='<?php echo xla("Search for patient by entering whole or part of any demographics field information"); ?>'
                                data-bind="event: {mousedown: viewPtFinder.bind( $data, '<?php echo xla("The search field cannot be empty. Please enter a search term") ?>', '<?php echo attr($search_any_type); ?>')}">
                                <i class="fa fa-search">&nbsp;</i></button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                <!--Below is the user data section that contains the user information and the attendant data-->
                <span id="userData" data-bind="template: {name: 'user-data-template', data: application_data}"></span>
                <?php
            // fire off a nav event
            $dispatcher->dispatch(new RenderEvent(), RenderEvent::EVENT_BODY_RENDER_NAV);
            ?>
            </div>
        </nav>

        <!-- REPLACE THIS ENTIRE SECTION -->

        <!-- <div id="attendantData" class="body_title acck" data-bind="template: {name: app_view_model.attendant_template_type, data: application_data}"></div> -->



        <!-- WITH THIS COMPLETE UPDATED LAYOUT -->

        <style>
        html,
        body {
            width: 100%;
            min-height: 100%;
            margin: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        /* MAIN APP LAYOUT */
        .app-container {
            display: flex;
            min-height: calc(100vh - 56px);
        }

        .modal,
        .modal-dialog,
        .modal-content {
            z-index: 1055 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }

        /* SIDEBAR */
        /* =========================================
   MODERN SIDEBAR
========================================= */

        .sidebar-modern {
            width: 60px;
            background: #060b1a;
            height: calc(100vh - 56px);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 16px;
            position: sticky;
            top: 56px;
            flex-shrink: 0;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            z-index: 100;
        }

        /* LOGO */
        .sidebar-logo {
            margin-bottom: 24px;
        }

        .logo-circle {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, #18c29c, #0ea5e9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 22px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
        }

        .navbar {
            border-bottom: 2px solid rgba(29, 158, 117, .3);
        }

        /* NAV ITEM */
        .ni {
            /* width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            color: #8b9bb4;
            cursor: pointer;
            position: relative;
            transition: all .25s ease;
            font-size: 24px; */
            width: 44px;
            height: 44px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .18s;
            position: relative;
            color: #8b9bb4;
            font-size: 18px;
            flex-shrink: 0;
            border: none;
            margin-bottom: 6px;

        }

        .ni span {
            position: absolute;


            color: rgba(255, 255, 255, .35);
            font-size: 18px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 6px;
            white-space: nowrap;
            pointer-events: none;

            transition: opacity .15s;
            z-index: 200;
            box-shadow: 0 3px 12px rgba(29, 158, 117, .13);
        }


        /* ACTIVE */
        .ni.on {
            background: linear-gradient(135deg, #1d9e75, #1d9e75);
            color: white !important;
            box-shadow: 0 10px 30px rgba(20, 184, 166, 0.35);
        }

        .ni.on span {

            color: white !important;

        }

        /* HOVER */
        .ni:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white !important;
            transform: translateY(-2px);
        }

        .ni:hover span {
            color: white !important;
        }

        /* BADGE */
        .nb {
            position: absolute;
            top: 0px;
            right: 2px;
            background: #ef4444;
            color: white;
            font-size: 11px;
            min-width: 18px;
            height: 18px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border: 2px solid #060b1a;
        }

        /* SEPARATOR */
        .nsep {
            width: 38px;
            height: 1px;
            background: rgba(255, 255, 255, 0.08);
            margin: 0px 0 6px;
        }

        /* BOTTOM */
        .nbot {
            margin-top: auto;
            margin-bottom: 20px;
        }

        /* TOOLTIP */
        .ntip {
            position: absolute;
            left: 78px;
            background: #111827;
            color: white;
            padding: 10px 14px;
            border-radius: 12px;
            white-space: nowrap;
            font-size: 13px;
            opacity: 0;
            pointer-events: none;
            transition: all .2s ease;
            transform: translateX(-10px);
            z-index: 9999;
        }

        .ni:hover .ntip {
            opacity: 1;
            transform: translateX(0);
        }

        /* CONTENT AREA */
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f5f7fa;
        }

        /* MAKE MENU VERTICAL */
        .sidebar .navbar-nav {
            flex-direction: column !important;
            width: 100%;
        }

        /* NAV ITEMS */
        .sidebar .nav-item {
            width: 100%;
        }

        /* LINKS */
        .sidebar .nav-link {
            color: #ffffff !important;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            width: 100%;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: all 0.2s ease;
        }

        /* HOVER */
        .sidebar .nav-link:hover {
            background: #1f2937;
            color: #fff !important;
        }

        /* ACTIVE */
        .sidebar .nav-link.active {
            background: #2563eb;
        }

        /* DROPDOWNS */
        .sidebar .dropdown-menu {
            position: static !important;
            transform: none !important;
            float: none !important;
            background: #17202f;
            border: none;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* DROPDOWN ITEMS */
        .sidebar .dropdown-item {
            color: #d1d5db !important;
            padding: 10px 25px;
            font-size: 14px;
        }

        /* DROPDOWN HOVER */
        .sidebar .dropdown-item:hover {
            background: #253043;
            color: #fff !important;
        }

        /* CONTENT */
        #mainFrames_div {
            flex: 1;
            overflow: hidden;
        }

        /* IFRAMES */
        #framesDisplay {
            height: 100%;
        }

        #framesDisplay iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* PATIENT BAR */
        #attendantData {
            background: #ffffff;
            border-bottom: 1px solid #dbe2ea;
        }

        /* TABS */
        #tabs_div {
            background: #ffffff;
            border-bottom: 1px solid #dbe2ea;
        }

        /* SCROLLBAR */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 10px;
        }

        /* MOBILE */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
        }

        /* ALWAYS SHOW TOGGLER */
        .custom-toggler {
            display: block !important;
            margin-left: 10px;
            border: none;
            background: transparent;
        }

        /* HAMBURGER ICON */
        .custom-toggler .navbar-toggler-icon {
            width: 28px;
            height: 28px;
            display: inline-block;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='white' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
        }

        /* HIDE MENU BY DEFAULT */
        #mainMenu {
            position: absolute;
            top: 56px;
            left: 0;
            width: 280px;
            background: #fff;
            z-index: 1040;
            padding: 10px 0;
            box-shadow: -8px 0 40px rgba(0, 0, 0, .25);
        }

        #mainMenu .menuLabel {
            padding: 5px 10px;
            border-bottom: 1px solid #eee;
        }

        /* VERTICAL MENU */
        #mainMenu .navbar-nav {
            flex-direction: column !important;
            width: 100%;
        }

        /* MENU LINKS */
        #mainMenu .nav-link {
            color: white !important;
            padding: 12px 20px;
            width: 100%;
        }

        /* HOVER */
        #mainMenu .nav-link:hover {
            background: #1f2937;
        }

        .tabNotchosen,
        .tabsNoHover {
            border-bottom: 1px solid #1D9E75;
        }

        .tabSpan {
            border-bottom: 0;
            border-left: 1px solid #1D9E75;
            border-right: 1px solid #1D9E75;
            border-top: 4px solid #1D9E75;
        }

        .tabsNoHover {
            background: 0 0 !important;
            border-top: 4px solid transparent !important;
            border-left: 0px solid #1D9E75;
            border-right: 0px solid #1D9E75;
            border-bottom: 1px solid #1D9E75;
        }

        .form-control {
            font-size: 14px !important;
            border-radius: 5px !important;
        }


        /* Hide the tab navigation bar only */
        #tabs_div .tabContainer,
        #tabs_div ul.tabNav {
            display: none !important;
        }

        
        
        </style>

        <div class="app-container">

            <!-- SIDEBAR -->
            <nav class="sidebar-modern">



                <div class="ni on"
                    onclick="sNav(this, webroot_url + '/interface/main/main_info.php', 'Schedule', 'cal')">
                    <span>📅</span>
                    <div class="ntip">Today's Schedule</div>
                </div>


                <div class="ni"
                    onclick="sNav(this, webroot_url + '/interface/main/finder/dynamic_finder.php', 'Patients', 'ptlist')">

                    <span>🏥</span>
                    <div class="ntip">Patient List</div>
                </div>

                <div class="ni"
                    onclick="sNav(this, webroot_url + '/interface/main/messages/messages.php?form_active=1', 'Messages', 'msg')">
                    <?php if (!empty($messageCount) && $messageCount != 0): ?>
                    <div class="nb"><?php echo $messageCount; ?></div>
                    <?php endif; ?>
                    <span>💬</span>
                    <div class="ntip">Messages</div>
                </div>

                <div class="nsep"></div>

                <div class="ni"
                    onclick="sNav(this, webroot_url + '/interface/main/display_documents.php', 'Lab Results', 'LabResults')">
                    <!-- <div class="nb">2</div> -->
                    <span>🧪</span>
                    <div class="ntip">Lab Results</div>
                </div>

                <div class="ni"
                    onclick="sNav(this, webroot_url + '/interface/patient_file/encounter/load_form.php?formname=procedure_order', 'Orders', 'Orders')">
                    <span>📝</span>
                    <div class="ntip">Orders</div>
                </div>

                <div class="ni"
                    onclick="sNav(this, webroot_url + '/interface/patient_file/encounter/load_form.php?formname=fee_sheet', 'Billing', 'Billing')">
                    <span>💰</span>
                    <div class="ntip">Billing & RCM</div>
                </div>

                <div class="nsep"></div>

                <div class="ni"  onclick="sNav(this, webroot_url + '/interface/reports/appointments_report.php', 'Analytics', 'Analytics')">
                    <span>📊</span>
                    <div class="ntip">Analytics</div>
                </div>





                <div class="ni nbot" onclick="setNav(this)">
                    <span>⚙️</span>
                    <div class="ntip">Settings</div>
                </div>

            </nav>

            <!-- CONTENT AREA -->
            <div class="content-area">

                <!-- PATIENT INFO -->
                <div id="attendantData" class="body_title acck"
                    data-bind="template: {name: app_view_model.attendant_template_type, data: application_data}">
                </div>

                <!-- TABS -->
                <div class="body_title pt-1" id="tabs_div"
                    data-bind="template: {name: 'tabs-controls', data: application_data}">
                </div>

                <!-- MAIN FRAMES -->
                <div class="mainFrames d-flex flex-row" id="mainFrames_div">

                    <div id="framesDisplay" data-bind="template: {name: 'tabs-frames', data: application_data}">
                    </div>

                </div>

            </div>

        </div>

        <!-- <div id="attendantData" class="body_title acck" data-bind="template: {name: app_view_model.attendant_template_type, data: application_data}"></div>
       
        <div class="body_title pt-1" id="tabs_div" data-bind="template: {name: 'tabs-controls', data: application_data}"></div>
        <div class="mainFrames d-flex flex-row" id="mainFrames_div">
            <div id="framesDisplay" data-bind="template: {name: 'tabs-frames', data: application_data}"></div>
        </div>
        < ?php echo $twig->render("product_registration/product_registration_modal.html.twig", [
            'webroot' => $webroot,
            'allowEmail' => $allowEmail ?? false,
            'allowTelemetry' => $allowTelemetry ?? false]); ?> -->
    </div>
    <script>
    ko.applyBindings(app_view_model);

    $(function() {
        $('.dropdown-toggle').dropdown();
        $('#patient_caret').click(function() {
            $('#attendantData').slideToggle();
            $('#patient_caret').toggleClass('fa-caret-down').toggleClass('fa-caret-up');
        });
        if ($('body').css('direction') == "rtl") {
            $('.dropdown-menu-right').each(function() {
                $(this).removeClass('dropdown-menu-right');
            });
        }
    });
    $(function() {
        $('#logo_menu').focus();
    });
    $('#anySearchBox').keypress(function(event) {
        if (event.which === 13 || event.keyCode === 13) {
            event.preventDefault();
            $('#search_globals').mousedown();
        }
    });
    document.addEventListener('touchstart', {}); //specifically added for iOS devices, especially in iframes
    <?php if (($_ENV['OPENEMR__NO_BACKGROUND_TASKS'] ?? 'false') !== 'true') { ?>
    $(function() {
        goRepeaterServices();
    });
    <?php } ?>

    //Set Nav
    function setNav(el) {

        document.querySelectorAll('.ni').forEach(item => {
            item.classList.remove('on');
        });

        el.classList.add('on');
    }

    // FUNCTION TO Work sidebar menu link
    function sNav(el, url, label, tabName) {

        // Sidebar active state
        document.querySelectorAll('.ni').forEach(item => item.classList.remove('on'));
        el.classList.add('on');

        if (!url) return;

        top.restoreSession();

        let tabsObj = app_view_model.application_data.tabs;

        // Find existing tab
        let existing = tabsObj.tabsList().find(t =>
            t.name && ko.unwrap(t.name) === tabName
        );

        // FUNCTION TO ACTIVATE TAB PROPERLY
        function activateTab(tabObj) {

            // Deactivate ALL tabs first
            tabsObj.tabsList().forEach(t => {

                if (typeof t.current === 'function') {
                    t.current(false);
                }

                if (typeof t.visible === 'function') {
                    t.visible(false);
                }

            });

            // Activate ONLY selected tab
            if (typeof tabObj.current === 'function') {
                tabObj.current(true);
            }

            if (typeof tabObj.visible === 'function') {
                tabObj.visible(true);
            }

            // Set current tab in Knockout
            tabsObj.current_tab(tabObj);

            // WAIT for DOM update
            setTimeout(function() {

                // Remove active class from all tab elements
                document.querySelectorAll('#tabs_div li').forEach(li => {
                    li.classList.remove('active');
                });

                // Click correct tab element
                let tabs = document.querySelectorAll('#tabs_div li');

                tabs.forEach(li => {

                    // Match using tab text OR internal tab name
                    if (
                        li.innerText.includes(label) ||
                        li.innerText.includes('Unknown')
                    ) {


                        li.click();

                        li.classList.add('active');

                        // FORCE TAB TITLE
                        let span = li.querySelector('span');

                        if (span) {
                            span.innerText = label;
                        } else {
                            li.childNodes[0].nodeValue = label;
                        }

                    }

                });

                // FORCE FULL WIDTH LAYOUT
                let iframes = document.querySelectorAll('iframe');

                iframes.forEach(f => {
                    f.style.width = '100%';
                    f.style.flex = '1';
                });

            }, 300);
        }

        // Existing tab → activate
        if (existing) {
            activateTab(existing);
            return;
        }

        // Create new tab
        let newTab = new tabStatus(
            label,
            url,
            tabName,
            label,
            true,
            true,
            false
        );

        // Add tab
        tabsObj.tabsList.push(newTab);

        // Activate after render
        setTimeout(function() {
            activateTab(newTab);
        }, 300);
    }

    let sydCurrentPid = 0;

function sydCheckPatientChange()
{
    fetch(
        "<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-physician-dashboard/public/current_pid.php",
        {
            credentials: 'same-origin'
        }
    )
    .then(response => response.text())
    .then(pid => {

        pid = parseInt(pid);

        // No change
        if (pid === sydCurrentPid) {
            return;
        }

        sydCurrentPid = pid;

        // Refresh patient ribbon
        sydLoadPatientRibbon();
    });
}

function sydLoadPatientRibbon()
{
    fetch(
        "<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-physician-dashboard/public/patient_info_bar.php",
        {
            credentials: 'same-origin'
        }
    )
    .then(response => response.text())
    .then(html => {

        document.getElementById(
            'syd-navbar-patient'
        ).innerHTML = html;

    });
}

// Initial load
sydCheckPatientChange();

// Check every 3 sec
setInterval(sydCheckPatientChange, 3000);

    </script>
    <?php

    // fire off an event here
    $dispatcher->dispatch(new RenderEvent(), RenderEvent::EVENT_BODY_RENDER_POST);

    if (!empty($allowRegisterDialog)) { // disable if running unit tests.
        // Include the product registration js, telemetry and usage data reporting dialog
        echo $twig->render("product_registration/product_reg.js.twig", ['webroot' => $webroot]);
    }

    ?>
</body>

</html>