<?php

use Illuminate\Support\Facades\Log;
use ProcessMaker\BusinessModel\Cases as BusinessModelCases;
use ProcessMaker\BusinessModel\Light\NotificationDevice;
use ProcessMaker\BusinessModel\Task as BusinessModelTask;
use ProcessMaker\BusinessModel\User as BusinessModelUser;
use ProcessMaker\BusinessModel\WebEntryEvent;
use ProcessMaker\Cases\CasesTrait;
use ProcessMaker\Core\System;
use ProcessMaker\Model\AppDelay as Delay;
use ProcessMaker\Model\AppThread as Thread;
use ProcessMaker\Model\Delegation;
use ProcessMaker\Model\User;
use ProcessMaker\Plugins\PluginRegistry;
use ProcessMaker\Util\DateTime;

/**
 * A Cases object where you can do start, load, update, refresh about cases
 * This object is applied to Task
 * @package    workflow.engine.classes
 */
class Cases
{
    use CasesTrait;
    private $appSolr = null;
    public $dir = 'ASC';
    public $sort = 'APP_MSG_DATE';
    public $arrayTriggerExecutionTime = [];
    public $caseTitle = '';
    private $triggerMessageExecution = '';

    public function __construct()
    {
        //get Solr initialization variables
        if (($solrConf = System::solrEnv()) !== false) {
            $this->appSolr = new AppSolr($solrConf['solr_enabled'], $solrConf['solr_host'], $solrConf['solr_instance']);
        }
    }
    /**
     * Get the caseTitle
     *
     * @return string
     */
    public function getCaseTitle()
    {
        return $this->caseTitle;
    }

    /**
     * Set the caseTitle
     *
     * @param string $v
     *
     * @return void
     */
    public function setCaseTitle(string $v)
    {
        $this->caseTitle = $v;
    }

    /**
     * Get the triggerMessageExecution
     *
     * @return string
     */
    public function getTriggerMessageExecution()
    {
        return $this->triggerMessageExecution;
    }

    /**
     * Add messages related to the trigger execution
     *
     * @param string $v
     *
     * @return void
     */
    public function addTriggerMessageExecution($v)
    {
        $this->triggerMessageExecution .= $v;
    }

    /**
     * Ask if an user can start a case
     * @param string $sUIDUser
     * @return boolean
     */
    public function canStartCase($sUIDUser = '', $processUid = '')
    {
        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn('COUNT(*)');
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(ProcessPeer::PRO_SUBPROCESS, '0');
        $c->add(TaskPeer::TAS_START, 'TRUE');
        $c->add(TaskUserPeer::USR_UID, $sUIDUser);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        if ($processUid != '') {
            $c->add(TaskPeer::PRO_UID, $processUid);
        }

        $rs = TaskPeer::doSelectRS($c);
        $rs->next();
        $row = $rs->getRow();
        $count = $row[0];
        if ($count > 0) {
            return true;
        }

        //check groups
        $group = new Groups();
        $aGroups = $group->getActiveGroupsForAnUser($sUIDUser);

        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn('COUNT(*)');
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(ProcessPeer::PRO_SUBPROCESS, '0');
        $c->add(TaskPeer::TAS_START, 'TRUE');
        $c->add(TaskUserPeer::USR_UID, $aGroups, Criteria::IN);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        if ($processUid != '') {
            $c->add(TaskPeer::PRO_UID, $processUid);
        }

        $rs = TaskPeer::doSelectRS($c);
        $rs->next();
        $row = $rs->getRow();
        $count = $row[0];
        return ($count > 0);
    }

    /**
     * Get user's starting tasks
     *
     * @param string $uidUser
     * @param bool $withoutDummyTasks
     * @return array
     */
    public function getStartCases($uidUser = '', $withoutDummyTasks = false)
    {
        $rows = [['uid' => 'char', 'value' => 'char']];
        $tasks = [];

        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(TaskPeer::TAS_START, 'TRUE');
        $c->add(TaskUserPeer::USR_UID, $uidUser);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();

        while (is_array($row)) {
            $tasks[] = $row['TAS_UID'];
            $rs->next();
            $row = $rs->getRow();
        }

        //check groups
        $group = new Groups();
        $groups = $group->getActiveGroupsForAnUser($uidUser);

        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(TaskPeer::TAS_START, 'TRUE');
        $c->add(TaskUserPeer::USR_UID, $groups, Criteria::IN);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();

        while (is_array($row)) {
            $tasks[] = $row['TAS_UID'];
            $rs->next();
            $row = $rs->getRow();
        }

        $c = new Criteria();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::TAS_TITLE);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addSelectColumn(ProcessPeer::PRO_TITLE);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->add(TaskPeer::TAS_UID, $tasks, Criteria::IN);
        // Include or not the dummy tasks
        if ($withoutDummyTasks) {
            $c->add(TaskPeer::TAS_TYPE, BusinessModelTask::getDummyTypes(), Criteria::NOT_IN);
        }
        $c->addAscendingOrderByColumn(ProcessPeer::PRO_TITLE);
        $c->addAscendingOrderByColumn(TaskPeer::TAS_TITLE);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        while ($row = $rs->getRow()) {
            $rows[] = [
                'uid' => $row['TAS_UID'],
                'value' => $row['PRO_TITLE'] . ' (' . $row['TAS_TITLE'] . ')',
                'pro_uid' => $row['PRO_UID']
            ];
            $rs->next();
            $row = $rs->getRow();
        }
        return $rows;
    }

    /**
     * Get user starting tasks, but per type (dropdown, radio and category type)
     *
     * @param string $sUIDUser
     * @return $rows
     */
    public function getStartCasesPerType($sUIDUser = '', $typeView = null)
    {
        $rows[] = ['uid' => 'char', 'value' => 'char'];
        $tasks = [];
        $arrayTaskTypeToExclude = [
            "WEBENTRYEVENT",
            "END-MESSAGE-EVENT",
            "START-MESSAGE-EVENT",
            "INTERMEDIATE-THROW-MESSAGE-EVENT",
            "INTERMEDIATE-CATCH-MESSAGE-EVENT",
            "SCRIPT-TASK",
            "START-TIMER-EVENT",
            "INTERMEDIATE-CATCH-TIMER-EVENT"
        ];
        $webEntryEvent = new WebEntryEvent();
        $arrayWebEntryEvent = [];
        // Set the parameter $considerShowInCase=true, to consider the WE_SHOW_IN_CASE
        // configuration to filter the Start events with WebEntry.
        $allWebEntryEvents = $webEntryEvent->getAllWebEntryEvents(true);
        foreach ($allWebEntryEvents as $webEntryEvents) {
            $arrayWebEntryEvent[] = $webEntryEvents["ACT_UID"];
        }
        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(ProcessPeer::PRO_SUBPROCESS, '0');
        $c->add(TaskPeer::TAS_TYPE, $arrayTaskTypeToExclude, Criteria::NOT_IN);
        $c->add(TaskPeer::TAS_UID, $arrayWebEntryEvent, Criteria::NOT_IN);
        $c->add(TaskPeer::TAS_START, 'TRUE');
        $c->add(TaskUserPeer::USR_UID, $sUIDUser);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();
        while (is_array($row)) {
            $tasks[] = $row['TAS_UID'];
            $rs->next();
            $row = $rs->getRow();
        }
        //check groups
        $group = new Groups();
        $aGroups = $group->getActiveGroupsForAnUser($sUIDUser);
        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(ProcessPeer::PRO_SUBPROCESS, '0');
        $c->add(TaskPeer::TAS_TYPE, $arrayTaskTypeToExclude, Criteria::NOT_IN);
        $c->add(TaskPeer::TAS_UID, $arrayWebEntryEvent, Criteria::NOT_IN);
        $c->add(TaskPeer::TAS_START, 'TRUE');
        $c->add(TaskUserPeer::USR_UID, $aGroups, Criteria::IN);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();
        while (is_array($row)) {
            $tasks[] = $row['TAS_UID'];
            $rs->next();
            $row = $rs->getRow();
        }
        $c = new Criteria();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::TAS_TITLE);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addSelectColumn(ProcessPeer::PRO_TITLE);
        $c->addSelectColumn(ProcessPeer::PRO_DESCRIPTION);
        if ($typeView == 'category') {
            $c->addAsColumn('PRO_CATEGORY', 'PCS.PRO_CATEGORY');
            $c->addAsColumn('CATEGORY_NAME', 'PCSCAT.CATEGORY_NAME');
            $c->addAlias('PCS', 'PROCESS');
            $c->addAlias('PCSCAT', 'PROCESS_CATEGORY');
            $aConditions = [];
            $aConditions[] = [TaskPeer::PRO_UID, 'PCS.PRO_UID'];
            $c->addJoinMC($aConditions, Criteria::LEFT_JOIN);
            $aConditions = [];
            $aConditions[] = ['PCS.PRO_CATEGORY', 'PCSCAT.CATEGORY_UID'];
            $c->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        }
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->add(TaskPeer::TAS_UID, $tasks, Criteria::IN);
        $c->add(ProcessPeer::PRO_SUBPROCESS, '0');
        $c->addAscendingOrderByColumn(ProcessPeer::PRO_TITLE);
        $c->addAscendingOrderByColumn(TaskPeer::TAS_TITLE);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $countTaskLabel = 1;
        while ($row = $rs->getRow()) {
            if ($row['TAS_TITLE'] != '') {
                $taskTitleLabel = $row['TAS_TITLE'];
            } else {
                $taskTitleLabel = G::LoadTranslation('ID_UNTITLED_TASK') . ' ' . $countTaskLabel;
                $countTaskLabel++;
            }
            if ($typeView == 'category') {
                $taskTitle = TaskPeer::retrieveByPK($row['TAS_UID']);
                $row['TAS_TITLE'] = $taskTitle->getTasTitle();
                $row['CATEGORY_NAME'] = ($row['CATEGORY_NAME'] == '') ?
                        G::LoadTranslation('ID_PROCESS_NOCATEGORY') : $row['CATEGORY_NAME'];
                $rows[] = [
                    'uid' => $row['TAS_UID'],
                    'value' => $row['PRO_TITLE'] . ' (' . $taskTitleLabel . ')',
                    'pro_uid' => $row['PRO_UID'],
                    'pro_description' => $row['PRO_DESCRIPTION'],
                    'cat' => $row['PRO_CATEGORY'],
                    'catname' => $row['CATEGORY_NAME']
                ];
            } else {
                $rows[] = [
                    'uid' => $row['TAS_UID'],
                    'value' => $row['PRO_TITLE'] . ' (' . $taskTitleLabel . ')',
                    'pro_uid' => $row['PRO_UID'],
                    'pro_description' => $row['PRO_DESCRIPTION']
                ];
            }
            $rs->next();
            $row = $rs->getRow();
        }
        $rowsToReturn = $rows;
        if ($typeView === 'category') {
            $rowsToReturn = $this->orderStartCasesByCategoryAndName($rows);
        }
        return $rowsToReturn;
    }

    /**
     * get user's SelfService tasks
     * @param string $sUIDUser
     * @return $rows
     */
    public function getSelfServiceTasks($sUIDUser = '')
    {
        $rows[] = array('uid' => '', 'value' => '');
        $tasks = array();

        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(TaskPeer::TAS_ASSIGN_TYPE, 'SELF_SERVICE');
        $c->add(TaskUserPeer::USR_UID, $sUIDUser);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();

        while (is_array($row)) {
            $tasks[] = $row['TAS_UID'];
            $rs->next();
            $row = $rs->getRow();
        }

        //check groups
        $group = new Groups();
        $aGroups = $group->getActiveGroupsForAnUser($sUIDUser);

        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(TaskPeer::TAS_UID, TaskUserPeer::TAS_UID, Criteria::LEFT_JOIN);
        $c->add(ProcessPeer::PRO_STATUS, 'ACTIVE');
        $c->add(TaskPeer::TAS_ASSIGN_TYPE, 'SELF_SERVICE');
        $c->add(TaskUserPeer::USR_UID, $aGroups, Criteria::IN);
        $c->add(TaskUserPeer::TU_TYPE, 1);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();

        while (is_array($row)) {
            $tasks[] = $row['TAS_UID'];
            $rs->next();
            $row = $rs->getRow();
        }

        $c = new Criteria();
        $c->addSelectColumn(TaskPeer::TAS_UID);
        $c->addSelectColumn(TaskPeer::TAS_TITLE);
        $c->addSelectColumn(TaskPeer::PRO_UID);
        $c->addSelectColumn(ProcessPeer::PRO_TITLE);
        $c->addJoin(TaskPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->add(TaskPeer::TAS_UID, $tasks, Criteria::IN);
        $c->addAscendingOrderByColumn(ProcessPeer::PRO_TITLE);
        $c->addAscendingOrderByColumn(TaskPeer::TAS_TITLE);
        $rs = TaskPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        while ($row = $rs->getRow()) {
            $rows[] = array(
                'uid' => $row['TAS_UID'],
                'value' => $row['PRO_TITLE'] . ' (' . $row['TAS_TITLE'] . ')',
                'pro_uid' => $row['PRO_UID']
            );
            $rs->next();
            $row = $rs->getRow();
        }
        return $rows;
    }

    /**
     * Checks if at least one of the user’s tasks is self-service
     *
     * @param string $usrUid
     * @param string $tasUid
     * @param string $appUid
     *
     * @return boolean
     */
    public function isSelfService($usrUid, $tasUid, $appUid = '')
    {
        $selfServiceVariable = Task::getVariableUsedInSelfService($tasUid);
        switch ($selfServiceVariable){
            case Task::TASK_ASSIGN_TYPE_NO_SELF_SERVICE:
                return false;
                break;
            case Task::SELF_SERVICE_WITHOUT_VARIABLE:
                $tasks = $this->getSelfServiceTasks($usrUid);
                foreach ($tasks as $key => $val) {
                    if ($tasUid === $val['uid']) {
                        return true;
                    }
                }

                return false;
                break;
            default://When is "Self Service Value Based Assignment"
                if (!empty($appUid)) {
                    //If is Self service Value Based we will be get the value of variable defined in $selfServiceType
                    $selfServiceType = trim($selfServiceVariable, ' @#');
                    $caseData = $this->loadCase($appUid);
                    if (isset($caseData['APP_DATA'][$selfServiceType])) {
                        $dataVariable = $caseData['APP_DATA'][$selfServiceType];
                        if (empty($dataVariable)) {
                            return false;
                        }

                        $dataVariable = is_array($dataVariable) ? $dataVariable : (array)trim($dataVariable);
                        if (in_array($usrUid, $dataVariable, true)) {
                            return true;
                        }

                        $groups = new Groups();
                        foreach ($groups->getActiveGroupsForAnUser($usrUid) as $key => $group) {
                            if (in_array($group, $dataVariable, true)) {
                                return true;
                            }
                        }
                    }
                }
        }

        return false;
    }

    /**
     * Load an user existing case, this info is used in CaseResume
     * @param string  $sAppUid
     * @param integer $iDelIndex > 0 //get the Delegation fields
     * @return Fields
     */
    public function loadCase($sAppUid, $iDelIndex = 0, $jump = '')
    {
        try {
            $oApp = new Application;
            $fields = $oApp->Load($sAppUid);

            $appData = self::unserializeData($fields['APP_DATA']);

            $fields['APP_DATA'] = G::array_merges(G::getSystemConstants(), $appData);

            switch ($oApp->getAppStatus()) {
                case 'COMPLETED':
                    $fields['STATUS'] = G::LoadTranslation('ID_COMPLETED');
                    break;
                case 'CANCELLED':
                    $fields['STATUS'] = G::LoadTranslation('ID_CANCELLED');
                    break;
                case 'PAUSED':
                    $fields['STATUS'] = G::LoadTranslation('ID_PAUSED');
                    break;
                case 'DRAFT':
                    $fields['STATUS'] = G::LoadTranslation('ID_DRAFT');
                    break;
                case 'TO_DO':
                    $fields['STATUS'] = G::LoadTranslation('ID_TO_DO');
                    break;
            }
            $oUser = new Users();
            try {
                $oUser->load($oApp->getAppInitUser());
                $uFields = $oUser->toArray(BasePeer::TYPE_FIELDNAME);
                $fields['TITLE'] = $fields['APP_TITLE'];
                $fields['DESCRIPTION'] = $fields['APP_DESCRIPTION'];
                $fields['CREATOR'] = $oUser->getUsrFirstname() . ' ' . $oUser->getUsrLastname();
                $fields['CREATE_DATE'] = $oApp->getAppCreateDate();
                $fields['UPDATE_DATE'] = $oApp->getAppUpdateDate();
            } catch (Exception $oError) {
                $fields['TITLE'] = $oApp->getAppTitle();
                $fields['DESCRIPTION'] = '';
                $fields['CREATOR'] = G::LoadTranslation('ID_UNASSIGNED');
                $fields['CREATE_DATE'] = $oApp->getAppCreateDate();
                $fields['UPDATE_DATE'] = $oApp->getAppUpdateDate();
            }

            if ($iDelIndex > 0) {
                //get the Delegation fields,
                $oAppDel = new AppDelegation();
                $oAppDel->Load($sAppUid, $iDelIndex);
                $aAppDel = $oAppDel->toArray(BasePeer::TYPE_FIELDNAME);
                $fields['TAS_UID'] = $aAppDel['TAS_UID'];
                $fields['DEL_INDEX'] = $aAppDel['DEL_INDEX'];
                $fields['DEL_PREVIOUS'] = $aAppDel['DEL_PREVIOUS'];
                $fields['DEL_TYPE'] = $aAppDel['DEL_TYPE'];
                $fields['DEL_PRIORITY'] = $aAppDel['DEL_PRIORITY'];
                $fields['DEL_THREAD_STATUS'] = $aAppDel['DEL_THREAD_STATUS'];
                $fields['DEL_THREAD'] = $aAppDel['DEL_THREAD'];
                $fields['DEL_DELEGATE_DATE'] = $aAppDel['DEL_DELEGATE_DATE'];
                $fields['DEL_INIT_DATE'] = $aAppDel['DEL_INIT_DATE'];
                $fields['DEL_TASK_DUE_DATE'] = $aAppDel['DEL_TASK_DUE_DATE'];
                $fields['DEL_FINISH_DATE'] = $aAppDel['DEL_FINISH_DATE'];
                $fields['CURRENT_USER_UID'] = $aAppDel['USR_UID'];

                //Update the global variables
                $fields['TASK'] = $aAppDel['TAS_UID'];
                $fields['INDEX'] = $aAppDel['DEL_INDEX'];
                $fields['TAS_ID'] = $aAppDel['TAS_ID'];
                $fields['PRO_ID'] = $aAppDel['PRO_ID'];
                try {
                    $oCurUser = new Users();
                    if ($jump != '') {
                        $cases = $oAppDel->LoadParallel($sAppUid);
                        if (!empty($cases)) {
                            $fields['TAS_UID'] = '';
                        }
                        $fields['CURRENT_USER'] = array();
                        foreach ($cases as $key => $value) {
                            $oCurUser->load($value['USR_UID']);
                            $fields['CURRENT_USER'][] = $oCurUser->getUsrFirstname() . ' ' . $oCurUser->getUsrLastname();
                            $fields['TAS_UID'] .= (($fields['TAS_UID'] != '') ? '|' : '') . $value['TAS_UID'];
                        }
                        $fields['CURRENT_USER'] = implode(" - ", array_values($fields['CURRENT_USER']));
                        $tasksArray = array_filter(explode('|', $fields['TAS_UID']));

                        if (count($tasksArray) == 1) {
                            $fields['TAS_UID'] = $tasksArray[0];
                        }
                    } else {
                        $oCurUser->load($aAppDel['USR_UID']);
                        $fields['CURRENT_USER'] = $oCurUser->getUsrFirstname() . ' ' . $oCurUser->getUsrLastname();
                    }
                } catch (Exception $oError) {
                    $fields['CURRENT_USER'] = '';
                }
            }
            return $fields;
        } catch (Exception $e) {
            throw ($e);
        }
    }

    /**
     * LoadCaseByNumber
     * @param string $caseNumber
     * @return $aCases
     */
    public function loadCaseByNumber($sCaseNumber)
    {
        //('SELECT * FROM APP_DELEGATION WHERE APP_PROC_CODE="'.$sCaseNumber.'" ');
        try {
            $aCases = array();
            $c = new Criteria();
            $c->add(ApplicationPeer::APP_PROC_CODE, $sCaseNumber);
            $rs = ApplicationPeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            while (is_array($row)) {
                $case['APP_UID'] = $row['APP_UID'];
                $case['APP_NUMBER'] = $row['APP_NUMBER'];
                $case['APP_STATUS'] = $row['APP_STATUS'];
                $case['PRO_UID'] = $row['PRO_UID'];
                $case['APP_PARALLEL'] = $row['APP_PARALLEL'];
                $case['APP_CUR_USER'] = $row['APP_CUR_USER'];
                $aCases[] = $case;
                $rs->next();
                $row = $rs->getRow();
            }
            return $aCases;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * Update the thread title
     *
     * @param string $appUid
     * @param int $appNumber
     * @param int $delIndex
     * @param array $caseData
     *
     * @return array
     *
     * @see Cases::updateCase()
     * @see Derivation::derivate()
     */
    public function updateThreadTitle(string $appUid, int $appNumber, int $delIndex, $caseData = [])
    {
        $threadTitle = '';
        $threadDescription = '';
        if (!empty($appNumber) && !empty($delIndex)) {
            $thread = Delegation::getThreadInfo($appNumber, $delIndex);
            $tasUid = '';
            if (!empty($thread)) {
                $previous = $thread['DEL_PREVIOUS'];
                $appNumber = $thread['APP_NUMBER'];
                $tasUid = $thread['TAS_UID'];
            }
            if (!empty($tasUid)) {
                $response = Delegation::getThreadTitle($tasUid, $appNumber, $previous, $caseData);
                $threadTitle = $response['title'];
                $threadDescription = $response['description'];
            }
        }
        // Update thread title
        $rows = [];
        $rows['APP_UID'] = $appUid;
        $rows['DEL_INDEX'] = $delIndex;
        $rows['DEL_TITLE'] = $threadTitle;
        $delegation = new AppDelegation();
        $delegation->update($rows);

        return [
            'title' => $threadTitle,
            'description' => $threadDescription
        ];
    }

    /**
     * This function return an array without difference
     *
     *
     * @name arrayRecursiveDiff
     * @param  array $aArray1
     * @param  array $aArray2
     * @access public
     * @return $appLabel
     */
    public function arrayRecursiveDiff($aArray1, $aArray2)
    {
        $aReturn = array();
        foreach ($aArray1 as $mKey => $mValue) {
            if (is_array($aArray2) && array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) {
                        $aReturn[$mKey] = $aRecursiveDiff;
                    }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $aArray2[$mKey];
                    }
                }
            } else {
                $aReturn[$mKey] = isset($aArray2[$mKey]) ? $aArray2[$mKey] : null;
            }
        }
        return $aReturn;
    }

    public function array_key_intersect(&$a, &$b)
    {
        $array = array();
        foreach ($a as $key => $value) {
            if (isset($b[$key])) {
                if (is_object($b[$key]) && is_object($value)) {
                    if (serialize($b[$key]) === serialize($value)) {
                        $array[$key] = $value;
                    }
                } else {
                    if ($b[$key] === $value) {
                        $array[$key] = $value;
                    }
                }
            }
        }
        return $array;
    }

    /**
     * Update an existing case, this info is used in CaseResume
     *
     * @name updateCase
     *
     * @param string $appUid
     * @param array $Fields
     *
     * @return Fields
     * @throws Exception
     *
     * @see Cases->cancelCase()
     * @see Cases->executeTriggerFromList()
     * @see Cases->executeTriggersAfterExternal()
     * @see Cases->getExecuteTriggerProcess()
     * @see Cases->unCancelCase()
     * @see Cases->cancelCase()
     * @see executeCaseSelfService()/cron_single.php
     * @see handleErrors()/class.pmFunctions.php
     * @see handleFatalErrors()/class.pmFunctions.php
     * @see PMFRedirectToStep()/class.pmFunctions.php
     * @see setCaseTrackerCode()/class.pmFunctions.php
     * @see Derivation::derivate()
     * @see Derivation::verifyIsCaseChild()
     * @see WsBase::executeTrigger()
     * @see WsBase::executeTriggerFromDerivate()
     * @see WsBase::newCase()
     * @see WsBase::newCaseImpersonate()
     * @see WsBase::sendVariables()
     * @see AdditionalTables->saveDataInTable()
     * @see AppEvent->executeEvents()
     * @see cases_Derivate.php
     * @see cases_SaveData.php
     * @see cases_SaveDataSupervisor.php
     * @see cases_SaveDocument.php
     * @see cases_Step.php
     * @see cases_SupervisorSaveDocument.php
     * @see saveForm.php
     * @see ActionByEmail.php
     * @see ActionByEmailDataFormPost.php
     * @see cases_StartExternal.php
     * @see upload.php
     * @see \ProcessMaker\BusinessModel\Cases::deleteMultipleFile()
     * @see \ProcessMaker\BusinessModel\Cases::putExecuteTriggers()
     * @see \ProcessMaker\BusinessModel\Cases::setCaseVariables()
     * @see \ProcessMaker\BusinessModel\Consolidated::consolidatedUpdate()
     * @see \ProcessMaker\BusinessModel\Consolidated::postDerivate()
     * @see \ProcessMaker\BusinessModel\Light::doExecuteTriggerCase()
     * @see \ProcessMaker\BusinessModel\Light::getPrepareInformation()
     * @see \ProcessMaker\BusinessModel\Light::startCase()
     * @see \ProcessMaker\BusinessModel\MessageApplication::catchMessageEvent()
     * @see \ProcessMaker\BusinessModel\ScriptTask::execScriptByActivityUid()
     * @see \ProcessMaker\BusinessModel\Cases\InputDocument::addCasesInputDocument()
     * @see \ProcessMaker\BusinessModel\Cases\InputDocument::uploadFileCase()
     * @see \ProcessMaker\BusinessModel\Cases\Variable::create()
     * @see \ProcessMaker\BusinessModel\Cases\Variable::delete()
     * @see \ProcessMaker\BusinessModel\Cases\Variable::update()
     */
    public function updateCase($appUid, $Fields = [])
    {
        try {
            $application = new Application;
            if (!$application->exists($appUid)) {
                return false;
            }
            $appData = $Fields['APP_DATA'];
            $Fields['APP_UID'] = $appUid;
            $Fields['APP_UPDATE_DATE'] = 'now';
            $Fields['APP_DATA'] = serialize($Fields['APP_DATA']);

            $app = ApplicationPeer::retrieveByPk($appUid);
            $appFields = $app->toArray(BasePeer::TYPE_FIELDNAME);
            if (isset($Fields['APP_TITLE'])) {
                $appFields['APP_TITLE'] = $Fields['APP_TITLE'];
            }
            if (isset($Fields['APP_DESCRIPTION'])) {
                $appFields['APP_DESCRIPTION'] = $Fields['APP_DESCRIPTION'];
            }
            if (isset($Fields['DEL_INDEX'])) {
                $appFields['DEL_INDEX'] = $Fields['DEL_INDEX'];
            }

            // Update case title
            if (!empty($appUid) && !empty($appFields['APP_NUMBER']) && $appFields['APP_NUMBER'] > 0 && !empty($appFields['DEL_INDEX'])) {
                $threadInfo = $this->updateThreadTitle($appUid, $appFields['APP_NUMBER'], $appFields['DEL_INDEX'], $appData);
                $Fields['APP_TITLE'] = $threadInfo['title'];
                $Fields['APP_DESCRIPTION'] = $threadInfo['description'];
            }

            // Start: Save History --By JHL
            if (isset($Fields['CURRENT_DYNAFORM'])) {
                //only when that variable is set.. from Save
                $FieldsBefore = $this->loadCase($appUid);
                $FieldsDifference = $this->arrayRecursiveDiff($FieldsBefore['APP_DATA'], $appData);
                $fieldsOnBoth = $this->array_key_intersect($FieldsBefore['APP_DATA'], $appData);
                //Add fields that weren't in previous version
                foreach ($appData as $key => $value) {
                    if (is_array($value) && isset($fieldsOnBoth[$key]) && is_array($fieldsOnBoth[$key])) {
                        $afieldDifference = $this->arrayRecursiveDiff($value, $fieldsOnBoth[$key]);
                        $dfieldDifference = $this->arrayRecursiveDiff($fieldsOnBoth[$key], $value);
                        if ($afieldDifference || $dfieldDifference) {
                            $FieldsDifference[$key] = $value;
                        }
                    } else {
                        if (!(isset($fieldsOnBoth[$key]))) {
                            $FieldsDifference[$key] = $value;
                        }
                    }
                }
                if ((is_array($FieldsDifference)) && (count($FieldsDifference) > 0)) {
                    $dynaformInstance = new Dynaform();
                    try {
                        $currentDynaform = $dynaformInstance->Load($Fields['CURRENT_DYNAFORM']);
                    } catch (Exception $e) {
                        $currentDynaform["DYN_CONTENT"] = "";
                    }
                    // There are changes
                    $Fields['APP_STATUS'] = (isset($Fields['APP_STATUS'])) ? $Fields['APP_STATUS'] : $FieldsBefore['APP_STATUS'];
                    $appHistory = new AppHistory();
                    $aFieldsHistory = $Fields;
                    $appDataWithoutDynContentHistory = serialize($FieldsDifference);
                    $aFieldsHistory['APP_DATA'] = serialize($FieldsDifference);
                    $appHistory->insertHistory($aFieldsHistory);

                }
            }
            // End Save History
            if (isset($Fields["APP_STATUS"]) && $Fields["APP_STATUS"] == "COMPLETED") {
                if (isset($Fields['CURRENT_USER_UID'])) {
                    $Fields['USR_UID'] = $Fields['CURRENT_USER_UID'];
                }
                // Will be update the status in the list Participated
                $listParticipatedLast = new ListParticipatedLast();
                $listParticipatedLast->refreshStatus($Fields['APP_UID'], 'COMPLETED');
            }

            /** Update case */
            $app->update($Fields);

            // Update the reportTables and tables related to the case, only for applications with positive application number
            if ($appFields['APP_NUMBER'] > 0) {
                require_once 'classes/model/AdditionalTables.php';
                $reportTables = new ReportTables();
                $additionalTables = new additionalTables();

                if (!isset($Fields['APP_NUMBER'])) {
                    $Fields['APP_NUMBER'] = $appFields['APP_NUMBER'];
                }
                if (!isset($Fields['APP_STATUS'])) {
                    $Fields['APP_STATUS'] = $appFields['APP_STATUS'];
                }

                $reportTables->updateTables($appFields['PRO_UID'], $appUid, $Fields['APP_NUMBER'], $appData);
                $additionalTables->updateReportTables(
                    $appFields['PRO_UID'], $appUid, $Fields['APP_NUMBER'], $appData, $Fields['APP_STATUS']
                );
            }

            // Update the priority related to the task
            $delIndex = isset($Fields['DEL_INDEX']) ? trim($Fields['DEL_INDEX']) : '';
            $tasUid = isset($Fields['TAS_UID']) ? trim($Fields['TAS_UID']) : '';
            $appDel = new AppDelegation;
            $appDel->updatePriority($delIndex, $tasUid, $appUid, $appData);

            // Update Solr Index
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($appUid);
            }

            if (isset($Fields["APP_STATUS"]) && $Fields["APP_STATUS"] == "COMPLETED") {
                // Delete records of the table APP_ASSIGN_SELF_SERVICE_VALUE
                $appAssignSelfServiceValue = new AppAssignSelfServiceValue();
                $appAssignSelfServiceValue->remove($appUid);
            }


            return $Fields;
        } catch (Exception $e) {
            throw ($e);
        }
    }

    /**
     * Remove an existing case,
     *
     * @name removeCase
     * @param string  $sAppUid
     * @return Fields
     */
    public function removeCase($sAppUid, $deleteDelegation = true)
    {
        try {
            $appUidCopy = $sAppUid;
            $this->getExecuteTriggerProcess($sAppUid, 'DELETED');

            $oAppDocument = new AppDocument();

            if ($deleteDelegation) {
                //Delete the delegations of a application
                $this->deleteDelegation($sAppUid);
            }
            //Delete the documents assigned to a application
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDocumentPeer::APP_UID, $sAppUid);
            $oDataset2 = AppDocumentPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            while ($aRow2 = $oDataset2->getRow()) {
                $oAppDocument->remove($aRow2['APP_DOC_UID'], $aRow2['DOC_VERSION']);
                $oDataset2->next();
            }
            //Delete the actions from a application
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelayPeer::APP_UID, $sAppUid);
            AppDelayPeer::doDelete($oCriteria2);
            //Delete the messages from a application
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppMessagePeer::APP_UID, $sAppUid);
            AppMessagePeer::doDelete($oCriteria2);
            //Delete the threads from a application
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppThreadPeer::APP_UID, $sAppUid);
            AppThreadPeer::doDelete($oCriteria2);
            //Delete the events from a application
            $criteria = new Criteria("workflow");
            $criteria->add(AppEventPeer::APP_UID, $sAppUid);
            AppEventPeer::doDelete($criteria);
            //Delete the histories from a application
            $criteria = new Criteria("workflow");
            $criteria->add(AppHistoryPeer::APP_UID, $sAppUid);
            AppHistoryPeer::doDelete($criteria);
            //Delete the notes from a application
            $criteria = new Criteria("workflow");
            $criteria->add(AppNotesPeer::APP_UID, $sAppUid);
            AppNotesPeer::doDelete($criteria);
            //Delete the owners from a application
            $criteria = new Criteria("workflow");
            $criteria->add(AppOwnerPeer::APP_UID, $sAppUid);
            AppOwnerPeer::doDelete($criteria);
            //Delete the SolrQueue from a application
            $criteria = new Criteria("workflow");
            $criteria->add(AppSolrQueuePeer::APP_UID, $sAppUid);
            AppSolrQueuePeer::doDelete($criteria);

            try {
                //Before delete verify if is a child case
                $oCriteria2 = new Criteria('workflow');
                $oCriteria2->add(SubApplicationPeer::APP_UID, $sAppUid);
                $oCriteria2->add(SubApplicationPeer::SA_STATUS, 'ACTIVE');

                if (SubApplicationPeer::doCount($oCriteria2) > 0) {
                    $oDerivation = new Derivation();
                    $oDerivation->verifyIsCaseChild($sAppUid);
                }
            } catch (Exception $e) {
                $message = 'Error in sub-process when trying to route a child case related to the case';
                $context = [
                    'application_uid' => $sAppUid,
                    'error' => $e->getMessage()
                ];
                Log::channel(':DeleteCases')->info($message, Bootstrap::context($context));
            }

            //Delete the registries in the table SUB_APPLICATION
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(SubApplicationPeer::APP_UID, $sAppUid);
            SubApplicationPeer::doDelete($oCriteria2);
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(SubApplicationPeer::APP_PARENT, $sAppUid);
            SubApplicationPeer::doDelete($oCriteria2);

            //Delete records of the table APP_ASSIGN_SELF_SERVICE_VALUE
            $appAssignSelfServiceValue = new AppAssignSelfServiceValue();

            $appAssignSelfServiceValue->remove($sAppUid);

            //Delete records of the Report Table
            $this->reportTableDeleteRecord($sAppUid);

            //Delete record of the APPLICATION table (trigger: delete records of the APP_CACHE_VIEW table)
            $application = new Application();
            $result = $application->remove($sAppUid);
            //delete application from index
            if ($this->appSolr != null) {
                $this->appSolr->deleteApplicationSearchIndex($sAppUid);
            }
            //Logger deleteCase
            $nameFiles = '';
            foreach (debug_backtrace() as $node) {
                if (isset($node['file']) && isset($node['function']) && isset($node['line'])) {
                    $nameFiles .= $node['file'] . ":" . $node['function'] . "(" . $node['line'] . ")\n";
                }
            }

            /** ProcessMaker log */
            $message = 'Delete Case';
            $context = [
                'appUid' => $appUidCopy,
                'request' => $nameFiles
            ];
            Log::channel(':DeleteCases')->info($message, Bootstrap::context($context));
            return $result;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * Set the DEL_INIT_DATE
     *
     * @name setDelInitDate
     * @param string $sAppUid
     * @param string $iDelIndex
     * @return Fields
     */
    public function setDelInitDate($sAppUid, $iDelIndex)
    {
        try {
            $oAppDel = AppDelegationPeer::retrieveByPk($sAppUid, $iDelIndex);
            $oAppDel->setDelInitDate("now");
            $oAppDel->save();
            //update searchindex
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($sAppUid);
            }
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * Set user who is claim (ex-catch) a self service routing
     *
     * @name setCatchUser
     * @param string $sAppUid
     * @param string $iDelIndex
     * @param string $usrId
     * @return Fields
     */
    public function setCatchUser($sAppUid, $iDelIndex, $usrId)
    {
        try {
            $user = UsersPeer::retrieveByPk($usrId);

            $oAppDel = AppDelegationPeer::retrieveByPk($sAppUid, $iDelIndex);
            $oAppDel->setDelInitDate("now");
            $oAppDel->setUsrUid($usrId);
            $oAppDel->setUsrId($user->getUsrId());
            $oAppDel->save();

            //update searchindex
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($sAppUid);
            }

            //Delete record of the table APP_ASSIGN_SELF_SERVICE_VALUE
            $appAssignSelfServiceValue = new AppAssignSelfServiceValue();

            $appAssignSelfServiceValue->remove($sAppUid, $iDelIndex);
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * Determines if the all threads of a multiinstance task are closed
     *
     * @$appUid string appUid of the instance to be tested
     * @$tasUid string task uid of the multiinstance task
     * @$previousDelIndex int previous del index of the instance corresponding to the multiinstance task
     */
    public function multiInstanceIsCompleted($appUid, $tasUid, $previousDelIndex)
    {
        $result = false;
        try {
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->add(AppDelegationPeer::APP_UID, $appUid);
            $c->add(AppDelegationPeer::TAS_UID, $tasUid);
            $c->add(AppDelegationPeer::DEL_PREVIOUS, $previousDelIndex);
            $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
            $rs = AppDelegationPeer::doSelectRs($c);

            if ($rs->next()) {
                $result = false;
            } else {
                $result = true;
            }
        } catch (exception $e) {
            throw ($e);
        } finally {
            return $result;
        }
    }

    /**
     * GetOpenThreads
     *
     * @name GetOpenThreads
     * @param string $sAppUid
     * @return $row (number of APP_DELEGATION rows)
     */
    public function GetOpenThreads($sAppUid)
    {
        try {
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->addSelectColumn('COUNT(*)');
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
            $rs = AppDelegationPeer::doSelectRs($c);
            $rs->next();
            $row = $rs->getRow();
            return intval($row[0]);
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * this function returns SiblingThreads in a task
     *
     * @name getSiblingThreads
     * @param string $sAppUid
     * @param string $iDelIndex
     * @return $aThreads
     */
    public function getSiblingThreads($sAppUid, $iDelIndex)
    {
        try {
            //get the parent thread
            $c = new Criteria();
            $c->add(AppThreadPeer::APP_UID, $sAppUid);
            $c->add(AppThreadPeer::DEL_INDEX, $iDelIndex);
            $rs = AppThreadPeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            $iParent = $row['APP_THREAD_PARENT'];

            //get the sibling
            $aThreads = array();
            $c = new Criteria();
            $c->add(AppThreadPeer::APP_UID, $sAppUid);
            $c->add(AppThreadPeer::APP_THREAD_PARENT, $iParent);
            $c->add(AppThreadPeer::DEL_INDEX, $iDelIndex, Criteria::NOT_EQUAL);
            $rs = AppThreadPeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            while (is_array($row)) {
                $aThreads[] = $row;
                $rs->next();
                $row = $rs->getRow();
            }
            return $aThreads;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function returns the threads open in a task
     * get an array with all sibling threads open from next task
     *
     * @param string $nextTaskUid
     * @param string $applicationUid
     * @param string $delIndex
     * @param string $currentTaskUid
     *
     * return array Return $arrayThread
     */
    public function getOpenSiblingThreads($nextTaskUid, $applicationUid, $delIndex, $currentTaskUid)
    {
        try {
            //Get all tasks that are previous to my NextTask, we want to know if there are pending task for my nexttask
            //we need to filter only seq joins going to my next task
            //and we are removing the current task from the search
            $arrayThread = array();

            $criteria = new Criteria("workflow");

            $criteria->add(RoutePeer::TAS_UID, $currentTaskUid, Criteria::NOT_EQUAL);
            $criteria->add(RoutePeer::ROU_NEXT_TASK, $nextTaskUid, Criteria::EQUAL);
            $criteria->add(RoutePeer::ROU_TYPE, "SEC-JOIN", Criteria::EQUAL);

            $rsCriteria = RoutePeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

            while ($rsCriteria->next()) {
                $row = $rsCriteria->getRow();

                $arrayPrevious = $this->searchOpenPreviousTasks($row["TAS_UID"], $applicationUid);

                if (is_array($arrayPrevious) && !empty($arrayPrevious)) {
                    $arrayThread = array_merge($arrayThread, $arrayPrevious);
                }
            }

            //Return
            return $arrayThread;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * This function looks for the open previous task
     * get an array with all sibling previous threads open from next task
     *
     * @name searchOpenPreviousTasks,
     * @param string $taskUid
     * @param string $appUid
     * @param array $previousTasks, optional array that serves to trace the task routes and avoid infinite loops.
     * @return array, information about the threads in the case
     */
    public function searchOpenPreviousTasks($taskUid, $appUid, &$previousTasks = array())
    {
        //In this array we are storing all open delegation rows.
        $threads = array();

        //Check if this $taskUid has open delegations, this is a single review
        $threads = $this->getReviewedTasks($taskUid, $appUid);

        if ($threads !== false) {
            if (count($threads['open']) > 0) {
                //There is an open delegation, so we need to return the delegation row
                return $threads['open'];
            } else {
                if (count($threads['paused']) > 0) {
                    //there is an paused delegation, so we need to return the delegation row
                    return $threads['paused'];
                }
            }
        }
        //Search the open delegations in the previous task, this is a recursive review
        $threads = $this->getReviewedTasksRecursive($taskUid, $appUid, $previousTasks);
        return $threads;
    }

    /**
     * This function get the last open task
     * Usually is used when we have a SEC-JOIN and need to review if we need to route the case
     * @param string $taskUid
     * @param string $appUid
     * @param array $previousTasks
     * @return array $taskReviewed
     */
    public function getReviewedTasksRecursive($taskUid, $appUid, &$previousTasks)
    {
        $taskReviewed = array();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(RoutePeer::ROU_NEXT_TASK, $taskUid);
        $oDataset = RoutePeer::doSelectRs($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        while ($oDataset->next()) {
            $aRow = $oDataset->getRow();
            $delegations = $this->getReviewedTasks($aRow['TAS_UID'], $appUid);

            if ($delegations !== false) {
                if (count($delegations['open']) > 0) {
                    //there is an open delegation, so we need to return the delegation row
                    $taskReviewed = array_merge($taskReviewed, $delegations['open']);
                } elseif ($aRow['ROU_TYPE'] == 'PARALLEL-BY-EVALUATION') {
                    $taskReviewed = array();
                }
            } elseif (!in_array($aRow['TAS_UID'], $previousTasks)) {
                //Storing the current task uid of the task currently checked
                $previousTasks[] = $aRow['TAS_UID'];
                //Passing the array of previous tasks in order to avoid an infinite loop that prevents
                $openPreviousTask = $this->searchOpenPreviousTasks($aRow['TAS_UID'], $appUid, $previousTasks);
                if (count($previousTasks) > 0) {
                    $taskReviewed = array_merge($taskReviewed, $openPreviousTask);
                }
            }
        }

        return $taskReviewed;
    }

    /**
     * Get reviewed tasks (delegations started)
     * @param string $taskUid
     * @param string $sAppUid
     * @return array within the open & closed tasks
     *         false -> when has not any delegation started for that task
     */
    public function getReviewedTasks($taskUid, $sAppUid)
    {
        $openTasks = $closedTasks = $pausedTasks = array();

        // get all delegations fro this task
        $oCriteria2 = new Criteria('workflow');
        $oCriteria2->add(AppDelegationPeer::APP_UID, $sAppUid);
        $oCriteria2->add(AppDelegationPeer::TAS_UID, $taskUid);

        $oDataset2 = AppDelegationPeer::doSelectRs($oCriteria2);
        $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        // loop and separate open & closed delegations in theirs respective arrays
        while ($oDataset2->next()) {
            $row = $oDataset2->getRow();
            if ($row['DEL_THREAD_STATUS'] == 'OPEN') {
                $openTasks[] = $row;
            } else {
                //If exist paused cases
                $closedTasks[] = $row;
                $aIndex[] = $row['DEL_INDEX'];
                $res = $this->GetAllOpenDelegation(array('APP_UID' => $sAppUid, 'APP_THREAD_PARENT' => $row['DEL_PREVIOUS']), 'NONE');
                foreach ($res as $in) {
                    $aIndex[] = $in['DEL_INDEX'];
                }
                $pausedTasks = $this->getReviewedTasksPaused($sAppUid, $aIndex);
            }
        }

        if (count($openTasks) === 0 && count($closedTasks) === 0 && count($pausedTasks) === 0) {
            return false; // return false because there is not any delegation for this task.
        } else {
            return array('open' => $openTasks, 'closed' => $closedTasks, 'paused' => $pausedTasks);
        }
    }

    /**
     * Get reviewed tasks is Paused (delegations started)
     * @param string $sAppUid
     * @param array $aDelIndex
     * @return array within the paused tasks
     *         false -> when has not any delegation started for that task
     */
    public function getReviewedTasksPaused($sAppUid, $aDelIndex)
    {
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDelayPeer::APP_UID, $sAppUid);
        $oCriteria->add(AppDelayPeer::APP_DEL_INDEX, $aDelIndex, Criteria::IN);
        $oCriteria->add(AppDelayPeer::APP_TYPE, 'PAUSE');
        $oCriteria->add(
                $oCriteria->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0, Criteria::EQUAL)->addOr(
                        $oCriteria->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL))
        );

        $oDataset = AppDelayPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        $pausedTask = array();
        // loop and separate open & closed delegations in theirs respective arrays
        while ($oDataset->next()) {
            $row = $oDataset->getRow();
            $pausedTask[] = $row;
        }

        if (count($pausedTask) === 0) {
            return array(); // return false because there is not any delegation for this task.
        } else {
            return array('pause' => $pausedTask);
        }
    }

    /**
     * This function returns the total number of previous task
     *
     * @name CountTotalPreviousTasks
     * @param string $sTasUid $nextDel['TAS_UID']
     * @return $row[0]
     */
    public function CountTotalPreviousTasks($sTasUid)
    {
        try {
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->addSelectColumn('COUNT(*)');
            $c->add(RoutePeer::ROU_NEXT_TASK, $sTasUid);
            $rs = RoutePeer::doSelectRs($c);
            $rs->next();
            $row = $rs->getRow();
            return intval($row[0]);
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function gets open and null  rows in delegation
     *
     * @name getOpenNullDelegations
     * @param string $sAppUid $nextDel['TAS_UID']
     * @param string $sTasUid
     * @return $pendingDel
     */
    public function getOpenNullDelegations($sAppUid, $sTasUid)
    {
        $pendingDel = array();
        try {
            //first query
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->addSelectColumn(AppDelegationPeer::APP_UID);
            $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
            $c->addSelectColumn(AppDelegationPeer::DEL_PREVIOUS);
            $c->addSelectColumn(AppDelegationPeer::PRO_UID);
            $c->addSelectColumn(AppDelegationPeer::TAS_UID);
            $c->addSelectColumn(AppDelegationPeer::USR_UID);
            $c->addSelectColumn(AppDelegationPeer::DEL_TYPE);
            $c->addSelectColumn(AppDelegationPeer::DEL_PRIORITY);
            $c->addSelectColumn(AppDelegationPeer::DEL_THREAD);
            $c->addSelectColumn(AppDelegationPeer::DEL_THREAD_STATUS);
            $c->addSelectColumn(AppDelegationPeer::DEL_DELEGATE_DATE);
            $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
            $c->addSelectColumn(AppDelegationPeer::DEL_TASK_DUE_DATE);
            $c->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
            $c->addSelectColumn(RoutePeer::ROU_UID);
            $c->addSelectColumn(RoutePeer::ROU_PARENT);
            $c->addSelectColumn(RoutePeer::ROU_NEXT_TASK);
            $c->addSelectColumn(RoutePeer::ROU_CASE);
            $c->addSelectColumn(RoutePeer::ROU_TYPE);
            $c->addSelectColumn(RoutePeer::ROU_CONDITION);
            $c->addSelectColumn(RoutePeer::ROU_TO_LAST_USER);
            $c->addSelectColumn(RoutePeer::ROU_OPTIONAL);
            $c->addSelectColumn(RoutePeer::ROU_SEND_EMAIL);

            $c->addJoin(AppDelegationPeer::TAS_UID, RoutePeer::TAS_UID);
            $c->add(RoutePeer::ROU_NEXT_TASK, $sTasUid);
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $rs = RoutePeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            while (is_array($row)) {
                if ($row['DEL_THREAD_STATUS'] == 'OPEN' && $row['APP_UID'] = $sAppUid) {
                    $pendingDel[] = $row;
                }
                $rs->next();
                $row = $rs->getRow();
            }
            return $pendingDel;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function looks for some open rows in delegation
     *
     * @name isRouteOpen
     * @param string $sAppUid $nextDel['APP_UID']
     * @param string $sTasUid $nextDel['TAS_UID']
     * @return true or false
     */
    public function isRouteOpen($sAppUid, $sTasUid)
    {
        try {
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->addSelectColumn('COUNT(*)');
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $c->add(AppDelegationPeer::TAS_UID, $sTasUid);
            $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
            $rs = RoutePeer::doSelectRs($c);
            $rs->next();
            $row = $rs->getRow();
            $open = ($row[0] >= 1);
            if ($open) {
                return true;
            }
            $c->clearSelectColumns();
            $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
            $c->addSelectColumn(AppDelegationPeer::USR_UID);
            $c->addSelectColumn(AppDelegationPeer::DEL_TYPE);
            $c->addSelectColumn(AppDelegationPeer::DEL_THREAD);
            $c->addSelectColumn(AppDelegationPeer::DEL_THREAD_STATUS);
            $c->addSelectColumn(RoutePeer::ROU_UID);
            $c->addSelectColumn(RoutePeer::ROU_NEXT_TASK);
            $c->addSelectColumn(RoutePeer::ROU_CASE);
            $c->addSelectColumn(RoutePeer::ROU_TYPE);

            $c->addJoin(AppDelegationPeer::TAS_UID, RoutePeer::TAS_UID);
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $c->add(RoutePeer::ROU_NEXT_TASK, $sTasUid);
            $rs = RoutePeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            $sql = 'SELECT D.*,R.* ' .
                    'FROM ROUTE R LEFT JOIN APP_DELEGATION D ON (R.TAS_UID=D.TAS_UID) WHERE APP_UID="' .
                    $sAppUid . '" AND ROU_NEXT_TASK="' . $sTasUid . '"';

            while (is_array($row)) {
                switch ($row['DEL_THREAD_STATUS']) {
                    case 'OPEN':
                        //case 'NONE':
                        $open = true;
                        break;
                    case 'CLOSED':
                        //case 'DONE':
                        //case 'NOTDONE':
                        break;
                    case '':
                    case null:
                    default:
                        $open = $this->isRouteOpen($sAppUid, $row['TAS_UID']);
                        break;
                }
                if ($open) {
                    return true;
                }
                $rs->next();
                $row = $rs->getRow();
            }
            return false;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function creates a new row into APP_DELEGATION
     *
     * @name newAppDelegation
     * @param string $proUid
     * @param string $appUid
     * @param string $tasUid
     * @param string $usrUid
     * @param string $previous
     * @param string $priority
     * @param int $threadIndex
     * @param string $nextDel
     * @param boolean $flagControl
     * @param boolean $flagControlMulInstance
     * @param int $delPrevious
     * @param int $appNumber
     * @param int $proId
     * @param int $tasId
     * @param array $caseData
     *
     * @see Cases::reassignCase()
     * @see Cases::startCase()
     * @see Cases::unpauseCase()
     * @see Cases::unCancelCase()
     * @see Derivation::doDerivation()
     * @see Derivation::doDerivationStaticMi()
     *
     * @return int
     * @throw Exception
     */
    public function newAppDelegation(
        $proUid,
        $appUid,
        $tasUid,
        $usrUid,
        $previous,
        $priority,
        $threadIndex = 1,
        $nextDel = null,
        $flagControl = false,
        $flagControlMulInstance = false,
        $delPrevious = 0,
        $appNumber = 0,
        $proId = 0,
        $tasId = 0,
        $caseData = []
    ){
        try {
            // Get case title
            $threadTitle = $this->getCaseTitle();
            if (empty($threadTitle)) {
                $response = Delegation::getThreadTitle($tasUid, $appNumber, $previous, $caseData);
                $threadTitle = $response['title'];
            }

            $user = UsersPeer::retrieveByPK($usrUid);
            // Create new delegation
            $delegation = new AppDelegation();
            if ($appNumber > 0) {
                $delegation->setDelTitle($threadTitle);
            } else {
                $delegation->setDelTitle("");
            }
            $result = $delegation->createAppDelegation(
                $proUid,
                $appUid,
                $tasUid,
                $usrUid,
                $threadIndex,
                $priority,
                false,
                $previous,
                $nextDel,
                $flagControl,
                $flagControlMulInstance,
                $delPrevious,
                $appNumber,
                $tasId,
                (empty($user)) ? 0 : $user->getUsrId(),
                $proId
            );
            // Update search index
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($appUid);
            }
            return $result;
        } catch (Exception $e) {
            throw ($e);
        }
    }

    /**
     * this function is used to update a row in APP_DELEGATION
     *
     *
     * @name updateAppDelegation
     * @param string $sAppUid,
     * @param string $iDelIndex
     * @param string $iAppThreadIndex,
     * @return true
     */
    public function updateAppDelegation($sAppUid, $iDelIndex, $iAppThreadIndex)
    {
        try {
            $appDelegation = new AppDelegation();
            $aData = array();
            $aData['APP_UID'] = $sAppUid;
            $aData['DEL_INDEX'] = $iDelIndex;
            $aData['DEL_THREAD'] = $iAppThreadIndex;
            $appDelegation->update($aData);
            //update searchindex
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($sAppUid);
            }
            return true;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function gets all rows in APP_DELEGATION
     *
     * @name GetAllDelegations
     * @param string $sAppUid
     * @return $aDelegations
     */
    public function GetAllDelegations($sAppUid)
    {
        //('SELECT * FROM APP_DELEGATION WHERE APP_UID="'.$currentDelegation['APP_UID'].'" ');
        try {
            $aDelegations = array();
            $c = new Criteria();
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $rs = AppDelegationPeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            while (is_array($row)) {
                $aDelegations[] = $row;
                $rs->next();
                $row = $rs->getRow();
            }
            return $aDelegations;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * We're getting all threads in a task
     *
     * @name GetAllDelegations
     * @param string $sAppUid
     * @return $aThreads
     */
    public function GetAllThreads($sAppUid)
    {
        //('SELECT * FROM APP_DELEGATION WHERE APP_UID="'.$currentDelegation['APP_UID'].'" ');
        try {
            $aThreads = array();
            $c = new Criteria();
            $c->add(AppThreadPeer::APP_UID, $sAppUid);
            $rs = AppThreadPeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            while (is_array($row)) {
                $aThreads[] = $row;
                $rs->next();
                $row = $rs->getRow();
            }
            return $aThreads;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * With this function we can do update in AppThread
     *
     * @name updateAppThread
     * @param string $sAppUid,
     * @param string $iAppThreadIndex,
     * @param string $iNewDelIndex
     * @return $iNewDelIndex;
     */
    public function updateAppThread($sAppUid, $iAppThreadIndex, $iNewDelIndex)
    {
        try {
            /// updating the DEL_INDEX value in the APP_THREAD
            $con = Propel::getConnection('workflow');
            $c1 = new Criteria('workflow');
            $c1->add(AppThreadPeer::APP_UID, $sAppUid);
            $c1->add(AppThreadPeer::APP_THREAD_INDEX, $iAppThreadIndex);

            // update set
            $c2 = new Criteria('workflow');
            $c2->add(AppThreadPeer::DEL_INDEX, $iNewDelIndex);
            BasePeer::doUpdate($c1, $c2, $con);
            //update searchindex
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($sAppUid);
            }
            return $iNewDelIndex;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function changes the status to CLOSED in appThread
     *
     * @name closeAppThread
     * @param string $sAppUid,
     * @param string $iAppThreadIndex,
     * @return true
     */
    public function closeAppThread($sAppUid, $iAppThreadIndex)
    {
        try {
            $appThread = new AppThread();
            $aData = array();
            $aData['APP_UID'] = $sAppUid;
            $aData['APP_THREAD_INDEX'] = $iAppThreadIndex;
            $aData['APP_THREAD_STATUS'] = 'CLOSED';

            $appThread->update($aData);
            return true;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function updates a row in APP_DELEGATION
     *
     * @name closeAllDelegations
     * @param string $appUid
     *
     * @return void
     * @throws Exception
     */
    public function closeAllThreads($appUid)
    {
        try {
            $c = new Criteria();
            $c->add(AppThreadPeer::APP_UID, $appUid);
            $c->add(AppThreadPeer::APP_THREAD_STATUS, 'OPEN');
            $rowObj = AppThreadPeer::doSelect($c);
            foreach ($rowObj as $appThread) {
                $appThread->setAppThreadStatus('CLOSED');
                if ($appThread->Validate()) {
                    $appThread->Save();
                } else {
                    $msg = '';
                    foreach ($this->getValidationFailures() as $objValidationFailure) {
                        $msg .= $objValidationFailure->getMessage() . "<br/>";
                    }
                    throw (new PropelException('The row cannot be created!', new PropelException($msg)));
                }
            }

            /** Update search index */
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($appUid);
            }
        } catch (Exception $e) {
            throw ($e);
        }
    }

    /**
     * This function creates a new row in appThread
     *
     * @name newAppThread
     * @param string $sAppUid,
     * @param string $iNewDelIndex
     * @param string $iAppParent
     * @param string $appNumber
     * @return $iAppThreadIndex $iNewDelIndex, $iAppThreadIndex );
     */
    public function newAppThread($sAppUid, $iNewDelIndex, $iAppParent)
    {
        try {
            $appThread = new AppThread();
            $result = $appThread->createAppThread($sAppUid, $iNewDelIndex, $iAppParent);
            //update searchindex
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($sAppUid);
            }
            return $result;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * With this function we can change status to CLOSED in APP_DELEGATION
     *
     * @param string $appUid
     *
     * @return void
     * @throws Exception
     */
    public function closeAllDelegations($appUid)
    {
        try {
            $criteria = new Criteria();
            $criteria->add(AppDelegationPeer::APP_UID, $appUid);
            $criteria->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
            $rowObj = AppDelegationPeer::doSelect($criteria);
            $data = [];
            foreach ($rowObj as $appDel) {
                $appDel->setDelThreadStatus('CLOSED');
                $appDel->setDelThreadStatusId(Delegation::$thread_status['CLOSED']);
                $appDel->setDelFinishDate('now');
                if ($appDel->Validate()) {
                    $appDel->Save();
                } else {
                    $msg = '';
                    foreach ($this->getValidationFailures() as $objValidationFailure) {
                        $msg .= $objValidationFailure->getMessage() . "<br/>";
                    }
                    throw (new PropelException('The row cannot be created!', new PropelException($msg)));
                }

            }

            /** Update search index */
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($appUid);
            }
        } catch (Exception $e) {
            throw ($e);
        }
    }

    /**
     * With this we can change the status to CLOSED in APP_DELEGATION
     * We close a thread in the action: paused reassign cancel
     *
     * @param string $appUid
     * @param string $delIndex
     * @param string $status
     * @param int $statusId
     *
     * @return void
     * @throws Exception
     */
    public function CloseCurrentDelegation($appUid, $delIndex, string $status = 'CLOSED', int $statusId = 0)
    {
        try {
            $criteria = new Criteria();
            $criteria->add(AppDelegationPeer::APP_UID, $appUid);
            $criteria->add(AppDelegationPeer::DEL_INDEX, $delIndex);
            $rowObj = AppDelegationPeer::doSelect($criteria);
            $user = '';
            foreach ($rowObj as $appDel) {
                $appDel->setDelThreadStatus($status);
                $appDel->setDelThreadStatusId($statusId);
                $appDel->setDelFinishDate('now');
                $user = $appDel->getUsrUid();
                if ($appDel->Validate()) {
                    $appDel->Save();
                } else {
                    $msg = '';
                    foreach ($this->getValidationFailures() as $objValidationFailure) {
                        $msg .= $objValidationFailure->getMessage() . "<br/>";
                    }
                    throw (new PropelException('The row cannot be created!', new PropelException($msg)));
                }
            }


            /** Update search index */
            if ($this->appSolr != null) {
                $this->appSolr->updateApplicationSearchIndex($appUid);
            }
        } catch (Exception $e) {
            throw ($e);
        }
    }

    /**
     * This function creates a new row in APP_DELEGATION whether it has status closed
     *
     * @name ReactivateCurrentDelegation
     * @Description:  This function reativate the case previously cancelled from to do
     * @param string $sAppUid
     * @param string $iDelIndex
     * @return Fields
     */
    public function ReactivateCurrentDelegation($sAppUid, $iDelegation)
    {
        try {
            $c = new Criteria();
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $c->add(AppDelegationPeer::DEL_INDEX, $iDelegation);

            $rowObj = AppDelegationPeer::doSelect($c);
            foreach ($rowObj as $appDel) {
                $appDel->setDelThreadStatus('OPEN');
                $appDel->setDelThreadStatusId(Delegation::$thread_status['OPEN']);
                $appDel->setDelFinishDate(null);
                if ($appDel->Validate()) {
                    $appDel->Save();
                } else {
                    $msg = '';
                    foreach ($this->getValidationFailures() as $objValidationFailure) {
                        $msg .= $objValidationFailure->getMessage() . "<br/>";
                    }
                    throw (new PropelException('The row cannot be created!', new PropelException($msg)));
                }
            }
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * This function start a case using the task for the user $usrUid
     * With this function we can Start a case
     *
     * @name startCase
     * @param string $tasUid
     * @param string $usrUid
     * @param bool $isSubprocess
     * @param array $previousInfo
     * @param bool $isSelfService
     * @param string $sequenceType
     *
     * @return Fields
     * @throw Exception
     */
    public function startCase(string $tasUid, string $usrUid, $isSubprocess = false, $previousInfo = [], $isSelfService = false, $sequenceType = AppSequence::APP_TYPE_NORMAL)
    {
        if (!empty($tasUid)) {
            try {
                $task = TaskPeer::retrieveByPK($tasUid);
                $user = UsersPeer::retrieveByPK($usrUid);
                if (is_null($task)) {
                    throw new Exception(G::LoadTranslation("ID_TASK_NOT_EXIST", ["TAS_UID", $tasUid]));
                }

                // To allow Self Service as the first task
                $tasksTypeToExclude = ["START-TIMER-EVENT"];
                if (!is_null($task) && !in_array($task->getTasType(), $tasksTypeToExclude) && $task->getTasAssignType() != "SELF_SERVICE" && $usrUid == "") {
                    throw (new Exception('You tried to start a new case without send the USER UID!'));
                }

                // Load Process
                $proUid = $task->getProUid();
                $this->Process = new Process;
                $proFields = $this->Process->Load($proUid);

                // Create application
                $application = new Application;
                $appUid = $application->create($proUid, $usrUid, $sequenceType);
                $fields = $application->toArray(BasePeer::TYPE_FIELDNAME);

                // Create appDelegation
                $delIndex = $this->newAppDelegation(
                    $proUid,
                    $appUid,
                    $tasUid,
                    $usrUid,
                    -1, // previous
                    3, // Priority
                    1, // Start Thread
                    null, // Next delegation
                    false, // Flag control
                    false, // Flag control multi-instance
                    0, // Thread previous
                    $application->getAppNumber(),
                    $this->Process->getProId(),
                    $task->getTasId(),
                    $fields['APP_DATA']
                );

                // Instance appThread
                $thread = new AppThread;
                $threadIndex = $thread->createAppThread($appUid, $delIndex, 0);
                // Instance Derivation
                $routing = new Derivation();

                // Multiple Instance
                $userFields = [];
                $taskAssignType = $task->getTasAssignType();
                if ($taskAssignType == "MULTIPLE_INSTANCE" || $taskAssignType == "MULTIPLE_INSTANCE_VALUE_BASED") {
                    switch ($taskAssignType) {
                        case 'MULTIPLE_INSTANCE':
                            $userFields = $routing->getUsersFullNameFromArray($routing->getAllUsersFromAnyTask($tasUid));
                            break;
                        default:
                            throw (new Exception('Invalid Task Assignment method'));
                            break;
                    }
                    $userFields = $routing->getUsersFullNameFromArray($routing->getAllUsersFromAnyTask($tasUid));
                    $count = 0;
                    foreach ($userFields as $rowUser) {
                        if ($rowUser["USR_UID"] != $usrUid) {
                            // Create appDelegation
                            $delegation = new AppDelegation;
                            $threadIndex ++; // Start Thread
                            $priority = 3; // Priority
                            $user = UsersPeer::retrieveByPK($rowUser["USR_UID"]);
                            // Create a new delegation
                            $delIndex1 = $this->newAppDelegation(
                                $proUid,
                                $appUid,
                                $tasUid,
                                $rowUser["USR_UID"],
                                -1, // previous
                                $priority, // Priority
                                $threadIndex, // Start Thread
                                null, // Next delegation
                                false, // Flag control
                                false, // Flag control multi-instance
                                0, // Thread previous
                                $application->getAppNumber(),
                                $this->Process->getProId(),
                                $task->getTasId(),
                                $fields['APP_DATA']
                            );
                            // Create appThread
                            $thread = new AppThread;
                            $threadIndex = $thread->createAppThread($appUid, $delIndex1, 0);
                            // Save Information
                            $userFields[$count] = $rowUser;
                            $userFields[$count]["DEL_INDEX"] = $delIndex1;
                            $count++;
                        }
                    }
                }

                // Update the application
                $fields = $application->toArray(BasePeer::TYPE_FIELDNAME);
                $fields['DEL_INDEX'] = $delIndex;
                $application->update($fields);
                // Get current case number
                $caseNumber = $fields['APP_NUMBER'];
                // Update the task last assigned (for web entry and web services)
                $routing->setTasLastAssigned($tasUid, $usrUid);

                // Execute Events
                $event = new Event();
                $event->createAppEvents($proUid, $appUid, $delIndex, $tasUid);

                // Update solr
                if ($this->appSolr != null) {
                    $this->appSolr->updateApplicationSearchIndex($appUid);
                }

            } catch (Exception $e) {
                throw ($e);
            }
        } else {
            throw new Exception('You tried to start a new case without send the USER UID or TASK UID!');
        }

        // Log
        $message = 'Create case';
        $context = $data = [
            "appUid" => $appUid,
            "usrUid" => $usrUid,
            "tasUid" => $tasUid,
            "isSubprocess" => $isSubprocess,
            "appNumber" => $caseNumber,
            "delIndex" => $delIndex,
            "appInitDate" => $fields['APP_INIT_DATE']
        ];
        Log::channel(':CreateCase')->info($message, Bootstrap::context($context));
        // Call plugin
        if (class_exists('folderData')) {
            $folderData = new folderData($proUid, $proFields['PRO_TITLE'], $appUid, '', $usrUid);
            $oPluginRegistry = PluginRegistry::loadSingleton();
            $oPluginRegistry->executeTriggers(PM_CREATE_CASE, $folderData);
        }
        $this->getExecuteTriggerProcess($appUid, 'CREATE', false);
        //end plugin
        return [
            'APPLICATION' => $appUid,
            'INDEX' => $delIndex,
            'PROCESS' => $proUid,
            'CASE_NUMBER' => $caseNumber
        ];
    }

    /**
     * Get the next step
     *
     * @name getNextStep
     * @param string $proUid
     * @param string $appUid
     * @param integer $delIndex
     * @param integer $position
     * @return array
     */
    public function getNextStep($proUid = '', $appUid = '', $delIndex = 0, $position = 0)
    {
        $pmScript = new PMScript();
        $application = new Application();
        $fields = $application->Load($appUid);
        $data = Cases::unserializeData($fields['APP_DATA']);
        unset($data['USER_LOGGED']);
        unset($data['USR_USERNAME']);

        if (!is_array($fields['APP_DATA'])) {
            $fields['APP_DATA'] = G::array_merges(G::getSystemConstants(), $data);
        }

        $pmScript->setFields($fields['APP_DATA']);

        try {
            //get the current Delegation, and TaskUID
            $c = new Criteria('workflow');
            $c->add(AppDelegationPeer::PRO_UID, $proUid);
            $c->add(AppDelegationPeer::APP_UID, $appUid);
            $c->add(AppDelegationPeer::DEL_INDEX, $delIndex);
            $rows = AppDelegationPeer::doSelect($c);

            if (!isset($rows[0])) {
                return false;
            }

            $taskUid = $rows[0]->getTasUid();

            //get max step for this task
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->addSelectColumn('MAX(' . StepPeer::STEP_POSITION . ')');
            $c->add(StepPeer::PRO_UID, $proUid);
            $c->add(StepPeer::TAS_UID, $taskUid);
            $rs = StepPeer::doSelectRS($c);
            $rs->next();
            $row = $rs->getRow();
            $lastStep = intval($row[0]);
            if ($position != 10000 && $position > $lastStep) {
                throw (new Exception(G::LoadTranslation('ID_STEP_DOES_NOT_EXIST',
                    [G::LoadTranslation('ID_POSITION'), $position])));
            }
            $position += 1;
            $nextStep = null;
            if ($position <= $lastStep) {
                while ($position <= $lastStep) {
                    $accessStep = false;
                    //step
                    $step = new Step;
                    $step = $step->loadByProcessTaskPosition($proUid, $taskUid, $position);
                    if ($step) {
                        if (trim($step->getStepCondition()) !== '') {
                            $pmScript->setScript($step->getStepCondition());
                            $pmScript->setExecutedOn(PMScript::CONDITION);
                            $accessStep = $pmScript->evaluate();
                        } else {
                            $accessStep = true;
                        }
                        if ($accessStep) {
                            switch ($step->getStepTypeObj()) {
                                case 'DYNAFORM':
                                    $action = 'EDIT';
                                    break;
                                case 'OUTPUT_DOCUMENT':
                                    $action = 'GENERATE';
                                    break;
                                case 'INPUT_DOCUMENT':
                                    $action = 'ATTACH';
                                    break;
                                case 'EXTERNAL':
                                    $action = 'EDIT';
                                    break;
                                case 'MESSAGE':
                                    $action = '';
                                    break;
                            }
                            if (array_key_exists('gmail', $_SESSION) || (array_key_exists('gmail',
                                        $_GET) && $_GET['gmail'] == 1)) {
                                $nextStep = [
                                    'TYPE' => $step->getStepTypeObj(),
                                    'UID' => $step->getStepUidObj(),
                                    'POSITION' => $step->getStepPosition(),
                                    'PAGE' => 'cases_Step?TYPE=' . $step->getStepTypeObj() . '&UID=' .
                                        $step->getStepUidObj() . '&POSITION=' . $step->getStepPosition() .
                                        '&ACTION=' . $action .
                                        '&gmail=1'
                                ];
                            } else {
                                $nextStep = [
                                    'TYPE' => $step->getStepTypeObj(),
                                    'UID' => $step->getStepUidObj(),
                                    'POSITION' => $step->getStepPosition(),
                                    'PAGE' => 'cases_Step?TYPE=' . $step->getStepTypeObj() . '&UID=' .
                                        $step->getStepUidObj() . '&POSITION=' . $step->getStepPosition() .
                                        '&ACTION=' . $action
                                ];
                            }
                            $position = $lastStep;
                        }
                    }
                    $position += 1;
                }
            }
            if (!$nextStep) {
                if (array_key_exists('gmail', $_SESSION) || (array_key_exists('gmail', $_GET) && $_GET['gmail'] == 1)) {
                    $nextStep = [
                        'TYPE' => 'DERIVATION',
                        'UID' => -1,
                        'POSITION' => ($lastStep + 1),
                        'PAGE' => 'cases_Step?TYPE=ASSIGN_TASK&UID=-1&POSITION=10000&ACTION=ASSIGN&gmail=1'
                    ];
                } else {
                    $nextStep = [
                        'TYPE' => 'DERIVATION',
                        'UID' => -1,
                        'POSITION' => ($lastStep + 1),
                        'PAGE' => 'cases_Step?TYPE=ASSIGN_TASK&UID=-1&POSITION=10000&ACTION=ASSIGN'
                    ];
                }
            }
            return $nextStep;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * Get the previous step
     *
     * @name getPreviousStep
     * @param string $sProUid
     * @param string $sAppUid
     * @param integer $iDelIndex
     * @param integer $iPosition
     * @return array
     */
    public function getPreviousStep($sProUid = '', $sAppUid = '', $iDelIndex = 0, $iPosition = 0)
    {
        //Note: Depreciated, delete in the future
        $oPMScript = new PMScript();
        $oApplication = new Application();
        //$aFields = $oApplication->load($sAppUid);
        $oApplication = ApplicationPeer::retrieveByPk($sAppUid);
        $aFields = $oApplication->toArray(BasePeer::TYPE_FIELDNAME);
        if (!is_array($aFields['APP_DATA'])) {
            $aFields['APP_DATA'] = G::array_merges(G::getSystemConstants(), unserialize($aFields['APP_DATA']));
        }
        $oPMScript->setFields($aFields['APP_DATA']);

        try {
            //get the current Delegation, and TaskUID
            $c = new Criteria();
            $c->add(AppDelegationPeer::PRO_UID, $sProUid);
            $c->add(AppDelegationPeer::APP_UID, $sAppUid);
            $c->add(AppDelegationPeer::DEL_INDEX, $iDelIndex);
            $aRow = AppDelegationPeer::doSelect($c);

            $sTaskUid = $aRow[0]->getTasUid();
            $iFirstStep = 1;

            if ($iPosition == 10000) {
                //get max step for this task
                $c = new Criteria();
                $c->clearSelectColumns();
                $c->addSelectColumn('MAX(' . StepPeer::STEP_POSITION . ')');
                $c->add(StepPeer::PRO_UID, $sProUid);
                $c->add(StepPeer::TAS_UID, $sTaskUid);
                $rs = StepPeer::doSelectRS($c);
                $rs->next();
                $row = $rs->getRow();
                $iPosition = intval($row[0]);
            } else {
                $iPosition -= 1;
            }

            $aPreviousStep = null;
            if ($iPosition >= 1) {
                while ($iPosition >= $iFirstStep) {
                    $bAccessStep = false;
                    //step
                    $oStep = new Step;
                    $oStep = $oStep->loadByProcessTaskPosition($sProUid, $sTaskUid, $iPosition);
                    if ($oStep) {
                        if (trim($oStep->getStepCondition()) !== '') {
                            $oPMScript->setScript($oStep->getStepCondition());
                            $oPMScript->setExecutedOn(PMScript::CONDITION);
                            $bAccessStep = $oPMScript->evaluate();
                        } else {
                            $bAccessStep = true;
                        }
                        if ($bAccessStep) {
                            switch ($oStep->getStepTypeObj()) {
                                case 'DYNAFORM':
                                    $sAction = 'EDIT';
                                    break;
                                case 'OUTPUT_DOCUMENT':
                                    $sAction = 'GENERATE';
                                    break;
                                case 'INPUT_DOCUMENT':
                                    $sAction = 'ATTACH';
                                    break;
                                case 'EXTERNAL':
                                    $sAction = 'EDIT';
                                    break;
                                case 'MESSAGE':
                                    $sAction = '';
                                    break;
                            }
                            $aPreviousStep = array('TYPE' => $oStep->getStepTypeObj(),
                                'UID' => $oStep->getStepUidObj(),
                                'POSITION' => $oStep->getStepPosition(),
                                'PAGE' => 'cases_Step?TYPE=' . $oStep->getStepTypeObj() . '&UID=' .
                                $oStep->getStepUidObj() . '&POSITION=' .
                                $oStep->getStepPosition() . '&ACTION=' . $sAction
                            );
                            $iPosition = $iFirstStep;
                        }
                    }
                    $iPosition -= 1;
                }
            }
            if (!$aPreviousStep) {
                $aPreviousStep = false;
            }
            return $aPreviousStep;
        } catch (exception $e) {
            throw ($e);
        }
    }

    /**
     * Get the next supervisor step
     *
     * @name getNextSupervisorStep
     * @param string $sProcessUID
     * @param string $iPosition
     * @param integer $sType
     * @return $aNextStep
     */
    public function getNextSupervisorStep($sProcessUID, $iPosition, $sType = 'DYNAFORM')
    {
        $oCriteria = new Criteria();
        $oCriteria->add(StepSupervisorPeer::PRO_UID, $sProcessUID);
        $oCriteria->add(StepSupervisorPeer::STEP_TYPE_OBJ, $sType);
        $oCriteria->add(StepSupervisorPeer::STEP_POSITION, $iPosition);
        $oDataset = StepSupervisorPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aRow = $oDataset->getRow();
        if (!$aRow) {
            $oCriteria = new Criteria();
            $oCriteria->add(StepSupervisorPeer::PRO_UID, $sProcessUID);
            $oCriteria->add(StepSupervisorPeer::STEP_TYPE_OBJ, $sType);
            $oCriteria->add(StepSupervisorPeer::STEP_POSITION, ($iPosition + 1));
            $oDataset = StepSupervisorPeer::doSelectRS($oCriteria);
            $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset->next();
            $aRow = $oDataset->getRow();
        }
        $aNextStep = array('UID' => $aRow['STEP_UID_OBJ'], 'POSITION' => $aRow['STEP_POSITION']);
        return $aNextStep;
    }

    /**
     * Get the previous supervisor step
     *
     * @name getPreviousSupervisorStep
     * @param string $sProcessUID
     * @param string $iPosition
     * @param integer $sType
     * @return $aNextStep
     */
    public function getPreviousSupervisorStep($sProcessUID, $iPosition, $sType = 'DYNAFORM')
    {
        $iPosition -= 1;
        if ($iPosition > 0) {
            $oCriteria = new Criteria();
            $oCriteria->add(StepSupervisorPeer::PRO_UID, $sProcessUID);
            $oCriteria->add(StepSupervisorPeer::STEP_TYPE_OBJ, $sType);
            $oCriteria->add(StepSupervisorPeer::STEP_POSITION, $iPosition);
            $oDataset = StepSupervisorPeer::doSelectRS($oCriteria);
            $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset->next();
            $aRow = $oDataset->getRow();
            if (!$aRow) {
                $oCriteria = new Criteria();
                $oCriteria->add(StepSupervisorPeer::PRO_UID, $sProcessUID);
                $oCriteria->add(StepSupervisorPeer::STEP_TYPE_OBJ, $sType);
                $oCriteria->add(StepSupervisorPeer::STEP_POSITION, 1);
                $oDataset = StepSupervisorPeer::doSelectRS($oCriteria);
                $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                $oDataset->next();
                $aRow = $oDataset->getRow();
            }
            $aNextStep = array('UID' => $aRow['STEP_UID_OBJ'], 'POSITION' => $aRow['STEP_POSITION']);
            return $aNextStep;
        } else {
            return false;
        }
    }

    /**
     * Get the transfer History
     *
     * @name getTransferHistoryCriteria
     * @param integer $appNumber
     *
     * @return object
     */
    public static function getTransferHistoryCriteria($appNumber)
    {
        $c = new Criteria('workflow');
        $c->addSelectColumn(UsersPeer::USR_FIRSTNAME);
        $c->addSelectColumn(UsersPeer::USR_LASTNAME);
        $c->addSelectColumn(AppDelegationPeer::DEL_DELEGATE_DATE);
        $c->addSelectColumn(AppDelegationPeer::PRO_UID);
        $c->addSelectColumn(AppDelegationPeer::TAS_UID);
        $c->addSelectColumn(AppDelegationPeer::APP_UID);
        $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
        $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
        $c->addSelectColumn(AppDelayPeer::APP_ENABLE_ACTION_DATE);
        $c->addSelectColumn(AppDelayPeer::APP_DISABLE_ACTION_DATE);

        //We added this custom query for the case tracker
        $c->addAsColumn(
            'TAS_TITLE',
            'CASE WHEN TASK.TAS_TITLE = "INTERMEDIATE-THROW-EMAIL-EVENT" THEN "' . G::LoadTranslation('ID_INTERMEDIATE_THROW_EMAIL_EVENT') . '"
                   WHEN TASK.TAS_TITLE = "INTERMEDIATE-THROW-MESSAGE-EVENT" THEN "' . G::LoadTranslation('ID_INTERMEDIATE_THROW_MESSAGE_EVENT') . '"
                   WHEN TASK.TAS_TITLE = "INTERMEDIATE-CATCH-MESSAGE-EVENT" THEN "' . G::LoadTranslation('ID_INTERMEDIATE_CATCH_MESSAGE_EVENT') . '"
                   WHEN TASK.TAS_TITLE = "INTERMEDIATE-CATCH-TIMER-EVENT" THEN "' . G::LoadTranslation('ID_INTERMEDIATE_CATCH_TIMER_EVENT') . '"
                   ELSE TASK.TAS_TITLE
                   END'
        );

        $dbAdapter = 'database_' . strtolower(DB_ADAPTER);
        if (G::LoadSystemExist($dbAdapter)) {
            $dataBase = new database();
            $c->addAsColumn(
                'USR_NAME',
                $dataBase->concatString("USR_LASTNAME", "' '", "USR_FIRSTNAME")
            );
            $c->addAsColumn(
                'DEL_FINISH_DATE',
                $dataBase->getCaseWhen("DEL_FINISH_DATE IS NULL", "'-'", AppDelegationPeer::DEL_FINISH_DATE)
            );
            $c->addAsColumn(
                'APP_TYPE',
                $dataBase->getCaseWhen("DEL_FINISH_DATE IS NULL", "'IN_PROGRESS'", AppDelayPeer::APP_TYPE)
            );
        }

        //Define the joins
        $c->addJoin(AppDelegationPeer::USR_ID, UsersPeer::USR_ID, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::TAS_ID, TaskPeer::TAS_ID, Criteria::LEFT_JOIN);

        $del = DBAdapter::getStringDelimiter();
        $app = [];
        $app[] = [AppDelegationPeer::DEL_INDEX, AppDelayPeer::APP_DEL_INDEX];
        $app[] = [AppDelegationPeer::APP_NUMBER, AppDelayPeer::APP_NUMBER];
        $c->addJoinMC($app, Criteria::LEFT_JOIN);

        //Define the where
        $c->add(AppDelegationPeer::APP_NUMBER, $appNumber);

        //Order by
        $c->clearOrderByColumns();
        $c->addAscendingOrderByColumn(AppDelegationPeer::DEL_INDEX);

        return $c;
    }

    /**
     * Get the Criteria for To Do Cases List
     * @param string $sUIDUserLogged
     * @return array ( 'where' => Criteria, 'group' => Criteria )
     * @return array
     */
    public function prepareCriteriaForToDo($sUIDUserLogged)
    {
        // NEW QUERY
        $c = new Criteria('workflow');
        //$gf->clearSelectColumns();DEL_INIT_DATE
        $c->addSelectColumn(AppCacheViewPeer::APP_UID);
        $c->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $c->addSelectColumn(AppCacheViewPeer::APP_NUMBER);
        $c->addSelectColumn(AppCacheViewPeer::APP_STATUS);
        $c->addSelectColumn(AppCacheViewPeer::USR_UID);
        $c->addSelectColumn(AppCacheViewPeer::PREVIOUS_USR_UID);
        $c->addSelectColumn(AppCacheViewPeer::TAS_UID);
        $c->addSelectColumn(AppCacheViewPeer::PRO_UID);
        $c->addSelectColumn(AppCacheViewPeer::DEL_DELEGATE_DATE);
        $c->addSelectColumn(AppCacheViewPeer::DEL_INIT_DATE);
        //$c->addSelectColumn(AppCacheViewPeer::DEL_TASK_DUE_DATE  );
        $c->addAsColumn(
                'DEL_TASK_DUE_DATE', " IF (" . AppCacheViewPeer::DEL_TASK_DUE_DATE . " <= NOW(), CONCAT('<span style=\'color:red\';>', " .
                AppCacheViewPeer::DEL_TASK_DUE_DATE . ", '</span>'), " . AppCacheViewPeer::DEL_TASK_DUE_DATE . ") "
        );
        $c->addSelectColumn(AppCacheViewPeer::DEL_FINISH_DATE);
        $c->addSelectColumn(AppCacheViewPeer::DEL_THREAD_STATUS);
        $c->addSelectColumn(AppCacheViewPeer::APP_THREAD_STATUS);
        $c->addSelectColumn(AppCacheViewPeer::APP_TITLE);
        $c->addSelectColumn(AppCacheViewPeer::APP_PRO_TITLE);
        $c->addSelectColumn(AppCacheViewPeer::APP_TAS_TITLE);
        $c->addSelectColumn(AppCacheViewPeer::APP_CURRENT_USER);
        $c->addSelectColumn(AppCacheViewPeer::APP_DEL_PREVIOUS_USER);
        $c->addSelectColumn(AppCacheViewPeer::DEL_PRIORITY);
        $c->addSelectColumn(AppCacheViewPeer::DEL_DURATION);
        $c->addSelectColumn(AppCacheViewPeer::DEL_QUEUE_DURATION);
        $c->addSelectColumn(AppCacheViewPeer::DEL_DELAY_DURATION);
        $c->addSelectColumn(AppCacheViewPeer::DEL_STARTED);
        $c->addSelectColumn(AppCacheViewPeer::DEL_FINISHED);
        $c->addSelectColumn(AppCacheViewPeer::DEL_DELAYED);
        $c->addSelectColumn(AppCacheViewPeer::APP_CREATE_DATE);
        $c->addSelectColumn(AppCacheViewPeer::APP_FINISH_DATE);
        $c->addSelectColumn(AppCacheViewPeer::APP_UPDATE_DATE);

        $c->add(AppCacheViewPeer::USR_UID, $sUIDUserLogged);
        $c->add(AppCacheViewPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
        $c->add(AppCacheViewPeer::APP_STATUS, 'TO_DO');
        $c->add(AppCacheViewPeer::APP_THREAD_STATUS, 'OPEN');

        //call cleanup session vars
        return $c;
        //return array ( 'where' => $cf, 'whereFilter' => $cf, 'group' => $g , 'groupFilter' => $gf );
    }

    //DEPRECATED
    /**
     * Get the condition for Cases List
     *
     * @name getConditionCasesList
     * @param string $sTypeList
     * @param string $sUIDUserLogged
     * @param string $ClearSession
     * @param string $aAdditionalFilter
     * @return array
     */
    public function getConditionCasesList($sTypeList = 'all', $sUIDUserLogged = '', $ClearSession = true, $aAdditionalFilter = null)
    {
        $c = new Criteria('workflow');
        $c->clearSelectColumns();
        $c->addSelectColumn(ApplicationPeer::APP_UID);
        $c->addSelectColumn(ApplicationPeer::APP_TITLE);
        $c->addSelectColumn(ApplicationPeer::APP_NUMBER);
        $c->addSelectColumn(ApplicationPeer::APP_UPDATE_DATE);
        $c->addSelectColumn(ApplicationPeer::PRO_UID);
        $c->addSelectColumn(ApplicationPeer::APP_INIT_USER);
        $c->addSelectColumn(AppDelegationPeer::DEL_PRIORITY);
        //$c->addSelectColumn(AppDelegationPeer::DEL_TASK_DUE_DATE);
        $c->addAsColumn(
                'DEL_TASK_DUE_DATE', " IF (" . AppDelegationPeer::DEL_TASK_DUE_DATE . " <= NOW(), CONCAT('<span style=\'color:red\';>', " .
                AppDelegationPeer::DEL_TASK_DUE_DATE . ", '</span>'), " . AppDelegationPeer::DEL_TASK_DUE_DATE . ") "
        );

        global $RBAC;
        //seems the PM_SUPERVISOR can delete a completed case
        if ($sTypeList == "completed" && $RBAC->userCanAccess('PM_SUPERVISOR') == 1) {
            $c->addAsColumn("DEL_LINK", "CONCAT('" . G::LoadTranslation('ID_DELETE') . "')");
        }

        $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
        $c->addSelectColumn(AppDelegationPeer::TAS_UID);
        $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
        $c->addSelectColumn(UsersPeer::USR_UID);
        $c->addAsColumn('APP_CURRENT_USER', "CONCAT(USERS.USR_LASTNAME, ' ', USERS.USR_FIRSTNAME)");
        $c->addSelectColumn(ApplicationPeer::APP_STATUS);
        $c->addAsColumn('APP_PRO_TITLE', ProcessPeer::PRO_TITLE);
        $c->addAsColumn('APP_TAS_TITLE', TaskPeer::TAS_TITLE);
        //$c->addAsColumn('APP_DEL_PREVIOUS_USER', 'APP_LAST_USER.USR_USERNAME');
        $c->addAsColumn(
                'APP_DEL_PREVIOUS_USER', "CONCAT(APP_LAST_USER.USR_LASTNAME,
            ' ',
            APP_LAST_USER.USR_FIRSTNAME)"
        );

        $c->addAlias("APP_PREV_DEL", 'APP_DELEGATION');
        $c->addAlias("APP_LAST_USER", 'USERS');

        $c->addJoin(ApplicationPeer::APP_UID, AppDelegationPeer::APP_UID, Criteria::LEFT_JOIN);
        $c->addJoin(ApplicationPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::TAS_UID, TaskPeer::TAS_UID, Criteria::LEFT_JOIN);
        $appThreadConds[] = array(ApplicationPeer::APP_UID, AppThreadPeer::APP_UID);
        $appThreadConds[] = array(AppDelegationPeer::DEL_INDEX, AppThreadPeer::DEL_INDEX);
        $c->addJoinMC($appThreadConds, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

        $prevConds = array();
        $prevConds[] = array(ApplicationPeer::APP_UID, 'APP_PREV_DEL.APP_UID');
        $prevConds[] = array('APP_PREV_DEL.DEL_INDEX', AppDelegationPeer::DEL_PREVIOUS);
        $c->addJoinMC($prevConds, Criteria::LEFT_JOIN);

        $usrConds = array();
        $usrConds[] = array('APP_PREV_DEL.USR_UID', 'APP_LAST_USER.USR_UID');
        $c->addJoinMC($usrConds, Criteria::LEFT_JOIN);

        $c->add(TaskPeer::TAS_TYPE, 'SUBPROCESS', Criteria::NOT_EQUAL);

        //gral, to_revise, to_reassign dont have userid in the query
        if ($sTypeList != 'gral' && $sTypeList != 'to_revise' && $sTypeList != 'to_reassign' &&
                $sTypeList != 'my_started' && $sTypeList != 'sent') {
            $c->add(UsersPeer::USR_UID, $sUIDUserLogged);
        }

        /**
         * Additional filters
         * By Erik <erik@colosa.com>
         */
        if (isset($aAdditionalFilter) && is_array($aAdditionalFilter)) {
            foreach ($aAdditionalFilter as $sFilter => $sValue) {
                switch ($sFilter) {
                    case 'PRO_UID':
                        if ($sValue != "0") {
                            $c->add(ApplicationPeer::PRO_UID, $sValue, Criteria::EQUAL);
                        }
                        break;
                    case 'READ':
                        $c->add(AppDelegationPeer::DEL_INIT_DATE, null, Criteria::ISNOTNULL);
                        break;
                    case 'UNREAD':
                        $c->add(AppDelegationPeer::DEL_INIT_DATE, null, Criteria::ISNULL);
                        break;
                }
            }
        }

        $filesList = array(
            //7 standard list
            'to_do' => 'cases/cases_ListTodo',
            'draft' => 'cases/cases_ListDraft',
            'paused' => 'cases/cases_ListOnHold',
            'cancelled' => 'cases/cases_ListCancelled',
            'completed' => 'cases/cases_ListCompleted',
            'sent' => 'cases/cases_ListSent',
            'selfservice' => 'cases/cases_ListSelfService',
            //5 admin list
            'all' => 'cases/cases_ListAll',
            'to_revise' => 'cases/cases_ListToRevise',
            'to_reassign' => 'cases/cases_ListAll_Reassign',
            'my_started' => 'cases/cases_ListStarted',
            'Alldelete' => 'cases/cases_ListAllDelete'
        );
        switch ($sTypeList) {
            case 'all':
                $c->add(
                        $c->getNewCriterion(
                                        AppThreadPeer::APP_THREAD_STATUS, 'OPEN')->
                                addOr($c->getNewCriterion(ApplicationPeer::APP_STATUS, 'COMPLETED')->
                                        addAnd($c->getNewCriterion(AppDelegationPeer::DEL_PREVIOUS, 0)
                                        )
                                )
                );
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'my_started':
                $oCriteria = new Criteria('workflow');
                $oCriteria->addSelectColumn(AppDelayPeer::APP_UID);
                $oCriteria->add(
                        $oCriteria->getNewCriterion(
                                AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL
                        )->addOr(
                                $oCriteria->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0)
                        )
                );
                //$oCriteria->add(AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL);
                $oDataset = AppDelayPeer::doSelectRS($oCriteria);
                $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                $oDataset->next();
                $aProcesses = array();
                while ($aRow = $oDataset->getRow()) {
                    $aProcesses[] = $aRow['APP_UID'];
                    $oDataset->next();
                }

                $c->add($c->getNewCriterion(ApplicationPeer::APP_INIT_USER, $sUIDUserLogged));
                $c->add(
                        $c->getNewCriterion(
                                AppThreadPeer::APP_THREAD_STATUS, 'OPEN'
                        )->addOr(
                                $c->getNewCriterion(
                                        ApplicationPeer::APP_STATUS, 'COMPLETED'
                                )->addAnd(
                                        $c->getNewCriterion(AppDelegationPeer::DEL_PREVIOUS, 0)
                                )
                        )
                );
                $c->add($c->getNewCriterion(ApplicationPeer::APP_UID, $aProcesses, Criteria::NOT_IN));
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'to_do':
                $c->add(ApplicationPeer::APP_STATUS, 'TO_DO');
                $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
                $c->add(AppThreadPeer::APP_THREAD_STATUS, 'OPEN');
                $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'draft':
                $c->add(ApplicationPeer::APP_STATUS, 'DRAFT');
                $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
                $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'paused':
                $appDelayConds[] = array(ApplicationPeer::APP_UID, AppDelayPeer::APP_UID);
                $appDelayConds[] = array(AppDelegationPeer::DEL_INDEX, AppDelayPeer::APP_DEL_INDEX);
                $c->addJoinMC($appDelayConds, Criteria::LEFT_JOIN);
                $c->add(AppDelayPeer::APP_DELAY_UID, null, Criteria::ISNOTNULL);
                $c->add(AppDelayPeer::APP_TYPE, array("REASSIGN", "ADHOC", "CANCEL"), Criteria::NOT_IN);
                $c->add(
                        $c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL)->
                                addOr($c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0))
                );
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'cancelled':
                $c->add(
                        $c->getNewCriterion(AppThreadPeer::APP_THREAD_STATUS, 'CLOSED')->
                                addAnd($c->getNewCriterion(ApplicationPeer::APP_STATUS, 'CANCELLED'))
                );
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'completed':
                $c->add(ApplicationPeer::APP_STATUS, 'COMPLETED');
                $c->add(AppDelegationPeer::DEL_PREVIOUS, '0', Criteria::NOT_EQUAL);
                //$c->addAsColumn('DEL_FINISH_DATE', 'max('.AppDelegationPeer::DEL_FINISH_DATE.')');
                $c->addGroupByColumn(ApplicationPeer::APP_UID);
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'gral':
                $c->add(
                        $c->getNewCriterion(AppThreadPeer::APP_THREAD_STATUS, 'OPEN')->
                                addOr($c->getNewCriterion(ApplicationPeer::APP_STATUS, 'COMPLETED')->
                                        addAnd($c->getNewCriterion(AppDelegationPeer::DEL_PREVIOUS, 0)))
                );
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                $params = array();
                $sSql = BasePeer::createSelectSql($c, $params);
                break;
            case 'to_revise':
                $oCriteria = new Criteria('workflow');
                $oCriteria->add(ProcessUserPeer::USR_UID, $sUIDUserLogged);
                $oCriteria->add(ProcessUserPeer::PU_TYPE, 'SUPERVISOR');
                $oDataset = ProcessUserPeer::doSelectRS($oCriteria);
                $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                $oDataset->next();
                $aProcesses = array();
                while ($aRow = $oDataset->getRow()) {
                    $aProcesses[] = $aRow['PRO_UID'];
                    $oDataset->next();
                }
                $c->add(ApplicationPeer::PRO_UID, $aProcesses, Criteria::IN);
                $c->add(ApplicationPeer::APP_STATUS, 'TO_DO');
                $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
                $c->add(AppThreadPeer::APP_THREAD_STATUS, 'OPEN');
                $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'selfservice':
                //get the valid selfservice tasks for this user
                $oCase = new Cases();
                $tasks = $oCase->getSelfServiceTasks($_SESSION['USER_LOGGED']);
                $aTasks = array();
                foreach ($tasks as $key => $val) {
                    if (strlen(trim($val['uid'])) > 10) {
                        $aTasks[] = $val['uid'];
                    }
                }
                $c = new Criteria('workflow');
                $c->clearSelectColumns();
                $c->addSelectColumn(ApplicationPeer::APP_UID);
                $c->addSelectColumn(ApplicationPeer::APP_TITLE);
                $c->addSelectColumn(ApplicationPeer::APP_NUMBER);
                $c->addSelectColumn(ApplicationPeer::APP_UPDATE_DATE);
                $c->addSelectColumn(ApplicationPeer::PRO_UID);
                $c->addSelectColumn(ApplicationPeer::APP_INIT_USER);
                $c->addSelectColumn(AppDelegationPeer::DEL_PRIORITY);

                $c->addAsColumn(
                        'DEL_TASK_DUE_DATE', " IF (" . AppDelegationPeer::DEL_TASK_DUE_DATE . " <= NOW(),
                    CONCAT('<span style=\'color:red\';>', " . AppDelegationPeer::DEL_TASK_DUE_DATE .
                        ", '</span>'), " . AppDelegationPeer::DEL_TASK_DUE_DATE . ") "
                );

                $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
                $c->addSelectColumn(AppDelegationPeer::TAS_UID);
                $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
                $c->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
                $c->addSelectColumn(UsersPeer::USR_UID);
                $c->addAsColumn('APP_CURRENT_USER', "CONCAT(USERS.USR_LASTNAME, ' ', USERS.USR_FIRSTNAME)");
                $c->addSelectColumn(ApplicationPeer::APP_STATUS);
                $c->addAsColumn('APP_PRO_TITLE', ProcessPeer::PRO_TITLE);
                $c->addAsColumn('APP_TAS_TITLE', TaskPeer::TAS_TITLE);

                $c->addJoin(ApplicationPeer::APP_UID, AppDelegationPeer::APP_UID, Criteria::LEFT_JOIN);
                $c->addJoin(ApplicationPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
                $c->addJoin(AppDelegationPeer::TAS_UID, TaskPeer::TAS_UID, Criteria::LEFT_JOIN);
                $appThreadConds[] = array(ApplicationPeer::APP_UID, AppThreadPeer::APP_UID);
                $appThreadConds[] = array(AppDelegationPeer::DEL_INDEX, AppThreadPeer::DEL_INDEX);
                $c->addJoinMC($appThreadConds, Criteria::LEFT_JOIN);
                $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

                $c->add(AppDelegationPeer::USR_UID, '');
                $c->add(AppDelegationPeer::TAS_UID, $aTasks, Criteria::IN);
                break;
            case 'to_reassign':
                $c->add(
                        $c->getNewCriterion(ApplicationPeer::APP_STATUS, 'TO_DO')->
                                addOr($c->getNewCriterion(ApplicationPeer::APP_STATUS, 'DRAFT'))
                );
                $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
                $c->add(AppThreadPeer::APP_THREAD_STATUS, 'OPEN');
                $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
            case 'sent':
                $oCriteria = new Criteria('workflow');
                $oCriteria->addSelectColumn(AppDelayPeer::APP_UID);
                $oCriteria->add(
                        $oCriteria->getNewCriterion(
                                AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL
                        )->addOr(
                                $oCriteria->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0)
                        )
                );
                $oDataset = AppDelayPeer::doSelectRS($oCriteria);
                $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                $oDataset->next();
                $aProcesses = array();
                while ($aRow = $oDataset->getRow()) {
                    $aProcesses[] = $aRow['APP_UID'];
                    $oDataset->next();
                }
                if (isset($aAdditionalFilter) && isset($aAdditionalFilter['MINE'])) {
                    $c->add($c->getNewCriterion(ApplicationPeer::APP_INIT_USER, $sUIDUserLogged));
                } else {
                    $c->add(
                            $c->getNewCriterion(
                                    ApplicationPeer::APP_INIT_USER, $sUIDUserLogged
                            )->addOr(
                                    $c->getNewCriterion(
                                            AppDelegationPeer::USR_UID, $sUIDUserLogged
                                    )
                            )
                    );
                }
                if (isset($aAdditionalFilter) && isset($aAdditionalFilter['APP_STATUS_FILTER'])) {
                    $c->add(ApplicationPeer::APP_STATUS, $sValue, Criteria::EQUAL);
                } else {
                    $c->add(ApplicationPeer::APP_STATUS, 'DRAFT', Criteria::NOT_EQUAL);
                }

                $c->add(
                        $c->getNewCriterion(
                                AppDelegationPeer::DEL_THREAD_STATUS, 'CLOSED'
                        )->addOr(
                                $c->getNewCriterion(
                                        ApplicationPeer::APP_STATUS, 'COMPLETED'
                                )->addAnd(
                                        $c->getNewCriterion(AppDelegationPeer::DEL_PREVIOUS, 0)
                                )
                        )
                );
                $c->add($c->getNewCriterion(ApplicationPeer::APP_UID, $aProcesses, Criteria::NOT_IN));
                $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);
                break;
        }

        //select the xmlList to show
        if ($sTypeList == 'gral') {
            if ($RBAC->userCanAccess('PM_DELETECASE') == 1) {
                $xmlfile = $filesList['Alldelete'];
            } else {
                $xmlfile = $filesList['all'];
            }
        } else {
            $xmlfile = $filesList[$sTypeList];
        }

        if ($ClearSession) {
            //OPCION_1: claening the $_SESSION and updating the List.... only case List
            foreach ($filesList as $file) {
                $id = G::createUID('', $file . '.xml');
                unset($_SESSION['pagedTable[' . $id . ']']);
                unset($_SESSION[$id]);
            }
            //OPTION_2: cleaning the $_SESSION and whole List and xmls
            $cur = array_keys($_SESSION);
            foreach ($cur as $key) {
                if (substr($key, 0, 11) === "pagedTable[") {
                    unset($_SESSION[$key]);
                } else {
                    $xml = G::getUIDName($key, '');
                    if (strpos($xml, '.xml') !== false) {
                        unset($_SESSION[$key]);
                    }
                }
            }
        }
        return array($c, $xmlfile);
    }

    /**
     * Get a case in its current index
     *
     * @name loadCaseInCurrentDelegation
     * @param string $sTypeList
     * @param string $sUIDUserLogged
     * @Author Erik Amaru Ortiz <erik@colosa.com>
     * @return array
     */
    public function loadCaseInCurrentDelegation($APP_UID, $titles = false)
    {
        $c = new Criteria('workflow');
        $c->clearSelectColumns();
        $c->addSelectColumn(ApplicationPeer::APP_UID);
        $c->addSelectColumn(ApplicationPeer::APP_NUMBER);
        $c->addSelectColumn(ApplicationPeer::APP_UPDATE_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_PRIORITY);
        //$c->addSelectColumn(AppDelegationPeer::DEL_TASK_DUE_DATE);
        $c->addAsColumn(
                'DEL_TASK_DUE_DATE', " IF (" . AppDelegationPeer::DEL_TASK_DUE_DATE . " <= NOW(),
            " . AppDelegationPeer::DEL_TASK_DUE_DATE . " ,
            " . AppDelegationPeer::DEL_TASK_DUE_DATE . ") "
        );

        $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
        $c->addSelectColumn(AppDelegationPeer::TAS_UID);
        $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
        $c->addSelectColumn(UsersPeer::USR_UID);
        $c->addAsColumn('APP_CURRENT_USER', "CONCAT(USERS.USR_LASTNAME, ' ', USERS.USR_FIRSTNAME)");
        $c->addSelectColumn(ApplicationPeer::APP_STATUS);
        if ($titles) {
            $c->addSelectColumn(ApplicationPeer::APP_TITLE);
            $c->addAsColumn('APP_PRO_TITLE', ProcessPeer::PRO_TITLE);
            $c->addAsColumn('APP_TAS_TITLE', TaskPeer::TAS_TITLE);
        }
        //$c->addAsColumn('APP_DEL_PREVIOUS_USER', 'APP_LAST_USER.USR_USERNAME');
        $c->addAsColumn(
                'APP_DEL_PREVIOUS_USER', "CONCAT(APP_LAST_USER.USR_LASTNAME, ' ', APP_LAST_USER.USR_FIRSTNAME)");

        $c->addAlias("APP_PREV_DEL", 'APP_DELEGATION');
        $c->addAlias("APP_LAST_USER", 'USERS');

        $c->addJoin(ApplicationPeer::APP_UID, AppDelegationPeer::APP_UID, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::TAS_UID, TaskPeer::TAS_UID, Criteria::LEFT_JOIN);
        $appThreadConds[] = array(ApplicationPeer::APP_UID, AppThreadPeer::APP_UID);
        $appThreadConds[] = array(AppDelegationPeer::DEL_INDEX, AppThreadPeer::DEL_INDEX);
        $c->addJoinMC($appThreadConds, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

        if ($titles) {
            $c->addJoin(ApplicationPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        }

        $prevConds = array();
        $prevConds[] = array(ApplicationPeer::APP_UID, 'APP_PREV_DEL.APP_UID');
        $prevConds[] = array('APP_PREV_DEL.DEL_INDEX', AppDelegationPeer::DEL_PREVIOUS);
        $c->addJoinMC($prevConds, Criteria::LEFT_JOIN);

        $usrConds = array();
        $usrConds[] = array('APP_PREV_DEL.USR_UID', 'APP_LAST_USER.USR_UID');
        $c->addJoinMC($usrConds, Criteria::LEFT_JOIN);

        $c->add(TaskPeer::TAS_TYPE, 'SUBPROCESS', Criteria::NOT_EQUAL);

        $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
        $c->add(AppThreadPeer::APP_THREAD_STATUS, 'OPEN');
        $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');

        $c->add(ApplicationPeer::APP_UID, $APP_UID);

        $oDataset = ApplicationPeer::doSelectRS($c);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $row = $oDataset->getRow();

        return $row;
    }

    /**
     * Get a case in its current index
     *
     * @name loadCaseByDelegation
     * @param string $appUid,
     * @param string $delIndex
     * @author gustavo cruz
     * @return array
     */
    public function loadCaseByDelegation($appUid, $delIndex)
    {
        $c = new Criteria('workflow');
        $c->clearSelectColumns();
        $c->addSelectColumn(ApplicationPeer::APP_UID);
        $c->addSelectColumn(ApplicationPeer::APP_TITLE);
        $c->addSelectColumn(ApplicationPeer::APP_NUMBER);
        $c->addSelectColumn(ApplicationPeer::APP_UPDATE_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_PRIORITY);
        //$c->addSelectColumn(AppDelegationPeer::DEL_TASK_DUE_DATE);
        $c->addAsColumn(
                'DEL_TASK_DUE_DATE', " IF (" . AppDelegationPeer::DEL_TASK_DUE_DATE . " <= NOW(),  " . AppDelegationPeer::DEL_TASK_DUE_DATE . " ,
            " . AppDelegationPeer::DEL_TASK_DUE_DATE . ") "
        );

        $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
        $c->addSelectColumn(AppDelegationPeer::TAS_UID);
        $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
        $c->addSelectColumn(UsersPeer::USR_UID);
        $c->addAsColumn('APP_CURRENT_USER', "CONCAT(USERS.USR_LASTNAME, ' ', USERS.USR_FIRSTNAME)");
        $c->addSelectColumn(ApplicationPeer::APP_STATUS);
        $c->addAsColumn('APP_PRO_TITLE', ProcessPeer::PRO_TITLE);
        $c->addAsColumn('APP_TAS_TITLE', TaskPeer::TAS_TITLE);
        //$c->addAsColumn('APP_DEL_PREVIOUS_USER', 'APP_LAST_USER.USR_USERNAME');
        $c->addAsColumn(
                'APP_DEL_PREVIOUS_USER', "CONCAT(APP_LAST_USER.USR_LASTNAME, ' ', APP_LAST_USER.USR_FIRSTNAME)");

        $c->addAlias("APP_PREV_DEL", 'APP_DELEGATION');
        $c->addAlias("APP_LAST_USER", 'USERS');

        $c->addJoin(ApplicationPeer::APP_UID, AppDelegationPeer::APP_UID, Criteria::LEFT_JOIN);
        $c->addJoin(ApplicationPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::TAS_UID, TaskPeer::TAS_UID, Criteria::LEFT_JOIN);
        $appThreadConds[] = array(ApplicationPeer::APP_UID, AppThreadPeer::APP_UID);
        $appThreadConds[] = array(AppDelegationPeer::DEL_INDEX, AppThreadPeer::DEL_INDEX);
        $c->addJoinMC($appThreadConds, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

        $prevConds = array();
        $prevConds[] = array(ApplicationPeer::APP_UID, 'APP_PREV_DEL.APP_UID');
        $prevConds[] = array('APP_PREV_DEL.DEL_INDEX', AppDelegationPeer::DEL_PREVIOUS);
        $c->addJoinMC($prevConds, Criteria::LEFT_JOIN);

        $usrConds = array();
        $usrConds[] = array('APP_PREV_DEL.USR_UID', 'APP_LAST_USER.USR_UID');
        $c->addJoinMC($usrConds, Criteria::LEFT_JOIN);

        $c->add(TaskPeer::TAS_TYPE, 'SUBPROCESS', Criteria::NOT_EQUAL);

        $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
        $c->add(AppThreadPeer::APP_THREAD_STATUS, 'OPEN');
        $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');

        $c->add(ApplicationPeer::APP_UID, $appUid);
        $c->add(AppDelegationPeer::DEL_INDEX, $delIndex);

        $oDataset = ApplicationPeer::doSelectRS($c);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $row = $oDataset->getRow();

        return $row;
    }

    /**
     *
     * @name ThrowUnpauseDaemon
     * author: erik@colosa.com
     * Description: This method set all cases with the APP_DISABLE_ACTION_DATE for today
     * @return void
     */
    public function ThrowUnpauseDaemon($today, $cron = 0)
    {
        $c = new Criteria('workflow');
        $c->clearSelectColumns();
        $c->add(
                $c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0, Criteria::EQUAL)->addOr(
                        $c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL))
        );
        $c->add(
                $c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_DATE, (count(explode(" ", $today)) > 1) ? $today : $today . " 23:59:59", Criteria::LESS_EQUAL)->addAnd(
                        $c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_DATE, null, Criteria::ISNOTNULL))
        );
        $d = AppDelayPeer::doSelectRS($c);
        $d->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $d->next();

        while ($aRow = $d->getRow()) {
            if ($cron == 1) {
                $arrayCron = unserialize(trim(@file_get_contents(PATH_DATA . "cron")));
                $arrayCron["processcTimeStart"] = time();
                @file_put_contents(PATH_DATA . "cron", serialize($arrayCron));
            }

            $this->unpauseCase($aRow['APP_UID'], $aRow['APP_DEL_INDEX'], 'System Daemon');
            $d->next();
        }
    }

    /**
     * it Changes the date and APP_DISABLE_ACTION_USER to unpause cases
     *
     * @name UnpauseRoutedCasesWithPauseFlagEnabled
     * @param string $usrLogged
     * @return void
     */
    public function UnpauseRoutedCasesWithPauseFlagEnabled($usrLogged)
    {
        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(AppDelayPeer::APP_DELAY_UID);
        $c->add(
                $c->getNewCriterion(
                                AppDelayPeer::APP_DELEGATION_USER, $usrLogged, Criteria::EQUAL)->
                        addAnd($c->getNewCriterion(AppDelegationPeer::DEL_THREAD_STATUS, 'CLOSED', Criteria::EQUAL))->
                        addAnd($c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL)->
                                addOr($c->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0))
                        )
        );
        $aConditions = array();
        $aConditions[] = array(AppDelayPeer::APP_UID, AppDelegationPeer::APP_UID);
        $aConditions[] = array(AppDelayPeer::APP_DEL_INDEX, AppDelegationPeer::DEL_INDEX);
        $c->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        $rs = AppDelayPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        while ($rs->next()) {
            $row = $rs->getRow();
            if (is_array($row)) {
                $con = Propel::getConnection('workflow');
                $c1 = new Criteria('workflow');
                $c1->add(AppDelayPeer::APP_DELAY_UID, $row['APP_DELAY_UID']);
                // update set
                $c2 = new Criteria('workflow');
                $c2->add(AppDelayPeer::APP_DISABLE_ACTION_USER, $usrLogged);
                $c2->add(AppDelayPeer::APP_DISABLE_ACTION_DATE, date('Y-m-d'));
                BasePeer::doUpdate($c1, $c2, $con);
            }
        }
    }

    /**
     * Get the application UID by case number
     *
     * @name getApplicationUIDByNumber
     * @param integer $iApplicationNumber
     * @return string
     */
    public function getApplicationUIDByNumber($iApplicationNumber)
    {
        $oCriteria = new Criteria();
        $oCriteria->add(ApplicationPeer::APP_NUMBER, $iApplicationNumber);
        $oApplication = ApplicationPeer::doSelectOne($oCriteria);
        if (!is_null($oApplication)) {
            return $oApplication->getAppUid();
        } else {
            return null;
        }
    }

    /**
     * Get the current delegation of a user or a case
     * @name getCurrentDelegation
     * @param string $sApplicationUID
     * @param string $sUserUID
     * @return integer
     */
    public function getCurrentDelegation($sApplicationUID = '', $sUserUID = '', $onlyOpenThreads = false)
    {
        $oCriteria = new Criteria();
        $oCriteria->add(AppDelegationPeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppDelegationPeer::USR_UID, $sUserUID);
        $oCriteria->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
        $oCriteria->addDescendingOrderByColumn(AppDelegationPeer::DEL_DELEGATE_DATE);
        $oApplication = AppDelegationPeer::doSelectOne($oCriteria);
        if (!is_null($oApplication)) {
            return $oApplication->getDelIndex();
        }

        //if the user is not in the task, we need to return a valid del index, so we are returning the latest delindex
        $oCriteria = new Criteria();
        $oCriteria->add(AppDelegationPeer::APP_UID, $sApplicationUID);
        if ($onlyOpenThreads) {
            $oCriteria->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
        }
        $oCriteria->addDescendingOrderByColumn(AppDelegationPeer::DEL_DELEGATE_DATE);
        $oApplication = AppDelegationPeer::doSelectOne($oCriteria);
        if (!is_null($oApplication)) {
            return $oApplication->getDelIndex();
        }
        throw (new Exception('This case has 0 current delegations'));
    }

    /**
     * Get the current delegation of a user or a case
     * @name loadTriggers
     * @param string $sTasUid
     * @param string $sStepType
     * @param array $sStepUidObj
     * @param string $sTriggerType
     * @return integer
     */
    public function loadTriggers($sTasUid, $sStepType, $sStepUidObj, $sTriggerType)
    {
        $aTriggers = array();
        if (($sStepUidObj != -1) && ($sStepUidObj != -2)) {
            $c = new Criteria();
            $c->clearSelectColumns();
            $c->addSelectColumn(StepPeer::STEP_UID);
            $c->add(StepPeer::TAS_UID, $sTasUid);
            $c->add(StepPeer::STEP_TYPE_OBJ, $sStepType);
            $c->add(StepPeer::STEP_UID_OBJ, $sStepUidObj);
            $rs = StepPeer::doSelectRS($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            $sStepUid = $row['STEP_UID'];
        } else {
            $sStepUid = $sStepUidObj;
        }

        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TriggersPeer::TRI_UID);
        $c->addSelectColumn(TriggersPeer::TRI_TITLE);
        $c->addSelectColumn(StepTriggerPeer::ST_CONDITION);
        $c->addSelectColumn(TriggersPeer::TRI_TYPE);
        $c->addSelectColumn(TriggersPeer::TRI_WEBBOT);

        $c->add(StepTriggerPeer::STEP_UID, $sStepUid);
        $c->add(StepTriggerPeer::TAS_UID, $sTasUid);
        $c->add(StepTriggerPeer::ST_TYPE, $sTriggerType);
        $c->addJoin(StepTriggerPeer::TRI_UID, TriggersPeer::TRI_UID, Criteria::LEFT_JOIN);
        $c->addAscendingOrderByColumn(StepTriggerPeer::ST_POSITION);
        $rs = TriggersPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        while ($rs->next()) {
            $row = $rs->getRow();

            $aTriggers[] = $row;
        }

        return $aTriggers;
    }

    /**
     * Execute trigger in task
     * @name executeTriggers
     * @param string $tasUid
     * @param string $stepType
     * @param array $stepUidObj
     * @param string $triggerType
     * @param array $fieldsCase
     *
     * @return integer
     */
    public function executeTriggers($tasUid, $stepType, $stepUidObj, $triggerType, $fieldsCase = [])
    {
        //Load the triggers assigned in the step
        $triggersList = $this->loadTriggers($tasUid, $stepType, $stepUidObj, $triggerType);

        //Execute the trigger defined in the step
        $lastFields = $this->executeTriggerFromList($triggersList, $fieldsCase, $stepType, $stepUidObj, $triggerType);


        return $lastFields;
    }

    /**
     * This method executes the triggers send in an array
     *
     * @param array $triggersList
     * @param array $fieldsCase
     * @param string $stepType
     * @param string $stepUidObj
     * @param string $triggerType
     * @param string $labelAssignment
     * @param bool $useGlobal, needs to have the value true if the same case in execution is affected with this trigger
     *
     * @return array
     *
     * @see Cases::executeTriggers()
     * @see Cases::getExecuteTriggerProcess()
     * @see WsBase::executeTriggerFromDerivate()
     * @see ScriptTask::execScriptByActivityUid()
     *
     * @link https://wiki.processmaker.com/3.2/Triggers#Custom_Trigger
     * @link https://wiki.processmaker.com/3.2/Triggers#When_action_cases
     * @link https://wiki.processmaker.com/3.1/Triggers
     * @link https://wiki.processmaker.com/3.1/Tasks#ScriptTask
    */
    public function executeTriggerFromList(
        array $triggersList,
        array $fieldsCase,
        $stepType,
        $stepUidObj,
        $triggerType,
        $labelAssignment = '',
        $useGlobal = true
    )
    {
        if (count($triggersList) > 0) {
            if ($useGlobal) {
                /**
                 * The global $oPMScript is necessary when the trigger can be update the appData related to the case
                 * in execution
                 */
                global $oPMScript;
            }

            $this->addTriggerMessageExecution("<br /><b>" . $labelAssignment . "</b><br />");
            if (!isset($oPMScript)) {
                $oPMScript = new PMScript();
            }


            $varInAfterRouting = false;
            $fieldsTrigger = [];
            foreach ($triggersList as $trigger) {

                $oPMScript->setFields($fieldsCase);
                $execute = true;
                //Check if the trigger has conditions for the execution
                if (!empty($trigger['ST_CONDITION'])) {
                    $oPMScript->setDataTrigger($trigger);
                    $oPMScript->setScript($trigger['ST_CONDITION']);
                    $oPMScript->setExecutedOn(PMScript::CONDITION);
                    $execute = $oPMScript->evaluate();
                }

                //Execute the trigger
                if ($execute) {
                    $oPMScript->setDataTrigger($trigger);
                    $oPMScript->setScript($trigger['TRI_WEBBOT']);
                    $executedOn = $oPMScript->getExecutionOriginForAStep($stepType, $stepUidObj, $triggerType);
                    $oPMScript->setExecutedOn($executedOn);
                    $oPMScript->execute();
                    //Return all the appData + variables changed in the execution
                    $appDataAfterTrigger = $oPMScript->aFields;

                    /**
                     * This section of code its related to the route the case with parallel task in the same time
                     * @link https://processmaker.atlassian.net/browse/PMC-2
                     *
                     * @todo: The solution for ticket HOR-4602 should be restated in another ticket, for now this change was reverted
                    */
                    if ($oPMScript->executedOn() === $oPMScript::AFTER_ROUTING) {
                        //Get the variables changed with the trigger
                        $fieldsTrigger = getDiffBetweenModifiedVariables($appDataAfterTrigger, $fieldsCase);
                        $collection = collect($fieldsCase);
                        $merged = $collection->merge($fieldsTrigger);
                        //Merge the appData with variables changed
                        $fieldsCase = $merged->all();

                        //We will be load the last appData because:
                        //Other thread execution can be changed the variables
                        $appUid = !empty($fieldsCase['APPLICATION']) ? $fieldsCase['APPLICATION'] : '';
                        //Save the fields changed in the trigger
                        if (!$varInAfterRouting && !empty($fieldsTrigger)) {
                            $varInAfterRouting = true;
                        }
                    } else {
                        $fieldsCase = $appDataAfterTrigger;
                    }

                    //Register the time execution
                    $this->arrayTriggerExecutionTime[$trigger['TRI_UID']] = $oPMScript->scriptExecutionTime;
                    //Register the message of execution
                    $varTriggers = "&nbsp;- " . nl2br(htmlentities($trigger["TRI_TITLE"], ENT_QUOTES)) . "<br/>";
                    $this->addTriggerMessageExecution($varTriggers);
                }
            }

        }

        return $fieldsCase;
    }

    /**
     * Find keys and values into the appData
     *
     * @param array $appData
     * @param array $keyToSearch
     *
     * @return array
    */
    private function findKeysAndValues(array $appData, array $keyToSearch)
    {
        $keysAndValues = [];
        foreach ($keyToSearch as $key) {
            $keysAndValues[$key] = $appData[$key];
        }

        return $keysAndValues;
    }

    /**
     * If the feature is enable and the code_scanner_scope has the argument trigger the code scanner will check the code
     * Review in the running cases
     *
     * @param CodeScanner $cs
     * @param string $code
     * @param string $triTitle
     *
     * @return string
     *
     * @link https://wiki.processmaker.com/Plugin_Trigger_Code_Security_Scanner_v2
    */
    private function codeScannerReview(CodeScanner $cs, $code, $triTitle)
    {
        $foundDisabledCode = "";

        return $foundDisabledCode;
    }

    /**
     * Get the trigger's names
     * @name getTriggerNames
     * @param string $triggers
     * @return integer
     */
    public function getTriggerNames($triggers)
    {
        $triggers_info = array();
        $aTriggers = array();
        foreach ($triggers as $key => $val) {
            $aTriggers[] = $val['TRI_UID'];
        }
        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(TriggersPeer::TRI_TITLE);
        $c->add(TriggersPeer::TRI_UID, $aTriggers, Criteria::IN);
        $rs = TriggersPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        while ($row = $rs->getRow()) {
            $triggers_info[] = $row['TRI_TITLE'];
            $rs->next();
        }
        return $triggers_info;
    }

    /**
     * Return the input documents list criteria object
     *
     * @name getInputDocumentsCriteria
     * @param string $sApplicationUID
     * @param string $iDelegation
     * @param string $sDocumentUID
     * @param string $sAppDocuUID
     * @return object
     */
    public function getInputDocumentsCriteria($sApplicationUID, $iDelegation, $sDocumentUID, $sAppDocuUID = '')
    {
        try {
            $deletePermission = $this->getAllObjectsFrom(
                    $_SESSION['PROCESS'], $sApplicationUID, $_SESSION['TASK'], $_SESSION['USER_LOGGED'], $ACTION = 'DELETE'
            );
            $listing = false;
            $oPluginRegistry = PluginRegistry::loadSingleton();
            if ($oPluginRegistry->existsTrigger(PM_CASE_DOCUMENT_LIST)) {
                $folderData = new folderData(null, null, $sApplicationUID, null, $_SESSION['USER_LOGGED']);
                $folderData->PMType = "INPUT";
                $folderData->returnList = true;
                $listing = $oPluginRegistry->executeTriggers(PM_CASE_DOCUMENT_LIST, $folderData);
            }

            $oUser = new Users();
            $oAppDocument = new AppDocument();
            $oCriteria = new Criteria('workflow');
            $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
            $oCriteria->add(AppDocumentPeer::DOC_UID, $sDocumentUID);
            if ($sAppDocuUID != "") {
                $oCriteria->add(AppDocumentPeer::APP_DOC_UID, $sAppDocuUID);
            }

            $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('INPUT', 'ATTACHED'), CRITERIA::IN);
            $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
            $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_UID);

            $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
            $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset->next();
            $aInputDocuments = array();
            $aInputDocuments[] = array(
                'APP_DOC_UID' => 'char',
                'DOC_VERSION' => 'char',
                'DOC_UID' => 'char',
                'APP_DOC_COMMENT' => 'char',
                'APP_DOC_FILENAME' => 'char',
                'APP_DOC_INDEX' => 'integer'
            );

            while ($aRow = $oDataset->getRow()) {
                $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
                $lastVersion = $oAppDocument->getLastAppDocVersion($aRow['APP_DOC_UID'], $sApplicationUID);
                $aFields = array(
                    'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                    'DOC_VERSION' => $aAux['DOC_VERSION'],
                    'DOC_UID' => $aAux['DOC_UID'],
                    'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                    'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                    'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX']
                );
                if ($aFields['APP_DOC_FILENAME'] != '') {
                    $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
                } else {
                    $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
                }
                $aFields['POSITION'] = isset($_SESSION['STEP_POSITION']) ? $_SESSION['STEP_POSITION'] : 1;
                $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_INPUT_AND_HISTORY');

                $aFields['ID_DELETE'] = '';
                if (in_array($aRow['APP_DOC_UID'], $deletePermission['INPUT_DOCUMENTS'])) {
                    $aFields['ID_DELETE'] = G::LoadTranslation('ID_DELETE');
                }
                $aFields['REPLACE_LABEL'] = "";
                if (($aRow['DOC_VERSION'] == $lastVersion) || ($sAppDocuUID != "")) {
                    $aFields['REPLACE_LABEL'] = G::LoadTranslation('ID_REPLACE');
                    $oInputDocument = new InputDocument();
                    $inputDocumentFields = $oInputDocument->load($aRow['DOC_UID']);
                    if ($inputDocumentFields['INP_DOC_VERSIONING']) {
                        $aFields['NEWVERSION_LABEL'] = G::LoadTranslation('ID_NEW_VERSION');
                    }
                }
                if ($aRow['DOC_VERSION'] > 1) {
                    $aFields['VERSIONHISTORY_LABEL'] = G::LoadTranslation('ID_VERSION_HISTORY');
                }

                if ($aRow['USR_UID'] != -1) {
                    $aUser = $oUser->load($aRow['USR_UID']);
                    $aFields['CREATOR'] = $aUser['USR_FIRSTNAME'] . ' ' . $aUser['USR_LASTNAME'];
                } else {
                    $aFields['CREATOR'] = '***';
                }

                $aFields['APP_DOC_CREATE_DATE'] = $aRow['APP_DOC_CREATE_DATE'];
                $aFields['DOC_VERSION_LABEL'] = $aRow['DOC_VERSION'];
                $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_DOWNLOAD');
                $aFields['DOWNLOAD_LINK'] = "cases_ShowDocument?a=" .
                        $aRow['APP_DOC_UID'] . "&v=" . $aRow['DOC_VERSION'];

                if (is_array($listing)) {
                    foreach ($listing as $folderitem) {
                        if ($folderitem->filename == $aRow['APP_DOC_UID']) {
                            $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_GET_EXTERNAL_FILE');
                            $aFields['DOWNLOAD_LINK'] = $folderitem->downloadScript;
                            continue;
                        }
                    }
                }
                $aFields['COMMENT'] = $aFields['APP_DOC_COMMENT'];
                if (($aRow['DOC_VERSION'] == $lastVersion) || ($sAppDocuUID != "")) {
                    $aInputDocuments[] = $aFields;
                }
                $oDataset->next();
            }

            global $_DBArray;
            $_DBArray['inputDocuments'] = $aInputDocuments;
            $_SESSION['_DBArray'] = $_DBArray;
            $oCriteria = new Criteria('dbarray');
            $oCriteria->setDBArrayTable('inputDocuments');
            // $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_CREATE_DATE);
            return $oCriteria;
        } catch (exception $oException) {
            throw $oException;
        }
    }

    /**
     * Return the input documents list to Review
     *
     * @name getInputDocumentsCriteriaToRevise
     * @param string $sApplicationUID
     * @return object
     */
    public function getInputDocumentsCriteriaToRevise($sApplicationUID)
    {
        try {
            $oAppDocument = new AppDocument();
            $oCriteria = new Criteria('workflow');
            $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
            $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('INPUT', 'ATTACHED'), CRITERIA::IN);
            $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
            $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
            $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
            $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset->next();
            $aInputDocuments = array();
            $aInputDocuments[] = array(
                'APP_DOC_UID' => 'char',
                'DOC_UID' => 'char',
                'APP_DOC_COMMENT' => 'char',
                'APP_DOC_FILENAME' => 'char',
                'APP_DOC_INDEX' => 'integer'
            );
            while ($aRow = $oDataset->getRow()) {
                $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
                $aFields = array(
                    'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                    'DOC_UID' => $aAux['DOC_UID'],
                    'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                    'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                    'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX']
                );

                if ($aFields['APP_DOC_FILENAME'] != '') {
                    $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
                } else {
                    $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
                }
                $aFields['CREATE_DATE'] = $aRow['APP_DOC_CREATE_DATE'];
                $aFields['TYPE'] = $aRow['APP_DOC_TYPE'];

                $aFields['POSITION'] = $_SESSION['STEP_POSITION'];
                $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
                $aInputDocuments[] = $aFields;
                $oDataset->next();
            }
            global $_DBArray;
            $_DBArray['inputDocuments'] = $aInputDocuments;
            $_SESSION['_DBArray'] = $_DBArray;
            $oCriteria = new Criteria('dbarray');
            $oCriteria->setDBArrayTable('inputDocuments');
            $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
            return $oCriteria;
        } catch (exception $oException) {
            throw $oException;
        }
    }

    /**
     * Add a input document
     *
     * Return the application document ID
     *
     * @param string $inputDocumentUid Input document ID
     * @param string $appDocUid Application document ID
     * @param int $docVersion Document version
     * @param string $appDocType Document type
     * @param string $appDocComment Document comment
     * @param string $inputDocumentAction Action, posible values: null or empty (Add), "R" (Replace), "NV" (New Version)
     * @param string $applicationUid Application ID
     * @param int $delIndex Delegation index
     * @param string $taskUid Task ID
     * @param string $userUid User ID
     * @param string $option Option, posible values: "xmlform", "file"
     * @param string $file File ($_FILES["form"]["name"]["APP_DOC_FILENAME"] or path to file)
     * @param int $fileError File error ($_FILES["form"]["error"]["APP_DOC_FILENAME"] or 0)
     * @param string $fileTmpName File temporal name ($_FILES["form"]["tmp_name"]["APP_DOC_FILENAME"] or null)
     * @param string $fileSize    File size ($_FILES["form"]["size"]["APP_DOC_FILENAME"] or 0)
     * @return string Return application document ID
     */
    public function addInputDocument($inputDocumentUid, $appDocUid, $docVersion, $appDocType, $appDocComment, $inputDocumentAction, $applicationUid, $delIndex, $taskUid, $userUid, $option, $file, $fileError = 0, $fileTmpName = null, $fileSize = 0, $isInputDocumentOfGrid = false)
    {
        $appDocFileName = null;
        $sw = 0;

        switch ($option) {
            case "xmlform":
                $appDocFileName = $file;

                if ($fileError == 0) {
                    $sw = 1;
                }
                break;
            case "file":
                $appDocFileName = basename($file);

                if (file_exists($file) && is_file($file)) {
                    $sw = 1;
                }
                break;
        }

        if ($sw == 0) {
            return null;
        }

        $folderId = '';
        $tags = '';

        if (!$isInputDocumentOfGrid) {
            //Info
            $inputDocument = new InputDocument();
            $arrayInputDocumentData = $inputDocument->load($inputDocumentUid);

            //--- Validate Filesize of $_FILE
            $inpDocMaxFilesize = $arrayInputDocumentData["INP_DOC_MAX_FILESIZE"];
            $inpDocMaxFilesizeUnit = $arrayInputDocumentData["INP_DOC_MAX_FILESIZE_UNIT"];

            $inpDocMaxFilesize = $inpDocMaxFilesize * (($inpDocMaxFilesizeUnit == "MB") ? 1024 * 1024 : 1024); //Bytes

            if ($inpDocMaxFilesize > 0 && $fileSize > 0) {
                if ($fileSize > $inpDocMaxFilesize) {
                    throw new Exception(G::LoadTranslation("ID_SIZE_VERY_LARGE_PERMITTED"));
                }
            }
            //Get the Custom Folder ID (create if necessary)
            $appFolder = new AppFolder();
            $folderId = $appFolder->createFromPath($arrayInputDocumentData["INP_DOC_DESTINATION_PATH"], $applicationUid);

            $tags = $appFolder->parseTags($arrayInputDocumentData["INP_DOC_TAGS"], $applicationUid);
        }

        $appDocument = new AppDocument();
        $arrayField = array();

        switch ($inputDocumentAction) {
            case "R":
                //Replace
                $arrayField = array(
                    "APP_DOC_UID" => $appDocUid,
                    "APP_UID" => $applicationUid,
                    "DOC_VERSION" => $docVersion,
                    "DEL_INDEX" => $delIndex,
                    "USR_UID" => $userUid,
                    "DOC_UID" => $inputDocumentUid,
                    "APP_DOC_TYPE" => $appDocType,
                    "APP_DOC_CREATE_DATE" => date("Y-m-d H:i:s"),
                    "APP_DOC_COMMENT" => $appDocComment,
                    "APP_DOC_TITLE" => "",
                    "APP_DOC_FILENAME" => $appDocFileName,
                    "FOLDER_UID" => $folderId,
                    "APP_DOC_TAGS" => $tags
                );

                $appDocument->update($arrayField);
                break;
            case "NV":
                //New Version
                $arrayField = array(
                    "APP_DOC_UID" => $appDocUid,
                    "APP_UID" => $applicationUid,
                    "DEL_INDEX" => $delIndex,
                    "USR_UID" => $userUid,
                    "DOC_UID" => $inputDocumentUid,
                    "APP_DOC_TYPE" => $appDocType,
                    "APP_DOC_CREATE_DATE" => date("Y-m-d H:i:s"),
                    "APP_DOC_COMMENT" => $appDocComment,
                    "APP_DOC_TITLE" => "",
                    "APP_DOC_FILENAME" => $appDocFileName,
                    "FOLDER_UID" => $folderId,
                    "APP_DOC_TAGS" => $tags
                );

                $appDocument->create($arrayField);
                break;
            default:
                //New
                $arrayField = array(
                    "APP_UID" => $applicationUid,
                    "DEL_INDEX" => $delIndex,
                    "USR_UID" => $userUid,
                    "DOC_UID" => $inputDocumentUid,
                    "APP_DOC_TYPE" => $appDocType,
                    "APP_DOC_CREATE_DATE" => date("Y-m-d H:i:s"),
                    "APP_DOC_COMMENT" => $appDocComment,
                    "APP_DOC_TITLE" => "",
                    "APP_DOC_FILENAME" => $appDocFileName,
                    "FOLDER_UID" => $folderId,
                    "APP_DOC_TAGS" => $tags
                );

                $appDocument->create($arrayField);
                break;
        }

        //Save the file
        $appDocUid = $appDocument->getAppDocUid();
        $docVersion = $appDocument->getDocVersion();
        $arrayInfo = pathinfo($appDocument->getAppDocFilename());
        $extension = (isset($arrayInfo["extension"])) ? $arrayInfo["extension"] : null;
        $strPathName = PATH_DOCUMENT . G::getPathFromUID($applicationUid) . PATH_SEP;
        $strFileName = $appDocUid . "_" . $docVersion . "." . $extension;

        switch ($option) {
            case "xmlform":
                G::uploadFile($fileTmpName, $strPathName, $strFileName);
                break;
            case "file":
                $umaskOld = umask(0);

                if (!is_dir($strPathName)) {
                    G::verifyPath($strPathName, true);
                }


                $filter = new InputFilter();
                $file = $filter->xssFilterHard($file, 'path');

                copy($file, $strPathName . $strFileName);
                chmod($strPathName . $strFileName, 0666);
                umask($umaskOld);
                break;
        }

        //Plugin Hook PM_UPLOAD_DOCUMENT for upload document
        $pluginRegistry = PluginRegistry::loadSingleton();

        if ($pluginRegistry->existsTrigger(PM_UPLOAD_DOCUMENT) && class_exists("uploadDocumentData")) {
            $triggerDetail = $pluginRegistry->getTriggerInfo(PM_UPLOAD_DOCUMENT);
            $documentData = new uploadDocumentData(
                            $applicationUid,
                            $userUid,
                            $strPathName . $strFileName,
                            $arrayField["APP_DOC_FILENAME"],
                            $appDocUid,
                            $docVersion
            );
            $uploadReturn = $pluginRegistry->executeTriggers(PM_UPLOAD_DOCUMENT, $documentData);

            if ($uploadReturn) {
                $arrayField["APP_DOC_PLUGIN"] = $triggerDetail->sNamespace;

                if (!isset($arrayField["APP_DOC_UID"])) {
                    $arrayField["APP_DOC_UID"] = $appDocUid;
                }

                if (!isset($arrayField["DOC_VERSION"])) {
                    $arrayField["DOC_VERSION"] = $docVersion;
                }

                $appDocument->update($arrayField);

                unlink($strPathName . $strFileName);
            }
        }
        //End plugin

        return $appDocUid;
    }

    /**
     * Return the input documents list to Review
     *
     * @name getInputDocumentsCriteriaToRevise
     * @param string $sApplicationUID
     * @return object
     */
    public function getOutputDocumentsCriteriaToRevise($sApplicationUID)
    {
        try {
            $oAppDocument = new AppDocument();
            $oCriteria = new Criteria('workflow');
            $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
            $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, 'OUTPUT');
            $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
            $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
            $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
            $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset->next();
            $aOutputDocuments = array();
            $aOutputDocuments[] = array(
                'APP_DOC_UID' => 'char',
                'DOC_UID' => 'char',
                'APP_DOC_COMMENT' => 'char',
                'APP_DOC_FILENAME' => 'char',
                'APP_DOC_INDEX' => 'integer',
                'APP_DOC_CREATE_DATE' => 'char'
            );
            while ($aRow = $oDataset->getRow()) {
                $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
                $aFields = array(
                    'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                    'DOC_UID' => $aAux['DOC_UID'],
                    'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                    'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                    'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                    'APP_DOC_CREATE_DATE' => $aRow['APP_DOC_CREATE_DATE']
                );
                if ($aFields['APP_DOC_FILENAME'] != '') {
                    $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
                } else {
                    $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
                }
                $aOutputDocuments[] = $aFields;
                $oDataset->next();
            }
            global $_DBArray;
            $_DBArray['outputDocuments'] = $aOutputDocuments;
            $_SESSION['_DBArray'] = $_DBArray;
            $oCriteria = new Criteria('dbarray');
            $oCriteria->setDBArrayTable('outputDocuments');
            $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
            return $oCriteria;
        } catch (exception $oException) {
            throw $oException;
        }
    }

    /**
     * Return the (Application) Criteria object
     *
     * @name getCriteriaProcessCases
     * @param string $status
     * @param string $PRO_UID
     * @return object
     */
    public function getCriteriaProcessCases($status, $PRO_UID)
    {
        $c = new Criteria('workflow');
        $c->add(ApplicationPeer::APP_STATUS, $status);
        $c->add(ApplicationPeer::PRO_UID, $PRO_UID);
        return $c;
    }

    /**
     * Review is an unassigned Case
     *
     * @name isUnassignedPauseCase
     * @param string $sAppUid
     * @param string $iDelegation
     * @return boolean
     */
    public static function isUnassignedPauseCase($sAppUid, $iDelegation)
    {
        $oAppDelegation = new AppDelegation();
        $aFieldsDel = $oAppDelegation->Load($sAppUid, $iDelegation);
        $usrUid = $aFieldsDel['USR_UID'];
        if ($usrUid === '') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Pause a Case
     *
     * @param string $appUid
     * @param int $index
     * @param string $usrUid
     * @param string $unpauseDate
     * @param string $appTitle
     *
     * @return object
     */
    public function pauseCase($appUid, $index, $usrUid, $unpauseDate = null, $appTitle = null)
    {
        $application = new Application();
        $fields = $application->Load($appUid);
        $appNumber = $application->getAppNumber();
        // Get the index of appThread
        $appThread = Thread::getThread($appUid, $index);
        $appThread = head($appThread);
        $threadIndex = $appThread['APP_THREAD_INDEX'];
        if (empty($threadIndex)) {
            throw new Exception(G::LoadTranslation("ID_CASE_STOPPED_TRIGGER"));
        }
        /** Close the index for pause */
        $this->CloseCurrentDelegation($appUid, $index, 'PAUSED', Delegation::$thread_status['PAUSED']);

        // Prepare the data for pause
        $attributes = [
            'APP_DELAY_UID' => G::generateUniqueID(),
            'PRO_UID' => $application->getProUid(),
            'PRO_ID' => $application->getProId(),
            'APP_UID' => $appUid,
            'APP_NUMBER' => $appNumber,
            'APP_THREAD_INDEX' => $threadIndex,
            'APP_DEL_INDEX' => $index,
            'APP_TYPE' => 'PAUSE',
            'APP_STATUS' => $application->getAppStatus(),
            'APP_DELEGATION_USER' => $usrUid,
            'APP_DELEGATION_USER_ID' => User::getId($usrUid),
            'APP_ENABLE_ACTION_USER' => $usrUid,
            'APP_ENABLE_ACTION_DATE' => date('Y-m-d H:i:s'),
            'APP_DISABLE_ACTION_DATE' => $unpauseDate,
        ];

        /** Register the pause case */
        Delay::create($attributes);

        /** Update the application case */
        $application->update($fields);

        /** Update search index */
        if ($this->appSolr != null) {
            $this->appSolr->updateApplicationSearchIndex($appUid);
        }

        /** Execute the trigger */
        $this->getExecuteTriggerProcess($appUid, 'PAUSED');

    }

    /**
     * Unpause a case
     *
     * @name unpauseCase
     * @param string $appUid
     * @param int $index
     * @param string $usrUid
     *
     * @return object
     */
    public function unpauseCase($appUid, $index, $usrUid)
    {
        // Verify status of the case
        $delay = new AppDelay();
        if (method_exists($delay, 'isPaused')) {
            if ($delay->isPaused($appUid, $index) === false) {
                return false;
            }
        }
        // Get information about current $index row
        $delegation = new AppDelegation();
        $delRow = $delegation->Load($appUid, $index);
        // Define the values for create a new index
        $proUid = $delRow['PRO_UID'];
        $appUid = $delRow['APP_UID'];
        $tasUid = $delRow['TAS_UID'];
        $usrUid = $delRow['USR_UID'];
        // Load Application
        $application = new Application();
        $caseFields = $application->Load($appUid);
        $caseData = unserialize($caseFields['APP_DATA']);
        // Update to PAUSED to CLOSED
        $row = [];
        $row['APP_UID'] = $appUid;
        $row['DEL_INDEX'] = $index;
        $row['DEL_THREAD_STATUS'] = 'CLOSED';
        $row['DEL_THREAD_STATUS_ID'] = 0;
        $delegation->update($row);
        // Create a new delegation
        $newIndex = $this->newAppDelegation(
            $proUid,
            $appUid,
            $tasUid,
            $usrUid,
            -1, // previous
            3, // Priority
            1, // Start Thread
            null, // Next delegation
            false, // Flag control
            false, // Flag control multi-instance
            0, // Thread previous
            $delRow['APP_NUMBER'],
            $delRow['PRO_ID'],
            $delRow['TAS_ID'],
            $caseData
        );
        // Update other fields in the recent new appDelegation
        $row = [];
        $row['APP_UID'] = $delRow['APP_UID'];
        $row['DEL_INDEX'] = $newIndex;
        $row['DEL_PREVIOUS'] = $delRow['DEL_PREVIOUS'];
        $row['DEL_TYPE'] = $delRow['DEL_TYPE'];
        $row['DEL_PRIORITY'] = $delRow['DEL_PRIORITY'];
        $row['DEL_DELEGATE_DATE'] = $delRow['DEL_DELEGATE_DATE'];
        $row['DEL_INIT_DATE'] = date('Y-m-d H:i:s');
        $row['DEL_FINISH_DATE'] = null;
        $delegation->update($row);
        // Get the APP_DELAY row with app_uid, del_index and app_type=pause
        $criteria = new Criteria('workflow');
        $criteria->clearSelectColumns();
        $criteria->addSelectColumn(AppDelayPeer::APP_DELAY_UID);
        $criteria->addSelectColumn(AppDelayPeer::APP_THREAD_INDEX);
        $criteria->addSelectColumn(AppDelayPeer::APP_STATUS);
        $criteria->add(AppDelayPeer::APP_UID, $appUid);
        $criteria->add(AppDelayPeer::APP_DEL_INDEX, $index);
        $criteria->add(AppDelayPeer::APP_TYPE, 'PAUSE');
        $criteria->add(
            $criteria->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, 0, Criteria::EQUAL)->addOr(
                $criteria->getNewCriterion(AppDelayPeer::APP_DISABLE_ACTION_USER, null, Criteria::ISNULL))
        );
        $dataset = AppDelayPeer::doSelectRS($criteria);
        $dataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $dataset->next();
        $rowPaused = $dataset->getRow();
        $caseFields['APP_STATUS'] = $rowPaused['APP_STATUS'];
        $application->update($caseFields);
        // Update the APP_THREAD table
        $rowUpdate = [
            'APP_UID' => $appUid,
            'APP_THREAD_INDEX' => $rowPaused['APP_THREAD_INDEX'],
            'DEL_INDEX' => $newIndex
        ];
        $thread = new AppThread();
        $thread->update($rowUpdate);
        // Update the APP_DELAY table
        $row['APP_DELAY_UID'] = $rowPaused['APP_DELAY_UID'];
        $row['APP_DISABLE_ACTION_USER'] = $usrUid;
        $row['APP_DISABLE_ACTION_DATE'] = date('Y-m-d H:i:s');
        $delay = new AppDelay();
        $rowDelay = $delay->update($row);
        // Update solr
        if ($this->appSolr != null) {
            $this->appSolr->updateApplicationSearchIndex($appUid);
        }

        $this->getExecuteTriggerProcess($appUid, "UNPAUSE");

    }

    /**
     * Cancel case without matter the threads
     * if the force is true, we will cancel it does not matter the threads
     * if the force is false, we will to cancel one thread
     *
     * @param string $appUid
     * @param integer $delIndex
     * @param string $usrUid
     * @param bool $executeSameCase
     *
     * @see Ajax::cancelCase()
     * @see cases_Ajax
     * @see WsBase::cancelCase()
     *
    */
    public function cancelCase($appUid, $delIndex = null, $usrUid = null, $executeSameCase = true)
    {
        $caseFields = $this->loadCase($appUid);
        $appStatusCurrent = $caseFields['APP_STATUS'];

        /** Update the status CANCELLED in the tables related */
        $caseFields['APP_STATUS'] = Application::APP_STATUS_CANCELLED;
        $this->updateCase($appUid, $caseFields);

        /** Close the thread(s) in APP_DELEGATION and APP_THREAD */
        $indexesClosed = self::closeCaseThreads($appUid, $delIndex);

        /** Create a register in APP_DELAY */
        $delay = new AppDelay();

        foreach ($indexesClosed as $value) {
            $dataList = [];
            $rowDelay = AppDelay::buildAppDelayRow(
                $caseFields['PRO_UID'],
                isset($caseFields['PRO_ID']) ? $caseFields['PRO_ID'] : 0,
                $appUid,
                $caseFields['APP_NUMBER'],
                $value['DEL_THREAD'],
                $value['DEL_INDEX'],
                AppDelay::APP_TYPE_CANCEL,
                Application::APP_STATUS_CANCELLED,
                is_null($usrUid) ? '' : $usrUid
            );
            $delay->create($rowDelay);

            /** Execute a trigger when a case is cancelled */
            $this->getExecuteTriggerProcess($appUid, 'CANCELED', $executeSameCase);

        }
    }

    /**
     * This function will be close the one or all threads for cancel the case
     *
     * @param string $appUid
     * @param integer $delIndex, if is null we will to close all threads
     *
     * @return array
    */
    private function closeCaseThreads($appUid, $delIndex = null)
    {
        $delegation = new AppDelegation();
        $result = [];

        /** Close all the threads in APP_DELEGATION and APP_THREAD */
        if (is_null($delIndex)) {

        } else {
            /** Close the specific delIndex in APP_DELEGATION and APP_THREAD */
            $this->CloseCurrentDelegation($appUid, $delIndex);
            $resultDelegation = $delegation->Load($appUid, $delIndex);
            $this->closeAppThread($appUid, $resultDelegation['DEL_THREAD']);
            $result[] = $resultDelegation;
        }

        /** This case is subProcess? */
        if (SubApplication::isCaseSubProcess($appUid)) {
            foreach ($result as $value){
                $route = new Derivation();
                $route->verifyIsCaseChild($appUid, $value['DEL_INDEX']);
            }
        }

        return $result;
    }

    /**
     * Un cancel case
     *
     * @param string $appUid
     * @param string $usrUid
     *
     * @return void
     * @throws Exception
     */
    public function unCancelCase($appUid, $usrUid)
    {
        try {
            $user = new BusinessModelUser();
            /** Review if the user has the permission PM_UNCANCELCASE */
            if (!$user->checkPermission($usrUid, 'PM_UNCANCELCASE')) {
                throw new Exception(G::LoadTranslation('ID_YOU_DO_NOT_HAVE_PERMISSION'));
            }

            $caseFields = $this->loadCase($appUid);
            /** Review if the case has the status CANCELLED */
            if ($caseFields["APP_STATUS"] !== Application::APP_STATUS_CANCELLED) {
                throw new Exception(G::LoadTranslation('ID_THE_APPLICATION_IS_NOT_CANCELED', [$appUid]));
            }

            //Load the USR_ID
            $u = new Users();
            $userId = $u->load($usrUid)['USR_ID'];

            //Get the list of thread that close with the CancelCase
            $appDelay = new AppDelay();
            $threadsCanceled = $appDelay->getThreadByStatus($appUid, Application::APP_STATUS_CANCELLED);

            //Get all the threads in the AppDelay
            foreach ($threadsCanceled as $row){
                // Load the thread CLOSED
                $appDelegation = new AppDelegation();
                $delegationClosed = $appDelegation->Load($appUid, $row['APP_DEL_INDEX']);
                // Create an appDelegation for each thread
                $delIndex = $this->newAppDelegation(
                    $delegationClosed['PRO_UID'],
                    $delegationClosed['APP_UID'],
                    $delegationClosed['TAS_UID'],
                    $usrUid,
                    $delegationClosed['DEL_PREVIOUS'], // previous
                    3, // Priority
                    $delegationClosed['DEL_THREAD'], // Start Thread
                    null, // Next delegation
                    false, // Flag control
                    false, // Flag control multi-instance
                    0, // Thread previous
                    $delegationClosed['APP_NUMBER'],
                    $delegationClosed['PRO_ID'],
                    $delegationClosed['TAS_ID'],
                    $caseFields['APP_DATA']
                );

                //Update the appThread
                $dataAppThread = [
                    'APP_UID' => $row['APP_UID'],
                    'APP_THREAD_INDEX' => $delegationClosed['DEL_THREAD'],
                    'APP_THREAD_STATUS' => 'OPEN',
                    'DEL_INDEX' => $delIndex
                ];
                $appThread = new AppThread();
                $res = $appThread->update($dataAppThread);

                //New register in AppDelay
                $newAppDelay = AppDelay::buildAppDelayRow(
                    $row['PRO_UID'],
                    $delegationClosed['PRO_ID'],
                    $row['APP_UID'],
                    $delegationClosed['APP_NUMBER'],
                    $row['APP_THREAD_INDEX'],
                    $delIndex,
                    AppDelay::APP_TYPE_UNCANCEL,
                    Application::APP_STATUS_TODO,
                    $usrUid,
                    $userId
                );
                $appDelay->create($newAppDelay);

                //New register in the listInbox
                $newDelegation = array_merge($newAppDelay, $delegationClosed);
                $newDelegation['USR_UID'] = $usrUid;
                $newDelegation['DEL_INDEX'] = $delIndex;
                $newDelegation['APP_STATUS'] = Application::APP_STATUS_TODO;
                $inbox = new ListInbox();
                //Get the previous user
                //When the status of the case is DRAFT we does not have a previous thread
                $previousUser = '';
                if ($delegationClosed['DEL_PREVIOUS'] != 0){
                    $appDelegation = new AppDelegation();
                    $delegationPrevious = $appDelegation->Load($appUid, $delegationClosed['DEL_PREVIOUS']);
                    $previousUser = $delegationPrevious['USR_UID'];
                }

                $inbox->newRow($newDelegation, $previousUser);
            }

            //Update the status of the case
            $caseFields['APP_STATUS'] = Application::APP_STATUS_TODO;
            $this->updateCase($appUid, $caseFields);

            //Remove the case from the list Canceled
            $listCanceled = new ListCanceled();
            $listCanceled->removeAll($appUid);

        } catch (Exception $exception) {
            throw $exception;
        }
    }

    /**
     * reactive a case
     *
     * @name reactivateCase
     * @param string $sApplicationUID
     * @param string $iIndex
     * @param string $user_logged
     * @return void
     */
    public function reactivateCase($sApplicationUID, $iIndex, $user_logged)
    {
        $oApplication = new Application();
        $aFields = $oApplication->load(
                (isset($_POST['sApplicationUID']) ? $_POST['sApplicationUID'] : $_SESSION['APPLICATION'])
        );
        $aFields['APP_STATUS'] = 'TO_DO';
        $oApplication->update($aFields);
        $this->ReactivateCurrentDelegation($sApplicationUID, $iIndex);
        $c = new Criteria('workflow');
        $c->clearSelectColumns();
        $c->addSelectColumn(AppDelayPeer::APP_DELAY_UID);

        $c->add(AppDelayPeer::APP_UID, $sApplicationUID);
        $c->add(AppDelayPeer::PRO_UID, $aFields['PRO_UID']);
        $c->add(AppDelayPeer::APP_DEL_INDEX, $iIndex);
        $c->add(AppDelayPeer::APP_TYPE, 'CANCEL');
        $c->add(AppDelayPeer::APP_DISABLE_ACTION_USER, 0);
        $c->add(AppDelayPeer::APP_DISABLE_ACTION_DATE, null, Criteria::ISNULL);

        $oDataset = AppDelayPeer::doSelectRS($c);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aRow = $oDataset->getRow();
        //var_dump($aRow);
        $aFields = array();
        $aFields['APP_DELAY_UID'] = $aRow['APP_DELAY_UID'];
        $aFields['APP_DISABLE_ACTION_USER'] = $user_logged;
        $aFields['APP_DISABLE_ACTION_DATE'] = date('Y-m-d H:i:s');

        $delay = new AppDelay();
        $delay->update($aFields);
        //$this->ReactivateCurrentDelegation($sApplicationUID);
        $con = Propel::getConnection('workflow');
        $sql = "UPDATE APP_THREAD SET APP_THREAD_STATUS = 'OPEN' WHERE APP_UID =  '$sApplicationUID' " .
                " AND DEL_INDEX  ='$iIndex' ";
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery($sql, ResultSet::FETCHMODE_ASSOC);

        //update searchindex
        if ($this->appSolr != null) {
            $this->appSolr->updateApplicationSearchIndex($sApplicationUID);
        }
    }

    /**
     * Reassign a case
     *
     * @name reassignCase
     *
     * @param string $appUid
     * @param string $delIndex
     * @param string $currentUserUid
     * @param string $newUserUid
     * @param string $type
     *
     * @return true
     */
    public function reassignCase($appUid, $delIndex, $currentUserUid, $newUserUid, $type = 'REASSIGN')
    {
        $this->CloseCurrentDelegation($appUid, $delIndex);
        $user = UsersPeer::retrieveByPK($newUserUid);
        $appDelegation = new AppDelegation();
        $fieldsDel = $appDelegation->Load($appUid, $delIndex);

        // Load application
        $application = new Application();
        $dataFields = $application->Load($appUid);
        $caseData = unserialize($dataFields['APP_DATA']);

        // Create a new delegation
        $newDelIndex = $this->newAppDelegation(
            $fieldsDel['PRO_UID'],
            $fieldsDel['APP_UID'],
            $fieldsDel['TAS_UID'],
            $newUserUid,
            -1, // previous
            3, // Priority
            $fieldsDel['DEL_THREAD'], // Start Thread
            null, // Next delegation
            false, // Flag control
            false, // Flag control multi-instance
            0, // Thread previous
            $fieldsDel['APP_NUMBER'],
            $fieldsDel['PRO_ID'],
            $fieldsDel['TAS_ID'],
            $caseData
        );

        $newData = [];
        $newData['APP_UID'] = $fieldsDel['APP_UID'];
        $newData['DEL_INDEX'] = $newDelIndex;
        $newData['DEL_PREVIOUS'] = $fieldsDel['DEL_PREVIOUS'];
        $newData['DEL_TYPE'] = $fieldsDel['DEL_TYPE'];
        $newData['DEL_PRIORITY'] = $fieldsDel['DEL_PRIORITY'];
        $newData['USR_UID'] = $newUserUid;
        $newData['DEL_INIT_DATE'] = null;
        $newData['DEL_FINISH_DATE'] = null;
        $newData['USR_ID'] = (empty($user)) ? 0 : $user->getUsrId();
        $appDelegation->update($newData);
        $appThread = new AppThread();
        $appThread->update(
            [
                'APP_UID' => $appUid,
                'APP_THREAD_INDEX' => $fieldsDel['DEL_THREAD'],
                'DEL_INDEX' => $newDelIndex
            ]
        );


        $newData['PRO_UID'] = $fieldsDel['PRO_UID'];
        $newData['APP_UID'] = $appUid;
        $newData['APP_THREAD_INDEX'] = $fieldsDel['DEL_THREAD'];
        $newData['APP_DEL_INDEX'] = $delIndex;
        $newData['APP_TYPE'] = ($type != '' ? $type : 'REASSIGN');
        $newData['APP_STATUS'] = $dataFields['APP_STATUS'];
        $newData['APP_DELEGATION_USER'] = $currentUserUid;
        $newData['APP_ENABLE_ACTION_USER'] = $currentUserUid;
        $newData['APP_ENABLE_ACTION_DATE'] = date('Y-m-d H:i:s');
        $newData['APP_NUMBER'] = $fieldsDel['APP_NUMBER'];
        $appDelay = new AppDelay();
        $appDelay->create($newData);

        //Update searchindex
        if ($this->appSolr != null) {
            $this->appSolr->updateApplicationSearchIndex($appUid);
        }

        //Execute trigger
        $this->getExecuteTriggerProcess($appUid, 'REASSIGNED');


        //Delete record of the table LIST_UNASSIGNED
        $unassigned = new ListUnassigned();
        $unassigned->remove($appUid, $delIndex);

        return true;
    }

    /**
     * get all dynaforms that they have send it
     *
     * @name getAllDynaformsStepsToRevise
     * @param string $APP_UID
     * @return object
     */
    public function getAllDynaformsStepsToRevise($APP_UID)
    {
        $aCase = $this->loadCase($APP_UID);
        $oCriteria = new Criteria('workflow');
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_UID);
        $oCriteria->addSelectColumn(StepSupervisorPeer::PRO_UID);
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_TYPE_OBJ);
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_UID_OBJ);
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_POSITION);
        $oCriteria->add(StepSupervisorPeer::PRO_UID, $aCase['PRO_UID']);
        $oCriteria->add(StepSupervisorPeer::STEP_TYPE_OBJ, 'DYNAFORM');
        $oCriteria->addAscendingOrderByColumn(StepSupervisorPeer::STEP_POSITION);
        $oDataset = StepSupervisorPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        return $oDataset;
    }

    /**
     * get all inputs that they have send it
     *
     * @name getAllInputsStepsToRevise
     * @param string $APP_UID
     * @return object
     */
    public function getAllInputsStepsToRevise($APP_UID)
    {
        $aCase = $this->loadCase($APP_UID);
        $oCriteria = new Criteria('workflow');
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_UID);
        $oCriteria->addSelectColumn(StepSupervisorPeer::PRO_UID);
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_TYPE_OBJ);
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_UID_OBJ);
        $oCriteria->addSelectColumn(StepSupervisorPeer::STEP_POSITION);
        $oCriteria->add(StepSupervisorPeer::PRO_UID, $aCase['PRO_UID']);
        $oCriteria->add(StepSupervisorPeer::STEP_TYPE_OBJ, 'INPUT_DOCUMENT');
        $oCriteria->addAscendingOrderByColumn(StepSupervisorPeer::STEP_POSITION);
        $oDataset = StepSupervisorPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        return $oDataset;
    }

    /**
     * Get all upload document that they have send it
     * @todo we need to improve the code in this function
     *
     * @name getAllUploadedDocumentsCriteria
     * @param string $sProcessUID
     * @param string $sApplicationUID
     * @param string $sTasKUID
     * @param string $sUserUID
     * @param integer $delIndex
     *
     * @return object
     */
    public function getAllUploadedDocumentsCriteria($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, $delIndex = 0)
    {
        $conf = new Configurations();

        $confEnvSetting = $conf->getFormats();

        $listing = false;
        $oPluginRegistry = PluginRegistry::loadSingleton();
        if ($oPluginRegistry->existsTrigger(PM_CASE_DOCUMENT_LIST)) {
            $folderData = new folderData(null, null, $sApplicationUID, null, $sUserUID);
            $folderData->PMType = "INPUT";
            $folderData->returnList = true;
            $listing = $oPluginRegistry->executeTriggers(PM_CASE_DOCUMENT_LIST, $folderData);
        }

        $aObjectPermissions = $this->getAllObjects($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, $delIndex);
        //The logic of the code that continues is based on the fact that INPUT_DOCUMENTS contains the uid of ATTACHMENTS
        $aObjectPermissions['INPUT_DOCUMENTS'] = array_merge(
            $aObjectPermissions['INPUT_DOCUMENTS'],
            $aObjectPermissions['ATTACHMENTS']
        );

        if (!is_array($aObjectPermissions)) {
            $aObjectPermissions = array(
                'DYNAFORMS' => array(-1),
                'INPUT_DOCUMENTS' => array(-1),
                'OUTPUT_DOCUMENTS' => array(-1)
            );
        }
        if (!isset($aObjectPermissions['DYNAFORMS'])) {
            $aObjectPermissions['DYNAFORMS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['DYNAFORMS'])) {
                $aObjectPermissions['DYNAFORMS'] = array(-1);
            }
        }
        if (!isset($aObjectPermissions['INPUT_DOCUMENTS'])) {
            $aObjectPermissions['INPUT_DOCUMENTS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['INPUT_DOCUMENTS'])) {
                $aObjectPermissions['INPUT_DOCUMENTS'] = array(-1);
            }
        }
        if (!isset($aObjectPermissions['OUTPUT_DOCUMENTS'])) {
            $aObjectPermissions['OUTPUT_DOCUMENTS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['OUTPUT_DOCUMENTS'])) {
                $aObjectPermissions['OUTPUT_DOCUMENTS'] = array(-1);
            }
        }

        $aDelete = $this->getAllObjectsFrom($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, 'DELETE');
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('INPUT'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
        //$oCriteria->add(AppDocumentPeer::APP_DOC_UID, $aObjectPermissions['INPUT_DOCUMENTS'], Criteria::IN);
        $oCriteria->add(
                $oCriteria->getNewCriterion(
                                AppDocumentPeer::APP_DOC_UID, $aObjectPermissions['INPUT_DOCUMENTS'], Criteria::IN)->
                        addOr($oCriteria->getNewCriterion(AppDocumentPeer::USR_UID, array($sUserUID, '-1'), Criteria::IN))
        );

        $aConditions = array();
        $aConditions[] = array(AppDocumentPeer::APP_UID, AppDelegationPeer::APP_UID);
        $aConditions[] = array(AppDocumentPeer::DEL_INDEX, AppDelegationPeer::DEL_INDEX);
        $oCriteria->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        $oCriteria->add(AppDelegationPeer::PRO_UID, $sProcessUID);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aInputDocuments = array();
        $aInputDocuments[] = array(
            'APP_DOC_UID' => 'char',
            'DOC_UID' => 'char',
            'APP_DOC_COMMENT' => 'char',
            'APP_DOC_FILENAME' => 'char', 'APP_DOC_INDEX' => 'integer'
        );
        $oUser = new Users();
        while ($aRow = $oDataset->getRow()) {
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelegationPeer::APP_UID, $sApplicationUID);
            $oCriteria2->add(AppDelegationPeer::DEL_INDEX, $aRow['DEL_INDEX']);
            $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            $aRow2 = $oDataset2->getRow();
            $oTask = new Task();
            if ($oTask->taskExists($aRow2['TAS_UID'])) {
                $aTask = $oTask->load($aRow2['TAS_UID']);
            } else {
                $aTask = array('TAS_TITLE' => '(TASK DELETED)');
            }
            $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
            $lastVersion = $oAppDocument->getLastAppDocVersion($aRow['APP_DOC_UID'], $sApplicationUID);

            try {
                $aAux1 = $oUser->load($aAux['USR_UID']);

                $sUser = $conf->usersNameFormatBySetParameters($confEnvSetting["format"], $aAux1["USR_USERNAME"], $aAux1["USR_FIRSTNAME"], $aAux1["USR_LASTNAME"]);
            } catch (Exception $oException) {
                //$sUser = '(USER DELETED)';
                $sUser = '***';
            }

            $aFields = array(
                'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                'DOC_UID' => $aAux['DOC_UID'],
                'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                'TYPE' => $aAux['APP_DOC_TYPE'],
                'ORIGIN' => $aTask['TAS_TITLE'],
                'CREATE_DATE' => $aAux['APP_DOC_CREATE_DATE'],
                'CREATED_BY' => $sUser
            );
            if ($aFields['APP_DOC_FILENAME'] != '') {
                $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
            } else {
                $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
            }
            $aFields['POSITION'] = $_SESSION['STEP_POSITION'];
            $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
            if (in_array($aRow['APP_DOC_UID'], $aDelete['INPUT_DOCUMENTS'])) {
                $aFields['ID_DELETE'] = G::LoadTranslation('ID_DELETE');
            }

            $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_DOWNLOAD');
            $aFields['DOWNLOAD_LINK'] = "cases_ShowDocument?a=" . $aRow['APP_DOC_UID'] . "&v=" . $aRow['DOC_VERSION'];
            $aFields['DOC_VERSION'] = $aRow['DOC_VERSION'];
            if (is_array($listing)) {
                foreach ($listing as $folderitem) {
                    if ($folderitem->filename == $aRow['APP_DOC_UID']) {
                        $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_GET_EXTERNAL_FILE');
                        $aFields['DOWNLOAD_LINK'] = $folderitem->downloadScript;
                        continue;
                    }
                }
            }
            if ($lastVersion == $aRow['DOC_VERSION']) {
                //Show only last version
                $aInputDocuments[] = $aFields;
            }
            $oDataset->next();
        }
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('ATTACHED'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);

        $oCriteria->add(
                $oCriteria->getNewCriterion(
                                AppDocumentPeer::APP_DOC_UID, $aObjectPermissions['INPUT_DOCUMENTS'], Criteria::IN
                        )->
                        addOr($oCriteria->getNewCriterion(AppDocumentPeer::USR_UID, array($sUserUID, '-1'), Criteria::IN)));

        $aConditions = array();
        $aConditions[] = array(AppDocumentPeer::APP_UID, AppDelegationPeer::APP_UID);
        $aConditions[] = array(AppDocumentPeer::DEL_INDEX, AppDelegationPeer::DEL_INDEX);
        $oCriteria->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        $oCriteria->add(AppDelegationPeer::PRO_UID, $sProcessUID);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        while ($aRow = $oDataset->getRow()) {
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelegationPeer::APP_UID, $sApplicationUID);
            $oCriteria2->add(AppDelegationPeer::DEL_INDEX, $aRow['DEL_INDEX']);
            $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            $aRow2 = $oDataset2->getRow();
            $oTask = new Task();
            if ($oTask->taskExists($aRow2['TAS_UID'])) {
                $aTask = $oTask->load($aRow2['TAS_UID']);
            } else {
                $aTask = array('TAS_TITLE' => '(TASK DELETED)');
            }
            $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
            $lastVersion = $oAppDocument->getLastAppDocVersion($aRow['APP_DOC_UID'], $sApplicationUID);

            try {
                $aAux1 = $oUser->load($aAux['USR_UID']);

                $sUser = $conf->usersNameFormatBySetParameters($confEnvSetting["format"], $aAux1["USR_USERNAME"], $aAux1["USR_FIRSTNAME"], $aAux1["USR_LASTNAME"]);
            } catch (Exception $oException) {
                $sUser = '***';
            }

            $aFields = array(
                'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                'DOC_UID' => $aAux['DOC_UID'],
                'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                'TYPE' => $aAux['APP_DOC_TYPE'],
                'ORIGIN' => $aTask['TAS_TITLE'],
                'CREATE_DATE' => $aAux['APP_DOC_CREATE_DATE'],
                'CREATED_BY' => $sUser
            );
            if ($aFields['APP_DOC_FILENAME'] != '') {
                $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
            } else {
                $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
            }
            $aFields['POSITION'] = $_SESSION['STEP_POSITION'];

            $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
            if (in_array($aRow['APP_DOC_UID'], $aDelete['INPUT_DOCUMENTS'])) {
                $aFields['ID_DELETE'] = G::LoadTranslation('ID_DELETE');
            }

            $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_DOWNLOAD');
            $aFields['DOWNLOAD_LINK'] = "cases_ShowDocument?a=" . $aRow['APP_DOC_UID'];
            if ($lastVersion == $aRow['DOC_VERSION']) {
                //Show only last version
                $aInputDocuments[] = $aFields;
            }
            $oDataset->next();
        }
        // Get input documents added/modified by a supervisor - Begin
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('INPUT'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::DEL_INDEX, 100000);
        $oCriteria->addJoin(AppDocumentPeer::APP_UID, ApplicationPeer::APP_UID, Criteria::LEFT_JOIN);
        $oCriteria->add(ApplicationPeer::PRO_UID, $sProcessUID);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $oUser = new Users();
        while ($aRow = $oDataset->getRow()) {
            $aTask = array('TAS_TITLE' => '[ ' . G::LoadTranslation('ID_SUPERVISOR') . ' ]');
            $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
            $lastVersion = $oAppDocument->getLastAppDocVersion($aRow['APP_DOC_UID'], $sApplicationUID);

            try {
                $aAux1 = $oUser->load($aAux['USR_UID']);

                $sUser = $conf->usersNameFormatBySetParameters($confEnvSetting["format"], $aAux1["USR_USERNAME"], $aAux1["USR_FIRSTNAME"], $aAux1["USR_LASTNAME"]);
            } catch (Exception $oException) {
                $sUser = '***';
            }

            $aFields = array(
                'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                'DOC_UID' => $aAux['DOC_UID'],
                'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                'TYPE' => $aAux['APP_DOC_TYPE'],
                'ORIGIN' => $aTask['TAS_TITLE'],
                'CREATE_DATE' => $aAux['APP_DOC_CREATE_DATE'],
                'CREATED_BY' => $sUser
            );
            if ($aFields['APP_DOC_FILENAME'] != '') {
                $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
            } else {
                $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
            }
            $aFields['POSITION'] = $_SESSION['STEP_POSITION'];
            $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
            if (in_array($aRow['APP_DOC_UID'], $aDelete['INPUT_DOCUMENTS'])) {
                $aFields['ID_DELETE'] = G::LoadTranslation('ID_DELETE');
            }

            $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_DOWNLOAD');
            $aFields['DOWNLOAD_LINK'] = "cases_ShowDocument?a=" . $aRow['APP_DOC_UID'] . "&v=" . $aRow['DOC_VERSION'];
            $aFields['DOC_VERSION'] = $aRow['DOC_VERSION'];
            if (is_array($listing)) {
                foreach ($listing as $folderitem) {
                    if ($folderitem->filename == $aRow['APP_DOC_UID']) {
                        $aFields['DOWNLOAD_LABEL'] = G::LoadTranslation('ID_GET_EXTERNAL_FILE');
                        $aFields['DOWNLOAD_LINK'] = $folderitem->downloadScript;
                        continue;
                    }
                }
            }
            if ($lastVersion == $aRow['DOC_VERSION']) {
                //Show only last version
                $aInputDocuments[] = $aFields;
            }
            $oDataset->next();
        }
        // Get input documents added/modified by a supervisor - End
        global $_DBArray;
        $_DBArray['inputDocuments'] = $aInputDocuments;
        $_SESSION['_DBArray'] = $_DBArray;
        $oCriteria = new Criteria('dbarray');
        $oCriteria->setDBArrayTable('inputDocuments');
        $oCriteria->addDescendingOrderByColumn('CREATE_DATE');
        return $oCriteria;
    }

    /**
     * get all generate document
     *
     * @name getAllGeneratedDocumentsCriteria
     * @param string $sProcessUID
     * @param string $sApplicationUID
     * @param string $sTasKUID
     * @param string $sUserUID
     * @return object
     */
    public function getAllGeneratedDocumentsCriteria($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, $delIndex = 0)
    {
        $conf = new Configurations();

        $confEnvSetting = $conf->getFormats();
        $listing = false;
        $oPluginRegistry = PluginRegistry::loadSingleton();
        if ($oPluginRegistry->existsTrigger(PM_CASE_DOCUMENT_LIST)) {
            $folderData = new folderData(null, null, $sApplicationUID, null, $sUserUID);
            $folderData->PMType = "OUTPUT";
            $folderData->returnList = true;
            $listing = $oPluginRegistry->executeTriggers(PM_CASE_DOCUMENT_LIST, $folderData);
        }

        $aObjectPermissions = $this->getAllObjects($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, $delIndex);
        if (!is_array($aObjectPermissions)) {
            $aObjectPermissions = array(
                'DYNAFORMS' => array(-1),
                'INPUT_DOCUMENTS' => array(-1),
                'OUTPUT_DOCUMENTS' => array(-1)
            );
        }
        if (!isset($aObjectPermissions['DYNAFORMS'])) {
            $aObjectPermissions['DYNAFORMS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['DYNAFORMS'])) {
                $aObjectPermissions['DYNAFORMS'] = array(-1);
            }
        }
        if (!isset($aObjectPermissions['INPUT_DOCUMENTS'])) {
            $aObjectPermissions['INPUT_DOCUMENTS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['INPUT_DOCUMENTS'])) {
                $aObjectPermissions['INPUT_DOCUMENTS'] = array(-1);
            }
        }
        if (!isset($aObjectPermissions['OUTPUT_DOCUMENTS'])) {
            $aObjectPermissions['OUTPUT_DOCUMENTS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['OUTPUT_DOCUMENTS'])) {
                $aObjectPermissions['OUTPUT_DOCUMENTS'] = array(-1);
            }
        }
        $aDelete = $this->getAllObjectsFrom($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, 'DELETE');
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, 'OUTPUT');
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
        //$oCriteria->add(AppDocumentPeer::APP_DOC_UID, $aObjectPermissions['OUTPUT_DOCUMENTS'], Criteria::IN);
        $oCriteria->add(
                $oCriteria->getNewCriterion(
                        AppDocumentPeer::APP_DOC_UID, $aObjectPermissions['OUTPUT_DOCUMENTS'], Criteria::IN)->addOr($oCriteria->getNewCriterion(AppDocumentPeer::USR_UID, $sUserUID, Criteria::EQUAL))
        );

        $aConditions = array();
        $aConditions[] = array(AppDocumentPeer::APP_UID, AppDelegationPeer::APP_UID);
        $aConditions[] = array(AppDocumentPeer::DEL_INDEX, AppDelegationPeer::DEL_INDEX);
        $oCriteria->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        $oCriteria->add(AppDelegationPeer::PRO_UID, $sProcessUID);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aOutputDocuments = array();
        $aOutputDocuments[] = array(
            'APP_DOC_UID' => 'char',
            'DOC_UID' => 'char',
            'APP_DOC_COMMENT' => 'char',
            'APP_DOC_FILENAME' => 'char',
            'APP_DOC_INDEX' => 'integer'
        );
        $oUser = new Users();
        while ($aRow = $oDataset->getRow()) {
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelegationPeer::APP_UID, $sApplicationUID);
            $oCriteria2->add(AppDelegationPeer::DEL_INDEX, $aRow['DEL_INDEX']);
            $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            $aRow2 = $oDataset2->getRow();
            $oTask = new Task();
            if ($oTask->taskExists($aRow2['TAS_UID'])) {
                $aTask = $oTask->load($aRow2['TAS_UID']);
            } else {
                $aTask = array('TAS_TITLE' => '(TASK DELETED)');
            }
            $lastVersion = $oAppDocument->getLastDocVersion($aRow['DOC_UID'], $sApplicationUID);
            if ($lastVersion == $aRow['DOC_VERSION']) {
                //Only show last document Version
                $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
                //Get output Document information
                $oOutputDocument = new OutputDocument();
                $aGields = $oOutputDocument->load($aRow['DOC_UID']);
                //OUTPUTDOCUMENT
                $outDocTitle = $aGields['OUT_DOC_TITLE'];
                switch ($aGields['OUT_DOC_GENERATE']) {
                    //G::LoadTranslation(ID_DOWNLOAD)
                    case "PDF":
                        $fileDoc = 'javascript:alert("NO DOC")';
                        $fileDocLabel = " ";
                        $filePdf = 'cases_ShowOutputDocument?a=' .
                                $aRow['APP_DOC_UID'] . '&v=' . $aRow['DOC_VERSION'] . '&ext=pdf&random=' . rand();
                        $filePdfLabel = ".pdf";
                        if (is_array($listing)) {
                            foreach ($listing as $folderitem) {
                                if (($folderitem->filename == $aRow['APP_DOC_UID']) && ($folderitem->type == "PDF")) {
                                    $filePdfLabel = G::LoadTranslation('ID_GET_EXTERNAL_FILE') . " .pdf";
                                    $filePdf = $folderitem->downloadScript;
                                    continue;
                                }
                            }
                        }
                        break;
                    case "DOC":
                        $fileDoc = 'cases_ShowOutputDocument?a=' .
                                $aRow['APP_DOC_UID'] . '&v=' . $aRow['DOC_VERSION'] . '&ext=doc&random=' . rand();
                        $fileDocLabel = ".doc";
                        $filePdf = 'javascript:alert("NO PDF")';
                        $filePdfLabel = " ";
                        if (is_array($listing)) {
                            foreach ($listing as $folderitem) {
                                if (($folderitem->filename == $aRow['APP_DOC_UID']) && ($folderitem->type == "DOC")) {
                                    $fileDocLabel = G::LoadTranslation('ID_GET_EXTERNAL_FILE') . " .doc";
                                    $fileDoc = $folderitem->downloadScript;
                                    continue;
                                }
                            }
                        }
                        break;
                    case "BOTH":
                        $fileDoc = 'cases_ShowOutputDocument?a=' .
                                $aRow['APP_DOC_UID'] . '&v=' . $aRow['DOC_VERSION'] . '&ext=doc&random=' . rand();
                        $fileDocLabel = ".doc";
                        if (is_array($listing)) {
                            foreach ($listing as $folderitem) {
                                if (($folderitem->filename == $aRow['APP_DOC_UID']) && ($folderitem->type == "DOC")) {
                                    $fileDocLabel = G::LoadTranslation('ID_GET_EXTERNAL_FILE') . " .doc";
                                    $fileDoc = $folderitem->downloadScript;
                                    continue;
                                }
                            }
                        }
                        $filePdf = 'cases_ShowOutputDocument?a=' .
                                $aRow['APP_DOC_UID'] . '&v=' . $aRow['DOC_VERSION'] . '&ext=pdf&random=' . rand();
                        $filePdfLabel = ".pdf";

                        if (is_array($listing)) {
                            foreach ($listing as $folderitem) {
                                if (($folderitem->filename == $aRow['APP_DOC_UID']) && ($folderitem->type == "PDF")) {
                                    $filePdfLabel = G::LoadTranslation('ID_GET_EXTERNAL_FILE') . " .pdf";
                                    $filePdf = $folderitem->downloadScript;
                                    continue;
                                }
                            }
                        }
                        break;
                }

                try {
                    $aAux1 = $oUser->load($aAux['USR_UID']);

                    $sUser = $conf->usersNameFormatBySetParameters($confEnvSetting["format"], $aAux1["USR_USERNAME"], $aAux1["USR_FIRSTNAME"], $aAux1["USR_LASTNAME"]);
                } catch (Exception $oException) {
                    $sUser = '(USER DELETED)';
                }

                //if both documents were generated, we choose the pdf one, only if doc was
                //generate then choose the doc file.
                $firstDocLink = $filePdf;
                $firstDocLabel = $filePdfLabel;
                if ($aGields['OUT_DOC_GENERATE'] == 'DOC') {
                    $firstDocLink = $fileDoc;
                    $firstDocLabel = $fileDocLabel;
                }

                $aFields = array(
                    'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                    'DOC_UID' => $aAux['DOC_UID'],
                    'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                    'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                    'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                    'ORIGIN' => $aTask['TAS_TITLE'],
                    'CREATE_DATE' => $aAux['APP_DOC_CREATE_DATE'],
                    'CREATED_BY' => $sUser,
                    'FILEDOC' => $fileDoc,
                    'FILEPDF' => $filePdf,
                    'OUTDOCTITLE' => $outDocTitle,
                    'DOC_VERSION' => $aAux['DOC_VERSION'],
                    'TYPE' => $aAux['APP_DOC_TYPE'] . ' ' . $aGields['OUT_DOC_GENERATE'],
                    'DOWNLOAD_LINK' => $firstDocLink,
                    'DOWNLOAD_FILE' => $aAux['APP_DOC_FILENAME'] . $firstDocLabel
                );

                if (trim($fileDocLabel) != '') {
                    $aFields['FILEDOCLABEL'] = $fileDocLabel;
                }
                if (trim($filePdfLabel) != '') {
                    $aFields['FILEPDFLABEL'] = $filePdfLabel;
                }
                if ($aFields['APP_DOC_FILENAME'] != '') {
                    $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
                } else {
                    $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
                }
                $aFields['POSITION'] = $_SESSION['STEP_POSITION'];
                $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
                if (in_array($aRow['APP_DOC_UID'], $aObjectPermissions['OUTPUT_DOCUMENTS'])) {
                    if (in_array($aRow['APP_DOC_UID'], $aDelete['OUTPUT_DOCUMENTS'])) {
                        $aFields['ID_DELETE'] = G::LoadTranslation('ID_DELETE');
                    }
                }
                $aOutputDocuments[] = $aFields;
            }
            $oDataset->next();
        }
        global $_DBArray;
        $_DBArray['outputDocuments'] = $aOutputDocuments;
        $_SESSION['_DBArray'] = $_DBArray;
        $oCriteria = new Criteria('dbarray');
        $oCriteria->setDBArrayTable('outputDocuments');
        $oCriteria->addDescendingOrderByColumn('CREATE_DATE');
        return $oCriteria;
    }

    /**
     * get all dynaforms in a task
     *
     * @name getallDynaformsCriteria
     * @param string $sProcessUID
     * @param string $sApplicationUID
     * @param string $sTasKUID
     * @param string $sUserUID
     * @return object
     */
    public function getallDynaformsCriteria($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, $delIndex = 0)
    {
        $aObjectPermissions = $this->getAllObjects($sProcessUID, $sApplicationUID, $sTasKUID, $sUserUID, $delIndex);
        if (!is_array($aObjectPermissions)) {
            $aObjectPermissions = array(
                'DYNAFORMS' => array(-1),
                'INPUT_DOCUMENTS' => array(-1),
                'OUTPUT_DOCUMENTS' => array(-1)
            );
        }
        if (!isset($aObjectPermissions['DYNAFORMS'])) {
            $aObjectPermissions['DYNAFORMS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['DYNAFORMS'])) {
                $aObjectPermissions['DYNAFORMS'] = array(-1);
            }
        }
        if (!isset($aObjectPermissions['INPUT_DOCUMENTS'])) {
            $aObjectPermissions['INPUT_DOCUMENTS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['INPUT_DOCUMENTS'])) {
                $aObjectPermissions['INPUT_DOCUMENTS'] = array(-1);
            }
        }
        if (!isset($aObjectPermissions['OUTPUT_DOCUMENTS'])) {
            $aObjectPermissions['OUTPUT_DOCUMENTS'] = array(-1);
        } else {
            if (!is_array($aObjectPermissions['OUTPUT_DOCUMENTS'])) {
                $aObjectPermissions['OUTPUT_DOCUMENTS'] = array(-1);
            }
        }
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(ApplicationPeer::APP_UID, $sApplicationUID);
        $oCriteria->addJoin(ApplicationPeer::PRO_UID, StepPeer::PRO_UID);
        $oCriteria->addJoin(StepPeer::STEP_UID_OBJ, DynaformPeer::DYN_UID);
        $oCriteria->add(StepPeer::STEP_TYPE_OBJ, 'DYNAFORM');
        $oCriteria->add(StepPeer::STEP_UID_OBJ, $aObjectPermissions['DYNAFORMS'], Criteria::IN);

        //These fields are missing now is completed
        $oCriteria->addSelectColumn(DynaformPeer::DYN_UID);
        $oCriteria->addSelectColumn(DynaformPeer::DYN_TITLE);
        $oCriteria->addSelectColumn(DynaformPeer::DYN_TYPE);
        $oCriteria->addSelectColumn(DynaformPeer::DYN_FILENAME);
        $oCriteria->addSelectColumn(ApplicationPeer::PRO_UID);
        ///-- Adding column STEP_POSITION for standardization
        $oCriteria->addSelectColumn(StepPeer::STEP_POSITION);

        $oCriteria->addAscendingOrderByColumn(StepPeer::STEP_POSITION);
        $oCriteria->setDistinct();


        $oDataset = DynaformPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aInputDocuments = array();
        $aInputDocuments[] = array(
            'DYN_TITLE' => 'char'
        );

        while ($aRow = $oDataset->getRow()) {
            $aFields['DYN_TITLE'] = $aRow['DYN_TITLE'];
            $aFields['DYN_UID'] = $aRow['DYN_UID'];
            $aFields['EDIT'] = G::LoadTranslation('ID_EDIT');
            $aFields['PRO_UID'] = $sProcessUID;
            $aFields['APP_UID'] = $sApplicationUID;
            $aFields['TAS_UID'] = $sTasKUID;
            $aInputDocuments[] = $aFields;
            $oDataset->next();
        }

        $distinctArray = $aInputDocuments;
        $distinctArrayBase = $aInputDocuments;
        $distinctOriginal = array();
        foreach ($distinctArray as $distinctArrayKey => $distinctArrayValue) {
            $distinctOriginalPush = 1;
            foreach ($distinctOriginal as $distinctOriginalKey => $distinctOriginalValue) {
                if ($distinctArrayValue == $distinctOriginalValue) {
                    $distinctOriginalPush = 0;
                }
            }
            if ($distinctOriginalPush == 1) {
                $distinctOriginal[] = $distinctArrayValue;
            }
        }
        $aInputDocuments = $distinctOriginal;

        global $_DBArray;
        $_DBArray['Dynaforms'] = $aInputDocuments;
        $_SESSION['_DBArray'] = $_DBArray;
        $oCriteria = new Criteria('dbarray');
        $oCriteria->setDBArrayTable('Dynaforms');
        $oCriteria->setDistinct();
        //$oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_CREATE_DATE);
        return $oCriteria;
    }

    /**
     * @param $aTaskInfo
     * @param $arrayData
     * @param $typeSend
     * @param $from
     * @return array
     * @throws Exception
     */
    public function loadDataSendEmail($aTaskInfo, $arrayData, $from, $typeSend)
    {
        $eServer = new \ProcessMaker\BusinessModel\EmailServer();
        $dataLastEmail = array();

        switch ($typeSend) {
            case 'LAST':
                if (isset($aTaskInfo['TAS_DEF_SUBJECT_MESSAGE']) && $aTaskInfo['TAS_DEF_SUBJECT_MESSAGE'] != '') {
                    $sSubject = G::replaceDataField($aTaskInfo['TAS_DEF_SUBJECT_MESSAGE'], $arrayData, 'mysql', false);
                } else {
                    $sSubject = G::LoadTranslation('ID_MESSAGE_SUBJECT_DERIVATION');
                }

                $oConf = new Configurations;
                $oConf->loadConfig($x, 'TAS_EXTRA_PROPERTIES', $aTaskInfo['TAS_UID'], '', '');
                $conf = $oConf->aConfig;

                $pathEmail = PATH_DATA_SITE . "mailTemplates" . PATH_SEP . $aTaskInfo["PRO_UID"] . PATH_SEP;
                $swtplDefault = 0;
                $sBody = null;

                if (isset($conf["TAS_DEF_MESSAGE_TYPE"]) &&
                        isset($conf["TAS_DEF_MESSAGE_TEMPLATE"]) &&
                        $conf["TAS_DEF_MESSAGE_TYPE"] == "template" &&
                        $conf["TAS_DEF_MESSAGE_TEMPLATE"] != ""
                ) {
                    if ($conf["TAS_DEF_MESSAGE_TEMPLATE"] == "alert_message.html") {
                        $swtplDefault = 1;
                    }

                    $fileTemplate = $pathEmail . $conf["TAS_DEF_MESSAGE_TEMPLATE"];

                    if (!file_exists($fileTemplate)) {
                        $tempale = PATH_CORE . "templates" . PATH_SEP . "mails" . PATH_SEP . "alert_message.html";
                        $copied = @copy($tempale, $fileTemplate);
                        if ($copied) {
                            $dataTemplate = array("prf_filename" => $conf["TAS_DEF_MESSAGE_TEMPLATE"],
                                "prf_path" => $fileTemplate,
                                "pro_uid" => $aTaskInfo["PRO_UID"],
                                "usr_uid" => "00000000000000000000000000000001",
                                "prf_uid" => G::generateUniqueID(),
                                "prf_type" => "file",
                                "prf_create_date" => date("Y-m-d H:i:s"));
                            $filesManager = new ProcessMaker\BusinessModel\FilesManager();
                            $filesManager->addProcessFilesManagerInDb($dataTemplate);
                        } else {
                            throw (new Exception("Template file \"$fileTemplate\" does not exist."));
                        }
                    }

                    $sBody = file_get_contents($fileTemplate);
                } else {
                    $sBody = nl2br($aTaskInfo['TAS_DEF_MESSAGE']);
                }
                $aConfiguration = (!is_null(\EmailServerPeer::retrieveByPK($aTaskInfo['TAS_EMAIL_SERVER_UID']))) ?
                        $eServer->getEmailServer($aTaskInfo['TAS_EMAIL_SERVER_UID'], true) :
                        $eServer->getEmailServerDefault();
                $msgError = '';
                if (empty($aConfiguration)) {
                    $msgError = G::LoadTranslation('ID_THE_DEFAULT_CONFIGURATION');
                    $aConfiguration['MESS_ENGINE'] = '';
                } else {
                    $aConfiguration['SMTPSecure'] = $aConfiguration['SMTPSECURE'];
                }
                if ($aTaskInfo['TAS_NOT_EMAIL_FROM_FORMAT']) {
                    $fromName = $aConfiguration['MESS_FROM_NAME'];
                    $fromMail = $aConfiguration['MESS_FROM_MAIL'];
                    $from = $fromName . (($fromMail != '') ? ' <' . $fromMail . '>' : '');
                    //If the configuration was not configured correctly
                    if (empty($fromMail)) {
                        $message = 'Email server';
                        $context = [
                            'appUid' => $arrayData['APPLICATION'],
                            'usrUid' => $arrayData['USER_LOGGED'],
                            'appNumber' => $arrayData['APP_NUMBER'],
                            'tasUid' => $arrayData['TASK'],
                            'proUid' => $aTaskInfo['PRO_UID'],
                            'appMessageStatus' => 'pending',
                            'subject' => $sSubject,
                            'from' => $from,
                            'action' => G::LoadTranslation('ID_EMAIL_SERVER_FROM_MAIL_EMPTY')
                        ];
                        Log::channel(':EmailServer')->warning($message, Bootstrap::context($context));
                    }
                }
                $dataLastEmail['msgError'] = $msgError;
                $dataLastEmail['configuration'] = $aConfiguration;
                $dataLastEmail['subject'] = $sSubject;
                $dataLastEmail['pathEmail'] = $pathEmail;
                $dataLastEmail['swtplDefault'] = $swtplDefault;
                $dataLastEmail['body'] = $sBody;
                $dataLastEmail['from'] = $from;
                break;
            case 'RECEIVE':
                if (isset($aTaskInfo['TAS_RECEIVE_SUBJECT_MESSAGE']) && $aTaskInfo['TAS_RECEIVE_SUBJECT_MESSAGE'] != '') {
                    $sSubject = G::replaceDataField($aTaskInfo['TAS_RECEIVE_SUBJECT_MESSAGE'], $arrayData, 'mysql', false);
                } else {
                    $sSubject = G::LoadTranslation('ID_MESSAGE_SUBJECT_DERIVATION');
                }

                $pathEmail = PATH_DATA_SITE . "mailTemplates" . PATH_SEP . $aTaskInfo["PRO_UID"] . PATH_SEP;
                $swtplDefault = 0;
                $sBody = null;

                if (isset($aTaskInfo["TAS_RECEIVE_MESSAGE_TYPE"]) &&
                        isset($aTaskInfo["TAS_RECEIVE_MESSAGE_TEMPLATE"]) &&
                        $aTaskInfo["TAS_RECEIVE_MESSAGE_TYPE"] == "template" &&
                        $aTaskInfo["TAS_RECEIVE_MESSAGE_TEMPLATE"] != ""
                ) {
                    if ($aTaskInfo["TAS_RECEIVE_MESSAGE_TEMPLATE"] == "alert_message.html") {
                        $swtplDefault = 1;
                    }

                    $fileTemplate = $pathEmail . $aTaskInfo["TAS_RECEIVE_MESSAGE_TEMPLATE"];

                    if (!file_exists($fileTemplate)) {
                        $tempale = PATH_CORE . "templates" . PATH_SEP . "mails" . PATH_SEP . "alert_message.html";
                        $copied = @copy($tempale, $fileTemplate);
                        if ($copied) {
                            $dataTemplate = array("prf_filename" => $aTaskInfo["TAS_RECEIVE_MESSAGE_TEMPLATE"],
                                "prf_path" => $fileTemplate,
                                "pro_uid" => $aTaskInfo["PRO_UID"],
                                "usr_uid" => "00000000000000000000000000000001",
                                "prf_uid" => G::generateUniqueID(),
                                "prf_type" => "file",
                                "prf_create_date" => date("Y-m-d H:i:s"));
                            $filesManager = new ProcessMaker\BusinessModel\FilesManager();
                            $filesManager->addProcessFilesManagerInDb($dataTemplate);
                        } else {
                            throw (new Exception("Template file \"$fileTemplate\" does not exist."));
                        }
                    }

                    $sBody = file_get_contents($fileTemplate);
                } else {
                    $sBody = nl2br($aTaskInfo['TAS_RECEIVE_MESSAGE']);
                }
                $aConfiguration = (!is_null(\EmailServerPeer::retrieveByPK($aTaskInfo['TAS_RECEIVE_SERVER_UID']))) ?
                        $eServer->getEmailServer($aTaskInfo['TAS_RECEIVE_SERVER_UID'], true) :
                        $eServer->getEmailServerDefault();
                $msgError = '';
                if (empty($aConfiguration)) {
                    $msgError = G::LoadTranslation('ID_THE_DEFAULT_CONFIGURATION');
                    $aConfiguration['MESS_ENGINE'] = '';
                } else {
                    $aConfiguration['SMTPSecure'] = $aConfiguration['SMTPSECURE'];
                }
                if ($aTaskInfo['TAS_RECEIVE_EMAIL_FROM_FORMAT']) {
                    $fromName = $aConfiguration['MESS_FROM_NAME'];
                    $fromMail = $aConfiguration['MESS_FROM_MAIL'];
                    $from = $fromName . (($fromMail != '') ? ' <' . $fromMail . '>' : '');
                    //If the configuration was not configured correctly
                    if (empty($fromMail)) {
                        $message = 'Email server';
                        $context = [
                            'appUid' => $arrayData['APPLICATION'],
                            'usrUid' => $arrayData['USER_LOGGED'],
                            'appNumber' => $arrayData['APP_NUMBER'],
                            'tasUid' => $arrayData['TASK'],
                            'proUid' => $aTaskInfo['PRO_UID'],
                            'appMessageStatus' => 'pending',
                            'subject' => $sSubject,
                            'from' => $from,
                            'action' => G::LoadTranslation('ID_EMAIL_SERVER_FROM_MAIL_EMPTY')
                        ];
                        Log::channel(':EmailServer')->warning($message, Bootstrap::context($context));
                    }
                }
                $dataLastEmail['msgError'] = $msgError;
                $dataLastEmail['configuration'] = $aConfiguration;
                $dataLastEmail['subject'] = $sSubject;
                $dataLastEmail['pathEmail'] = $pathEmail;
                $dataLastEmail['swtplDefault'] = $swtplDefault;
                $dataLastEmail['body'] = $sBody;
                $dataLastEmail['from'] = $from;
                break;
        }
        return $dataLastEmail;
    }

    /**
     * This function send an email for each task in $arrayTask if $to is definded
     *
     * @param $dataLastEmail
     * @param $arrayData
     * @param $arrayTask
     * @return void
     *
     * @see \Cases->sendNotifications()
     */
    public function sendMessage($dataLastEmail, $arrayData, $arrayTask)
    {
        foreach ($arrayTask as $aTask) {
            //Check and fix if Task Id is complex
            if (strpos($aTask['TAS_UID'], "/") !== false) {
                $aux = explode("/", $aTask['TAS_UID']);
                if (isset($aux[1])) {
                    $aTask['TAS_UID'] = $aux[1];
                }
            }
            //if the next is EOP dont send notification and continue with the next
            if ($aTask['TAS_UID'] === '-1') {
                continue;
            }
            if (isset($aTask['DEL_INDEX'])) {
                $arrayData2 = $arrayData;
                $appDelegation = AppDelegationPeer::retrieveByPK($dataLastEmail['applicationUid'], $aTask['DEL_INDEX']);
                if (!is_null($appDelegation)) {
                    $oTaskUpd = new Task();
                    $aTaskUpdate = $oTaskUpd->load($appDelegation->getTasUid());
                    $arrayData2['TAS_TITLE'] = $aTaskUpdate['TAS_TITLE'];
                    $arrayData2['DEL_TASK_DUE_DATE'] = $appDelegation->getDelTaskDueDate();
                }
            } else {
                $arrayData2 = $arrayData;
            }

            if (isset($aTask['USR_UID']) && !empty($aTask['USR_UID'])) {
                $user = new \ProcessMaker\BusinessModel\User();
                $arrayUserData = $user->getUser($aTask['USR_UID'], true);
                $arrayData2 = \ProcessMaker\Util\DateTime::convertUtcToTimeZone($arrayData2,
                    (trim($arrayUserData['USR_TIME_ZONE']) != '') ? trim($arrayUserData['USR_TIME_ZONE']) :
                                \ProcessMaker\Util\System::getTimeZone());
            } else {
                $arrayData2 = \ProcessMaker\Util\DateTime::convertUtcToTimeZone($arrayData2);
            }
            $body2 = G::replaceDataGridField($dataLastEmail['body'], $arrayData2, false);
            $to = null;
            $cc = '';
            if ($aTask['TAS_UID'] != '-1') {
                $respTo = $this->getTo($aTask['TAS_UID'], $aTask['USR_UID'], $arrayData);
                $to = $respTo['to'];
                $cc = $respTo['cc'];
            }

            if ($aTask ["TAS_ASSIGN_TYPE"] === "SELF_SERVICE") {
                if ($dataLastEmail['swtplDefault'] == 1) {
                    G::verifyPath($dataLastEmail['pathEmail'], true); // Create if it does not exist
                    $fileTemplate = $dataLastEmail['pathEmail'] . G::LoadTranslation('ID_UNASSIGNED_MESSAGE');

                    if ((!file_exists($fileTemplate)) && file_exists(PATH_TPL . "mails" . PATH_SEP .
                                    G::LoadTranslation('ID_UNASSIGNED_MESSAGE'))
                    ) {
                        @copy(PATH_TPL . "mails" . PATH_SEP . G::LoadTranslation('ID_UNASSIGNED_MESSAGE'), $fileTemplate);
                    }
                    $body2 = G::replaceDataField(file_get_contents($fileTemplate), $arrayData2, 'mysql', false);
                }
            }

            if ($to != null) {
                $spool = new SpoolRun();

                //Load the TAS_ID
                if (!isset($arrayData['TAS_ID'])) {
                    $task= new Task();
                    $taskId = $task->load($arrayData['TASK'])['TAS_ID'];
                } else {
                    $taskId = $arrayData['TAS_ID'];
                }
                //Load the PRO_ID
                if (!isset($arrayData['PRO_ID'])) {
                    $process = new Process();
                    $proId = $process->load($arrayData['PROCESS'])['PRO_ID'];
                } else {
                    $proId = $arrayData['PRO_ID'];
                }

                $spool->setConfig($dataLastEmail['configuration']);
                $messageArray = AppMessage::buildMessageRow(
                    '',
                    $dataLastEmail['applicationUid'],
                    $dataLastEmail['delIndex'],
                    WsBase::MESSAGE_TYPE_TASK_NOTIFICATION,
                    $dataLastEmail['subject'],
                    $dataLastEmail['from'],
                    $to,
                    $body2,
                    $cc,
                    '',
                    '',
                    '',
                    'pending',
                    1,
                    $dataLastEmail['msgError'],
                    true,
                    isset($arrayData['APP_NUMBER']) ? $arrayData['APP_NUMBER'] : 0,
                    $proId,
                    $taskId
                );
                $spool->create($messageArray);

                if ($dataLastEmail['msgError'] == '') {
                    if (($dataLastEmail['configuration']["MESS_BACKGROUND"] == "") ||
                            ($dataLastEmail['configuration']["MESS_TRY_SEND_INMEDIATLY"] == "1")
                    ) {
                        $spool->sendMail();
                    }
                }
            }
        }
    }

    /**
     * This function send an email notification when tas_send_last_email = true
     * The users assigned to the next task will receive a custom email message when the case is routed
     *
     * @param string $taskUid
     * @param array $arrayTask
     * @param array $arrayData
     * @param string $applicationUid
     * @param integer $delIndex
     * @param string $from
     *
     * @return bool
     * @throws Exception
     */
    public function sendNotifications($taskUid, $arrayTask, $arrayData, $applicationUid, $delIndex, $from = '')
    {
        try {
            $arrayApplicationData = $this->loadCase($applicationUid);
            $arrayData['APP_NUMBER'] = $arrayApplicationData['APP_NUMBER'];

            $task = new Task();
            $taskInfo = $task->load($taskUid);

            if ($taskInfo['TAS_SEND_LAST_EMAIL'] == 'TRUE') {
                $dataLastEmail = $this->loadDataSendEmail($taskInfo, $arrayData, $from, 'LAST');
                $dataLastEmail['applicationUid'] = $applicationUid;
                $dataLastEmail['delIndex'] = $delIndex;
                //Load the TAS_ID
                if (isset($taskInfo['TAS_ID'])) {
                    $arrayData['TAS_ID'] = $taskInfo['TAS_ID'];
                }
                $this->sendMessage($dataLastEmail, $arrayData, $arrayTask);
            } else {
                return false;
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get the to email
     *
     * @param string $taskUid
     * @param string $userUid
     * @param array $caseData
     *
     * @return array
    */
    public function getTo($taskUid, $userUid, $caseData)
    {
        $response = [];
        $to = '';
        $cc = '';
        $task = TaskPeer::retrieveByPK($taskUid);
        switch ($task->getTasAssignType()) {
            case 'SELF_SERVICE':
                // Load the user that can claim the case
                $light = new NotificationDevice();
                $userUids = $light->getTaskUserSelfService($taskUid, $caseData);
                // Get the user information for send the email
                foreach ($userUids as $userUid) {
                    $u = new Users();
                    $user = $u->load($userUid);
                    $toAux = ((!empty($user['USR_FIRSTNAME']) || !empty($user['USR_LASTNAME'])) ? $user['USR_FIRSTNAME'] . ' ' . $user['USR_LASTNAME'] . ' ' : '') . '<' . $user['USR_EMAIL'] . '>';
                    if (empty($to)) {
                        $to = $toAux;
                    } else {
                        $to .= ',' . $toAux;
                    }
                }
                $response ['to'] = $to;
                $response ['cc'] = '';
                break;
            default:
                // Load the user assigned for send the email
                if (!empty($userUid)) {
                    $u = new Users();
                    $user = $u->load($userUid);
                    $to = ((!empty($user['USR_FIRSTNAME']) || !empty($user['USR_LASTNAME'])) ? $user['USR_FIRSTNAME'] . ' ' . $user['USR_LASTNAME'] . ' ' : '') . '<' . $user['USR_EMAIL'] . '>';
                }
                $response ['to'] = $to;
                $response ['cc'] = '';
                break;
        }
        return $response;
    }

    /**
     * Obtain all user permits for Dynaforms, Input and output documents
     * function getAllObjects ($proUid, $appUid, $tasUid, $usrUid)
     *
     * @access public
     * @param string $proUid, Process ID
     * @param string $appUid, Application ID,
     * @param string $tasUid, Task ID
     * @param string $usrUid, User ID
     * @param integer $delIndex, User ID
     *
     * @return array within all user permissions all objects' types
     */
    public function getAllObjects($proUid, $appUid, $tasUid = '', $usrUid = '', $delIndex = 0)
    {
        $permissionAction = ['VIEW', 'BLOCK', 'DELETE']; //TO COMPLETE
        $mainObjects = [];
        $resultObjects = [];

        foreach ($permissionAction as $action) {
            $mainObjects[$action] = $this->getAllObjectsFrom($proUid, $appUid, $tasUid, $usrUid, $action, $delIndex);
        }

        //We will review data with VIEW and BLOCK
        //Dynaforms BLOCK it means does not show in the list
        $resultObjects['DYNAFORMS'] = G::arrayDiff(
            $mainObjects['VIEW']['DYNAFORMS'], $mainObjects['BLOCK']['DYNAFORMS']
        );
        //Input BLOCK it means does not show in the list
        $resultObjects['INPUT_DOCUMENTS'] = G::arrayDiff(
            $mainObjects['VIEW']['INPUT_DOCUMENTS'], $mainObjects['BLOCK']['INPUT_DOCUMENTS']
        );
        //Output BLOCK it means does not show in the list
        $resultObjects['OUTPUT_DOCUMENTS'] = array_merge_recursive(
            G::arrayDiff($mainObjects['VIEW']['OUTPUT_DOCUMENTS'], $mainObjects['BLOCK']['OUTPUT_DOCUMENTS']), G::arrayDiff($mainObjects['DELETE']['OUTPUT_DOCUMENTS'], $mainObjects['BLOCK']['OUTPUT_DOCUMENTS'])
        );
        //Case notes BLOCK it means does not show in the list
        $resultObjects['CASES_NOTES'] = G::arrayDiff(
            $mainObjects['VIEW']['CASES_NOTES'], $mainObjects['BLOCK']['CASES_NOTES']
        );
        //Summary form it means does not show in the list
        $resultObjects['SUMMARY_FORM'] = isset($mainObjects['VIEW']['SUMMARY_FORM']) ? $mainObjects['VIEW']['SUMMARY_FORM'] : 0;
        //Attachments BLOCK it means does not show in the list
        $resultObjects['ATTACHMENTS'] = G::arrayDiff(
            $mainObjects['VIEW']['ATTACHMENTS'], $mainObjects['BLOCK']['ATTACHMENTS']
        );
        array_push($resultObjects["DYNAFORMS"], -1, -2);
        array_push($resultObjects['INPUT_DOCUMENTS'], -1);
        array_push($resultObjects['OUTPUT_DOCUMENTS'], -1);
        array_push($resultObjects['CASES_NOTES'], -1);
        array_push($resultObjects['ATTACHMENTS'], -1);

        return $resultObjects;
    }

    /**
     * Obtain all object permissions for Dynaforms, Input, Output and Message history
     * This function return information about a specific object permissions or for all = ANY
     *
     * @access public
     * @param string $proUid
     * @param string $appUid
     * @param string $tasUid
     * @param string $usrUid
     * @param string $action some action [VIEW, BLOCK, RESEND]
     * @param integer $delIndex
     *
     * @return array within all user permissions all objects' types
     */
    public function getAllObjectsFrom($proUid, $appUid, $tasUid = '', $usrUid = '', $action = '', $delIndex = 0)
    {
        $caseData = $this->loadCase($appUid);

        if ($delIndex != 0) {
            $appDelay = new AppDelay();

            if ($appDelay->isPaused($appUid, $delIndex)) {
                $caseData["APP_STATUS"] = "PAUSED";
            }
        }

        $userPermissions = [];
        $groupPermissions = [];

        $objectPermission = new ObjectPermission();
        $userPermissions = $objectPermission->verifyObjectPermissionPerUser($usrUid, $proUid, $tasUid, $action, $caseData);
        $groupPermissions = $objectPermission->verifyObjectPermissionPerGroup($usrUid, $proUid, $tasUid, $action, $caseData);
        $permissions = array_merge($userPermissions, $groupPermissions);

        $resultDynaforms = [];
        $resultInputs = [];
        $resultAttachments = [];
        $resultOutputs = [];
        $resultCaseNotes = 0;
        $resultSummary = 0;
        $resultMessages = [];
        $resultReassignCases = [];

        foreach ($permissions as $row) {
            $userUid = $row['USR_UID'];
            $opUserRelation = $row['OP_USER_RELATION'];
            $originTask = $row['OP_TASK_SOURCE']; // We can see the steps related to this task
            $targetTask = $row['TAS_UID']; // We can see the steps related to this task if is the current task
            if (!empty($targetTask)) {
                $opTaskSource = $targetTask;
            } else {
                $opTaskSource = $originTask;
            }
            $opParticipated = (int) $row['OP_PARTICIPATE'];
            $opType = $row['OP_OBJ_TYPE'];
            $opObjUid = $row['OP_OBJ_UID'];
            $obCaseStatus = $row['OP_CASE_STATUS'];

            // The values of obCaseStatus is [ALL, COMPLETED, DRAFT, TO_DO, PAUSED]
            // If the case is todo and we need the participate
            // but we did not participated did not validate nothing and return array empty
            $swParticipate = false; // must be false for default
            if ($opParticipated === 1) {
                $criteria = new Criteria('workflow');
                $criteria->add(AppDelegationPeer::USR_UID, $usrUid);
                $criteria->add(AppDelegationPeer::APP_UID, $appUid);
                $dataset = AppDelegationPeer::doSelectRS($criteria);
                $dataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                $dataset->next();
                $row = $dataset->getRow();
                if (!is_array($row)) {
                    //The user was not participated in the case and the participation is required
                    $swParticipate = true;
                }
            }

            //If user can be see the objects process
            //We will be prepare the data relate to the Type can be ANY, DYNAFORM, INPUT, OUTPUT, ...
            if (!$swParticipate) {
                switch ($opType) {
                    case 'ANY':
                        //For dynaforms
                        $listDynaform = BusinessModelCases::dynaFormsByApplication(
                            $appUid,
                            $opTaskSource,
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultDynaforms = array_merge($resultDynaforms, $listDynaform);

                        //For Ouputs
                        $listOutput = $objectPermission->objectPermissionByOutputInput(
                            $appUid,
                            $proUid,
                            $opTaskSource,
                            'OUTPUT',
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultOutputs = array_merge($resultOutputs, $listOutput);

                        //For Inputs
                        $listInput = $objectPermission->objectPermissionByOutputInput(
                            $appUid,
                            $proUid,
                            $opTaskSource,
                            'INPUT',
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultInputs = array_merge($resultInputs, $listInput);

                        //For Attachment
                        $listAttachment = $objectPermission->objectPermissionByOutputInput(
                            $appUid,
                            $proUid,
                            $opTaskSource,
                            'ATTACHED',
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultAttachments = array_merge($resultAttachments, $listAttachment);

                        $resultCaseNotes = 1;

                        //Message History
                        $listMessage = $objectPermission->objectPermissionMessage(
                            $appUid,
                            $proUid,
                            $userUid,
                            $action,
                            $opTaskSource,
                            $opUserRelation,
                            $caseData['APP_STATUS'],
                            $opParticipated
                        );
                        $resultMessages = array_merge($resultMessages, $listMessage);
                        break;
                    case 'DYNAFORM':
                        $listDynaform = BusinessModelCases::dynaFormsByApplication(
                            $appUid,
                            $opTaskSource,
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultDynaforms = array_merge($resultDynaforms, $listDynaform);
                        break;
                    case 'INPUT':
                        $listInput = $objectPermission->objectPermissionByOutputInput(
                            $appUid,
                            $proUid,
                            $opTaskSource,
                            'INPUT',
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultInputs = array_merge($resultInputs, $listInput);
                        break;
                    case 'ATTACHMENT':
                        $listAttachment = $objectPermission->objectPermissionByOutputInput(
                            $appUid,
                            $proUid,
                            $opTaskSource,
                            'ATTACHED',
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultAttachments = array_merge($resultAttachments, $listAttachment);
                        break;
                    case 'OUTPUT':
                        $listOutput = $objectPermission->objectPermissionByOutputInput(
                            $appUid,
                            $proUid,
                            $opTaskSource,
                            'OUTPUT',
                            $opObjUid,
                            $caseData['APP_STATUS']
                        );
                        $resultOutputs = array_merge($resultOutputs, $listOutput);
                        break;
                    case 'CASES_NOTES':
                        $resultCaseNotes = 1;
                        break;
                    case 'MSGS_HISTORY':
                        $listMessage = $objectPermission->objectPermissionMessage(
                            $appUid,
                            $proUid,
                            $userUid,
                            $action,
                            $opTaskSource,
                            $opUserRelation,
                            $caseData['APP_STATUS'],
                            $opParticipated
                        );
                        $resultMessages = array_merge($resultMessages, $listMessage);
                        break;
                }
            }
        }

        return [
            "DYNAFORMS" => $resultDynaforms,
            "INPUT_DOCUMENTS" => $resultInputs,
            "ATTACHMENTS" => $resultAttachments,
            "OUTPUT_DOCUMENTS" => $resultOutputs,
            "CASES_NOTES" => $resultCaseNotes,
            "MSGS_HISTORY" => $resultMessages
        ];
    }

    /**
     * to check the user External
     * @author Everth The Answer
     *
     * verifyCaseTracker($case, $pin)
     * @access public
     * @param  $case, $pin
     * @return Array
     */
    public function verifyCaseTracker($case, $pin)
    {
        //CASE INSENSITIVE pin
        $pin = G::toUpper($pin);
        $pin = G::encryptOld($pin);

        $oCriteria = new Criteria('workflow');
        $oCriteria->addSelectColumn(ApplicationPeer::APP_UID);
        $oCriteria->addSelectColumn(ApplicationPeer::APP_PIN);
        $oCriteria->addSelectColumn(ApplicationPeer::PRO_UID);
        $oCriteria->addSelectColumn(ApplicationPeer::APP_NUMBER);
        $oCriteria->addSelectColumn(ApplicationPeer::APP_PROC_CODE);
        //$oCriteria->add(ApplicationPeer::APP_NUMBER, $case);
        $oCriteria->add(ApplicationPeer::APP_PROC_CODE, $case);

        $oDataset = DynaformPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aRow = $oDataset->getRow();

        $sw = 0;
        if (is_array($aRow)) {
            $PRO_UID = $aRow['PRO_UID'];
            $APP_UID = $aRow['APP_UID'];
            $PIN = $aRow['APP_PIN'];
        } else {
            $oCriteria = new Criteria('workflow');
            $oCriteria->addSelectColumn(ApplicationPeer::APP_UID);
            $oCriteria->addSelectColumn(ApplicationPeer::APP_PIN);
            $oCriteria->addSelectColumn(ApplicationPeer::PRO_UID);
            $oCriteria->addSelectColumn(ApplicationPeer::APP_NUMBER);
            $oCriteria->addSelectColumn(ApplicationPeer::APP_PROC_CODE);
            $oCriteria->add(ApplicationPeer::APP_NUMBER, $case);

            $oDataseti = DynaformPeer::doSelectRS($oCriteria);
            $oDataseti->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataseti->next();
            $aRowi = $oDataseti->getRow();

            if (is_array($aRowi)) {
                $PRO_UID = $aRowi['PRO_UID'];
                $APP_UID = $aRowi['APP_UID'];
                $PIN = $aRowi['APP_PIN'];
            } else {
                $sw = 1;
            }
        }

        $s = 0;
        if ($sw == 1) {
            //it isn't a case
            return -1;
        } else {
            $s++;
        }
        if ($PIN != $pin) {
            //the pin isn't correct
            return -2;
        } else {
            $s++;
        }
        $res = array();
        $res['PRO_UID'] = $PRO_UID;
        $res['APP_UID'] = $APP_UID;

        if ($s == 2) {
            return $res;
        }
    }

    /**
     * funcion caseTrackerPermissions, by Everth
     *
     * @name caseTrackerPermissions
     * @param string $PRO_UID
     * @return string
     */
    public function caseTrackerPermissions($PRO_UID)
    {
        $newCaseTracker = new CaseTracker();
        $caseTracker = $newCaseTracker->load($PRO_UID);
        if (is_array($caseTracker)) {
            $caseTracker['CT_MAP_TYPE'] = ($caseTracker['CT_MAP_TYPE'] != 'NONE') ? true : false;
            //$caseTracker['CT_DERIVATION_HISTORY']  = ($caseTracker['CT_DERIVATION_HISTORY'] == 1)? true : false;
            //$caseTracker['CT_MESSAGE_HISTORY']     = ($caseTracker['CT_MESSAGE_HISTORY'] == 1)? true : false;

            $criteria = new Criteria();
            $criteria->add(CaseTrackerObjectPeer::PRO_UID, $PRO_UID);
            $caseTracker['DYNADOC'] = (CaseTrackerObjectPeer::doCount($criteria) > 0) ? true : false;
        }
        return $caseTracker;
    }

    /**
     * funcion input documents for case tracker
     * by Everth The Answer
     *
     * @name getAllUploadedDocumentsCriteriaTracker
     * @param string $sProcessUID
     * @param string $sApplicationUID
     * @param string $sDocUID
     * @return object
     */
    public function getAllUploadedDocumentsCriteriaTracker($sProcessUID, $sApplicationUID, $sDocUID)
    {
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        //$oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('INPUT'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, 'INPUT');
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::DOC_UID, $sDocUID);
        $aConditions = array();
        $aConditions[] = array(AppDocumentPeer::APP_UID, AppDelegationPeer::APP_UID);
        $aConditions[] = array(AppDocumentPeer::DEL_INDEX, AppDelegationPeer::DEL_INDEX);
        $oCriteria->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        $oCriteria->add(AppDelegationPeer::PRO_UID, $sProcessUID);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aInputDocuments = array();
        $aInputDocuments[] = array(
            'APP_DOC_UID' => 'char',
            'DOC_UID' => 'char',
            'APP_DOC_COMMENT' => 'char',
            'APP_DOC_FILENAME' => 'char',
            'APP_DOC_INDEX' => 'integer'
        );
        while ($aRow = $oDataset->getRow()) {
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelegationPeer::APP_UID, $sApplicationUID);
            $oCriteria2->add(AppDelegationPeer::DEL_INDEX, $aRow['DEL_INDEX']);
            $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            $aRow2 = $oDataset2->getRow();
            $oTask = new Task();
            if ($oTask->taskExists($aRow2['TAS_UID'])) {
                $aTask = $oTask->load($aRow2['TAS_UID']);
            } else {
                $aTask = array('TAS_TITLE' => '(TASK DELETED)');
            }
            $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
            $aFields = array(
                'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                'DOC_UID' => $aAux['DOC_UID'],
                'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                'TYPE' => $aAux['APP_DOC_TYPE'], 'ORIGIN' => $aTask['TAS_TITLE']
            );
            if ($aFields['APP_DOC_FILENAME'] != '') {
                $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
            } else {
                $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
            }
            //$aFields['POSITION'] = $_SESSION['STEP_POSITION'];
            $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
            $aInputDocuments[] = $aFields;
            $oDataset->next();
        }
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        //$oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, array('ATTACHED'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, 'ATTACHED');
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        while ($aRow = $oDataset->getRow()) {
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelegationPeer::DEL_INDEX, $aRow['DEL_INDEX']);
            $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            $aRow2 = $oDataset2->getRow();
            $oTask = new Task();
            if ($oTask->taskExists($aRow2['TAS_UID'])) {
                $aTask = $oTask->load($aRow2['TAS_UID']);
            } else {
                $aTask = array('TAS_TITLE' => '(TASK DELETED)');
            }
            $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
            $aFields = array(
                'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                'DOC_UID' => $aAux['DOC_UID'],
                'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                'TYPE' => $aAux['APP_DOC_TYPE'], 'ORIGIN' => $aTask['TAS_TITLE']
            );
            if ($aFields['APP_DOC_FILENAME'] != '') {
                $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
            } else {
                $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
            }
            //$aFields['POSITION'] = $_SESSION['STEP_POSITION'];
            $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
            $aInputDocuments[] = $aFields;
            $oDataset->next();
        }
        global $_DBArray;
        $_DBArray['inputDocuments'] = $aInputDocuments;
        $_SESSION['_DBArray'] = $_DBArray;
        $oCriteria = new Criteria('dbarray');
        $oCriteria->setDBArrayTable('inputDocuments');
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_CREATE_DATE);
        return $oCriteria;
    }

    /**
     * funcion output documents for case tracker
     * by Everth The Answer
     *
     * @name getAllGeneratedDocumentsCriteriaTracker
     * @param string $sProcessUID
     * @param string $sApplicationUID
     * @param string $sDocUID
     * @return object
     */
    public function getAllGeneratedDocumentsCriteriaTracker($sProcessUID, $sApplicationUID, $sDocUID)
    {
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppDocumentPeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, 'OUTPUT');
        $oCriteria->add(AppDocumentPeer::APP_DOC_STATUS, array('ACTIVE'), Criteria::IN);
        $oCriteria->add(AppDocumentPeer::DOC_UID, $sDocUID);
        $aConditions = array();
        $aConditions[] = array(AppDocumentPeer::APP_UID, AppDelegationPeer::APP_UID);
        $aConditions[] = array(AppDocumentPeer::DEL_INDEX, AppDelegationPeer::DEL_INDEX);
        $oCriteria->addJoinMC($aConditions, Criteria::LEFT_JOIN);
        $oCriteria->add(AppDelegationPeer::PRO_UID, $sProcessUID);
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oDataset = AppDocumentPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aOutputDocuments = array();
        $aOutputDocuments[] = array(
            'APP_DOC_UID' => 'char',
            'DOC_UID' => 'char',
            'APP_DOC_COMMENT' => 'char',
            'APP_DOC_FILENAME' => 'char',
            'APP_DOC_INDEX' => 'integer'
        );
        while ($aRow = $oDataset->getRow()) {
            $oCriteria2 = new Criteria('workflow');
            $oCriteria2->add(AppDelegationPeer::APP_UID, $sApplicationUID);
            $oCriteria2->add(AppDelegationPeer::DEL_INDEX, $aRow['DEL_INDEX']);
            $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
            $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $oDataset2->next();
            $aRow2 = $oDataset2->getRow();
            $oTask = new Task();
            if ($oTask->taskExists($aRow2['TAS_UID'])) {
                $aTask = $oTask->load($aRow2['TAS_UID']);
            } else {
                $aTask = array('TAS_TITLE' => '(TASK DELETED)');
            }
            $aAux = $oAppDocument->load($aRow['APP_DOC_UID'], $aRow['DOC_VERSION']);
            $aFields = array(
                'APP_DOC_UID' => $aAux['APP_DOC_UID'],
                'DOC_UID' => $aAux['DOC_UID'],
                'APP_DOC_COMMENT' => $aAux['APP_DOC_COMMENT'],
                'APP_DOC_FILENAME' => $aAux['APP_DOC_FILENAME'],
                'APP_DOC_INDEX' => $aAux['APP_DOC_INDEX'],
                'ORIGIN' => $aTask['TAS_TITLE']
            );
            if ($aFields['APP_DOC_FILENAME'] != '') {
                $aFields['TITLE'] = $aFields['APP_DOC_FILENAME'];
            } else {
                $aFields['TITLE'] = $aFields['APP_DOC_COMMENT'];
            }
            //$aFields['POSITION'] = $_SESSION['STEP_POSITION'];
            $aFields['CONFIRM'] = G::LoadTranslation('ID_CONFIRM_DELETE_ELEMENT');
            $aOutputDocuments[] = $aFields;
            $oDataset->next();
        }

        global $_DBArray;
        $_DBArray['outputDocuments'] = $aOutputDocuments;
        $_SESSION['_DBArray'] = $_DBArray;
        $oCriteria = new Criteria('dbarray');
        $oCriteria->setDBArrayTable('outputDocuments');
        $oCriteria->addAscendingOrderByColumn(AppDocumentPeer::APP_DOC_CREATE_DATE);
        return $oCriteria;
    }

    /**
     * funcion History messages for case tracker
     * by Everth The Answer
     *
     * @name getHistoryMessagesTracker
     * @param string sApplicationUID
     * @return object
     */
    public function getHistoryMessagesTracker($sApplicationUID)
    {
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppMessagePeer::APP_UID, $sApplicationUID);
        $oCriteria->addAscendingOrderByColumn(AppMessagePeer::APP_MSG_DATE);
        $oDataset = AppMessagePeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();

        $aMessages = array();
        $aMessages[] = array(
            'APP_MSG_UID' => 'char',
            'APP_UID' => 'char',
            'DEL_INDEX' => 'char',
            'APP_MSG_TYPE' => 'char',
            'APP_MSG_SUBJECT' => 'char',
            'APP_MSG_FROM' => 'char',
            'APP_MSG_TO' => 'char',
            'APP_MSG_BODY' => 'char',
            'APP_MSG_DATE' => 'char',
            'APP_MSG_CC' => 'char',
            'APP_MSG_BCC' => 'char',
            'APP_MSG_TEMPLATE' => 'char',
            'APP_MSG_STATUS' => 'char',
            'APP_MSG_ATTACH' => 'char'
        );
        while ($aRow = $oDataset->getRow()) {
            $aMessages[] = array(
                'APP_MSG_UID' => $aRow['APP_MSG_UID'],
                'APP_UID' => $aRow['APP_UID'],
                'DEL_INDEX' => $aRow['DEL_INDEX'],
                'APP_MSG_TYPE' => $aRow['APP_MSG_TYPE'],
                'APP_MSG_SUBJECT' => $aRow['APP_MSG_SUBJECT'],
                'APP_MSG_FROM' => $aRow['APP_MSG_FROM'],
                'APP_MSG_TO' => $aRow['APP_MSG_TO'],
                'APP_MSG_BODY' => $aRow['APP_MSG_BODY'],
                'APP_MSG_DATE' => $aRow['APP_MSG_DATE'],
                'APP_MSG_CC' => $aRow['APP_MSG_CC'],
                'APP_MSG_BCC' => $aRow['APP_MSG_BCC'],
                'APP_MSG_TEMPLATE' => $aRow['APP_MSG_TEMPLATE'],
                'APP_MSG_STATUS' => $aRow['APP_MSG_STATUS'],
                'APP_MSG_ATTACH' => $aRow['APP_MSG_ATTACH']
            );
            $oDataset->next();
        }

        global $_DBArray;
        $_DBArray['messages'] = $aMessages;
        $_SESSION['_DBArray'] = $_DBArray;
        $oCriteria = new Criteria('dbarray');
        $oCriteria->setDBArrayTable('messages');

        return $oCriteria;
    }

    /**
     * funcion History messages for case tracker
     * by Everth The Answer
     *
     * @name getHistoryMessagesTrackerView
     * @param string sApplicationUID
     * @param string Msg_UID
     * @return array
     */
    public function getHistoryMessagesTrackerView($sApplicationUID, $Msg_UID)
    {
        $oAppDocument = new AppDocument();
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(AppMessagePeer::APP_UID, $sApplicationUID);
        $oCriteria->add(AppMessagePeer::APP_MSG_UID, $Msg_UID);
        $oCriteria->addAscendingOrderByColumn(AppMessagePeer::APP_MSG_DATE);
        $oDataset = AppMessagePeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aRow = $oDataset->getRow();
        return $aRow;
    }

    /**
     * This function gets all data about APP_DOCUMENT
     *
     * @name getAllObjectsFromProcess
     * @param string sApplicationUID
     * @param object OBJ_TYPE
     * @return array
     */
    public function getAllObjectsFromProcess($PRO_UID, $OBJ_TYPE = '%')
    {
        $RESULT = array();
        $oCriteria = new Criteria('workflow');
        $oCriteria->addSelectColumn(AppDocumentPeer::APP_DOC_UID);
        $oCriteria->addSelectColumn(AppDocumentPeer::APP_UID);
        $oCriteria->addSelectColumn(AppDocumentPeer::DEL_INDEX);
        $oCriteria->addSelectColumn(AppDocumentPeer::DOC_UID);
        $oCriteria->addSelectColumn(AppDocumentPeer::USR_UID);
        $oCriteria->addSelectColumn(AppDocumentPeer::APP_DOC_TYPE);
        $oCriteria->addSelectColumn(AppDocumentPeer::APP_DOC_CREATE_DATE);
        $oCriteria->addSelectColumn(AppDocumentPeer::APP_DOC_INDEX);
        $oCriteria->addSelectColumn(AppDocumentPeer::DOC_VERSION);

        $oCriteria->add(ApplicationPeer::PRO_UID, $PRO_UID);
        $oCriteria->addJoin(ApplicationPeer::APP_UID, AppDocumentPeer::APP_UID);

        $oCriteria->add(AppDocumentPeer::APP_DOC_TYPE, $OBJ_TYPE, Criteria::LIKE);

        $oDataset = DynaformPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        while ($oDataset->next()) {
            $row = $oDataset->getRow();
            $oAppDocument = new AppDocument();
            $oAppDocument->Fields = $oAppDocument->load($row['APP_DOC_UID'], $row['DOC_VERSION']);

            $row['APP_DOC_FILENAME'] = $oAppDocument->Fields['APP_DOC_FILENAME'];
            array_push($RESULT, $row);
        }
        return $RESULT;
    }

    /**
     * execute triggers after derivation
     *
     * @name executeTriggersAfterExternal
     * @param string $sProcess
     * @param string $sTask
     * @param string $sApplication
     * @param string $iIndex
     * @param string $iStepPosition
     * @param array  $aNewData
     * @return void
     */
    public function executeTriggersAfterExternal($sProcess, $sTask, $sApplication, $iIndex, $iStepPosition, $aNewData = array())
    {
        //load the variables
        $Fields = $this->loadCase($sApplication);
        $Fields['APP_DATA'] = array_merge($Fields['APP_DATA'], G::getSystemConstants());
        $Fields['APP_DATA'] = array_merge($Fields['APP_DATA'], $aNewData);
        //execute triggers
        $oCase = new Cases();
        $aNextStep = $this->getNextStep($sProcess, $sApplication, $iIndex, $iStepPosition - 1);
        $Fields['APP_DATA'] = $this->ExecuteTriggers(
                $sTask, 'EXTERNAL', $aNextStep['UID'], 'AFTER', $Fields['APP_DATA']
        );
        //save data
        $aData = array();
        $aData['APP_NUMBER'] = $Fields['APP_NUMBER'];
        //$aData['APP_PROC_STATUS'] = $Fields['APP_PROC_STATUS'];
        $aData['APP_DATA'] = $Fields['APP_DATA'];
        $aData['DEL_INDEX'] = $iIndex;
        $aData['TAS_UID'] = $sTask;
        $this->updateCase($sApplication, $aData);
    }

    /**
     * this function gets the current user in a task
     *
     * @name thisIsTheCurrentUser
     * @param string $sApplicationUID
     * @param string $iIndex
     * @param string $sUserUID
     * @param string $sAction
     * @param string $sURL
     * @return void
     */
    public function thisIsTheCurrentUser($sApplicationUID, $iIndex, $sUserUID, $sAction = '', $sURL = '')
    {
        $c = new Criteria('workflow');
        $c->add(AppDelegationPeer::APP_UID, $sApplicationUID);
        $c->add(AppDelegationPeer::DEL_INDEX, $iIndex);
        $c->add(AppDelegationPeer::USR_UID, $sUserUID);
        switch ($sAction) {
            case '':
                return (boolean) AppDelegationPeer::doCount($c);
                break;
            case 'REDIRECT':
                if (!(boolean) AppDelegationPeer::doCount($c)) {
                    $c = new Criteria('workflow');
                    $c->addSelectColumn(UsersPeer::USR_USERNAME);
                    $c->addSelectColumn(UsersPeer::USR_FIRSTNAME);
                    $c->addSelectColumn(UsersPeer::USR_LASTNAME);
                    $c->add(AppDelegationPeer::APP_UID, $sApplicationUID);
                    $c->add(AppDelegationPeer::DEL_INDEX, $iIndex);
                    $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);
                    $oDataset = AppDelegationPeer::doSelectRs($c);
                    $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                    $oDataset->next();
                    $aData = $oDataset->getRow();
                    G::SendMessageText(
                            G::LoadTranslation('ID_CASE_IS_CURRENTLY_WITH_ANOTHER_USER') . ': ' .
                            $aData['USR_FIRSTNAME'] . ' ' . $aData['USR_LASTNAME'] .
                            ' (' . $aData['USR_USERNAME'] . ')', 'error'
                    );
                    G::header('Location: ' . $sURL);
                    die;
                } else {
                    $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
                    if (!(boolean) AppDelegationPeer::doCount($c)) {
                        G::SendMessageText(G::LoadTranslation('ID_CASE_ALREADY_DERIVATED'), 'error');
                        G::header('Location: ' . $sURL);
                        die;
                    }
                }
                break;
            case 'SHOW_MESSAGE':
                if (!(boolean) AppDelegationPeer::doCount($c)) {
                    $c = new Criteria('workflow');
                    $c->addSelectColumn(UsersPeer::USR_USERNAME);
                    $c->addSelectColumn(UsersPeer::USR_FIRSTNAME);
                    $c->addSelectColumn(UsersPeer::USR_LASTNAME);
                    $c->add(AppDelegationPeer::APP_UID, $sApplicationUID);
                    $c->add(AppDelegationPeer::DEL_INDEX, $iIndex);
                    $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);
                    $oDataset = AppDelegationPeer::doSelectRs($c);
                    $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                    $oDataset->next();
                    $aData = $oDataset->getRow();
                    die('<strong>' .
                            G::LoadTranslation('ID_CASE_ALREADY_DERIVATED') . ': ' .
                            $aData['USR_FIRSTNAME'] . ' ' .
                            $aData['USR_LASTNAME'] . ' (' . $aData['USR_USERNAME'] . ')</strong>'
                    );
                } else {
                    $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
                    if (!(boolean) AppDelegationPeer::doCount($c)) {
                        die('<strong>' . G::LoadTranslation('ID_CASE_ALREADY_DERIVATED') . '</strong>');
                    }
                }
                break;
        }
    }

    /**
     * this function gets the user in Case
     *
     * @name getCriteriaUsersCases
     * @param string $status
     * @param string $USR_UID
     * @return object
     */
    public function getCriteriaUsersCases($status, $USR_UID)
    {
        $c = new Criteria('workflow');
        $c->addJoin(ApplicationPeer::APP_UID, AppDelegationPeer::APP_UID, Criteria::LEFT_JOIN);
        $c->add(ApplicationPeer::APP_STATUS, $status);
        $c->add(AppDelegationPeer::USR_UID, $USR_UID);
        $c->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);
        return $c;
    }

    /**
     * this function gets information in a search
     *
     * @name getCriteriaUsersCases
     * @param string $sCase
     * @param string $sTask
     * @param string $sCurrentUser
     * @param string $sSentby
     * @param string $sLastModFrom
     * @param string $sLastModTo
     * @param string $status
     * @param string $permisse
     * @param string $userlogged
     * @param array  $aSupervisor
     * @return object
     */
    public function getAdvancedSearch($sCase, $sProcess, $sTask, $sCurrentUser, $sSentby, $sLastModFrom, $sLastModTo, $sStatus, $permisse, $userlogged, $aSupervisor)
    {
        $sTypeList = '';
        $sUIDUserLogged = '';

        $c = new Criteria('workflow');
        $c->clearSelectColumns();
        $c->addSelectColumn(ApplicationPeer::APP_UID);
        $c->addSelectColumn(ApplicationPeer::APP_TITLE);
        $c->addSelectColumn(ApplicationPeer::APP_NUMBER);
        $c->addSelectColumn(ApplicationPeer::APP_UPDATE_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_PRIORITY);
        //$c->addSelectColumn(AppDelegationPeer::DEL_TASK_DUE_DATE);
        $c->addAsColumn(
                'DEL_TASK_DUE_DATE', " IF (" . AppDelegationPeer::DEL_TASK_DUE_DATE . " <= NOW(), CONCAT('<span style=\'color:red\';>', " .
                AppDelegationPeer::DEL_TASK_DUE_DATE . ", '</span>'), " . AppDelegationPeer::DEL_TASK_DUE_DATE . ") ");
        $c->addSelectColumn(AppDelegationPeer::DEL_INDEX);
        $c->addSelectColumn(AppDelegationPeer::TAS_UID);
        $c->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
        $c->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
        $c->addSelectColumn(UsersPeer::USR_UID);
        $c->addAsColumn('APP_CURRENT_USER', "CONCAT(USERS.USR_LASTNAME, ' ', USERS.USR_FIRSTNAME)");
        $c->addSelectColumn(ApplicationPeer::APP_STATUS);
        $c->addAsColumn('APP_PRO_TITLE', ProcessPeer::PRO_TITLE);
        $c->addAsColumn('APP_TAS_TITLE', TaskPeer::TAS_TITLE);
        //$c->addAsColumn('APP_DEL_PREVIOUS_USER', 'APP_LAST_USER.USR_USERNAME');
        $c->addAsColumn(
                'APP_DEL_PREVIOUS_USER', "CONCAT(APP_LAST_USER.USR_LASTNAME, ' ', APP_LAST_USER.USR_FIRSTNAME)"
        );

        $c->addAlias("APP_PREV_DEL", 'APP_DELEGATION');
        $c->addAlias("APP_LAST_USER", 'USERS');

        $c->addJoin(ApplicationPeer::APP_UID, AppDelegationPeer::APP_UID, Criteria::LEFT_JOIN);
        $c->addJoin(ApplicationPeer::PRO_UID, ProcessPeer::PRO_UID, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::TAS_UID, TaskPeer::TAS_UID, Criteria::LEFT_JOIN);
        $appThreadConds[] = array(ApplicationPeer::APP_UID, AppThreadPeer::APP_UID);
        $appThreadConds[] = array(AppDelegationPeer::DEL_INDEX, AppThreadPeer::DEL_INDEX);
        $c->addJoinMC($appThreadConds, Criteria::LEFT_JOIN);
        $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

        $prevConds = array();
        $prevConds[] = array(ApplicationPeer::APP_UID, 'APP_PREV_DEL.APP_UID');
        $prevConds[] = array('APP_PREV_DEL.DEL_INDEX', AppDelegationPeer::DEL_PREVIOUS);
        $c->addJoinMC($prevConds, Criteria::LEFT_JOIN);

        $usrConds = array();
        $usrConds[] = array('APP_PREV_DEL.USR_UID', 'APP_LAST_USER.USR_UID');
        $c->addJoinMC($usrConds, Criteria::LEFT_JOIN);

        $c->add(TaskPeer::TAS_TYPE, 'SUBPROCESS', Criteria::NOT_EQUAL);

        $c->add(
                $c->getNewCriterion(AppThreadPeer::APP_THREAD_STATUS, 'OPEN')->
                        addOr($c->getNewCriterion(ApplicationPeer::APP_STATUS, 'COMPLETED')->
                                addAnd($c->getNewCriterion(AppDelegationPeer::DEL_PREVIOUS, 0)))
        );

        if ($sCase != '') {
            $c->add(ApplicationPeer::APP_NUMBER, $sCase);
        }
        if ($sProcess != '') {
            $c->add(ApplicationPeer::PRO_UID, $sProcess);
        }
        if ($sTask != '' && $sTask != "0" && $sTask != 0) {
            $c->add(AppDelegationPeer::TAS_UID, $sTask);
        }
        if ($sCurrentUser != '') {
            $c->add(ApplicationPeer::APP_CUR_USER, $sCurrentUser);
        }
        if ($sSentby != '') {
            $c->add('APP_PREV_DEL.USR_UID', $sSentby);
        }
        if ($sLastModFrom != '0000-00-00' && $sLastModTo != '0000-00-00' && $sLastModFrom != '' && $sLastModTo != '') {
            $c->add(
                    $c->getNewCriterion(ApplicationPeer::APP_UPDATE_DATE, $sLastModFrom . ' 00:00:00', Criteria::GREATER_EQUAL)->
                            addAnd($c->getNewCriterion(ApplicationPeer::APP_UPDATE_DATE, $sLastModTo . ' 23:59:59', Criteria::LESS_EQUAL))
            );
        }
        if ($sStatus != '') {
            if ($sStatus != 'gral') {
                $c->add(ApplicationPeer::APP_STATUS, $sStatus);
            }
        }

        if ($permisse != 0) {
            $c->add(
                    $c->getNewCriterion(AppDelegationPeer::USR_UID, $userlogged)->
                            addOr($c->getNewCriterion(AppDelegationPeer::PRO_UID, $aSupervisor, Criteria::IN))
            );
        }

        $c->addDescendingOrderByColumn(ApplicationPeer::APP_NUMBER);

        return $c;
    }

    //**DEPRECATED
    /**
     * this function gets a condition rule
     *
     * @name getConditionCasesCount
     * @param string $type
     * @return int
     */
    public function getConditionCasesCount($type, $sumary = null)
    {
        $result = 0;
        return $result;

        $nCount = 0;

        list($aCriteria, $xmlfile) = $this->getConditionCasesList($type, $_SESSION['USER_LOGGED'], false);
        $rs = ApplicationPeer::doSelectRS($aCriteria);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        if (isset($sumary) && $sumary === true) {
            $sumary = array();
            while ($rs->next()) {
                $nCount++;
                $row = $rs->getRow();
                if (isset($sumary[$row['PRO_UID']])) {
                    $sumary[$row['PRO_UID']]['count'] += 1;
                } else {
                    $sumary[$row['PRO_UID']]['count'] = 1;
                    $sumary[$row['PRO_UID']]['name'] = $row['APP_PRO_TITLE'];
                }
            }
            return array('count' => $nCount, 'sumary' => $sumary);
        } else {
            while ($rs->next()) {
                $nCount++;
            }
            return $nCount;
        }
    }

    //**DEPRECATED
    /**
     * this function gets all conditions rules
     *
     * @name getAllConditionCasesCount
     * @param string $type
     * @return array
     */
    public function getAllConditionCasesCount($types, $sumary = null)
    {
        $aResult = array();
        foreach ($types as $type) {
            $aResult[$type] = $this->getConditionCasesCount($type, $sumary);
        }
        return $aResult;
    }

    /**
     * this function gets a user that it is in a case
     *
     * @name userParticipatedInCase
     * @param string $sAppUid
     * @param string $sUIDUserLogged
     * @return int
     */
    public function userParticipatedInCase($sAppUid, $sUIDUserLogged)
    {
        $c = new Criteria('workflow');
        $c->addSelectColumn(AppDelegationPeer::APP_UID);
        $c->add(AppDelegationPeer::APP_UID, $sAppUid);
        $c->add(AppDelegationPeer::USR_UID, $sUIDUserLogged);

        $rs = ApplicationPeer::doSelectRS($c);
        $count = 0;
        while ($rs->next()) {
            $count++;
        }
        return $count;
    }

    /**
     * Get the current delegation of a case (This is a clone of getCurrentDelegation but this will return
     * the index with out filtering by user or status.
     * todo: deprecated ?
     * @name getCurrentDelegationCase
     * @param string $sApplicationUID
     * @return integer
     */
    public function getCurrentDelegationCase($sApplicationUID = '')
    {
        $criteria = new \Criteria('workflow');
        $criteria->addSelectColumn(\AppDelegationPeer::DEL_INDEX);
        $criteria->add(\AppDelegationPeer::APP_UID, $sApplicationUID, Criteria::EQUAL);
        $criteria->add(\AppDelegationPeer::DEL_LAST_INDEX, 1, Criteria::EQUAL);
        $dataSet = AppDelegationPeer::doSelectRS($criteria);
        $dataSet->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $dataSet->next();
        $row = $dataSet->getRow();
        return isset($row['DEL_INDEX']) ? $row['DEL_INDEX'] : 0;
    }

    public static function clearCaseSessionData()
    {
        if (isset($_SESSION['APPLICATION'])) {
            unset($_SESSION['APPLICATION']);
        }
        if (isset($_SESSION['PROCESS'])) {
            unset($_SESSION['PROCESS']);
        }
        if (isset($_SESSION['INDEX'])) {
            unset($_SESSION['INDEX']);
        }
        if (isset($_SESSION['STEP_POSITION'])) {
            unset($_SESSION['STEP_POSITION']);
        }
    }

    /**
     * Jump to the determinated case by its Application number
     *
     * @param interger $APP_NUMBER
     */
    public function jumpToCase($APP_NUMBER)
    {
        $_GET['APP_UID'] = $oCase->getApplicationUIDByNumber($_GET['APP_NUMBER']);
        $_GET['DEL_INDEX'] = $oCase->getCurrentDelegation($_GET['APP_UID'], $_SESSION['USER_LOGGED']);
        if (is_null($_GET['DEL_INDEX'])) {
            $participated = $oCase->userParticipatedInCase($_GET['APP_UID'], $_SESSION['USER_LOGGED']);
            if ($participated == 0) {
                if (is_null($_GET['APP_UID'])) {
                    G::SendMessageText(G::LoadTranslation('ID_CASE_DOES_NOT_EXISTS'), 'info');
                } else {
                    G::SendMessageText(G::LoadTranslation('ID_CASE_IS_CURRENTLY_WITH_ANOTHER_USER'), 'info');
                }
                G::header('location: cases_List');
            }
        }
    }

    /**
     * We're getting all threads in a task
     *
     * @name GetAllThreads of Particular Parent Thread
     * @param string $sAppUid
     * @param string $sAppParent
     * @return $aThreads
     */
    public function GetAllOpenDelegation($aData, $status = 'OPEN')
    {
        try {
            $aThreads = array();
            $c = new Criteria();
            $c->add(AppDelegationPeer::APP_UID, $aData['APP_UID']);
            $c->add(AppDelegationPeer::DEL_PREVIOUS, $aData['APP_THREAD_PARENT']);
            if ($status === 'OPEN') {
                $c->add(AppDelegationPeer::DEL_THREAD_STATUS, 'OPEN');
            }
            $rs = AppDelegationPeer::doSelectRs($c);
            $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $rs->next();
            $row = $rs->getRow();
            while (is_array($row)) {
                $aThreads[] = $row;
                $rs->next();
                $row = $rs->getRow();
            }
            return $aThreads;
        } catch (exception $e) {
            throw ($e);
        }
    }

    public function getUsersToReassign($TAS_UID, $USR_UID, $PRO_UID = null)
    {
        $oTasks = new Tasks();
        $aAux = $oTasks->getGroupsOfTask($TAS_UID, 1);
        $row = array();
        $groups = new Groups();
        foreach ($aAux as $aGroup) {
            $aUsers = $groups->getUsersOfGroup($aGroup['GRP_UID']);
            foreach ($aUsers as $aUser) {
                if ($aUser['USR_UID'] != $USR_UID) {
                    $row[] = $aUser['USR_UID'];
                }
            }
        }

        $aAux = $oTasks->getUsersOfTask($TAS_UID, 1);
        foreach ($aAux as $aUser) {
            if ($aUser['USR_UID'] != $USR_UID) {
                $row[] = $aUser['USR_UID'];
            }
        }

        // Group Ad Hoc
        $oTasks = new Tasks();
        $aAux = $oTasks->getGroupsOfTask($TAS_UID, 2);
        $groups = new Groups();
        foreach ($aAux as $aGroup) {
            $aUsers = $groups->getUsersOfGroup($aGroup['GRP_UID']);
            foreach ($aUsers as $aUser) {
                if ($aUser['USR_UID'] != $USR_UID) {
                    $row[] = $aUser['USR_UID'];
                }
            }
        }

        // User Ad Hoc
        $aAux = $oTasks->getUsersOfTask($TAS_UID, 2);
        foreach ($aAux as $aUser) {
            if ($aUser['USR_UID'] != $USR_UID) {
                $row[] = $aUser['USR_UID'];
            }
        }

        global $RBAC;
        //Adding the actual user if this has the PM_SUPERVISOR permission assigned.
        if ($RBAC->userCanAccess('PM_SUPERVISOR') == 1) {
            if (!in_array($RBAC->aUserInfo['USER_INFO']['USR_UID'], $row)) {
                $row[] = $RBAC->aUserInfo['USER_INFO']['USR_UID'];
            }
        }

        $c = new Criteria('workflow');
        $c->addSelectColumn(UsersPeer::USR_UID);
        $c->addSelectColumn(UsersPeer::USR_USERNAME);
        $c->addSelectColumn(UsersPeer::USR_FIRSTNAME);
        $c->addSelectColumn(UsersPeer::USR_LASTNAME);
        $c->add(UsersPeer::USR_UID, $row, Criteria::IN);

        $rs = UsersPeer::doSelectRs($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        $rows = array();
        while ($rs->next()) {
            $rows[] = $rs->getRow();
        }

        if ($PRO_UID != null) {
            //Add supervisor
            // Users
            $oCriteria = new Criteria('workflow');
            $oCriteria->addSelectColumn(ProcessUserPeer::PU_UID);
            $oCriteria->addSelectColumn(ProcessUserPeer::USR_UID);
            $oCriteria->addSelectColumn(ProcessUserPeer::PRO_UID);
            $oCriteria->addSelectColumn(UsersPeer::USR_FIRSTNAME);
            $oCriteria->addSelectColumn(UsersPeer::USR_LASTNAME);
            $oCriteria->addSelectColumn(UsersPeer::USR_USERNAME);
            $oCriteria->addSelectColumn(UsersPeer::USR_EMAIL);
            $oCriteria->addJoin(ProcessUserPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);
            $oCriteria->add(ProcessUserPeer::PU_TYPE, 'SUPERVISOR');
            $oCriteria->add(ProcessUserPeer::PRO_UID, $PRO_UID);
            $oCriteria->add(ProcessUserPeer::USR_UID, $USR_UID);
            $oCriteria->addAscendingOrderByColumn(UsersPeer::USR_FIRSTNAME);
            $oDataset = ProcessUserPeer::doSelectRS($oCriteria);
            $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $flagSupervisors = false;

            if ($oDataset->next()) {
                if (!in_array($USR_UID, $row)) {
                    $rows[] = $oDataset->getRow();
                }
                $flagSupervisors = true;
            }

            if (!$flagSupervisors) {
                // Groups
                $oCriteria = new Criteria('workflow');
                $oCriteria->addSelectColumn(ProcessUserPeer::PU_UID);
                $oCriteria->addSelectColumn(ProcessUserPeer::USR_UID);
                $oCriteria->addSelectColumn(ProcessUserPeer::PRO_UID);

                $oCriteria->addSelectColumn(UsersPeer::USR_UID);
                $oCriteria->addSelectColumn(UsersPeer::USR_FIRSTNAME);
                $oCriteria->addSelectColumn(UsersPeer::USR_LASTNAME);
                $oCriteria->addSelectColumn(UsersPeer::USR_USERNAME);
                $oCriteria->addSelectColumn(UsersPeer::USR_EMAIL);

                $oCriteria->addJoin(ProcessUserPeer::USR_UID, GroupUserPeer::GRP_UID, Criteria::LEFT_JOIN);
                $oCriteria->addJoin(GroupUserPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

                $oCriteria->add(ProcessUserPeer::PU_TYPE, 'GROUP_SUPERVISOR');
                $oCriteria->add(ProcessUserPeer::PRO_UID, $PRO_UID);
                $oCriteria->add(GroupUserPeer::USR_UID, $USR_UID);

                $oDataset = ProcessUserPeer::doSelectRS($oCriteria);
                $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);

                if ($oDataset->next()) {
                    if (!in_array($USR_UID, $row)) {
                        $rows[] = $oDataset->getRow();
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * this function gets all users that already participated in a case
     *
     * @name getUsersParticipatedInCase
     * @param string $sAppUid
     * @return array (criteria+array)
     */
    public function getUsersParticipatedInCase($sAppUid, $usrStatus = '')
    {
        $c = new Criteria('workflow');
        $c->addSelectColumn(AppDelegationPeer::APP_UID);
        $c->addSelectColumn(AppDelegationPeer::USR_UID);
        $c->addSelectColumn(UsersPeer::USR_USERNAME);
        $c->addSelectColumn(UsersPeer::USR_EMAIL);

        if ($usrStatus != '') {
            $c->add(UsersPeer::USR_STATUS, $usrStatus, CRITERIA::EQUAL);
        }

        $c->add(AppDelegationPeer::APP_UID, $sAppUid, CRITERIA::EQUAL);
        $c->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);
        $rs = AppDelegationPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        $rows = array();
        $rs->next();
        while ($row = $rs->getRow()) {
            //In some cases the thread does not have a User Script task, Itee
            if ($row['USR_UID'] !== '') {
                $rows[$row['USR_UID']] = $row;
            }
            $rs->next();
        }
        $response['criteria'] = $c;
        $response['array'] = $rows;
        return $response;
    }

    /**
     * This method return the cases notes
     * @param $applicationID
     * @param string $type
     * @param string $userUid
     * @return array|stdclass|string
     *
     */
    public static function getCaseNotes($applicationID, $type = 'array', $userUid = '')
    {
        require_once("classes/model/AppNotes.php");
        $appNotes = new AppNotes();
        $appNotes = $appNotes->getNotesList($applicationID, $userUid);
        $appNotes = AppNotes::applyHtmlentitiesInNotes($appNotes);

        $response = '';
        if (is_array($appNotes)) {
            switch ($type) {
                case 'array':
                    $response = [];
                    foreach ($appNotes['array']['notes'] as $key => $value) {
                        $list = array();
                        $list['FULL_NAME'] = $value['USR_FIRSTNAME'] . " " . $value['USR_LASTNAME'];
                        foreach ($value as $keys => $value) {
                            if ($keys != 'USR_FIRSTNAME' && $keys != 'USR_LASTNAME' && $keys != 'USR_EMAIL') {
                                $list[$keys] = $value;
                            }
                            if ($keys == 'NOTE_DATE') {
                                $list[$keys] = DateTime::convertUtcToTimeZone($value);
                            }
                        }
                        $response[$key + 1] = $list;
                    }
                    break;
                case 'object':
                    $response = new stdclass();
                    foreach ($appNotes['array']['notes'] as $key => $value) {
                        $response->$key = new stdclass();
                        $response->$key->FULL_NAME = $value['USR_FIRSTNAME'] . " " . $value['USR_LASTNAME'];
                        foreach ($value as $keys => $value) {
                            if ($keys != 'USR_FIRSTNAME' && $keys != 'USR_LASTNAME' && $keys != 'USR_EMAIL') {
                                $response->$key->$keys = $value;
                            }
                            if ($keys == 'NOTE_DATE') {
                                $response->$key->$keys = DateTime::convertUtcToTimeZone($value);
                            }
                        }
                    }
                    break;
                case 'string':
                    $response = '';
                    foreach ($appNotes['array']['notes'] as $key => $value) {
                        $response .= $value['USR_FIRSTNAME'] . " " .
                            $value['USR_LASTNAME'] . " " .
                            "(" . $value['USR_USERNAME'] . ")" .
                            " " . $value['NOTE_CONTENT'] . " " . " (" . DateTime::convertUtcToTimeZone($value['NOTE_DATE']) . " ) " .
                            " \n";
                    }
                    break;
            }
        }
        return $response;
    }

    /**
     * Execute triggers when committing an action in cases
     *
     * @param string $appUid
     * @param string $action, can be [OPEN, CANCELED, PAUSED, REASSIGNED, DELETED, CREATE, UNPAUSE]
     * @param bool $executeSameCase
     *
     * @return bool
     *
     * @see cases_Open.php
     * @see cancelCase/Cases.php pauseCase/Cases.php reassignCase/Cases.php removeCase/Cases.php unpauseCase/Cases.php on
     * @link https://wiki.processmaker.com/3.2/Triggers#When_action_cases
     */
    public function getExecuteTriggerProcess($appUid, $action, $executeSameCase = true)
    {
        if (empty($appUid) || empty($action)) {
            return false;
        }

        require_once("classes/model/Process.php");
        $fieldsCase = $this->loadCase($appUid);
        $proUid = $fieldsCase['PRO_UID'];

        //Set some global system variables
        $fieldsCase['APP_DATA']['APPLICATION'] = $appUid;
        $fieldsCase['APP_DATA']['PROCESS'] = $proUid;

        //Get the trigger configured in the process action
        $appProcess = new Process();
        $triggersList = $appProcess->getTriggerWebBotProcess($proUid, $action);

        if (!empty($triggersList)){
            //Execute the trigger defined in the process action
            $fieldsCase['APP_DATA'] = $this->executeTriggerFromList(
                $triggersList,
                $fieldsCase['APP_DATA'],
                'PROCESS_ACTION',
                '',
                '',
                '',
                $executeSameCase
            );

            //Update the case
            $this->updateCase($appUid, $fieldsCase);

            return true;
        } else {
            return false;
        }
    }

    /**
     * When the case is deleted will be removed the case from the report tables related
     *
     * @param string $applicationUid
     *
     * @return void
     * @throws Exception
    */
    public function reportTableDeleteRecord($applicationUid)
    {
        $app = new Application();
        $applicationFields = $app->Load($applicationUid);
        if (!empty($applicationFields["PRO_UID"])) {
            $additionalTables = new AdditionalTables();
            $listTables = $additionalTables->getReportTables($applicationFields["PRO_UID"]);
            $pmTable = new PmTable();
            $tableName = '';
            foreach ($listTables as $row) {
                try {
                    $tableName = $row["ADD_TAB_NAME"];
                    $pmTableName = $pmTable->toCamelCase($tableName);
                    require_once(PATH_WORKSPACE . 'classes' . PATH_SEP . $pmTableName . '.php');
                    $criteria = new Criteria("workflow");
                    $pmTablePeer = $pmTableName . 'Peer';
                    $criteria->add($pmTablePeer::APP_UID, $applicationUid);
                    $pmTablePeer::doDelete($criteria);
                } catch (Exception $e) {
                    $message = $e->getMessage();
                    $context = [
                        'appUid' => $applicationUid,
                        'proUid' => $applicationFields["PRO_UID"],
                        'reportTable' => $tableName
                    ];
                    Log::channel(':DeleteCases')->error($message, Bootstrap::context($context));
                }
            }
        }
    }

    /**
     * Unserialize the case data
     *
     * @param string $data
     *
     * @return array
    */
    public static function unserializeData($data)
    {
        $unserializedData = @unserialize($data);

        // BUG 8134, FIX!// for single/double quote troubles // Unserialize with utf8 content get trouble
        if ($unserializedData === false) {
            $unserializedData = preg_replace_callback('!s:(\d+):"(.*?)";!', function ($m) {
                return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
            }, $data);
            $unserializedData = @unserialize($unserializedData);
        }

        return $unserializedData;
    }

    /**
     * This function returns the list of cases (and  their categories) that a user can start.
     * Used by : End Point workflow/engine/src/ProcessMaker/Services/Api/Cases.php  -> doGetCasesListStarCase
     * Observation: This function and the doGetCasesListStarCase end point implements a similar functionality
     * of the mobile light/process/start-case  endpoint. It was decided (Sep 3 2015) the it was necessary to have
     * a ProcessMaker endpoint for this functionality and in the future al the mobile end points will be deprecated
     * and just the ProcessMaker endpoints will exist.
     *
     * @param $usrUid
     * @param $typeView
     * @return array
     */
    public function getProcessListStartCase($usrUid, $typeView)
    {
        $usrUid = empty($usrUid) ? $_SESSION['USER_LOGGED'] : $usrUid;

        $canStart = $this->canStartCase($usrUid);
        if ($canStart) {
            $processList = [];
            $list = $this->getStartCasesPerType($usrUid, $typeView);
            foreach ($list as $index => $row) {
                if (!empty($row['pro_uid'])) {
                    if ($typeView == 'category') {
                        $processList[] = [
                            'tas_uid' => $row['uid'],
                            'pro_title' => $row['value'],
                            'pro_uid' => $row['pro_uid'],
                            'pro_description' => $row['pro_description'],
                            'pro_category' => $row['cat'],
                            'category_name' => $row['catname']
                        ];
                    } else {
                        $processList[] = [
                            'tas_uid' => $row['uid'],
                            'pro_title' => $row['value'],
                            'pro_uid' => $row['pro_uid'],
                            'pro_description' => $row['pro_description']
                        ];
                    }
                }
            }
        } else {
            $processList['success'] = 'failure';
            $processList['message'] = G::LoadTranslation('ID_USER_PROCESS_NOT_START');
        }
        return $processList;
    }

    public function deleteDelegation($sAppUid)
    {
        $oAppDelegation = new AppDelegation();
        $oCriteria2 = new Criteria('workflow');
        $oCriteria2->add(AppDelegationPeer::APP_UID, $sAppUid);
        $oDataset2 = AppDelegationPeer::doSelectRS($oCriteria2);
        $oDataset2->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset2->next();
        while ($aRow2 = $oDataset2->getRow()) {
            $oAppDelegation->remove($sAppUid, $aRow2['DEL_INDEX']);
            $oDataset2->next();
        }
    }

    private function orderStartCasesByCategoryAndName($rows)
    {
        //now we order in category, proces_name order:
        $comparatorSequence = array(
            function ($a, $b) {
                $retval = 0;
                if (array_key_exists('catname', $a) && array_key_exists('catname', $b)) {
                    $retval = strcmp($a['catname'], $b['catname']);
                }
                return $retval;
            }
            , function ($a, $b) {
                $retval = 0;
                if (array_key_exists('value', $a) && array_key_exists('value', $b)) {
                    $retval = strcmp($a['value'], $b['value']);
                }
                return $retval;
            }
        );

        usort($rows, function ($a, $b) use ($comparatorSequence) {
            foreach ($comparatorSequence as $cmpFn) {
                $diff = call_user_func($cmpFn, $a, $b);
                if ($diff !== 0) {
                    return $diff;
                }
            }
            return 0;
        });
        return $rows;
    }

    /**
     * @param $proUid
     * @param $dynaformUid
     * @return bool
     */
    public function getAllObjectsTrackerDynaform($proUid, $dynaformUid)
    {
        $c = new Criteria();
        $c->clearSelectColumns();
        $c->addSelectColumn(CaseTrackerObjectPeer::CTO_TYPE_OBJ);
        $c->add(CaseTrackerObjectPeer::PRO_UID, $proUid, Criteria::EQUAL);
        $c->add(CaseTrackerObjectPeer::CTO_UID_OBJ, $dynaformUid, Criteria::EQUAL);
        $c->setLimit(1);
        $rs = CaseTrackerObjectPeer::doSelectRS($c);
        $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rs->next();
        $row = $rs->getRow();

        if (is_array($row)) {
            return true;
        }
        return false;
    }

    /**
     * Inserts int the ListInbox of the user $targetUserId case whose data is in the variable $caseDataRow
     *
     * @param array $caseDataRow, assoc. array with the data of the case
     * @param int $targetUserId, id of the user that will have the case.
     *
     * @return void
     */
    private function putCaseInInboxList(array $caseDataRow, $targetUserId)
    {
        $listInbox = new ListInbox();
        $caseDataRow["USR_UID"] = $targetUserId;
        $listInbox->create($caseDataRow);
    }

    /**
     * Obtains the task information and the user delegated to the task for an specific case
     *
     * @param string $applicationUid
     * @param string $processUid
     *
     * @see /workflow/engine/src/ProcessMaker/BusinessModel/Cases::getTasks($applicationUid)
     *
     * @return ResultSet
     * @throws Exception
     *
     */
    public function getTasksInfoForACase($applicationUid, $processUid)
    {
        $conn = Propel::getConnection('workflow');

        $sql = 'SELECT TASK.TAS_UID, TASK.TAS_TITLE, TASK.TAS_DESCRIPTION, TASK.TAS_START, 
                TASK.TAS_TYPE, TASK.TAS_DERIVATION, TASK.TAS_ASSIGN_TYPE, APP.USR_UID, USERS.USR_USERNAME, 
                USERS.USR_FIRSTNAME, USERS.USR_LASTNAME 
                FROM TASK LEFT JOIN (SELECT * FROM APP_DELEGATION WHERE APP_DELEGATION.APP_UID = ?) AS APP 
                ON TASK.TAS_UID = APP.TAS_UID LEFT JOIN USERS 
                ON (SELECT USR_UID FROM APP_DELEGATION WHERE APP_UID = ? AND TAS_UID = TASK.TAS_UID ORDER BY DEL_INDEX DESC LIMIT 1) = USERS.USR_UID 
                WHERE TASK.PRO_UID = ?';

        $stmt = $conn->prepareStatement($sql);

        $stmt->set(1, $applicationUid);
        $stmt->set(2, $applicationUid);
        $stmt->set(3, $processUid);

        if (!$stmt->executeQuery()) {
            throw Exception(G::LoadTranslation('ID_MSG_AJAX_FAILURE'));
        }

        return $stmt->getResultSet();
    }

    /**
     * Get the task information when the task is a sub-process
     *
     * @param string $processUid
     * @param string $tasUid
     *
     * @see /workflow/engine/src/ProcessMaker/BusinessModel/Cases::getTasks($applicationUid)
     *
     * @return ResultSet
     */
    public function getTaskInfoForSubProcess($processUid, $tasUid)
    {
        $criteria = new Criteria("workflow");

        $criteria->addSelectColumn(SubProcessPeer::PRO_UID);
        $criteria->addSelectColumn(TaskPeer::TAS_TITLE);
        $criteria->addSelectColumn(TaskPeer::TAS_DESCRIPTION);
        $criteria->addJoin(SubProcessPeer::TAS_PARENT, TaskPeer::TAS_UID, Criteria::LEFT_JOIN);
        $criteria->add(SubProcessPeer::PRO_PARENT, $processUid);
        $criteria->add(SubProcessPeer::TAS_PARENT, $tasUid);

        $rsCriteria = SubProcessPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        return $rsCriteria;
    }

    /**
     * Get the routes of a task
     *
     * @param string $processUid
     * @param string $tasUid
     *
     * @see /workflow/engine/src/ProcessMaker/BusinessModel/Cases::getTasks($applicationUid)
     *
     * @return ResultSet
     */
    public function getTaskRoutes($processUid, $tasUid)
    {
        $criteria = new Criteria("workflow");

        $criteria->addAsColumn("ROU_NUMBER", RoutePeer::ROU_CASE);
        $criteria->addSelectColumn(RoutePeer::ROU_TYPE);
        $criteria->addSelectColumn(RoutePeer::ROU_CONDITION);
        $criteria->addAsColumn("TAS_UID", RoutePeer::ROU_NEXT_TASK);
        $criteria->add(RoutePeer::PRO_UID, $processUid, Criteria::EQUAL);
        $criteria->add(RoutePeer::TAS_UID, $tasUid, Criteria::EQUAL);
        $criteria->addAscendingOrderByColumn("ROU_NUMBER");

        $rsCriteria = RoutePeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        return $rsCriteria;
    }

    /**
     * Get the delegations of an specific case
     *
     * @param string $applicationUid
     * @param string $tasUid
     *
     * @see /workflow/engine/src/ProcessMaker/BusinessModel/Cases::getTasks($applicationUid)
     *
     * @return ResultSet
     */
    public function getCaseDelegations($applicationUid, $tasUid)
    {
        $criteria = new Criteria("workflow");

        $criteria->addSelectColumn(AppDelegationPeer::DEL_INDEX);
        $criteria->addSelectColumn(AppDelegationPeer::DEL_DELEGATE_DATE);
        $criteria->addSelectColumn(AppDelegationPeer::DEL_INIT_DATE);
        $criteria->addSelectColumn(AppDelegationPeer::DEL_TASK_DUE_DATE);
        $criteria->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
        $criteria->addSelectColumn(UsersPeer::USR_UID);
        $criteria->addSelectColumn(UsersPeer::USR_USERNAME);
        $criteria->addSelectColumn(UsersPeer::USR_FIRSTNAME);
        $criteria->addSelectColumn(UsersPeer::USR_LASTNAME);

        $criteria->addJoin(AppDelegationPeer::USR_UID, UsersPeer::USR_UID, Criteria::LEFT_JOIN);

        $criteria->add(AppDelegationPeer::APP_UID, $applicationUid, Criteria::EQUAL);
        $criteria->add(AppDelegationPeer::TAS_UID, $tasUid, Criteria::EQUAL);
        $criteria->addAscendingOrderByColumn(AppDelegationPeer::DEL_INDEX);

        $rsCriteria = AppDelegationPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        return $rsCriteria;
    }

    /**
     * Get the total amount and the minimun date of the Delegation table for an specific case
     *
     * @param string $applicationUid
     * @param string $tasUid
     *
     * @see /workflow/engine/src/ProcessMaker/BusinessModel/Cases::getTasks($applicationUid)
     *
     * @return ResultSet
     */
    public function getTotalAndMinDateForACase($applicationUid, $tasUid)
    {
        $criteria = new Criteria("workflow");

        $criteria->addAsColumn("CANT", "COUNT(" . AppDelegationPeer::APP_UID . ")");
        $criteria->addAsColumn("FINISH", "MIN(" . AppDelegationPeer::DEL_FINISH_DATE . ")");
        $criteria->add(AppDelegationPeer::APP_UID, $applicationUid, Criteria::EQUAL);
        $criteria->add(AppDelegationPeer::TAS_UID, $tasUid, Criteria::EQUAL);

        $rsCriteria = AppDelegationPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        return $rsCriteria;
    }

    /**
     * Get the DEL_FINISH_DATE of the Delegation table of an specific task in a case
     *
     * @param string $applicationUid
     * @param string $tasUid
     *
     * @see /workflow/engine/src/ProcessMaker/BusinessModel/Cases::getTasks($applicationUid)
     *
     * @return ResultSet
     */
    public function getDelegationFinishDate($applicationUid, $tasUid)
    {
        $criteria = new Criteria("workflow");

        $criteria->addSelectColumn(AppDelegationPeer::DEL_FINISH_DATE);
        $criteria->add(AppDelegationPeer::APP_UID, $applicationUid, Criteria::EQUAL);
        $criteria->add(AppDelegationPeer::TAS_UID, $tasUid, Criteria::EQUAL);
        $criteria->add(AppDelegationPeer::DEL_FINISH_DATE, null, Criteria::ISNULL);

        $rsCriteria = AppDelegationPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        return $rsCriteria;
    }
}

