<?php

use App\Jobs\RouteCase;
use ProcessMaker\Core\JobsManager;

/**
 * cases_Derivate.php
 */
if (!isset($_SESSION['USER_LOGGED'])) {
    G::SendTemporalMessage('ID_LOGIN_AGAIN', 'warning', 'labels');
    $script = '
        <script type="text/javascript">
            var olink = document.location.href;
            olink = ( olink.search("gmail") == -1 ) ? parent.document.location.href : olink;
            if(olink.search("gmail") == -1){
                    parent.location = "../cases/casesStartPage?action=startCase";
            } else {
                    var data = olink.split("?");
                    var odata = data[1].split("&");
                    var appUid = odata[0].split("=");

                    var dataToSend = {
                            "action": "credentials",
                            "operation": "refreshPmSession",
                            "type": "processCall",
                            "funParams": [
                            appUid[1],
                            ""
                            ],
                            "expectReturn": false
                    };
                    var x = parent.postMessage(JSON.stringify(dataToSend), "*");
                    if (x == undefined){
                            x = parent.parent.postMessage(JSON.stringify(dataToSend), "*");
                    }
            }
        </script>';
    die($script);
}

/* Permissions */
switch ($RBAC->userCanAccess('PM_CASES')) {
    case -2:
        G::SendTemporalMessage('ID_USER_HAVENT_RIGHTS_SYSTEM', 'error', 'labels');
        G::header('location: ../login/login');
        die();
        break;
    case -1:
        G::SendTemporalMessage('ID_USER_HAVENT_RIGHTS_PAGE', 'error', 'labels');
        G::header('location: ../login/login');
        die();
        break;
}

/* Includes */
//If no variables are submitted and the $_POST variable is empty
if (!isset($_POST['form'])) {
    $_POST['form'] = [];
}
$postForm = $_POST['form'];

/* GET , POST & $_SESSION Vars */
/* Process the info */
$sStatus = 'TO_DO';

try {
    //Load Session variables
    $processUid = isset($_SESSION['PROCESS']) ? $_SESSION['PROCESS'] : '';
    // check if a task was already derivated
    if (isset($_SESSION["APPLICATION"]) && isset($_SESSION["INDEX"])) {
        $_SESSION['LAST_DERIVATED_APPLICATION'] = isset($_SESSION['LAST_DERIVATED_APPLICATION']) ? $_SESSION['LAST_DERIVATED_APPLICATION'] : '';
        $_SESSION['LAST_DERIVATED_INDEX'] = isset($_SESSION['LAST_DERIVATED_INDEX']) ? $_SESSION['LAST_DERIVATED_INDEX'] : '';
        if ($_SESSION["APPLICATION"] === $_SESSION['LAST_DERIVATED_APPLICATION'] && $_SESSION["INDEX"] === $_SESSION['LAST_DERIVATED_INDEX']) {
            throw new Exception(G::LoadTranslation('ID_INVALID_APPLICATION_ID_MSG', [G::LoadTranslation('ID_REOPEN')]));
        } else {
            $appDel = new AppDelegation();
            if ($appDel->alreadyRouted($_SESSION["APPLICATION"], $_SESSION['INDEX'])) {
                throw new Exception(G::LoadTranslation('ID_INVALID_APPLICATION_ID_MSG', [G::LoadTranslation('ID_REOPEN')]));
            } else {
                $_SESSION['LAST_DERIVATED_APPLICATION'] = $_SESSION["APPLICATION"];
                $_SESSION['LAST_DERIVATED_INDEX'] = $_SESSION["INDEX"];
            }
        }
    } else {
        throw new Exception(G::LoadTranslation('ID_INVALID_APPLICATION_ID_MSG', [G::LoadTranslation('ID_REOPEN')]));
    }

    $flagGmail = false;

    $application = $_SESSION['APPLICATION'];
    $tasUid = $_SESSION['TASK'];
    $index = $_SESSION["INDEX"];
    $userLogged = $_SESSION["USER_LOGGED"];
    
    // Now we dispatch the derivation of the case through Jobs Laravel.
    $closure = function() use ($processUid, $application, $postForm, $sStatus, $flagGmail, $tasUid, $index, $userLogged) {
        $cases = new Cases();
        $cases->routeCase($processUid, $application, $postForm, $sStatus, $flagGmail, $tasUid, $index, $userLogged);
    };
    if (!DISABLE_TASK_MANAGER_ROUTING_ASYNC) {
        // Routing the case asynchronically
        JobsManager::getSingleton()->dispatch(RouteCase::class, $closure);

        // We close the related threads.
        $cases = new Cases();
        $cases->CloseCurrentDelegation($application, $index);
    } else {
        // Routing the case synchronically
        $closure();
    }

    $debuggerAvailable = true;
    $casesRedirector = 'casesListExtJsRedirector';
    $nextStep = [];
    if (isset($_SESSION['user_experience']) && $flagGmail === false) {
        $nextStep['PAGE'] = $casesRedirector . '?ux=' . $_SESSION['user_experience'];
        $debuggerAvailable = false;
    } else {
        if ($flagGmail === true) {
            $nextStep['PAGE'] = $casesRedirector . '?gmail=1';
        } else {
            $nextStep['PAGE'] = $casesRedirector;
        }
    }

    // Triggers After
    $isIE = Bootstrap::isIE();

    // If the routing of cases asynchronically is disabled, use the old behaviour for debug option
    if (DISABLE_TASK_MANAGER_ROUTING_ASYNC) {
        // Determine the landing page
        if (isset($_SESSION['PMDEBUGGER']) && $_SESSION['PMDEBUGGER'] && $debuggerAvailable) {
            $_SESSION['TRIGGER_DEBUG']['BREAKPAGE'] = $nextStep['PAGE'];
            $loc = 'cases_Step?' . 'breakpoint=triggerdebug';
        } else {
            $loc = $nextStep['PAGE'];
        }
        // If debug option is enabled for the process, load the debug template
        if (isset($_SESSION['TRIGGER_DEBUG']['ISSET']) && !$isIE) {
            if ($_SESSION['TRIGGER_DEBUG']['ISSET'] == 1) {
                $templatePower = new TemplatePower(PATH_TPL . 'cases/cases_Step.html');
                $templatePower->prepare();
                $G_PUBLISH = new Publisher();
                $G_PUBLISH->AddContent('template', '', '', '', $templatePower);
                $_POST['NextStep'] = $loc;
                $G_PUBLISH->AddContent('view', 'cases/showDebugFrameLoader');
                $G_PUBLISH->AddContent('view', 'cases/showDebugFrameBreaker');
                $_SESSION['TRIGGER_DEBUG']['ISSET'] == 0;
                G::RenderPage('publish', 'blank');
                exit();
            } else {
                unset($_SESSION['TRIGGER_DEBUG']);
            }
        }
    } else {
        // If the case is routed synchronically, always redirect to the next step
        $loc = $nextStep['PAGE'];
        unset($_SESSION['TRIGGER_DEBUG']);
    }

    // Close tab only if IE11 add a validation was added if the current skin is uxs
    if ($isIE && !isset($_SESSION['__OUTLOOK_CONNECTOR__']) && SYS_SKIN !== "uxs") {
        $script = "
            <script type='text/javascript'>
                try {
                    if(top.opener) {
                        top.opener.location.reload();
                    }
                    top.close();
                } catch(e) {
                }
            </script>";
        die($script);
    }

    G::header("location: $loc");
} catch (Exception $e) {
    $aMessage = [];
    $aMessage['MESSAGE'] = $e->getMessage();
    $G_PUBLISH = new Publisher();
    $G_PUBLISH->AddContent('xmlform', 'xmlform', 'login/showMessage', '', $aMessage);
    G::RenderPage('publish', 'blank');
}
