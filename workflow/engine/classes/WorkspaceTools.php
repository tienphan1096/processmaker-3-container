<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ProcessMaker\BusinessModel\Process as BmProcess;
use ProcessMaker\BusinessModel\TaskSchedulerBM;
use ProcessMaker\BusinessModel\WebEntry;
use ProcessMaker\Core\Installer;
use ProcessMaker\Core\ProcessesManager;
use ProcessMaker\Core\System;
use ProcessMaker\Model\Application;
use ProcessMaker\Model\Delegation;
use ProcessMaker\Model\Fields;
use ProcessMaker\Plugins\Adapters\PluginAdapter;
use ProcessMaker\Project\Adapter\BpmnWorkflow;
use ProcessMaker\Upgrade\RunProcessUpgradeQuery;
use ProcessMaker\Util\FixReferencePath;

/**
 * class workspaceTools.
 *
 * Utility functions to manage a workspace.
 *
 * @package workflow.engine.classes
 */
class WorkspaceTools
{
    public $name = null;
    public $path = null;
    public $db = null;
    public $dbPath = null;
    public $dbInfo = null;
    public $dbInfoRegExp = "/( *define *\( *'(?P<key>.*?)' *, *\n* *')(?P<value>.*?)(' *\) *;.*)/";
    public $initPropel = false;
    public $initPropelRoot = false;
    public static $populateIdsQueries = array(
        'UPDATE LIST_CANCELED SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_CANCELED.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_CANCELED.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_CANCELED.PRO_UID)',
        'UPDATE LIST_COMPLETED SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_COMPLETED.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_COMPLETED.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_COMPLETED.PRO_UID)',
        'UPDATE LIST_INBOX SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_INBOX.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_INBOX.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_INBOX.PRO_UID)',
        'UPDATE LIST_MY_INBOX SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_MY_INBOX.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_MY_INBOX.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_MY_INBOX.PRO_UID)',
        'UPDATE LIST_PARTICIPATED_HISTORY SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_PARTICIPATED_HISTORY.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_PARTICIPATED_HISTORY.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_PARTICIPATED_HISTORY.PRO_UID)',
        'UPDATE LIST_PARTICIPATED_LAST SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_PARTICIPATED_LAST.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_PARTICIPATED_LAST.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_PARTICIPATED_LAST.PRO_UID)',
        'UPDATE LIST_PAUSED SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_PAUSED.USR_UID),
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_PAUSED.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_PAUSED.PRO_UID)',
        'UPDATE LIST_UNASSIGNED SET
            TAS_ID=(SELECT TAS_ID FROM TASK WHERE TASK.TAS_UID=LIST_UNASSIGNED.TAS_UID),
            PRO_ID=(SELECT PRO_ID FROM PROCESS WHERE PROCESS.PRO_UID=LIST_UNASSIGNED.PRO_UID)',
        'UPDATE LIST_UNASSIGNED_GROUP SET
            USR_ID=(SELECT USR_ID FROM USERS WHERE USERS.USR_UID=LIST_UNASSIGNED_GROUP.USR_UID)',
    );
    public static $triggers = [
        'APP_DELEGATION_UPDATE',
        'APPLICATION_UPDATE',
        'CONTENT_UPDATE'
    ];
    public static $bigTables = [
        'APPLICATION',
        'APP_ASSIGN_SELF_SERVICE_VALUE_GROUP',
        'APP_CACHE_VIEW',
        'APP_DELEGATION',
        'APP_DELAY',
        'APP_DOCUMENT',
        'APP_HISTORY',
        'APP_MESSAGE',
        'APP_NOTES',
        'GROUP_USER',
        'LOGIN_LOG'
    ];
    private $lastContentMigrateTable = false;
    private $listContentMigrateTable = [];

    /**
     * Create a workspace tools object.
     * Note that workspace might not exist when
     * this object is created, however most methods requires that a workspace with
     * this name does exists.
     *
     * @author Alexandre Rosenfeld <alexandre@colosa.com>
     * @access public
     * @param string $workspaceName name of the workspace
     */
    public function __construct($workspaceName)
    {
        $this->name = $workspaceName;
        $this->path = PATH_DB . $this->name;
        $this->dbPath = $this->path . '/db.php';
        if ($this->workspaceExists()) {
            $this->getDBInfo();
        }
        $this->setListContentMigrateTable();
    }

    /**
     * Gets the last content migrate table
     *
     * @return string
     */
    public function getLastContentMigrateTable()
    {
        return $this->lastContentMigrateTable;
    }

    /**
     * Sets the last content migrate table
     *
     * @param string $tableColumn
     *
     */
    public function setLastContentMigrateTable($tableColumn)
    {
        $this->lastContentMigrateTable = $tableColumn;
    }

    /**
     * Gets the array for list content migrate table
     *
     * @return array
     */
    public function getListContentMigrateTable()
    {
        return $this->listContentMigrateTable;
    }

    /**
     * Sets the array list content migrate table
     */
    public function setListContentMigrateTable()
    {
        $migrateTables = array(
            'Groupwf' => array(
                'uid' => 'GRP_UID',
                'fields' => array('GRP_TITLE'),
                'methods' => array('exists' => 'GroupwfExists')
            ),
            'Process' => array(
                'uid' => 'PRO_UID',
                'fields' => array('PRO_TITLE', 'PRO_DESCRIPTION'),
                'methods' => array('exists' => 'exists')
            ),
            'Department' => array(
                'uid' => 'DEP_UID',
                'fields' => array('DEPO_TITLE'),
                'alias' => array('DEPO_TITLE' => 'DEP_TITLE'),
                'methods' => array('exists' => 'existsDepartment')
            ),
            'Task' => array(
                'uid' => 'TAS_UID',
                'fields' => array('TAS_TITLE', 'TAS_DESCRIPTION', 'TAS_DEF_TITLE', 'TAS_DEF_SUBJECT_MESSAGE', 'TAS_DEF_PROC_CODE', 'TAS_DEF_MESSAGE', 'TAS_DEF_DESCRIPTION'),
                'methods' => array('exists' => 'taskExists')
            ),
            'InputDocument' => array(
                'uid' => 'INP_DOC_UID',
                'fields' => array('INP_DOC_TITLE', 'INP_DOC_DESCRIPTION'),
                'methods' => array('exists' => 'InputExists')
            ),
            'Application' => array(
                'uid' => 'APP_UID',
                'fields' => array('APP_TITLE', 'APP_DESCRIPTION'),
                'methods' => array('exists' => 'exists')
            ),
            'AppDocument' => array(
                'uid' => 'APP_DOC_UID',
                'alias' => array('CON_PARENT' => 'DOC_VERSION'),
                'fields' => array('APP_DOC_TITLE', 'APP_DOC_COMMENT', 'APP_DOC_FILENAME'),
                'methods' => array('exists' => 'exists')
            ),
            'Dynaform' => array(
                'uid' => 'DYN_UID',
                'fields' => array('DYN_TITLE', 'DYN_DESCRIPTION'),
                'methods' => array('exists' => 'exists')
            ),
            'OutputDocument' => array(
                'uid' => 'OUT_DOC_UID',
                'fields' => array('OUT_DOC_TITLE', 'OUT_DOC_DESCRIPTION', 'OUT_DOC_FILENAME', 'OUT_DOC_TEMPLATE'),
                'methods' => array('exists' => 'OutputExists')
            ),
            'ReportTable' => array(
                'uid' => 'REP_TAB_UID',
                'fields' => array('REP_TAB_TITLE'),
                'methods' => array('exists' => 'reportTableExists', 'update' => function ($row) {
                    $oRepTab = \ReportTablePeer::retrieveByPK($row['REP_TAB_UID']);
                    $oRepTab->fromArray($row, BasePeer::TYPE_FIELDNAME);
                    if ($oRepTab->validate()) {
                        $result = $oRepTab->save();
                    }
                })
            ),
            'Triggers' => array(
                'uid' => 'TRI_UID',
                'fields' => array('TRI_TITLE', 'TRI_DESCRIPTION'),
                'methods' => array('exists' => 'TriggerExists')
            ),
            '\ProcessMaker\BusinessModel\WebEntryEvent' => array(
                'uid' => 'WEE_UID',
                'fields' => array('WEE_TITLE', 'WEE_DESCRIPTION'),
                'methods' => array('exists' => 'exists', 'update' => function ($row) {
                    $webEntry = \WebEntryEventPeer::retrieveByPK($row['WEE_UID']);
                    $webEntry->fromArray($row, BasePeer::TYPE_FIELDNAME);
                    if ($webEntry->validate()) {
                        $result = $webEntry->save();
                    }
                }),
                'peer' => 'WebEntryEventPeer'
            )
        );

        $this->listContentMigrateTable = $migrateTables;
    }

    /**
     * Returns true if the workspace already exists
     *
     * @return bool
     */
    public function workspaceExists()
    {
        return (file_exists($this->path) && file_exists($this->dbPath));
    }

    /**
     * Upgrade this workspace to the latest system version
     *
     * @param string $workspace
     * @param string $lang
     * @param array $arrayOptTranslation
     * @param array $optionMigrateHistoryData
     *
     * @return void
     */
    public function upgrade($workspace, $lang = 'en', array $arrayOptTranslation = null, $optionMigrateHistoryData = [])
    {
        if (is_null($arrayOptTranslation)) {
            $arrayOptTranslation = ['updateXml' => true, 'updateMafe' => true];
        }

        CLI::logging("* Start updating database schema...\n");
        $start = microtime(true);
        $this->upgradeDatabase(false);
        CLI::logging("* End updating database schema...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start updating translations...\n");
        $start = microtime(true);
        $this->upgradeTranslation($arrayOptTranslation['updateXml'], $arrayOptTranslation['updateMafe']);
        CLI::logging("* End updating translations...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start checking MAFE requirements...\n");
        $start = microtime(true);
        $this->checkMafeRequirements($workspace, $lang);
        CLI::logging("* End checking MAFE requirements...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start deleting MySQL triggers: " . implode(', ', self::$triggers) . "...\n");
        $start = microtime(true);
        $this->deleteTriggersMySQL(self::$triggers);
        CLI::logging("* End deleting MySQL triggers: " . implode(', ', self::$triggers) . "... (Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start deleting indexes from big tables: " . implode(', ', self::$bigTables) . "...\n");
        $start = microtime(true);
        $this->deleteIndexes(self::$bigTables);
        CLI::logging("* End deleting indexes from big tables: " . implode(', ', self::$bigTables) . "... (Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start to update CONTENT table...\n");
        $start = microtime(true);
        $this->upgradeContent($workspace);
        CLI::logging("* End to update CONTENT table... (Completed on  " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start to migrate texts/values from 'CONTENT' table to the corresponding object tables...\n");
        $start = microtime(true);
        $this->migrateContent($lang);
        CLI::logging("* End to migrate texts/values from 'CONTENT' table to the corresponding object tables... (Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start updating rows in Web Entry table for classic processes...\n");
        $start = microtime(true);
        $this->updatingWebEntryClassicModel(true);
        CLI::logging("* End updating rows in Web Entry table for classic processes...(Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start to update Files Manager...\n");
        $start = microtime(true);
        $this->processFilesUpgrade($workspace);
        CLI::logging("* End to update Files Manager... (Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start migrating and populating plugin singleton data...\n");
        $start = microtime(true);
        $this->migrateSingleton($workspace);
        CLI::logging("* End migrating and populating plugin singleton data...(Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start cleaning expired tokens...\n");
        $start = microtime(true);
        $this->cleanTokens();
        CLI::logging("* End cleaning expired tokens...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start to check Intermediate Email Event...\n");
        $start = microtime(true);
        $this->checkIntermediateEmailEvent();
        CLI::logging("* End to check Intermediate Email Event... (Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start cleaning DYN_CONTENT in APP_HISTORY...\n");
        $start = microtime(true);
        $keepDynContent = isset($optionMigrateHistoryData['keepDynContent']) && $optionMigrateHistoryData['keepDynContent'] === true;
        $this->clearDynContentHistoryData(false, $keepDynContent);
        CLI::logging("* End cleaning DYN_CONTENT in APP_HISTORY...(Completed on " . (microtime(true) - $start) . " seconds)\n");


        CLI::logging("* Start migrating and populating indexing for avoiding the use of table APP_CACHE_VIEW...\n");
        $start = microtime(true);
        $this->migratePopulateIndexingACV();
        CLI::logging("* End migrating and populating indexing for avoiding the use of table APP_CACHE_VIEW...(Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start optimizing Self-Service data in table APP_ASSIGN_SELF_SERVICE_VALUE_GROUP....\n");
        $start = microtime(true);
        $this->migrateSelfServiceRecordsRun();
        CLI::logging("* End optimizing Self-Service data in table APP_ASSIGN_SELF_SERVICE_VALUE_GROUP....(Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start adding new fields and populating values in tables related to feature self service by value...\n");
        $start = microtime(true);
        $this->upgradeSelfServiceData();
        CLI::logging("* End adding new fields and populating values in tables related to feature self service by value...(Completed on " .
            (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start adding/replenishing all indexes...\n");
        $start = microtime(true);
        $systemSchema = System::getSystemSchema($this->dbAdapter);
        $this->upgradeSchema($systemSchema);
        CLI::logging("* End adding/replenishing all indexes...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        // The list tables was deprecated, the migration is not required

        CLI::logging("* Start updating MySQL triggers...\n");
        $start = microtime(true);
        $this->updateTriggers(true, $lang);
        CLI::logging("* End updating MySQL triggers...(" . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start adding +async option to scheduler commands...\n");
        $start = microtime(true);
        $this->addAsyncOptionToSchedulerCommands(true);
        CLI::logging("* End adding +async option to scheduler commands...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start Converting Web Entries v1.0 to v2.0 for BPMN processes...\n");
        $start = microtime(true);
        Bootstrap::setConstantsRelatedWs($workspace);
        Propel::init(PATH_CONFIG . 'databases.php');
        $statement = Propel::getConnection('workflow')->createStatement();
        $statement->executeQuery(WebEntry::UPDATE_QUERY_V1_TO_V2);
        CLI::logging("* End converting Web Entries v1.0 to v2.0 for BPMN processes...(" . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start migrating case title...\n");
        $start = microtime(true);
        $this->migrateCaseTitleToThreads([$workspace]);
        CLI::logging("* End migrating case title...(Completed on " . (microtime(true) - $start) . " seconds)\n");

        CLI::logging("* Start converting Output Documents from 'HTML2PDF' to 'TCPDF'...\n");
        $start = microtime(true);
        $this->convertOutDocsHtml2Ps2Pdf([$workspace]);
        CLI::logging("* End converting Output Documents from 'HTML2PDF' to 'TCPDF...(Completed on " . (microtime(true) - $start) . " seconds)\n");
    }

    /**
     * Updating cases directories structure
     *
     */
    public function updateStructureDirectories($workSpace = null)
    {
        if ($workSpace === null) {
            $workSpace = config("system.workspace");
        }
        $start = microtime(true);
        CLI::logging("> Updating cases directories structure...\n");
        $this->upgradeCasesDirectoryStructure($workSpace);
        $stop = microtime(true);
        $final = $stop - $start;
        CLI::logging("<*>   Database Upgrade Structure Process took $final seconds.\n");
    }

    /**
     * Update the email events with the current email server
     */
    public function checkIntermediateEmailEvent()
    {
        $oEmailEvent = new \ProcessMaker\BusinessModel\EmailEvent();
        $oEmailServer = new \ProcessMaker\BusinessModel\EmailServer();
        $oCriteria = $oEmailEvent->getEmailEventCriteriaEmailServer();
        $rsCriteria = \EmailServerPeer::doSelectRS($oCriteria);
        $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
        while ($rsCriteria->next()) {
            $row = $rsCriteria->getRow();
            $newUidData = $oEmailServer->getUidEmailServer($row['EMAIL_EVENT_FROM']);
            if (is_array($newUidData)) {
                $oEmailEvent->update($row['EMAIL_EVENT_UID'], $newUidData);
            }
        }
    }

    /**
     * Scan the db.php file for database information and return it as an array
     *
     * @return array with database information
     */
    public function getDBInfo()
    {
        if (!$this->workspaceExists()) {
            throw new Exception("Could not get db.php in workspace " . $this->name);
        }
        if (isset($this->dbInfo)) {
            return $this->dbInfo;
        }
        $sDbFile = file_get_contents($this->dbPath);
        /* This regular expression will match any "define ('<key>', '<value>');"
         * with any combination of whitespace between words.
         * Each match will have these groups:
         * ((define('(<key>)2', ')1 (<value>)3 (');)4 )0
         */
        preg_match_all($this->dbInfoRegExp, $sDbFile, $matches, PREG_SET_ORDER);
        $values = [];
        foreach ($matches as $match) {
            $values[$match['key']] = $match['value'];
        }
        $this->dbAdapter = $values["DB_ADAPTER"];
        $this->dbName = $values["DB_NAME"];
        $this->dbHost = $values["DB_HOST"];
        $this->dbUser = $values["DB_USER"];
        $this->dbPass = $values["DB_PASS"];

        $this->dbRbacHost = $values["DB_RBAC_HOST"];
        $this->dbRbacName = $values["DB_RBAC_NAME"];
        $this->dbRbacUser = $values["DB_RBAC_USER"];
        $this->dbRbacPass = $values["DB_RBAC_PASS"];

        $this->setDataBaseConnectionPropertiesForEloquent();
        return $this->dbInfo = $values;
    }

    /**
     * This used for eloquent model.
     */
    public function setDataBaseConnectionPropertiesForEloquent(): void
    {
        $dbHost = explode(':', $this->dbHost);
        config(['database.connections.workflow.host' => $dbHost[0]]);
        config(['database.connections.workflow.database' => $this->dbName]);
        config(['database.connections.workflow.username' => $this->dbUser]);
        config(['database.connections.workflow.password' => $this->dbPass]);
        if (count($dbHost) > 1) {
            config(['database.connections.workflow.port' => $dbHost[1]]);
        }
    }

    private function resetDBInfoCallback($matches)
    {
        /* This function changes the values of defines while keeping their formatting
         * intact.
         * $matches will contain several groups:
         * ((define('(<key>)2', ')1 (<value>)3 (');)4 )0
         */
        $key = isset($matches['key']) ? $matches['key'] : $matches[2];
        $value = isset($matches['value']) ? $matches['value'] : $matches[3];

        if ($this->onedb) {
            $dbInfo = $this->getDBInfo();
            $dbPrefix = array('DB_NAME' => 'wf_', 'DB_RBAC_NAME' => 'wf_', 'DB_REPORT_NAME' => 'wf_');
            $dbPrefixUser = array('DB_USER' => 'wf_', 'DB_RBAC_USER' => 'wf_', 'DB_REPORT_USER' => 'wf_');
        } else {
            $dbPrefix = array('DB_NAME' => 'wf_', 'DB_RBAC_NAME' => 'rb_', 'DB_REPORT_NAME' => 'rp_');
            $dbPrefixUser = array('DB_USER' => 'wf_', 'DB_RBAC_USER' => 'rb_', 'DB_REPORT_USER' => 'rp_');
        }

        if (array_search($key, array('DB_HOST', 'DB_RBAC_HOST', 'DB_REPORT_HOST')) !== false) {
            /* Change the database hostname for these keys */
            $value = $this->newHost;
        } elseif (array_key_exists($key, $dbPrefix)) {
            if ($this->resetDBNames) {
                /* Change the database name to the new workspace, following the standard
                 * of prefix (either wf_, rp_, rb_) and the workspace name.
                 */
                if ($this->unify) {
                    $nameDb = explode("_", $value);
                    if (!isset($nameDb[1])) {
                        $dbName = $value;
                    } else {
                        $dbName = $dbPrefix[$key] . $nameDb[1];
                    }
                } else {
                    $dbName = $dbPrefix[$key] . $this->name;
                }
            } else {
                $dbName = $value;
            }
            $this->resetDBDiff[$value] = $dbName;
            $value = $dbName;
        } elseif (array_key_exists($key, $dbPrefixUser)) {
            if ($this->resetDBNames) {
                $dbName = $this->dbGrantUser;
            } else {
                $dbName = $value;
            }
            $this->resetDBDiff['DB_USER'] = $dbName;
            $value = $dbName;
        }
        if (array_search($key, array('DB_PASS', 'DB_RBAC_PASS', 'DB_REPORT_PASS')) !== false && !empty($this->dbGrantUserPassword)) {
            $value = $this->dbGrantUserPassword;
        }
        return $matches[1] . $value . $matches[4];
    }

    /**
     * Reset the database information to that of a newly created workspace.
     *
     * This assumes this workspace already has a db.php file, which will be changed
     * to contain the new information.
     * This function will reset the database hostname to the system database.
     * If reseting database names, it will also use the the prefixes rp_,
     * rb_ and wf_, with the workspace name as database names.
     *
     * @param string $newHost the new hostname for the database
     * @param bool $resetDBNames if true, also reset all database names
     * @return array contains the new database names as values
     */
    public function resetDBInfo($newHost, $resetDBNames = true, $onedb = false, $unify = false)
    {
        if (count(explode(":", $newHost)) < 2) {
            $newHost .= ':3306';
        }
        $this->newHost = $newHost;
        $this->resetDBNames = $resetDBNames;
        $this->resetDBDiff = [];
        $this->onedb = $onedb;
        $this->unify = $unify;
        if ($resetDBNames) {
            $this->dbGrantUser = uniqid('wf_');
            $this->dbGrantUserPassword = G::generate_password(12, "luns", ".");
        }


        if (!$this->workspaceExists()) {
            throw new Exception("Could not find db.php in the workspace");
        }
        $sDbFile = file_get_contents($this->dbPath);

        if ($sDbFile === false) {
            throw new Exception("Could not read database information from db.php");
        }
        /* Match all defines in the config file. Check updateDBCallback to know what
         * keys are changed and what groups are matched.
         * This regular expression will match any "define ('<key>', '<value>');"
         * with any combination of whitespace between words.
         */
        $sNewDbFile = preg_replace_callback("/( *define *\( *'(?P<key>.*?)' *, *\n* *')(?P<value>.*?)(' *\) *;.*)/", array(&$this, 'resetDBInfoCallback'), $sDbFile);
        if (file_put_contents($this->dbPath, $sNewDbFile) === false) {
            throw new Exception("Could not write database information to db.php");
        }
        $newDBNames = $this->resetDBDiff;
        unset($this->resetDBDiff);
        unset($this->resetDBNames);
        //Clear the cached information about db.php
        unset($this->dbInfo);
        return $newDBNames;
    }

    /**
     * Get DB information for this workspace, such as hostname, username and password.
     *
     * @param string $dbName a db name, such as wf, rp and rb
     * @return array with all the database information.
     */
    public function getDBCredentials($dbName)
    {
        $prefixes = array("wf" => "", "rp" => "REPORT_", "rb" => "RBAC_");
        $prefix = $prefixes[$dbName];
        $dbInfo = $this->getDBInfo();
        return array('adapter' => $dbInfo["DB_ADAPTER"], 'name' => $dbInfo["DB_" . $prefix . "NAME"], 'host' => $dbInfo["DB_" . $prefix . "HOST"], 'user' => $dbInfo["DB_" . $prefix . "USER"], 'pass' => $dbInfo["DB_" . $prefix . "PASS"], 'dsn' => sprintf("%s://%s:%s@%s/%s?encoding=utf8", $dbInfo['DB_ADAPTER'], $dbInfo["DB_" . $prefix . "USER"], $dbInfo["DB_" . $prefix . "PASS"], $dbInfo["DB_" . $prefix . "HOST"], $dbInfo["DB_" . $prefix . "NAME"]));
    }

    /**
     * Initialize a Propel connection to the database
     *
     * @param bool $root wheter to also initialize a root connection
     * @return the Propel connection
     */
    public function initPropel($root = false)
    {
        if (($this->initPropel && !$root) || ($this->initPropelRoot && $root)) {
            return;
        }
        $wfDetails = $this->getDBCredentials("wf");
        $rbDetails = $this->getDBCredentials("rb");
        $rpDetails = $this->getDBCredentials("rp");

        $config = array(
            'datasources' => array(
                'workflow' => array(
                    'connection' => $wfDetails["dsn"],
                    'adapter' => $wfDetails["adapter"]
                ),
                'rbac' => array(
                    'connection' => $rbDetails["dsn"],
                    'adapter' => $rbDetails["adapter"]
                ),
                'rp' => array(
                    'connection' => $rpDetails["dsn"],
                    'adapter' => $rpDetails["adapter"]
                )
            )
        );

        if ($root) {
            $dbHash = @explode(SYSTEM_HASH, G::decrypt(HASH_INSTALLATION, SYSTEM_HASH));

            $dbInfo = $this->getDBInfo();
            $host = $dbHash[0];
            $user = $dbHash[1];
            $pass = $dbHash[2];
            $dbName = $dbInfo["DB_NAME"];

            $rootConfig = array(
                'datasources' => array('root' => array('connection' => "mysql://$user:$pass@$host/$dbName?encoding=utf8", 'adapter' => "mysql"))
            );

            $config["datasources"] = array_merge($config["datasources"], $rootConfig["datasources"]);

            $this->initPropelRoot = true;
        }

        $this->initPropel = true;

        require_once("propel/Propel.php");
        require_once("creole/Creole.php");

        Propel::initConfiguration($config);
    }

    /**
     * Close the propel connection from initPropel
     */
    private function closePropel()
    {
        Propel::close();
        $this->initPropel = false;
        $this->initPropelRoot = false;
    }

    /**
     * Upgrade this workspace Content
     *
     * @param string $workspace
     * @param boolean $executeRegenerateContent
     *
     * @return void
     */
    public function upgradeContent($workspace = null, $executeRegenerateContent = false)
    {
        if ($workspace === null) {
            $workspace = config("system.workspace");
        }
        $this->initPropel(true);
        //If the execute flag is false we will check if we needed
        if (!$executeRegenerateContent) {
            $conf = new Configuration();
            $blackList = [];
            if ($conf->exists('MIGRATED_CONTENT', 'content')) {
                $configData = $conf->load('MIGRATED_CONTENT', 'content');
                $blackList = unserialize($configData['CFG_VALUE']);
            }

            if (count($blackList) > 0) {
                //If we have the flag MIGRATED_CONTENT we will check the $blackList
                $content = $this->getListContentMigrateTable();
                foreach ($content as $className => $fields) {
                    //We check if all the label was migrated from content table
                    if (!in_array($className, $blackList)) {
                        $executeRegenerateContent = true;
                        break;
                    }
                }
            } else {
                //If the flag does not exist we will check over the schema
                //The $lastContentMigrateTable return false if we need to force regenerate content
                if (!$this->getLastContentMigrateTable()) {
                    $executeRegenerateContent = true;
                }
            }
        }

        //We will to regenerate the Content table
        if ($executeRegenerateContent) {
            CLI::logging("->   Start To Update...\n");
            $translation = new Translation();
            $information = $translation->getTranslationEnvironments();
            $arrayLang = [];
            foreach ($information as $key => $value) {
                $arrayLang[] = trim($value['LOCALE']);
            }
            $regenerateContent = new Content();
            $regenerateContent->regenerateContent($arrayLang, $workspace);
        }
    }

    /**
     * Upgrade the workspace translations from all available languages
     *
     * @param bool $flagXml Update XML
     * @param bool $flagMafe Update MAFE
     *
     * @return void
     */
    public function upgradeTranslation($flagXml = true, $flagMafe = true)
    {
        $this->initPropel(true);
        $this->checkDataConsistenceInContentTable();


        $language = new Language();

        foreach (System::listPoFiles() as $poFile) {
            $poName = basename($poFile);
            $names = explode(".", basename($poFile));
            $extension = array_pop($names);
            $langid = array_pop($names);

            CLI::logging('Updating Database translations with ' . $poName . "\n");

            if ($flagXml) {
                CLI::logging('Updating XML form translations with ' . $poName . "\n");
            }

            if ($flagMafe) {
                CLI::logging('Updating MAFE translations with ' . $poName . "\n");
            }

            $language->import($poFile, $flagXml, true, $flagMafe);
        }
    }

    /**
     * Verification of the Content data table for column CON_ID
     * @return void
     */
    private function checkDataConsistenceInContentTable()
    {
        $criteriaSelect = new Criteria("workflow");
        $criteriaSelect->add(
            $criteriaSelect->getNewCriterion(ContentPeer::CON_ID, '%' . "'" . '%', Criteria::LIKE)->addOr(
                $criteriaSelect->getNewCriterion(ContentPeer::CON_ID, '%' . '"' . '%', Criteria::LIKE)
            )
        );

        BasePeer::doDelete($criteriaSelect, Propel::getConnection("workflow"));
    }

    /**
     * Get a connection to this workspace wf database
     *
     * @return database connection
     */
    private function getDatabase($rbac = false)
    {
        if (isset($this->db) && $this->db->isConnected() && ($rbac == false && $this->db->getDatabaseName() == $this->dbName)) {
            return $this->db;
        }


        if ($rbac == true) {
            $this->db = new database($this->dbAdapter, $this->dbRbacHost, $this->dbRbacUser, $this->dbRbacPass, $this->dbRbacName);
        } else {
            $this->db = new database($this->dbAdapter, $this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
        }
        if (!$this->db->isConnected()) {
            $this->db->logQuery('No available connection to database!');
            throw new Exception("Could not connect to database");
        }
        return $this->db;
    }

    /**
     * Close any database opened with getDatabase
     */
    private function closeDatabase()
    {
        if (!isset($this->db)) {
            return;
        }
        $this->db->close();
        $this->db = null;
    }

    /**
     * Close all currently opened databases
     */
    public function close()
    {
        $this->closePropel();
        $this->closeDatabase();
    }

    /**
     * Get the current workspace database schema
     *
     * @return array with the database schema
     */
    public function getSchema($rbac = false)
    {
        $database = $this->getDatabase($rbac);

        $oldSchema = [];

        try {
            $database->iFetchType = MYSQLI_NUM;
            $result = $database->executeQuery($database->generateShowTablesSQL());
        } catch (Exception $e) {
            $database->logQuery($e->getmessage());
            return null;
        }

        //going thru all tables in current WF_ database
        foreach ($result as $table) {
            $table = strtoupper($table);

            //get description of each table, ( column and primary keys )
            $database->iFetchType = MYSQLI_ASSOC;
            $description = $database->executeQuery($database->generateDescTableSQL($table));
            $oldSchema[$table] = [];
            foreach ($description as $field) {
                $type = $field['Type'];
                if ($type === "int") {
                    $field['Type'] = $type . "(11)";
                }
                if ($type === "tinyint") {
                    $field['Type'] = $type . "(4)";
                }
                if ($type === "bigint") {
                    $field['Type'] = $type . "(20)";
                }
                $oldSchema[$table][$field['Field']]['Field'] = $field['Field'];
                $oldSchema[$table][$field['Field']]['Type'] = $field['Type'];
                $oldSchema[$table][$field['Field']]['Null'] = $field['Null'];
                $oldSchema[$table][$field['Field']]['Default'] = $field['Default'];
            }

            // Get indexes of each table  SHOW INDEX FROM `ADDITIONAL_TABLES`;
            $description = $database->executeQuery($database->generateTableIndexSQL($table));
            foreach ($description as $field) {
                $type = $field['Index_type'] != 'FULLTEXT' ? 'INDEXES' : 'FULLTEXT';
                if (!isset($oldSchema[$table][$type])) {
                    $oldSchema[$table][$type] = [];
                }
                if (!isset($oldSchema[$table][$type][$field['Key_name']])) {
                    $oldSchema[$table][$type][$field['Key_name']] = [];
                }
                $oldSchema[$table][$type][$field['Key_name']][] = $field['Column_name'];
            }

        }
        //finally return the array with old schema obtained from the Database
        if (count($oldSchema) === 0) {
            $oldSchema = null;
        }
        return $oldSchema;
    }

    /**
     * Upgrade triggers of tables (Database)
     *
     * @param bool $flagRecreate Recreate
     * @param string $language Language
     *
     * return void
     */
    private function upgradeTriggersOfTables($flagRecreate, $language)
    {
        try {
            $appCacheView = new AppCacheView();
            $appCacheView->setPathToAppCacheFiles(PATH_METHODS . "setup" . PATH_SEP . "setupSchemas" . PATH_SEP);

            $result = $appCacheView->triggerAppDelegationInsert($language, $flagRecreate);
            $result = $appCacheView->triggerAppDelegationUpdate($language, $flagRecreate);
            $result = $appCacheView->triggerApplicationUpdate($language, $flagRecreate);
            $result = $appCacheView->triggerApplicationDelete($language, $flagRecreate);
            $result = $appCacheView->triggerSubApplicationInsert($language, $flagRecreate);
            $result = $appCacheView->triggerContentUpdate($language, $flagRecreate);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Upgrade the AppCacheView table to the latest system version.
     *
     * This recreates the table and populates with data.
     *
     * @param bool $flagRecreate only check if the upgrade is needed if true
     * @param string $lang not currently used
     */
    public function upgradeCacheView($fill = true, $flagRecreate = false, $lang = "en")
    {
        $this->initPropel(true);

        //check the language, if no info in config about language, the default is 'en'

        $oConf = new Configurations();
        $oConf->loadConfig($x, 'APP_CACHE_VIEW_ENGINE', '', '', '', '');
        $appCacheViewEngine = $oConf->aConfig;

        //setup the appcacheview object, and the path for the sql files
        $appCache = new AppCacheView();
        $appCache->setPathToAppCacheFiles(PATH_METHODS . 'setup' . PATH_SEP . 'setupSchemas' . PATH_SEP);

        $userGrants = $appCache->checkGrantsForUser(false);

        $currentUser = $userGrants['user'];
        $currentUserIsSuper = $userGrants['super'];
        //if user does not have the SUPER privilege we need to use the root user and grant the SUPER priv. to normal user.

        if (!$currentUserIsSuper) {
            $appCache->checkGrantsForUser(true);
            $currentUserIsSuper = true;
        }

        CLI::logging("-> Creating tables \n");
        //now check if table APPCACHEVIEW exists, and it have correct number of fields, etc.
        $res = $appCache->checkAppCacheView();

        CLI::logging("-> Update DEL_LAST_INDEX field in APP_DELEGATION table \n");
        //Update APP_DELEGATION.DEL_LAST_INDEX data
        $res = $appCache->updateAppDelegationDelLastIndex($lang, $flagRecreate);


        CLI::logging("-> Creating triggers\n");

        //now check if we have the triggers installed
        $this->upgradeTriggersOfTables($flagRecreate, $lang);

        if ($fill) {
            CLI::logging("-> Rebuild Cache View with language $lang...\n");
            //build using the method in AppCacheView Class
            $res = $appCache->fillAppCacheView($lang);
        }
        //set status in config table
        $confParams = array('LANG' => $lang, 'STATUS' => 'active');
        $oConf->aConfig = $confParams;
        $oConf->saveConfig('APP_CACHE_VIEW_ENGINE', '', '', '');

        // removing casesList configuration records. TODO: removing these lines that resets all the configurations records
        $oCriteria = new Criteria();
        $oCriteria->add(ConfigurationPeer::CFG_UID, "casesList");
        $oCriteria->add(ConfigurationPeer::OBJ_UID, array("todo", "draft", "sent", "unassigned", "paused", "cancelled"), Criteria::NOT_IN);
        ConfigurationPeer::doDelete($oCriteria);
        // end of reset

        //close connection
        if (substr(PHP_OS, 0, 3) != 'WIN') {
            $connection = Propel::getConnection('workflow');

            $sql_sleep = "SELECT * FROM information_schema.processlist WHERE command = 'Sleep' and user = SUBSTRING_INDEX(USER(),'@',1) and db = DATABASE() ORDER BY id;";
            $stmt_sleep = $connection->createStatement();
            $rs_sleep = $stmt_sleep->executeQuery($sql_sleep, ResultSet::FETCHMODE_ASSOC);

            while ($rs_sleep->next()) {
                $row_sleep = $rs_sleep->getRow();
                $oStatement_sleep = $connection->prepareStatement("kill " . $row_sleep['ID']);
                $oStatement_sleep->executeQuery();
            }

            $sql_query = "SELECT * FROM information_schema.processlist WHERE user = SUBSTRING_INDEX(USER(),'@',1) and db = DATABASE() and time > 0 ORDER BY id;";
            $stmt_query = $connection->createStatement();
            $rs_query = $stmt_query->executeQuery($sql_query, ResultSet::FETCHMODE_ASSOC);

            while ($rs_query->next()) {
                $row_query = $rs_query->getRow();
                $oStatement_query = $connection->prepareStatement("kill " . $row_query['ID']);
                $oStatement_query->executeQuery();
            }
        }
    }

    /**
     * fix the 32K issue, by migrating /files directory structure to an uid tree structure based.
     * @param $workspace got the site(s) the manager wants to upgrade
     */
    public function upgradeCasesDirectoryStructure($workspace)
    {
        define('PATH_DOCUMENT', PATH_DATA . 'sites' . DIRECTORY_SEPARATOR . $workspace . DIRECTORY_SEPARATOR . 'files');
        if (!is_writable(PATH_DOCUMENT)) {
            CLI::logging(CLI::error("Error:" . PATH_DOCUMENT . " is not writable... please check the su permissions.\n"));
            return;
        }

        $directory = [];
        $blackHoleDir = G::getBlackHoleDir();
        $directory = glob(PATH_DOCUMENT . "*", GLOB_ONLYDIR);
        $dirslength = count($directory);

        if (!@chdir(PATH_DOCUMENT)) {
            CLI::logging(CLI::error("Cannot use Document directory. The upgrade must be done as root.\n"));
            return;
        }

        //Start migration
        for ($index = 0; $index < $dirslength; $index++) {
            $depthdirlevel = explode(DIRECTORY_SEPARATOR, $directory[$index]);
            $lastlength = count($depthdirlevel);
            $UIdDir = $depthdirlevel[$lastlength - 1];
            $lenDir = strlen($UIdDir);

            if ($lenDir == 32 && $UIdDir != $blackHoleDir) {
                $len = count(scandir($UIdDir));
                if ($len > 2) {
                    //lenght = 2, because the function check . and .. dir links
                    $newDiretory = G::getPathFromUIDPlain($UIdDir);
                    CLI::logging("Migrating $UIdDir to $newDiretory\n");
                    G::mk_dir($newDiretory, 0777);
                    //echo `cp -R $UIdDir/* $newDiretory/`;

                    if (G::recursive_copy($UIdDir, $newDiretory)) {
                        CLI::logging("Removing $UIdDir...\n");
                        G::rm_dir($UIdDir);
                        rmdir($UIdDir); //remove the diretory itself, G::rm_dir cannot do it
                    } else {
                        CLI::logging(CLI::error("Error: Failure at coping from $UIdDir...\n"));
                    }
                } else {
                    CLI::logging("$UIdDir is empty, removing it\n");
                    rmdir($UIdDir); //remove the diretory itself
                }
            }
        }

        //Start '0' directory migration
        $black = PATH_DOCUMENT . $blackHoleDir . DIRECTORY_SEPARATOR;
        if (is_dir($black)) {
            $newpattern = [];
            $file = glob($black . '*.*'); //files only
            $dirlen = count($file);

            for ($index = 0; $index < $dirlen; $index++) {
                $levelfile = explode(DIRECTORY_SEPARATOR, $file[$index]);
                $lastlevel = count($levelfile);
                $goalFile = $levelfile[$lastlevel - 1];
                $newpattern = G::getPathFromFileUIDPlain($blackHoleDir, $goalFile);
                CLI::logging("Migrating $blackHoleDir file: $goalFile\n");
                G::mk_dir($blackHoleDir . PATH_SEP . $newpattern[0], 0777);
                //echo `cp -R $black$goalFile $black$newpattern[0]/$newpattern[1]`;

                if (copy($black . $goalFile, $black . $newpattern[0] . DIRECTORY_SEPARATOR . $newpattern[1])) {
                    unlink($file[$index]);
                } else {
                    CLI::logging(CLI::error("Error: Failure at copy $file[$index] files...\n"));
                }
            }
        }

        //Set value of 2 to the directory structure version.
        $this->initPropel(true);
        $conf = new Configurations();
        if (!$conf->exists("ENVIRONMENT_SETTINGS")) {
            $conf->aConfig = array(
                "format" => '@userName (@firstName @lastName)',
                "dateFormat" => 'd/m/Y',
                "startCaseHideProcessInf" => false,
                "casesListDateFormat" => 'Y-m-d H:i:s',
                "casesListRowNumber" => 25,
                "casesListRefreshTime" => 120
            );
            $conf->saveConfig('ENVIRONMENT_SETTINGS', '');
        }
        $conf->setDirectoryStructureVer(2);
        CLI::logging(CLI::info("Version Directory Structure is 2 now.\n"));
    }

    /**
     * Upgrade this workspace database to the latest plugins schema
     */
    public function upgradePluginsDatabase()
    {
        foreach (System::getPlugins() as $pluginName) {
            $pluginSchema = System::getPluginSchema($pluginName);
            if ($pluginSchema !== false) {
                CLI::logging("Updating plugin " . CLI::info($pluginName) . "\n");
                $this->upgradeSchema($pluginSchema);
            }
        }
    }

    /**
     * Upgrade the workspace database to the latest system schema
     *
     * @param bool $includeIndexes
     */
    public function upgradeDatabase($includeIndexes = true)
    {
        $this->initPropel(true);
        P11835::$dbAdapter = $this->dbAdapter;
        P11835::isApplicable();
        $systemSchema = System::getSystemSchema($this->dbAdapter);
        $systemSchemaRbac = System::getSystemSchemaRbac($this->dbAdapter); // Get the RBAC Schema
        $this->registerSystemTables(array_merge($systemSchema, $systemSchemaRbac));
        $this->upgradeSchema($systemSchema, false, false, $includeIndexes);
        $this->upgradeSchema($systemSchemaRbac, false, true); // Perform upgrade to RBAC
        $this->upgradeData();
        $this->updateIsoCountry();
        $this->checkRbacPermissions(); //check or add new permissions
        $this->checkSchedulerTable();
        $this->checkSequenceNumber();
        $this->migrateIteeToDummytask($this->name);

        //There records in table "EMAIL_SERVER"
        $criteria = new Criteria("workflow");

        $criteria->addSelectColumn(EmailServerPeer::MESS_UID);
        $criteria->setOffset(0);
        $criteria->setLimit(1);

        $rsCriteria = EmailServerPeer::doSelectRS($criteria);

        if (!$rsCriteria->next()) {
            //Insert the first record
            $arrayData = [];

            $emailSever = new \ProcessMaker\BusinessModel\EmailServer();

            $emailConfiguration = System::getEmailConfiguration();

            if (!empty($emailConfiguration)) {
                $arrayData["MESS_ENGINE"] = $emailConfiguration["MESS_ENGINE"];

                switch ($emailConfiguration["MESS_ENGINE"]) {
                    case "PHPMAILER":
                        $arrayData["MESS_SERVER"] = $emailConfiguration["MESS_SERVER"];
                        $arrayData["MESS_PORT"] = (int)($emailConfiguration["MESS_PORT"]);
                        $arrayData["MESS_RAUTH"] = (is_numeric($emailConfiguration["MESS_RAUTH"])) ? (int)($emailConfiguration["MESS_RAUTH"]) : (($emailConfiguration["MESS_RAUTH"] . "" == "true") ? 1 : 0);
                        $arrayData["MESS_ACCOUNT"] = $emailConfiguration["MESS_ACCOUNT"];
                        $arrayData["MESS_PASSWORD"] = $emailConfiguration["MESS_PASSWORD"];
                        $arrayData["MESS_FROM_MAIL"] = (isset($emailConfiguration["MESS_FROM_MAIL"])) ? $emailConfiguration["MESS_FROM_MAIL"] : "";
                        $arrayData["MESS_FROM_NAME"] = (isset($emailConfiguration["MESS_FROM_NAME"])) ? $emailConfiguration["MESS_FROM_NAME"] : "";
                        $arrayData["SMTPSECURE"] = $emailConfiguration["SMTPSecure"];
                        $arrayData["MESS_TRY_SEND_INMEDIATLY"] = (isset($emailConfiguration["MESS_TRY_SEND_INMEDIATLY"]) && ($emailConfiguration["MESS_TRY_SEND_INMEDIATLY"] . "" == "true" || $emailConfiguration["MESS_TRY_SEND_INMEDIATLY"] . "" == "1")) ? 1 : 0;
                        $arrayData["MAIL_TO"] = isset($emailConfiguration["MAIL_TO"]) ? $emailConfiguration["MAIL_TO"] : '';
                        $arrayData["MESS_DEFAULT"] = (isset($emailConfiguration["MESS_ENABLED"]) && $emailConfiguration["MESS_ENABLED"] . "" == "1") ? 1 : 0;
                        break;
                    case "MAIL":
                        $arrayData["MESS_SERVER"] = "";
                        $arrayData["MESS_FROM_MAIL"] = (isset($emailConfiguration["MESS_FROM_MAIL"])) ? $emailConfiguration["MESS_FROM_MAIL"] : "";
                        $arrayData["MESS_FROM_NAME"] = (isset($emailConfiguration["MESS_FROM_NAME"])) ? $emailConfiguration["MESS_FROM_NAME"] : "";
                        $arrayData["MESS_TRY_SEND_INMEDIATLY"] = (isset($emailConfiguration["MESS_TRY_SEND_INMEDIATLY"]) && ($emailConfiguration["MESS_TRY_SEND_INMEDIATLY"] . "" == "true" || $emailConfiguration["MESS_TRY_SEND_INMEDIATLY"] . "" == "1")) ? 1 : 0;
                        $arrayData["MESS_ACCOUNT"] = "";
                        $arrayData["MESS_PASSWORD"] = "";
                        $arrayData["MAIL_TO"] = (isset($emailConfiguration["MAIL_TO"])) ? $emailConfiguration["MAIL_TO"] : "";
                        $arrayData["MESS_DEFAULT"] = (isset($emailConfiguration["MESS_ENABLED"]) && $emailConfiguration["MESS_ENABLED"] . "" == "1") ? 1 : 0;
                        break;
                }

                $arrayData = $emailSever->create($arrayData);
            } else {
                $arrayData["MESS_ENGINE"] = "MAIL";
                $arrayData["MESS_SERVER"] = "";
                $arrayData["MESS_ACCOUNT"] = "";
                $arrayData["MESS_PASSWORD"] = "";
                $arrayData["MAIL_TO"] = "";
                $arrayData["MESS_DEFAULT"] = 1;

                $arrayData = $emailSever->create2($arrayData);
            }
        }
        P11835::execute();
    }

    private function setFormatRows()
    {
        switch ($this->dbAdapter) {
            case 'mysql':
                $this->assoc = MYSQLI_ASSOC;
                $this->num = MYSQLI_NUM;
                break;
            case 'sqlsrv':
                $this->assoc = SQLSRV_FETCH_ASSOC;
                $this->num = SQLSRV_FETCH_NUMERIC;
                break;
            default:
                throw new Exception("Unknown adapter hae been set for associate fetch index row format.");
                break;
        }
    }

    /**
     * Upgrade the workspace database according to the schema
     *
     * @param array $schema The schema information, such as returned from getSystemSchema
     * @param bool $checkOnly Only return the diff between current database and the schema
     * @param bool $rbac Is RBAC database?
     * @param bool $includeIndexes Include or no indexes in new tables
     *
     * @return bool|array
     *
     * @throws Exception
     */
    public function upgradeSchema($schema, $checkOnly = false, $rbac = false, $includeIndexes = true)
    {
        $dbInfo = $this->getDBInfo();

        if ($dbInfo['DB_NAME'] == $dbInfo['DB_RBAC_NAME']) {
            $onedb = true;
        } else {
            $onedb = false;
        }

        if (strcmp($dbInfo["DB_ADAPTER"], "mysql") != 0) {
            throw new Exception("Only MySQL is supported");
        }

        $this->setFormatRows();

        $workspaceSchema = $this->getSchema($rbac);
        $database = $this->getDatabase($rbac);

        if (!$onedb) {
            if ($rbac) {
                $rename = System::verifyRbacSchema($workspaceSchema);
                if (count($rename) > 0) {
                    foreach ($rename as $tableName) {
                        $database->executeQuery($database->generateRenameTableSQL($tableName));
                    }
                }
            }
        }
        $workspaceSchema = $this->getSchema($rbac);

        //We will check if the database has the last content table migration
        $this->checkLastContentMigrate($workspaceSchema);

        $changes = System::compareSchema($workspaceSchema, $schema);

        $changed = (count($changes['tablesToAdd']) > 0 || count($changes['tablesToAlter']) > 0 ||
            count($changes['tablesWithNewIndex']) > 0 || count($changes['tablesToAlterIndex']) > 0 ||
            count($changes['tablesWithNewFulltext']) > 0 || count($changes['tablesToAlterFulltext']) > 0);

        if ($checkOnly || (!$changed)) {
            if ($changed) {
                return $changes;
            } else {
                CLI::logging("-> Nothing to change in the data base structure of " . (($rbac == true) ? "RBAC" : "WORKFLOW") . "\n");
                return $changed;
            }
        }

        $database->iFetchType = $this->num;

        $database->logQuery(count($changes));

        if (!empty($changes['tablesToAdd'])) {
            CLI::logging("-> " . count($changes['tablesToAdd']) . " tables to add\n");
        }

        foreach ($changes['tablesToAdd'] as $tableName => $columns) {
            $database->executeQuery($database->generateCreateTableSQL($tableName, $columns));
            if (isset($changes['tablesToAdd'][$tableName]['INDEXES']) && $includeIndexes) {
                foreach ($changes['tablesToAdd'][$tableName]['INDEXES'] as $indexName => $keys) {
                    $database->executeQuery($database->generateAddKeysSQL($tableName, $indexName, $keys));
                }
            }
        }

        if (!empty($changes['tablesToAlter'])) {
            CLI::logging("-> " . count($changes['tablesToAlter']) . " tables to alter\n");
        }

        $tablesToAddColumns = [];

        // Drop or change columns
        foreach ($changes['tablesToAlter'] as $tableName => $actions) {
            foreach ($actions as $action => $actionData) {
                if ($action == 'ADD') {
                    $tablesToAddColumns[$tableName] = $actionData;

                    // In a very old schema the primary key for tables "LOGIN_LOG" and "APP_SEQUENCE" were changed and we need to delete the
                    // primary index to avoid errors in the database upgrade
                    // TO DO: The change of a Primary Key in a table should be generic
                    if ($tableName == 'LOGIN_LOG' && array_key_exists('LOG_ID', $actionData)) {
                        $database->executeQuery('DROP INDEX `PRIMARY` ON LOGIN_LOG;');
                    }
                    if ($tableName == 'APP_SEQUENCE' && array_key_exists('APP_TYPE', $actionData)) {
                        $database->executeQuery('DROP INDEX `PRIMARY` ON APP_SEQUENCE;');
                    }
                } else {
                    foreach ($actionData as $columnName => $meta) {
                        switch ($action) {
                            case 'DROP':
                                $database->executeQuery($database->generateDropColumnSQL($tableName, $meta));
                                break;
                            case 'CHANGE':
                                $database->executeQuery($database->generateChangeColumnSQL($tableName, $columnName, $meta));
                                break;
                        }
                    }
                }
            }
        }

        // Add columns
        if (!empty($tablesToAddColumns)) {
            $upgradeQueries = [];
            foreach ($tablesToAddColumns as $tableName => $tableColumn) {
                // Normal indexes to add
                $indexes = [];
                if (!empty($changes['tablesWithNewIndex'][$tableName]) && $includeIndexes) {
                    $indexes = $changes['tablesWithNewIndex'][$tableName];
                    unset($changes['tablesWithNewIndex'][$tableName]);
                }

                // "fulltext" indexes to add
                $fulltextIndexes = [];
                if (!empty($changes['tablesWithNewFulltext'][$tableName]) && $includeIndexes) {
                    $fulltextIndexes = $changes['tablesWithNewFulltext'][$tableName];
                    unset($changes['tablesWithNewFulltext'][$tableName]);
                }

                // Instantiate the class to execute the query in background
                $upgradeQueries[] = new RunProcessUpgradeQuery($this->name, $database->generateAddColumnsSql(
                    $tableName,
                    $tableColumn,
                    $indexes,
                    $fulltextIndexes
                ), $rbac);
            }

            // Run queries in multiple threads
            $processesManager = new ProcessesManager($upgradeQueries);
            $processesManager->run();

            // If exists an error throw an exception
            if (!empty($processesManager->getErrors())) {
                $errorMessage = '';
                foreach ($processesManager->getErrors() as $error) {
                    $errorMessage .= $error['rawAnswer'] . PHP_EOL;
                }
                throw new Exception($errorMessage);
            }
        }

        // Add indexes
        if ((!empty($changes['tablesWithNewIndex']) || !empty($changes['tablesWithNewFulltext'])) && $includeIndexes) {
            CLI::logging("-> " . (count($changes['tablesWithNewIndex']) + count($changes['tablesWithNewFulltext'])) .
                " tables with indexes to add\n");
            $upgradeQueries = [];

            // Add normal indexes
            foreach ($changes['tablesWithNewIndex'] as $tableName => $indexes) {
                // Instantiate the class to execute the query in background
                $upgradeQueries[] = new RunProcessUpgradeQuery($this->name, $database->generateAddColumnsSql($tableName, [], $indexes), $rbac);
            }

            // Add "fulltext" indexes
            foreach ($changes['tablesWithNewFulltext'] as $tableName => $fulltextIndexes) {
                // Instantiate the class to execute the query in background
                $upgradeQueries[] = new RunProcessUpgradeQuery($this->name, $database->generateAddColumnsSql($tableName, [], [], $fulltextIndexes), $rbac);
            }

            // Run queries in multiple threads
            $processesManager = new ProcessesManager($upgradeQueries);
            $processesManager->run();

            // If exists an error throw an exception
            if (!empty($processesManager->getErrors())) {
                $errorMessage = '';
                foreach ($processesManager->getErrors() as $error) {
                    $errorMessage .= $error['rawAnswer'] . PHP_EOL;
                }
                throw new Exception($errorMessage);
            }
        }

        // Change indexes
        if ((!empty($changes['tablesToAlterIndex']) || !empty($changes['tablesToAlterFulltext'])) && $includeIndexes) {
            CLI::logging("-> " . (count($changes['tablesToAlterIndex']) + count($changes['tablesToAlterFulltext'])) .
                " tables with indexes to alter\n");

            // Change normal indexes
            foreach ($changes['tablesToAlterIndex'] as $tableName => $indexes) {
                foreach ($indexes as $indexName => $indexFields) {
                    $database->executeQuery($database->generateDropKeySQL($tableName, $indexName));
                    $database->executeQuery($database->generateAddKeysSQL($tableName, $indexName, $indexFields));
                }
            }

            // Change "fulltext" indexes
            foreach ($changes['tablesToAlterFulltext'] as $tableName => $fulltextIndexes) {
                foreach ($fulltextIndexes as $indexName => $indexFields) {
                    $database->executeQuery($database->generateDropKeySQL($tableName, $indexName));
                    $database->executeQuery($database->generateAddKeysSQL($tableName, $indexName, $indexFields, 'FULLTEXT'));
                }
            }
        }

        // Ending the schema update
        CLI::logging("-> Schema Updated\n");
        return true;
    }

    public function upgradeData()
    {
        $this->getSchema();
        if (file_exists(PATH_CORE . 'data' . PATH_SEP . 'check.data')) {
            $checkData = unserialize(file_get_contents(PATH_CORE . 'data' . PATH_SEP . 'check.data'));
            if (is_array($checkData)) {
                foreach ($checkData as $checkThis) {
                    $this->updateThisRegistry($checkThis);
                }
            }
        }
    }
     
    /**
     * Upgrade some IC_NAME values in the table ISO_COUNTRY
     */
    public function updateIsoCountry()
    {
        CLI::logging("->    Update table ISO_COUNTRY\n");

        // Initializing
        $con = Propel::getConnection(IsoCountryPeer::DATABASE_NAME);

        // Update table ISO_COUNTRY
        $con->begin();
        $stmt = $con->createStatement();
        $stmt->executeQuery(""
            . "UPDATE `ISO_COUNTRY` "
            . "SET `IC_NAME` = 'Côte d\'Ivoire' "
            . "WHERE `IC_UID` = 'CI'");
        $con->commit();

        CLI::logging("-> Update table ISO_COUNTRY Done\n");
    }

    public function updateThisRegistry($data)
    {
        $dataBase = $this->getDatabase();
        $sql = '';
        switch ($data['action']) {
            case 1:
                $sql = $dataBase->generateInsertSQL($data['table'], $data['data']);
                $message = "-> Row added in {$data['table']}\n";
                break;
            case 2:
                $sql = $dataBase->generateUpdateSQL($data['table'], $data['keys'], $data['data']);
                $message = "-> Row updated in {$data['table']}\n";
                break;
            case 3:
                $sql = $dataBase->generateDeleteSQL($data['table'], $data['keys'], $data['data']);
                $message = "-> Row deleted in {$data['table']}\n";
                break;
            case 4:
                $sql = $dataBase->generateSelectSQL($data['table'], $data['keys'], $data['data']);
                $dataset = $dataBase->executeQuery($sql);
                if ($dataset) {
                    $sql = $dataBase->generateDeleteSQL($data['table'], $data['keys'], $data['data']);
                    $dataBase->executeQuery($sql);
                }
                $sql = $dataBase->generateInsertSQL($data['table'], $data['data']);
                $message = "-> Row updated in {$data['table']}\n";
                break;
        }
        if ($sql != '') {
            $dataBase->executeQuery($sql);
            CLI::logging($message);
        }
    }

    /**
     * Get metadata from this workspace
     *
     * @param string $path the directory where to create the sql files
     * @return array information about this workspace
     */
    public function getMetadata()
    {
        $Fields = array_merge(System::getSysInfo(), $this->getDBInfo());
        $Fields['WORKSPACE_NAME'] = $this->name;

        if (isset($this->dbHost)) {
            $dbNetView = new Net($this->dbHost);
            $dbNetView->loginDbServer($this->dbUser, $this->dbPass);
            try {
                if (!defined('DB_ADAPTER')) {
                    require_once($this->dbPath);
                }
                $sMySQLVersion = $dbNetView->getDbServerVersion('mysql');
            } catch (Exception $oException) {
                $sMySQLVersion = 'Unknown';
            }

            $Fields['DATABASE'] = $dbNetView->dbName($this->dbAdapter) . ' (Version ' . $sMySQLVersion . ')';
            $Fields['DATABASE_SERVER'] = $this->dbHost;
            $Fields['DATABASE_NAME'] = $this->dbName;
            $Fields['AVAILABLE_DB'] = "Not defined";
            //$Fields['AVAILABLE_DB'] = $availdb;
        } else {
            $Fields['DATABASE'] = "Not defined";
            $Fields['DATABASE_SERVER'] = "Not defined";
            $Fields['DATABASE_NAME'] = "Not defined";
            $Fields['AVAILABLE_DB'] = "Not defined";
        }

        return $Fields;
    }

    /**
     * Print the system information gathered from getSysInfo
     */
    public static function printSysInfo()
    {
        $fields = System::getSysInfo();

        $info = array(
            'ProcessMaker Version' => $fields['PM_VERSION'],
            'System' => $fields['SYSTEM'],
            'PHP Version' => $fields['PHP'],
            'Server Address' => $fields['SERVER_ADDR'],
            'Client IP Address' => $fields['IP'],
            'Plugins' => (count($fields['PLUGINS_LIST']) > 0) ? $fields['PLUGINS_LIST'][0] : 'None'
        );

        foreach ($fields['PLUGINS_LIST'] as $k => $v) {
            if ($k == 0) {
                continue;
            }
            $info[] = $v;
        }

        foreach ($info as $k => $v) {
            if (is_numeric($k)) {
                $k = "";
            }
            CLI::logging(sprintf("%20s %s\n", $k, pakeColor::colorize($v, 'INFO')));
        }
    }

    public function printInfo($fields = null)
    {
        if (!$fields) {
            $fields = $this->getMetadata();
        }

        $wfDsn = $fields['DB_ADAPTER'] . '://' . $fields['DB_USER'] . ':' . $fields['DB_PASS'] . '@' . $fields['DB_HOST'] . '/' . $fields['DB_NAME'];

        if ($fields['DB_NAME'] == $fields['DB_RBAC_NAME']) {
            $info = array('Workspace Name' => $fields['WORKSPACE_NAME'], 'Workflow Database' => sprintf("%s://%s:%s@%s/%s", $fields['DB_ADAPTER'], $fields['DB_USER'], $fields['DB_PASS'], $fields['DB_HOST'], $fields['DB_NAME']), 'MySql Version' => $fields['DATABASE']);
        } else {
            $info = array(
                'Workspace Name' => $fields['WORKSPACE_NAME'],
                //'Available Databases'  => $fields['AVAILABLE_DB'],
                'Workflow Database' => sprintf("%s://%s:%s@%s/%s", $fields['DB_ADAPTER'], $fields['DB_USER'], $fields['DB_PASS'], $fields['DB_HOST'], $fields['DB_NAME']), 'RBAC Database' => sprintf("%s://%s:%s@%s/%s", $fields['DB_ADAPTER'], $fields['DB_RBAC_USER'], $fields['DB_RBAC_PASS'], $fields['DB_RBAC_HOST'], $fields['DB_RBAC_NAME']), 'Report Database' => sprintf("%s://%s:%s@%s/%s", $fields['DB_ADAPTER'], $fields['DB_REPORT_USER'], $fields['DB_REPORT_PASS'], $fields['DB_REPORT_HOST'], $fields['DB_REPORT_NAME']), 'MySql Version' => $fields['DATABASE']
            );
        }

        foreach ($info as $k => $v) {
            if (is_numeric($k)) {
                $k = "";
            }
            CLI::logging(sprintf("%20s %s\n", $k, pakeColor::colorize($v, 'INFO')));
        }
    }

    /**
     * Print workspace information
     *
     * @param bool $printSysInfo include sys info as well or not
     */
    public function printMetadata($printSysInfo = true)
    {
        if ($printSysInfo) {
            WorkspaceTools::printSysInfo();
            CLI::logging("\n");
        }

        WorkspaceTools::printInfo($this->getMetadata());
    }

    /**
     * exports this workspace database to the specified path
     *
     * @param string $path the directory where to create the sql files
     * @param boolean $onedb
     *
     * @return array
     * @throws Exception
     */
    public function exportDatabase($path, $onedb = false)
    {
        $dbInfo = $this->getDBInfo();

        $databases = ['wf', 'rp', 'rb'];
        if ($onedb) {
            $databases = ['rb', 'rp'];
        } else if ($dbInfo['DB_NAME'] === $dbInfo['DB_RBAC_NAME']) {
            $databases = ['wf'];
        }

        $dbNames = [];
        foreach ($databases as $db) {
            $dbInfo = $this->getDBCredentials($db);
            $oDbMaintainer = new DataBaseMaintenance($dbInfo['host'], $dbInfo['user'], $dbInfo['pass']);
            CLI::logging("Saving database {$dbInfo['name']}\n");
            $oDbMaintainer->connect($dbInfo['name']);
            $oDbMaintainer->setTempDir($path . '/');
            $oDbMaintainer->backupDataBase($oDbMaintainer->getTempDir() . $dbInfo['name'] . '.sql');
            $dbNames[] = $dbInfo;
        }
        return $dbNames;
    }

    /**
     * adds files to the backup archive
     */
    private function addToBackup($backup, $filename, $pathRoot, $archiveRoot = "")
    {
        if (is_file($filename)) {
            CLI::logging("-> $filename\n");
            $backup->addModify($filename, $archiveRoot, $pathRoot);
        } else {
            CLI::logging(" + $filename\n");
            $backup->addModify($filename, $archiveRoot, $pathRoot);
            //foreach (glob($filename . "/*") as $item) {
            //  $this->addToBackup($backup, $item, $pathRoot, $archiveRoot);
            //}
        }
    }

    /**
     * Creates a backup archive, which can be used instead of a filename to backup
     *
     * @param string $filename the backup filename
     * @param bool $compress wheter to compress or not
     */
    public static function createBackup($filename, $compress = true)
    {
        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename));
        }
        if (file_exists($filename)) {
            unlink($filename);
        }
        $backup = new Archive_Tar($filename);
        return $backup;
    }

    /**
     * create a backup of this workspace
     *
     * Exports the database and copies the files to an archive specified, so this
     * workspace can later be restored.
     *
     * @param string|archive $filename archive filename to use as backup or
     * archive object created by createBackup
     * @param bool $compress specifies wheter the backup is compressed or not
     */
    public function backup($backupFile, $compress = true)
    {
        /* $filename can be a string, in which case it's used as the filename of
         * the backup, or it can be a previously created tar, which allows for
         * multiple workspaces in one backup.
         */
        if (!$this->workspaceExists()) {
            throw new Exception("Workspace '{$this->name}' not found");
        }
        if (is_string($backupFile)) {
            $backup = $this->createBackup($backupFile);
            $filename = $backupFile;
        } else {
            $backup = $backupFile;
            $filename = $backup->_tarname;
        }
        if (!file_exists(PATH_DATA . "upgrade/")) {
            mkdir(PATH_DATA . "upgrade/");
        }
        $tempDirectory = PATH_DATA . "upgrade/" . basename(uniqid(__FILE__, ''));
        mkdir($tempDirectory);
        $metadata = $this->getMetadata();
        CLI::logging("Backing up database...\n");
        $metadata["databases"] = $this->exportDatabase($tempDirectory);
        $metadata["directories"] = array("{$this->name}.files");
        $metadata["version"] = 1;
        $metadata['backupEngineVersion'] = 2;
        $metaFilename = "$tempDirectory/{$this->name}.meta";
        /* Write metadata to file, but make it prettier before. The metadata is just
         * a JSON codified array.
         */
        if (!file_put_contents($metaFilename, str_replace(array(",", "{", "}"), array(",\n  ", "{\n  ", "\n}\n"), G::json_encode($metadata)))) {
            throw new Exception("Could not create backup metadata");
        }
        CLI::logging("Copying database to backup...\n");
        $this->addToBackup($backup, $tempDirectory, $tempDirectory);
        CLI::logging("Copying files to backup...\n");

        $this->addToBackup($backup, $this->path, $this->path, "{$this->name}.files");
        //Remove leftovers.
        G::rm_dir($tempDirectory);
    }

    //TODO: Move to class.dbMaintenance.php

    /**
     * create a user in the database
     *
     * Create a user specified by the parameters and grant all priviledges for
     * the database specified, when the user connects from the hostname.
     * Drops the user if it already exists.
     * This function only supports MySQL.
     *
     * @param string $username username
     * @param string $password password
     * @param string $hostname the hostname the user will be connecting from
     * @param string $database the database to grant permissions
     * @param string $connection name
     *
     * @throws Exception
     */
    public function createDBUser($username, $password, $hostname, $database, $connection)
    {
        try {
            $message = 'Unable to retrieve users: ';
            $hosts = explode(':', $hostname);
            $hostname = array_shift($hosts);

            $result = DB::connection($connection)->select(DB::raw("SELECT * FROM mysql.user WHERE user = '$username' AND host = '$hostname'"));

            if (count($result) === 0) {
                $message = "Unable to create user $username: ";
                CLI::logging("Creating user $username for $hostname\n");

                DB::connection($connection)->statement("CREATE USER '$username'@'$hostname' IDENTIFIED BY '$password'");
            }
            $message = "Unable to grant priviledges to user $username: ";
            DB::connection($connection)->statement("GRANT ALL ON $database.* TO '$username'@'$hostname'");
        } catch (QueryException $exception) {
            throw new Exception($message . $exception->getMessage());
        }
    }

    //TODO: Move to class.dbMaintenance.php
    /**
     * executes a mysql script
     *
     * This function supports scripts with -- comments in the beginning of a line
     * and multi-line statements.
     * It does not support other forms of comments (such as /*... or {{...}}).
     *
     * @param string $filename the script filename
     * @param string $database the database to execute this script into
     */
    /**
     * executes a mysql script
     *
     * This function supports scripts with -- comments in the beginning of a line
     * and multi-line statements.
     * It does not support other forms of comments (such as /*... or {{...}}).
     *
     * @param string $filename the script filename
     * @param string $database the database to execute this script into
     * @param $parameters
     * @param int $versionBackupEngine
     * @param string $connection
     */
    public function executeSQLScript($database, $filename, $parameters, $versionBackupEngine = 1, $connection = '')
    {
        DB::connection($connection)
            ->statement('CREATE DATABASE IF NOT EXISTS ' . $database);

        //check function shell_exec
        $disabled_functions = ini_get('disable_functions');
        $flag = false;
        if (!empty($disabled_functions)) {
            $arr = explode(',', $disabled_functions);
            sort($arr);
            if (in_array('shell_exec', $arr)) {
                $flag = true;
            }
        }

        // Check if mysql exist on server
        $flagFunction = null;
        if (!$flag) {
            $flagFunction = shell_exec('mysql --version');
        }

        $arrayRegExpEngineSearch = ["/\)\s*TYPE\s*=\s*(InnoDB)/i", "/\)\s*TYPE\s*=\s*(MyISAM)/i", "/SET\s*FOREIGN_KEY_CHECKS\s*=\s*0\s*;/"];
        $arrayRegExpEngineReplace = [") ENGINE=\\1 DEFAULT CHARSET=utf8", ") ENGINE=\\1", "SET FOREIGN_KEY_CHECKS=0;\nSET unique_checks=0;\nSET AUTOCOMMIT=0;"];

        //replace DEFINER
        $script = preg_replace('/DEFINER=[^*]*/', '', file_get_contents($filename));
        file_put_contents($filename, $script);

        if (!$flag && !is_null($flagFunction)) {
            //Replace TYPE by ENGINE
            if ($versionBackupEngine == 1) {
                $script = preg_replace($arrayRegExpEngineSearch, $arrayRegExpEngineReplace, file_get_contents($filename));
                file_put_contents($filename, $script . "\nCOMMIT;");
            } else {
                $arrayRegExpEngineSearch = ["/\)\s*TYPE\s*=\s*(InnoDB)/i", "/\)\s*TYPE\s*=\s*(MyISAM)/i"];
                $arrayRegExpEngineReplace = [") ENGINE=\\1 DEFAULT CHARSET=utf8", ") ENGINE=\\1"];
                $script = preg_replace($arrayRegExpEngineSearch, $arrayRegExpEngineReplace, file_get_contents($filename));
                file_put_contents($filename, $script);
            }

            $aHost = explode(':', $parameters['dbHost']);
            $dbHost = $aHost[0];
            if (isset($aHost[1])) {
                $dbPort = $aHost[1];
                $command = 'mysql'
                    . ' --host=' . $dbHost
                    . ' --port=' . $dbPort
                    . ' --user=' . $parameters['dbUser']
                    . ' --password=' . escapeshellarg($parameters['dbPass'])
                    . ' --database=' . $database
                    . ' --default_character_set utf8'
                    . ' --execute="SOURCE ' . $filename . '"';
            } else {
                $command = 'mysql'
                    . ' --host=' . $dbHost
                    . ' --user=' . $parameters['dbUser']
                    . ' --password=' . escapeshellarg($parameters['dbPass'])
                    . ' --database=' . $database
                    . ' --default_character_set utf8'
                    . ' --execute="SOURCE ' . $filename . '"';
            }
            shell_exec($command);
        } else {
            //If the safe mode of the server is actived
            try {
                $connection = 'RESTORE_' . $database;
                InstallerModule::setNewConnection($connection, $parameters['dbHost'], $parameters['dbUser'], $parameters['dbPass'], $database, '');

                //Replace TYPE by ENGINE
                $script = preg_replace($arrayRegExpEngineSearch, $arrayRegExpEngineReplace, file_get_contents($filename));
                if ($versionBackupEngine == 1) {
                    $script = $script . "\nCOMMIT;";
                }

                $lines = explode("\n", $script);
                $previous = null;
                $insert = false;
                foreach ($lines as $j => $line) {
                    // Remove comments from the script
                    $line = trim($line);
                    if (strpos($line, '--') === 0) {
                        $line = substr($line, 0, strpos($line, '--'));
                    }
                    if (empty($line)) {
                        continue;
                    }
                    // Concatenate the previous line, if any, with the current
                    if ($previous) {
                        $line = $previous . ' ' . $line;
                    }
                    $previous = null;

                    // If the current line doesnt end with ; then put this line together
                    // with the next one, thus supporting multi-line statements.
                    if (strrpos($line, ';') !== strlen($line) - 1) {
                        $previous = $line;
                        continue;
                    }
                    $line = substr($line, 0, strrpos($line, ';'));

                    if (strrpos($line, 'INSERT INTO') !== false) {
                        $insert = true;
                        if ($insert) {
                            DB::connection($connection)->beginTransaction();
                            $insert = false;
                        }
                        $result = DB::connection($connection)->statement($line);
                        continue;
                    } else {
                        if (!$insert) {
                            DB::connection($connection)->commitTransaction();
                            $insert = true;
                        }
                    }

                    $result = DB::connection($connection)->statement($line);
                    if ($result === false) {
                        DB::connection($connection)->rollbackTransaction();
                        throw new Exception("Error when running script '$filename', line $j, query '$line' ");
                    }
                }
                if (!$insert) {
                    DB::connection($connection)->commitTransaction();
                }
            } catch (Exception $e) {
                CLI::logging(CLI::error("Error:" . "There are problems running script '$filename': " . $e));
            } catch (QueryException $exception) {
                DB::connection($connection)->rollbackTransaction();
                throw new Exception("Error when running script '$filename', line $j, query '$line': " . $exception->getMessage());
            }
        }
    }

    public function executeScript($database, $filename, $parameters, $connection = null)
    {
        $this->executeSQLScript($database, $filename, $parameters, 1, $connection);
        return true;
    }

    public static function restoreLegacy($directory)
    {
        throw new Exception("Use gulliver to restore backups from old versions");
    }

    public static function getBackupInfo($filename)
    {
        $backup = new Archive_Tar($filename);
        //Get a temporary directory in the upgrade directory
        $tempDirectory = PATH_DATA . "upgrade/" . basename(uniqid(__FILE__, ''));
        mkdir($tempDirectory);
        $metafiles = [];
        foreach ($backup->listContent() as $backupFile) {
            $filename = $backupFile["filename"];
            if (strpos($filename, "/") === false && substr_compare($filename, ".meta", -5, 5, true) === 0) {
                if (!$backup->extractList(array($filename), $tempDirectory)) {
                    throw new Exception("Could not extract backup");
                }
                $metafiles[] = "$tempDirectory/$filename";
            }
        }

        CLI::logging("Found " . count($metafiles) . " workspace(s) in backup\n");

        foreach ($metafiles as $metafile) {
            $data = file_get_contents($metafile);
            $workspaceData = G::json_decode($data);
            CLI::logging("\n");
            WorkspaceTools::printInfo((array)$workspaceData);
        }

        G::rm_dir($tempDirectory);
    }

    public static function dirPerms($filename, $owner, $group, $perms)
    {
        $chown = @chown($filename, $owner);
        $chgrp = @chgrp($filename, $group);
        $chmod = @chmod($filename, $perms);

        if ($chgrp === false || $chmod === false || $chown === false) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec("icacls \"" . $filename . "\" /grant Administrador:(D,WDAC) /T", $res);
            } else {
                CLI::logging(CLI::error("Failed to set permissions for $filename") . "\n");
            }
        }
        if (is_dir($filename)) {
            foreach (array_merge(glob($filename . "/*"), glob($filename . "/.*")) as $item) {
                if (basename($item) == "." || basename($item) == "..") {
                    continue;
                }
                WorkspaceTools::dirPerms($item, $owner, $group, $perms);
            }
        }
    }

    /**
     * Restore a workspace
     *
     * Restores any database and files included in the backup, either as a new
     * workspace, or overwriting a previous one
     *
     * @param string $filename the backup filename
     * @param string $srcWorkspace name of the source workspace
     * @param string $dstWorkspace name of the destination workspace
     * @param boolean $overwrite if you need overwrite the database
     * @param string $lang for define the language
     * @param string $port of database if is empty take 3306
     * @param array $optionMigrateHistoryData
     *
     * @throws Exception
     *
     * @see workflow/engine/bin/tasks/cliWorkspaces.php::run_workspace_restore()
     *
     * @link https://wiki.processmaker.com/3.0/Backing_up_and_Restoring_ProcessMaker#RestoringWorkspaces
     */
    public static function restore($filename, $srcWorkspace, $dstWorkspace = null, $overwrite = true, $lang = 'en', $port = '', $optionMigrateHistoryData = [])
    {
        $backup = new Archive_Tar($filename);
        //Get a temporary directory in the upgrade directory
        $tempDirectory = PATH_DATA . "upgrade/" . basename(uniqid(__FILE__, ''));
        $parentDirectory = PATH_DATA . "upgrade";
        if (is_writable($parentDirectory)) {
            mkdir($tempDirectory);
        } else {
            throw new Exception("Could not create directory:" . $parentDirectory);
        }
        //Extract all backup files, including database scripts and workspace files
        if (!$backup->extract($tempDirectory)) {
            throw new Exception("Could not extract backup");
        }
        //Search for metafiles in the new standard (the old standard would contain
        //txt files).
        $metaFiles = glob($tempDirectory . "/*.meta");
        if (empty($metaFiles)) {
            $metaFiles = glob($tempDirectory . "/*.txt");
            if (!empty($metaFiles)) {
                return WorkspaceTools::restoreLegacy($tempDirectory);
            } else {
                throw new Exception("No metadata found in backup");
            }
        } else {
            CLI::logging("Found " . count($metaFiles) . " workspaces in backup:\n");
            foreach ($metaFiles as $metafile) {
                CLI::logging("-> " . basename($metafile) . "\n");
            }
        }
        if (count($metaFiles) > 1 && (!isset($srcWorkspace))) {
            throw new Exception("Multiple workspaces in backup but no workspace specified to restore");
        }
        if (isset($srcWorkspace) && !in_array("$srcWorkspace.meta", array_map('basename', $metaFiles))) {
            throw new Exception("Workspace $srcWorkspace not found in backup");
        }

        $version = System::getVersion();
        $pmVersion = (preg_match("/^([\d\.]+).*$/", $version, $arrayMatch)) ? $arrayMatch[1] : ""; //Otherwise: Branch master

        CLI::logging(CLI::warning("
            Warning: A workspace from a newer version of ProcessMaker can NOT be restored in an older version of
            ProcessMaker. For example, restoring from v.3.0 to v.2.5 will not work. However, it may be possible
            to restore a workspace from an older version to an newer version of ProcessMaker, although error
            messages may be displayed during the restore process.") . "\n");

        foreach ($metaFiles as $metaFile) {
            $metadata = preg_replace('/\r|\n/', '', file_get_contents($metaFile));
            $metadata = G::json_decode(preg_replace('/\s+/', '', $metadata));
            if ($metadata->version != 1) {
                throw new Exception("Backup version {$metadata->version} not supported");
            }
            $backupWorkspace = $metadata->WORKSPACE_NAME;

            if (strpos($metadata->DB_RBAC_NAME, 'rb_') === false) {
                $onedb = true;
                $oldDatabases = 1;
            } else {
                $onedb = false;
                $oldDatabases = 3;
            }

            if (isset($dstWorkspace)) {
                $workspaceName = $dstWorkspace;
                $createWorkspace = true;
            } else {
                $workspaceName = $metadata->WORKSPACE_NAME;
                $createWorkspace = false;
            }
            if (isset($srcWorkspace) && strcmp($metadata->WORKSPACE_NAME, $srcWorkspace) != 0) {
                CLI::logging(CLI::warning("> Workspace $backupWorkspace found, but not restoring.") . "\n");
                continue;
            } else {
                CLI::logging("> Restoring " . CLI::info($backupWorkspace) . " to " . CLI::info($workspaceName) . "\n");
            }
            $workspace = new WorkspaceTools($workspaceName);

            if (Installer::isset_site($workspaceName)) {
                if ($overwrite) {
                    if (!$workspace->workspaceExists()) {
                        throw new Exception('We can not overwrite this workspace because the workspace ' . $workspaceName . ' does not exist please check the lower case and upper case.');
                    }
                    CLI::logging(CLI::warning("> Workspace $workspaceName already exist, overwriting!") . "\n");
                } else {
                    throw new Exception("Destination workspace already exist (use -o to overwrite)");
                }
            }
            if (file_exists($workspace->path)) {
                G::rm_dir($workspace->path);
            }
            foreach ($metadata->directories as $dir) {
                CLI::logging("+> Restoring directory '$dir'\n");

                if (file_exists("$tempDirectory/$dir" . "/ee")) {
                    G::rm_dir("$tempDirectory/$dir" . "/ee");
                }
                if (file_exists("$tempDirectory/$dir" . "/plugin.singleton")) {
                    G::rm_dir("$tempDirectory/$dir" . "/plugin.singleton");
                }
                if (!rename("$tempDirectory/$dir", $workspace->path)) {
                    throw new Exception("There was an error copying the backup files ($tempDirectory/$dir) to the workspace directory {$workspace->path}.");
                }
            }

            CLI::logging("> Changing file permissions\n");
            $shared_stat = stat(PATH_DATA);

            if ($shared_stat !== false) {
                WorkspaceTools::dirPerms($workspace->path, $shared_stat['uid'], $shared_stat['gid'], $shared_stat['mode'] & 0777);
            } else {
                CLI::logging(CLI::error("Could not get the shared folder permissions, not changing workspace permissions") . "\n");
            }
            list($dbHost, $dbUser, $dbPass) = @explode(SYSTEM_HASH, G::decrypt(HASH_INSTALLATION, SYSTEM_HASH));
            if ($port != '') {
                $dbHost = $dbHost . $port; //127.0.0.1:3306
            }
            $aParameters = ['dbHost' => $dbHost, 'dbUser' => $dbUser, 'dbPass' => $dbPass];

            //Restore
            if (empty(config('system.workspace'))) {
                define('SYS_SYS', $workspaceName);
                config(['system.workspace' => $workspaceName]);
            }

            if (!defined('PATH_DATA_SITE')) {
                define('PATH_DATA_SITE', PATH_DATA . 'sites' . PATH_SEP . config('system.workspace') . PATH_SEP);
            }

            $pmVersionWorkspaceToRestore = preg_match("/^([\d\.]+).*$/", $metadata->PM_VERSION, $arrayMatch) ? $arrayMatch[1] : '';

            CLI::logging("> Connecting to system database in '$dbHost'\n");

            try {
                $connection = 'RESTORE';
                InstallerModule::setNewConnection('RESTORE', $dbHost, $dbUser, $dbPass, '', '');
                DB::connection($connection)
                    ->statement("SET NAMES 'utf8'");
                DB::connection($connection)
                    ->statement('SET FOREIGN_KEY_CHECKS=0');
            } catch (Exception $exception) {
                throw new Exception('Could not connect to system database: ' . $exception->getMessage());
            }

            $dbName = '';
            $newDBNames = $workspace->resetDBInfo($dbHost, $createWorkspace, $onedb);

            foreach ($metadata->databases as $db) {
                if ($dbName != $newDBNames[$db->name]) {
                    $dbName = $dbUser = $newDBNames[$db->name];
                    if (isset($newDBNames['DB_USER'])) {
                        $dbUser = $newDBNames['DB_USER'];
                    }
                    $result = DB::connection($connection)->select("show databases like '$dbName'");
                    if (count($result) > 0 && !$overwrite) {
                        throw new Exception("Destination Database already exist (use -o to overwrite)");
                    }

                    CLI::logging("+> Restoring database {$db->name} to $dbName\n");
                    $versionBackupEngine = (isset($metadata->backupEngineVersion)) ? $metadata->backupEngineVersion : 1;
                    $workspace->executeSQLScript($dbName, "$tempDirectory/{$db->name}.sql", $aParameters, $versionBackupEngine, $connection);
                    // Define the password
                    if (empty($workspace->dbGrantUserPassword)) {
                        $bdPassword = $db->pass;
                    } else {
                        $bdPassword = $workspace->dbGrantUserPassword;
                    }
                    $workspace->createDBUser($dbUser, $bdPassword, "localhost", $dbName, $connection);
                    $workspace->createDBUser($dbUser, $bdPassword, "%", $dbName, $connection);
                }
            }

            if (empty($pmVersion) && strpos(strtoupper($version), 'BRANCH')) {
                $pmVersion = 'dev-version-backup';
            }

            if (!empty($pmVersionWorkspaceToRestore) && (version_compare(
                $pmVersionWorkspaceToRestore . "",
                $pmVersion . "",
                "<"
            ) || empty($pmVersion)) || $pmVersion == "dev-version-backup") {
                // Upgrade the database schema and data
                CLI::logging("* Start updating database schema...\n");
                $start = microtime(true);
                $workspace->upgradeDatabase(false);
                CLI::logging("* End updating database schema...(Completed on " . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start checking MAFE requirements...\n");
                $start = microtime(true);
                $workspace->checkMafeRequirements($workspaceName, $lang);
                CLI::logging("* End checking MAFE requirements...(Completed on " . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start deleting MySQL triggers: " . implode(', ', self::$triggers) . "...\n");
                $start = microtime(true);
                $workspace->deleteTriggersMySQL(self::$triggers);
                CLI::logging("* End deleting MySQL triggers: " . implode(', ', self::$triggers) . "... (Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start deleting indexes from big tables: " . implode(', ', self::$bigTables) . "...\n");
                $start = microtime(true);
                $workspace->deleteIndexes(self::$bigTables);
                CLI::logging("* End deleting indexes from big tables: " . implode(', ', self::$bigTables) . "... (Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start to migrate texts/values from 'CONTENT' table to the corresponding object tables...\n");
                $start = microtime(true);
                $workspace->migrateContent($lang);
                CLI::logging("* End to migrate texts/values from 'CONTENT' table to the corresponding object tables... (Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start updating rows in Web Entry table for classic processes...\n");
                $start = microtime(true);
                $workspace->updatingWebEntryClassicModel(true);
                CLI::logging("* End updating rows in Web Entry table for classic processes...(Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start to update Files Manager...\n");
                $start = microtime(true);
                $workspace->processFilesUpgrade($workspaceName);
                CLI::logging("* End to update Files Manager... (Completed on " . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start migrating and populating plugin singleton data...\n");
                $start = microtime(true);
                $workspace->migrateSingleton($workspaceName);
                CLI::logging("* End migrating and populating plugin singleton data...(Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start to check Intermediate Email Event...\n");
                $start = microtime(true);
                $workspace->checkIntermediateEmailEvent();
                CLI::logging("* End to check Intermediate Email Event... (Completed on " . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start cleaning DYN_CONTENT in APP_HISTORY...\n");
                $start = microtime(true);
                $keepDynContent = isset($optionMigrateHistoryData['keepDynContent']) && $optionMigrateHistoryData['keepDynContent'] === true;
                $workspace->clearDynContentHistoryData(false, $keepDynContent);
                CLI::logging("* End cleaning DYN_CONTENT in APP_HISTORY...(Completed on " . (microtime(true) - $start) . " seconds)\n");


                CLI::logging("* Start migrating and populating indexing for avoiding the use of table APP_CACHE_VIEW...\n");
                $start = microtime(true);
                $workspace->migratePopulateIndexingACV();
                CLI::logging("* End migrating and populating indexing for avoiding the use of table APP_CACHE_VIEW...(Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start optimizing Self-Service data in table APP_ASSIGN_SELF_SERVICE_VALUE_GROUP....\n");
                $start = microtime(true);
                $workspace->migrateSelfServiceRecordsRun();
                CLI::logging("* End optimizing Self-Service data in table APP_ASSIGN_SELF_SERVICE_VALUE_GROUP....(Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start adding new fields and populating values in tables related to feature self service by value...\n");
                $start = microtime(true);
                $workspace->upgradeSelfServiceData();
                CLI::logging("* End adding new fields and populating values in tables related to feature self service by value...(Completed on " .
                    (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start adding/replenishing all indexes...\n");
                $start = microtime(true);
                $systemSchema = System::getSystemSchema($workspace->dbAdapter);
                $workspace->upgradeSchema($systemSchema);
                CLI::logging("* End adding/replenishing all indexes...(Completed on " . (microtime(true) - $start) . " seconds)\n");

                // The list tables was deprecated, the migration is not required

                CLI::logging("* Start updating MySQL triggers...\n");
                $start = microtime(true);
                $workspace->updateTriggers(true, $lang);
                CLI::logging("* End updating MySQL triggers...(" . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start adding +async option to scheduler commands...\n");
                $start = microtime(true);
                $workspace->addAsyncOptionToSchedulerCommands(false);
                CLI::logging("* End adding +async option to scheduler commands...(Completed on " . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start Converting Web Entries v1.0 to v2.0 for BPMN processes...\n");
                $start = microtime(true);
                $workspace->initPropel(true);
                $statement = Propel::getConnection('workflow')->createStatement();
                $statement->executeQuery(WebEntry::UPDATE_QUERY_V1_TO_V2);
                CLI::logging("* End converting Web Entries v1.0 to v2.0 for BPMN processes...(" . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start migrating case title...\n");
                $start = microtime(true);
                $workspace->migrateCaseTitleToThreads([$workspaceName]);
                CLI::logging("* End migrating case title...(Completed on " . (microtime(true) - $start) . " seconds)\n");

                CLI::logging("* Start converting Output Documents from 'HTML2PDF' to 'TCPDF'...\n");
                $start = microtime(true);
                $workspace->convertOutDocsHtml2Ps2Pdf([$workspaceName]);
                CLI::logging("* End converting Output Documents from 'HTML2PDF' to 'TCPDF...(Completed on " . (microtime(true) - $start) . " seconds)\n");
            }

            CLI::logging("> Start To Verify License Enterprise...\n");
            $start = microtime(true);
            $workspace->verifyLicenseEnterprise($workspaceName);
            CLI::logging("* End To Verify License Enterprise...(" . (microtime(true) - $start) . " seconds)\n");

            // Updating generated class files for PM Tables
            passthru(PHP_BINARY . ' processmaker regenerate-pmtable-classes ' . $workspaceName);
        }

        CLI::logging("Removing temporary files\n");

        G::rm_dir($tempDirectory);

        CLI::logging(CLI::info("Done restoring") . "\n");
    }

    public static function hotfixInstall($file)
    {
        $result = [];

        $dirHotfix = PATH_DATA . "hotfixes";

        $arrayPathInfo = pathinfo($file);

        $f = ($arrayPathInfo["dirname"] == ".") ? $dirHotfix . PATH_SEP . $file : $file;

        $swv = 1;
        $msgv = "";

        if (!file_exists($dirHotfix)) {
            G::mk_dir($dirHotfix, 0777);
        }

        if (!file_exists($f)) {
            $swv = 0;
            $msgv = $msgv . (($msgv != "") ? "\n" : null) . "- The file \"$f\" does not exist";
        }

        if ($arrayPathInfo["extension"] != "tar") {
            $swv = 0;
            $msgv = $msgv . (($msgv != "") ? "\n" : null) . "- The file extension \"$file\" is not \"tar\"";
        }

        if ($swv == 1) {
            //Extract
            $tar = new Archive_Tar($f);

            $swTar = $tar->extractModify(PATH_TRUNK, "processmaker"); //true on success, false on error

            if ($swTar) {
                $result["status"] = 1;
                $result["message"] = "- Hotfix installed successfully \"$f\"";
            } else {
                $result["status"] = 0;
                $result["message"] = "- Could not extract file \"$f\"";
            }
        } else {
            $result["status"] = 0;
            $result["message"] = $msgv;
        }

        return $result;
    }

    /**
     * Backup the log files
     */
    public function backupLogFiles()
    {
        $config = System::getSystemConfiguration();

        clearstatcache();
        $path = PATH_DATA . "log" . PATH_SEP;
        $filePath = $path . "cron.log";
        if (file_exists($filePath)) {
            $size = filesize($filePath);
            /* $config['size_log_file'] has the value 5000000 -> approximately 5 megabytes */
            if ($size > $config['size_log_file']) {
                rename($filePath, $filePath . ".bak");
            }
        }
    }

    /**
     * Check if the workspace have the clients used by MAFE registered
     *
     * @param string  $workspace
     * @param string $lang
     */
    public function checkMafeRequirements($workspace, $lang)
    {
        $this->initPropel(true);
        $pmRestClient = OauthClientsPeer::retrieveByPK('x-pm-local-client');
        $pmMobileRestClient = OauthClientsPeer::retrieveByPK(config('oauthClients.mobile.clientId'));
        if (empty($pmRestClient) || empty($pmMobileRestClient)) {
            if (!is_file(PATH_DATA . 'sites/' . $workspace . '/' . '.server_info')) {
                $_CSERVER = $_SERVER;
                unset($_CSERVER['REQUEST_TIME']);
                unset($_CSERVER['REMOTE_PORT']);
                $cput = serialize($_CSERVER);
                file_put_contents(PATH_DATA . 'sites/' . $workspace . '/' . '.server_info', $cput);
            }
            if (is_file(PATH_DATA . 'sites/' . $workspace . '/' . '.server_info')) {
                $SERVER_INFO = file_get_contents(PATH_DATA . 'sites/' . $workspace . '/' . '.server_info');
                $SERVER_INFO = unserialize($SERVER_INFO);

                $envFile = PATH_CONFIG . 'env.ini';
                $skin = 'neoclassic';
                if (file_exists($envFile)) {
                    $sysConf = System::getSystemConfiguration($envFile);
                    $lang = $sysConf['default_lang'];
                    $skin = $sysConf['default_skin'];
                }

                $endpoint = sprintf(
                    '%s/sys%s/%s/%s/oauth2/grant',
                    isset($SERVER_INFO['HTTP_ORIGIN']) ? $SERVER_INFO['HTTP_ORIGIN'] : '',
                    $workspace,
                    $lang,
                    $skin
                );

                if (empty($pmRestClient)) {
                    $oauthClients = new OauthClients();
                    $oauthClients->setClientId('x-pm-local-client');
                    $oauthClients->setClientSecret('179ad45c6ce2cb97cf1029e212046e81');
                    $oauthClients->setClientName('PM Web Designer');
                    $oauthClients->setClientDescription('ProcessMaker Web Designer App');
                    $oauthClients->setClientWebsite('www.processmaker.com');
                    $oauthClients->setRedirectUri($endpoint);
                    $oauthClients->setUsrUid('00000000000000000000000000000001');
                    $oauthClients->save();
                }

                if (empty($pmMobileRestClient) && !empty(config('oauthClients.mobile.clientId'))) {
                    $oauthClients = new OauthClients();
                    $oauthClients->setClientId(config('oauthClients.mobile.clientId'));
                    $oauthClients->setClientSecret(config('oauthClients.mobile.clientSecret'));
                    $oauthClients->setClientName(config('oauthClients.mobile.clientName'));
                    $oauthClients->setClientDescription(config('oauthClients.mobile.clientDescription'));
                    $oauthClients->setClientWebsite(config('oauthClients.mobile.clientWebsite'));
                    $oauthClients->setRedirectUri($endpoint);
                    $oauthClients->setUsrUid('00000000000000000000000000000001');
                    $oauthClients->save();
                }
            } else {
                eprintln("WARNING! No server info found!", 'red');
            }
        }
    }

    public function changeHashPassword($workspace, $response)
    {
        $this->initPropel(true);
        $licensedFeatures = PMLicensedFeatures::getSingleton();
        return true;
    }

    public function verifyFilesOldEnterprise()
    {
        $pathBackup = PATH_DATA . 'backups';
        if (!file_exists($pathBackup)) {
            G::mk_dir($pathBackup, 0777);
        }
        $pathNewFile = PATH_DATA . 'backups' . PATH_SEP . 'enterpriseBackup';
        $pathDirectoryEnterprise = PATH_CORE . 'plugins' . PATH_SEP . 'enterprise';
        $pathFileEnterprise = PATH_CORE . 'plugins' . PATH_SEP . 'enterprise.php';

        if (!file_exists($pathDirectoryEnterprise) && !file_exists($pathFileEnterprise)) {
            CLI::logging("    Without changes... \n");
            return true;
        }
        CLI::logging("    Migrating Enterprise Core version...\n");
        if (!file_exists($pathNewFile)) {
            CLI::logging("    Creating folder in $pathNewFile\n");
            G::mk_dir($pathNewFile, 0777);
        }
        $shared_stat = stat(PATH_DATA);
        if (file_exists($pathDirectoryEnterprise)) {
            CLI::logging("    Copying Enterprise Directory to $pathNewFile...\n");

            if ($shared_stat !== false) {
                WorkspaceTools::dirPerms($pathDirectoryEnterprise, $shared_stat['uid'], $shared_stat['gid'], $shared_stat['mode'] & 0777);
            } else {
                CLI::logging(CLI::error("Could not get shared folder permissions, workspace permissions couldn't be changed") . "\n");
            }
            if (G::recursive_copy($pathDirectoryEnterprise, $pathNewFile . PATH_SEP . 'enterprise')) {
                CLI::logging("    Removing $pathDirectoryEnterprise...\n");
                G::rm_dir($pathDirectoryEnterprise);
            } else {
                CLI::logging(CLI::error("    Error: Failure to copy from $pathDirectoryEnterprise...\n"));
            }
            if (file_exists($pathDirectoryEnterprise)) {
                CLI::logging(CLI::info("    Remove manually $pathDirectoryEnterprise...\n"));
            }
        }
        if (file_exists($pathFileEnterprise)) {
            CLI::logging("    Copying Enterprise.php file to $pathNewFile...\n");
            if ($shared_stat !== false) {
                WorkspaceTools::dirPerms($pathFileEnterprise, $shared_stat['uid'], $shared_stat['gid'], $shared_stat['mode'] & 0777);
            } else {
                CLI::logging(CLI::error("Could not get shared folder permissions, workspace permissions couldn't be changed") . "\n");
            }
            CLI::logging("    Removing $pathFileEnterprise...\n");
            copy($pathFileEnterprise, $pathNewFile . PATH_SEP . 'enterprise.php');
            G::rm_dir($pathFileEnterprise);
            if (file_exists($pathFileEnterprise)) {
                CLI::logging(CLI::info("    Remove manually $pathFileEnterprise...\n"));
            }
        }
    }

    /**
     * @param $workspace
     */
    public function verifyLicenseEnterprise($workspace)
    {
        $this->initPropel(true);
        $oCriteria = new Criteria('workflow');
        $oCriteria->add(LicenseManagerPeer::LICENSE_STATUS, 'ACTIVE');
        $oDataset = LicenseManagerPeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        if ($oDataset->next()) {
            $row = $oDataset->getRow();
            $tr = LicenseManagerPeer::retrieveByPK($row['LICENSE_UID']);
            $tr->setLicensePath(PATH_DATA_SITE . basename($row['LICENSE_PATH']));
            $tr->setLicenseWorkspace($workspace);
            $res = $tr->save();
        }
    }

    /**
     * Generate data for table APP_ASSIGN_SELF_SERVICE_VALUE
     *
     * @return void
     * @throws Exception
     *
     * @deprecated Method deprecated in Release 3.3.0
     */
    public function appAssignSelfServiceValueTableGenerateData()
    {
        try {
            $this->initPropel(true);

            $appAssignSelfServiceValue = new AppAssignSelfServiceValue();
            $appAssignSelfServiceValue->generateData();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * If the feature is enable and the code_scanner_scope was enable will check in the command
     * Review when the command check-workspace-disabled-code was executed
     *
     * @return array
     * @throws Exception
     *
     * @link https://wiki.processmaker.com/3.3/processmaker_command#check-workspace-disabled-code
     * @uses cliWorkspaces.php
     */
    public function getDisabledCode()
    {
        try {
            $this->initPropel(true);

            $process = new Processes();

            //Return
            return $process->getDisabledCode(null, $this->name);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Migrate all cases to New list
     *
     * @param bool $flagReinsert Flag that specifies the re-insertion
     * @param string $lang
     *
     * @return void
     *
     * @throws Exception
     *
     * @see \WorkspaceTools->upgrade
     * @see \WorkspaceTools->restore
     * @see workflow/engine/bin/tasks/cliWorkspaces.php:migrate_new_cases_lists()
     * @link https://wiki.processmaker.com/3.3/processmaker_command#migrate-new-cases-lists
     * @deprecated Class deprecated in Release 3.6.x
     */
    public function migrateList($flagReinsert = false, $lang = 'en')
    {
        $this->initPropel(true);

        $flagListAll = $this->listFirstExecution('check');
        $flagListUnassigned = $this->listFirstExecution('check', 'unassigned');

        if (!$flagReinsert && $flagListAll && $flagListUnassigned) {
            return;
        }

        $arrayTable1 = ['ListCanceled', 'ListInbox', 'ListParticipatedLast', 'ListPaused'];
        $arrayTable2 = ['ListUnassigned'];
        $arrayTable = array_merge($arrayTable1, $arrayTable2);

        if ($flagReinsert) {
            //Delete all records
            foreach ($arrayTable as $value) {
                $tableName = $value . 'Peer';
                $list = new $tableName();
                $list->doDeleteAll();
            }
        }

        if (!$flagReinsert && !$flagListAll) {
            foreach ($arrayTable1 as $value) {
                $tableName = $value . 'Peer';
                $list = new $tableName();

                if ((int)($list->doCount(new Criteria())) > 0) {
                    $flagListAll = true;
                    break;
                }
            }
        }

        // Initialize queries array
        $listQueries = [];

        if ($flagReinsert || !$flagListAll) {
            // Regenerate lists
            $listQueries[] = new RunProcessUpgradeQuery($this->name, $this->regenerateListCanceled($lang));
            $listQueries[] = new RunProcessUpgradeQuery($this->name, $this->regenerateListInbox());
            $listQueries[] = new RunProcessUpgradeQuery($this->name, $this->regenerateListParticipatedLast());
            $listQueries[] = new RunProcessUpgradeQuery($this->name, $this->regenerateListPaused());
        }

        if ($flagReinsert || !$flagListUnassigned) {
            // This list always is truncated
            $con = Propel::getConnection("workflow");
            $stmt = $con->createStatement();
            $stmt->executeQuery('TRUNCATE ' . $this->dbName . '.LIST_UNASSIGNED');

            // Regenerate list
            $listQueries[] = new RunProcessUpgradeQuery($this->name, $this->regenerateListUnassigned());
        }

        // Run queries in multiple threads for populate the list tables
        $processesManager = new ProcessesManager($listQueries);
        $processesManager->run();

        // If exists an error throw an exception
        if (!empty($processesManager->getErrors())) {
            $errorMessage = '';
            foreach ($processesManager->getErrors() as $error) {
                $errorMessage .= $error['rawAnswer'] . PHP_EOL;
            }
            throw new Exception($errorMessage);
        }

        // Updating PRO_ID field
        $this->runUpdateListField(['LIST_CANCELED', 'LIST_INBOX', 'LIST_PARTICIPATED_LAST', 'LIST_UNASSIGNED'], 'updateListProId');

        // Updating TAS_ID field
        $this->runUpdateListField(['LIST_CANCELED', 'LIST_INBOX', 'LIST_PARTICIPATED_LAST', 'LIST_UNASSIGNED'], 'updateListTasId');

        // Updating USR_ID field
        $this->runUpdateListField(['LIST_CANCELED', 'LIST_INBOX', 'LIST_PARTICIPATED_LAST'], 'updateListUsrId');

        // Updating APP_STATUS_ID field
        $this->runUpdateListField(['LIST_INBOX', 'LIST_PARTICIPATED_LAST'], 'updateListAppStatusId');

        // Updating Last Current User Information
        $this->runUpdateListField(['LIST_PARTICIPATED_LAST'], 'updateListParticipatedLastCurrentUser');

        // Updating flags for the list population
        $this->listFirstExecution('insert');
        $this->listFirstExecution('insert', 'unassigned');
    }

    /**
     * Return query to populate canceled list
     *
     * @param string $lang
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function regenerateListCanceled($lang = 'en')
    {
        $query = 'INSERT INTO ' . $this->dbName . '.LIST_CANCELED
                    (APP_UID,
                    USR_UID,
                    TAS_UID,
                    PRO_UID,
                    APP_NUMBER,
                    APP_TITLE,
                    APP_PRO_TITLE,
                    APP_TAS_TITLE,
                    APP_CANCELED_DATE,
                    DEL_INDEX,
                    DEL_PREVIOUS_USR_UID,
                    DEL_CURRENT_USR_USERNAME,
                    DEL_CURRENT_USR_FIRSTNAME,
                    DEL_CURRENT_USR_LASTNAME,
                    DEL_DELEGATE_DATE,
                    DEL_INIT_DATE,
                    DEL_DUE_DATE,
                    DEL_PRIORITY)
                    SELECT
                        ACV.APP_UID,
                        ACV.USR_UID,
                        ACV.TAS_UID,
                        ACV.PRO_UID,
                        ACV.APP_NUMBER,
                        C_APP.CON_VALUE AS APP_TITLE,
                        C_PRO.CON_VALUE AS APP_PRO_TITLE,
                        C_TAS.CON_VALUE AS APP_TAS_TITLE,
                        NOW() AS APP_CANCELED_DATE,
                        ACV.DEL_INDEX,
                        PREV_AD.USR_UID AS DEL_PREVIOUS_USR_UID,
                        USR.USR_USERNAME AS DEL_CURRENT_USR_USERNAME,
                        USR.USR_FIRSTNAME AS DEL_CURRENT_USR_FIRSTNAME,
                        USR.USR_LASTNAME AS DEL_CURRENT_USR_LASTNAME,
                        AD.DEL_DELEGATE_DATE AS DEL_DELEGATE_DATE,
                        AD.DEL_INIT_DATE AS DEL_INIT_DATE,
                        AD.DEL_TASK_DUE_DATE AS DEL_DUE_DATE,
                        ACV.DEL_PRIORITY
                    FROM
                        (' . $this->dbName . '.APP_CACHE_VIEW ACV
                        LEFT JOIN ' . $this->dbName . '.CONTENT C_APP ON ACV.APP_UID = C_APP.CON_ID
                            AND C_APP.CON_CATEGORY = \'APP_TITLE\'
                            AND C_APP.CON_LANG = \'' . $lang . '\'
                        LEFT JOIN ' . $this->dbName . '.CONTENT C_PRO ON ACV.PRO_UID = C_PRO.CON_ID
                            AND C_PRO.CON_CATEGORY = \'PRO_TITLE\'
                            AND C_PRO.CON_LANG = \'' . $lang . '\'
                        LEFT JOIN ' . $this->dbName . '.CONTENT C_TAS ON ACV.TAS_UID = C_TAS.CON_ID
                            AND C_TAS.CON_CATEGORY = \'TAS_TITLE\'
                            AND C_TAS.CON_LANG = \'' . $lang . '\')
                            LEFT JOIN
                        (' . $this->dbName . '.APP_DELEGATION AD
                        INNER JOIN ' . $this->dbName . '.APP_DELEGATION PREV_AD ON AD.APP_UID = PREV_AD.APP_UID
                            AND AD.DEL_PREVIOUS = PREV_AD.DEL_INDEX) ON ACV.APP_UID = AD.APP_UID
                            AND ACV.DEL_INDEX = AD.DEL_INDEX
                            LEFT JOIN
                        ' . $this->dbName . '.USERS USR ON ACV.USR_UID = USR.USR_UID
                    WHERE
                        ACV.APP_STATUS = \'CANCELLED\'
                            AND ACV.DEL_LAST_INDEX = 1';

        return $query;
    }

    /**
     * Return query to populate inbox list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function regenerateListInbox()
    {
        $query = 'INSERT INTO ' . $this->dbName . '.LIST_INBOX
                    (APP_UID,
                    DEL_INDEX,
                    USR_UID,
                    TAS_UID,
                    PRO_UID,
                    APP_NUMBER,
                    APP_STATUS,
                    APP_TITLE,
                    APP_PRO_TITLE,
                    APP_TAS_TITLE,
                    APP_UPDATE_DATE,
                    DEL_PREVIOUS_USR_UID,
                    DEL_PREVIOUS_USR_USERNAME,
                    DEL_PREVIOUS_USR_FIRSTNAME,
                    DEL_PREVIOUS_USR_LASTNAME,
                    DEL_DELEGATE_DATE,
                    DEL_INIT_DATE,
                    DEL_DUE_DATE,
                    DEL_RISK_DATE,
                    DEL_PRIORITY)

                    SELECT
                        ACV.APP_UID,
                        ACV.DEL_INDEX,
                        ACV.USR_UID,
                        ACV.TAS_UID,
                        ACV.PRO_UID,
                        ACV.APP_NUMBER,
                        ACV.APP_STATUS,
                        ACV.APP_TITLE,
                        ACV.APP_PRO_TITLE,
                        ACV.APP_TAS_TITLE,
                        ACV.APP_UPDATE_DATE,
                        ACV.PREVIOUS_USR_UID AS DEL_PREVIOUS_USR_UID,
                        USR.USR_USERNAME AS DEL_PREVIOUS_USR_USERNAME,
                        USR.USR_FIRSTNAME AS DEL_PREVIOUS_USR_FIRSTNAME,
                        USR.USR_LASTNAME AS DEL_PREVIOUS_USR_LASTNAME,
                        ACV.DEL_DELEGATE_DATE AS DEL_DELEGATE_DATE,
                        ACV.DEL_INIT_DATE AS DEL_INIT_DATE,
                        ACV.DEL_TASK_DUE_DATE AS DEL_DUE_DATE,
                        ACV.DEL_RISK_DATE AS DEL_RISK_DATE,
                        ACV.DEL_PRIORITY
                    FROM
                        ' . $this->dbName . '.APP_CACHE_VIEW ACV
                            LEFT JOIN
                        ' . $this->dbName . '.USERS USR ON ACV.PREVIOUS_USR_UID = USR.USR_UID
                    WHERE
                        ACV.DEL_THREAD_STATUS = \'OPEN\'';

        return $query;
    }

    /**
     * Return query to populate paused list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function regenerateListPaused()
    {
        $query = 'INSERT INTO ' . $this->dbName . '.LIST_PAUSED
                  (
                  APP_UID,
                  DEL_INDEX,
                  USR_UID,
                  TAS_UID,
                  PRO_UID,
                  APP_NUMBER,
                  APP_TITLE,
                  APP_PRO_TITLE,
                  APP_TAS_TITLE,
                  APP_PAUSED_DATE,
                  APP_RESTART_DATE,
                  DEL_PREVIOUS_USR_UID,
                  DEL_PREVIOUS_USR_USERNAME,
                  DEL_PREVIOUS_USR_FIRSTNAME,
                  DEL_PREVIOUS_USR_LASTNAME,
                  DEL_CURRENT_USR_USERNAME,
                  DEL_CURRENT_USR_FIRSTNAME,
                  DEL_CURRENT_USR_LASTNAME,
                  DEL_DELEGATE_DATE,
                  DEL_INIT_DATE,
                  DEL_DUE_DATE,
                  DEL_PRIORITY,
                  PRO_ID,
                  USR_ID,
                  TAS_ID
                  )
                  SELECT
                      AD1.APP_UID,
                      AD1.DEL_INDEX,
                      AD1.USR_UID,
                      AD1.TAS_UID,
                      AD1.PRO_UID,
                      AD1.APP_NUMBER,
                      APPLICATION.APP_TITLE,
                      PROCESS.PRO_TITLE,
                      TASK.TAS_TITLE,
                      APP_DELAY.APP_ENABLE_ACTION_DATE AS APP_PAUSED_DATE ,
                      APP_DELAY.APP_DISABLE_ACTION_DATE AS APP_RESTART_DATE,
                      AD2.USR_UID AS DEL_PREVIOUS_USR_UID,
                      PREVIOUS.USR_USERNAME AS DEL_PREVIOUS_USR_USERNAME,
                      PREVIOUS.USR_FIRSTNAME AS DEL_CURRENT_USR_FIRSTNAME,
                      PREVIOUS.USR_LASTNAME AS DEL_PREVIOUS_USR_LASTNAME,
                      USERS.USR_USERNAME AS DEL_CURRENT_USR_USERNAME,
                      USERS.USR_FIRSTNAME AS DEL_CURRENT_USR_FIRSTNAME,
                      USERS.USR_LASTNAME AS DEL_CURRENT_USR_LASTNAME,
                      AD1.DEL_DELEGATE_DATE AS DEL_DELEGATE_DATE,
                      AD1.DEL_INIT_DATE AS DEL_INIT_DATE,
                      AD1.DEL_TASK_DUE_DATE AS DEL_DUE_DATE,
                      AD1.DEL_PRIORITY AS DEL_PRIORITY,
                      PROCESS.PRO_ID,
                      USERS.USR_ID,
                      TASK.TAS_ID
                  FROM
                        ' . $this->dbName . '.APP_DELAY
                  LEFT JOIN
                        ' . $this->dbName . '.APP_DELEGATION AS AD1 ON (APP_DELAY.APP_NUMBER = AD1.APP_NUMBER AND AD1.DEL_INDEX = APP_DELAY.APP_DEL_INDEX)
                  LEFT JOIN
                        ' . $this->dbName . '.APP_DELEGATION AS AD2 ON (AD1.APP_NUMBER = AD2.APP_NUMBER AND AD1.DEL_PREVIOUS = AD2.DEL_INDEX)
                  LEFT JOIN
                        ' . $this->dbName . '.USERS ON (APP_DELAY.APP_DELEGATION_USER_ID = USERS.USR_ID)
                  LEFT JOIN
                        ' . $this->dbName . '.USERS PREVIOUS ON (AD2.USR_ID = PREVIOUS.USR_ID)
                  LEFT JOIN
                        ' . $this->dbName . '.APPLICATION ON (AD1.APP_NUMBER = APPLICATION.APP_NUMBER)
                  LEFT JOIN
                        ' . $this->dbName . '.PROCESS ON (AD1.PRO_ID = PROCESS.PRO_ID)
                  LEFT JOIN
                        ' . $this->dbName . '.TASK ON (AD1.TAS_ID = TASK.TAS_ID)
                  WHERE
                       APP_DELAY.APP_DISABLE_ACTION_USER = "0" AND
                       APP_DELAY.APP_TYPE = "PAUSE"
               ';

        return $query;
    }

    /**
     * Return query to populate participated last list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function regenerateListParticipatedLast()
    {
        $query = 'INSERT INTO ' . $this->dbName . '.LIST_PARTICIPATED_LAST
                    (
                      APP_UID,
                      USR_UID,
                      DEL_INDEX,
                      TAS_UID,
                      PRO_UID,
                      APP_NUMBER,
                      APP_TITLE,
                      APP_PRO_TITLE,
                      APP_TAS_TITLE,
                      APP_STATUS,
                      DEL_PREVIOUS_USR_UID,
                      DEL_PREVIOUS_USR_USERNAME,
                      DEL_PREVIOUS_USR_FIRSTNAME,
                      DEL_PREVIOUS_USR_LASTNAME,
                      DEL_CURRENT_USR_USERNAME,
                      DEL_CURRENT_USR_FIRSTNAME,
                      DEL_CURRENT_USR_LASTNAME,
                      DEL_DELEGATE_DATE,
                      DEL_INIT_DATE,
                      DEL_DUE_DATE,
                      DEL_CURRENT_TAS_TITLE,
                      DEL_PRIORITY,
                      DEL_THREAD_STATUS)
                    
                      SELECT
                        ACV.APP_UID,
                        IF(ACV.USR_UID=\'\', \'SELF_SERVICES\', ACV.USR_UID),
                        ACV.DEL_INDEX,
                        ACV.TAS_UID,
                        ACV.PRO_UID,
                        ACV.APP_NUMBER,
                        ACV.APP_TITLE,
                        ACV.APP_PRO_TITLE,
                        ACV.APP_TAS_TITLE,
                        ACV.APP_STATUS,
                        DEL_PREVIOUS_USR_UID,
                        IFNULL(PRE_USR.USR_USERNAME, CUR_USR.USR_USERNAME)   AS DEL_PREVIOUS_USR_USERNAME,
                        IFNULL(PRE_USR.USR_FIRSTNAME, CUR_USR.USR_FIRSTNAME) AS DEL_PREVIOUS_USR_USERNAME,
                        IFNULL(PRE_USR.USR_LASTNAME, CUR_USR.USR_LASTNAME)   AS DEL_PREVIOUS_USR_USERNAME,
                        CUR_USR.USR_USERNAME                                 AS DEL_CURRENT_USR_USERNAME,
                        CUR_USR.USR_FIRSTNAME                                AS DEL_CURRENT_USR_FIRSTNAME,
                        CUR_USR.USR_LASTNAME                                 AS DEL_CURRENT_USR_LASTNAME,
                        ACV.DEL_DELEGATE_DATE                                AS DEL_DELEGATE_DATE,
                        ACV.DEL_INIT_DATE                                    AS DEL_INIT_DATE,
                        ACV.DEL_TASK_DUE_DATE                                AS DEL_DUE_DATE,
                        ACV.APP_TAS_TITLE                                    AS DEL_CURRENT_TAS_TITLE,
                        ACV.DEL_PRIORITY,
                        ACV.DEL_THREAD_STATUS
                      FROM
                        (
                          SELECT
                            CASE WHEN ACV1.PREVIOUS_USR_UID = \'\' AND ACV1.DEL_INDEX = 1
                              THEN ACV1.USR_UID
                            ELSE ACV1.PREVIOUS_USR_UID END AS DEL_PREVIOUS_USR_UID,
                            ACV1.*
                          FROM ' . $this->dbName . '.APP_CACHE_VIEW ACV1
                            JOIN
                            (SELECT
                               ACV_INT.APP_UID,
                               MAX(ACV_INT.DEL_INDEX) MAX_DEL_INDEX
                             FROM
                               ' . $this->dbName . '.APP_CACHE_VIEW ACV_INT
                             GROUP BY
                               ACV_INT.USR_UID,
                               ACV_INT.APP_UID
                            ) ACV2
                              ON ACV2.APP_UID = ACV1.APP_UID AND ACV2.MAX_DEL_INDEX = ACV1.DEL_INDEX
                        ) ACV
                        LEFT JOIN ' . $this->dbName . '.USERS PRE_USR ON ACV.PREVIOUS_USR_UID = PRE_USR.USR_UID
                        LEFT JOIN ' . $this->dbName . '.USERS CUR_USR ON ACV.USR_UID = CUR_USR.USR_UID';

        return $query;
    }

    /**
     * Return query to populate unassigned list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function regenerateListUnassigned()
    {
        $query = 'INSERT INTO ' . $this->dbName . '.LIST_UNASSIGNED
                    (APP_UID,
                    DEL_INDEX,
                    TAS_UID,
                    PRO_UID,
                    APP_NUMBER,
                    APP_TITLE,
                    APP_PRO_TITLE,
                    APP_TAS_TITLE,
                    DEL_PREVIOUS_USR_USERNAME,
                    DEL_PREVIOUS_USR_FIRSTNAME,
                    DEL_PREVIOUS_USR_LASTNAME,
                    APP_UPDATE_DATE,
                    DEL_PREVIOUS_USR_UID,
                    DEL_DELEGATE_DATE,
                    DEL_DUE_DATE,
                    DEL_PRIORITY)

                    SELECT
                        ACV.APP_UID,
                        ACV.DEL_INDEX,
                        ACV.TAS_UID,
                        ACV.PRO_UID,
                        ACV.APP_NUMBER,
                        ACV.APP_TITLE,
                        ACV.APP_PRO_TITLE,
                        ACV.APP_TAS_TITLE,
                        USR.USR_USERNAME AS DEL_PREVIOUS_USR_USERNAME,
                        USR.USR_FIRSTNAME AS DEL_PREVIOUS_USR_FIRSTNAME,
                        USR.USR_LASTNAME AS DEL_PREVIOUS_USR_LASTNAME,
                        ACV.APP_UPDATE_DATE,
                        ACV.PREVIOUS_USR_UID AS DEL_PREVIOUS_USR_UID,
                        ACV.DEL_DELEGATE_DATE AS DEL_DELEGATE_DATE,
                        ACV.DEL_TASK_DUE_DATE AS DEL_DUE_DATE,
                        ACV.DEL_PRIORITY
                    FROM
                        ' . $this->dbName . '.APP_CACHE_VIEW ACV
                            LEFT JOIN
                        ' . $this->dbName . '.USERS USR ON ACV.PREVIOUS_USR_UID = USR.USR_UID
                    WHERE
                        ACV.DEL_THREAD_STATUS = \'OPEN\'
                        AND ACV.USR_UID = \'\' ';

        return $query;
    }

    /**
     * Re-populate only the unassigned list
     */
    public function runRegenerateListUnassigned()
    {
        // Init Propel
        $this->initPropel(true);

        // Initialize Propel objects
        $con = Propel::getConnection("workflow");
        $stmt = $con->createStatement();

        // Clean table
        $stmt->executeQuery('TRUNCATE ' . $this->dbName . '.LIST_UNASSIGNED;');

        // Populate table
        $stmt->executeQuery($this->regenerateListUnassigned());

        // Update some fields
        $stmt->executeQuery($this->updateListProId('LIST_UNASSIGNED'));
        $stmt->executeQuery($this->updateListTasId('LIST_UNASSIGNED'));
    }

    /**
     * Run the update queries for the specified tables
     *
     * @param array $listTables
     * @param string $methodName
     *
     * @throws Exception
     */
    public function runUpdateListField(array $listTables, $methodName)
    {
        // Clean the queries array
        $listQueries = [];

        // Get the queries
        foreach ($listTables as $listTable) {
            $listQueries[] = new RunProcessUpgradeQuery($this->name, $this->$methodName($listTable));
        }

        // Run queries in multiple threads for update the list tables
        $processesManager = new ProcessesManager($listQueries);
        $processesManager->run();

        // If exists an error throw an exception
        if (!empty($processesManager->getErrors())) {
            $errorMessage = '';
            foreach ($processesManager->getErrors() as $error) {
                $errorMessage .= $error['rawAnswer'] . PHP_EOL;
            }
            throw new Exception($errorMessage);
        }
    }

    /**
     * Return query to update PRO_ID in list table
     *
     * @param string $list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function updateListProId($list)
    {
        $query = 'UPDATE ' . $list . ' AS LT
                  INNER JOIN (
                      SELECT PROCESS.PRO_UID, PROCESS.PRO_ID
                      FROM PROCESS
                  ) AS PRO
                  ON (LT.PRO_UID = PRO.PRO_UID)
                  SET LT.PRO_ID = PRO.PRO_ID
                  WHERE LT.PRO_ID = 0';
        return $query;
    }

    /**
     * Return query to update USR_ID in list table
     *
     * @param string $list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function updateListUsrId($list)
    {
        $query = 'UPDATE ' . $list . ' AS LT
                  INNER JOIN (
                      SELECT USERS.USR_UID, USERS.USR_ID
                      FROM USERS
                  ) AS USR
                  ON (LT.USR_UID = USR.USR_UID)
                  SET LT.USR_ID = USR.USR_ID
                  WHERE LT.USR_ID = 0';
        return $query;
    }

    /**
     * Return query to update TAS_ID in list table
     *
     * @param string $list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function updateListTasId($list)
    {
        $query = 'UPDATE ' . $list . ' AS LT
                  INNER JOIN (
                      SELECT TASK.TAS_UID, TASK.TAS_ID
                      FROM TASK
                  ) AS TAS
                  ON (LT.TAS_UID = TAS.TAS_UID)
                  SET LT.TAS_ID = TAS.TAS_ID
                  WHERE LT.TAS_ID = 0';
        return $query;
    }

    /**
     * Return query to update APP_STATUS_ID in list table
     *
     * @para string $list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function updateListAppStatusId($list)
    {
        $query = "UPDATE " . $list . "
                  SET APP_STATUS_ID = (case
                      when APP_STATUS = 'DRAFT' then 1
                      when APP_STATUS = 'TO_DO' then 2
                      when APP_STATUS = 'COMPLETED' then 3
                      when APP_STATUS = 'CANCELLED' then 4
                  end)
                  WHERE APP_STATUS in ('DRAFT', 'TO_DO', 'COMPLETED', 'CANCELLED') AND APP_STATUS_ID = 0";
        return $query;
    }

    /**
     * Return query to update participated last list
     *
     * @return string
     *
     * @see \WorkspaceTools->migrateList()
     */
    public function updateListParticipatedLastCurrentUser()
    {
        $query = 'UPDATE ' . $this->dbName . '.LIST_PARTICIPATED_LAST LPL, (
                       SELECT
                         TASK.TAS_TITLE,
                         CUR_USER.APP_UID,
                         USERS.USR_UID,
                         USERS.USR_USERNAME,
                         USERS.USR_FIRSTNAME,
                         USERS.USR_LASTNAME
                       FROM (
                              SELECT
                                APP_UID,
                                TAS_UID,
                                DEL_INDEX,
                                USR_UID
                              FROM ' . $this->dbName . '.APP_DELEGATION
                              WHERE DEL_LAST_INDEX = 1
                            ) CUR_USER
                         LEFT JOIN ' . $this->dbName . '.USERS ON CUR_USER.USR_UID = USERS.USR_UID
                         LEFT JOIN ' . $this->dbName . '.TASK ON CUR_USER.TAS_UID = TASK.TAS_UID) USERS_VALUES
                    SET
                      LPL.DEL_CURRENT_USR_USERNAME  = IFNULL(USERS_VALUES.USR_USERNAME, \'\'),
                      LPL.DEL_CURRENT_USR_FIRSTNAME = IFNULL(USERS_VALUES.USR_FIRSTNAME, \'\'),
                      LPL.DEL_CURRENT_USR_LASTNAME  = IFNULL(USERS_VALUES.USR_LASTNAME, \'\'),
                      LPL.DEL_CURRENT_TAS_TITLE     = IFNULL(USERS_VALUES.TAS_TITLE, \'\')
                    WHERE LPL.APP_UID = USERS_VALUES.APP_UID';

        return $query;
    }

    /**
     * This function checks if List tables are going to migrated
     *
     * return boolean value
     */
    public function listFirstExecution($action, $list = 'all')
    {
        $this->initPropel(true);
        switch ($action) {
            case 'insert':
                $conf = new Configuration();
                if ($list === 'all') {
                    if (!($conf->exists('MIGRATED_LIST', 'list', 'list', 'list', 'list'))) {
                        $data["CFG_UID"] = 'MIGRATED_LIST';
                        $data["OBJ_UID"] = 'list';
                        $data["CFG_VALUE"] = 'true';
                        $data["PRO_UID"] = 'list';
                        $data["USR_UID"] = 'list';
                        $data["APP_UID"] = 'list';
                        $conf->create($data);
                    }
                }
                if ($list === 'unassigned') {
                    if (!($conf->exists('MIGRATED_LIST_UNASSIGNED', 'list', 'list', 'list', 'list'))) {
                        $data["CFG_UID"] = 'MIGRATED_LIST_UNASSIGNED';
                        $data["OBJ_UID"] = 'list';
                        $data["CFG_VALUE"] = 'true';
                        $data["PRO_UID"] = 'list';
                        $data["USR_UID"] = 'list';
                        $data["APP_UID"] = 'list';
                        $conf->create($data);
                    }
                }
                return true;
                break;
            case 'check':
                $criteria = new Criteria("workflow");
                $criteria->addSelectColumn(ConfigurationPeer::CFG_UID);
                if ($list === 'all') {
                    $criteria->add(ConfigurationPeer::CFG_UID, "MIGRATED_LIST", CRITERIA::EQUAL);
                }
                if ($list === 'unassigned') {
                    $criteria->add(ConfigurationPeer::CFG_UID, "MIGRATED_LIST_UNASSIGNED", CRITERIA::EQUAL);
                }
                $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
                $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                $aRows = [];
                while ($rsCriteria->next()) {
                    $aRows[] = $rsCriteria->getRow();
                }
                if (empty($aRows)) {
                    return false; //If is false continue with the migrated
                } else {
                    return true; //Stop
                }
                break;
            default:
                return true;
        }
    }

    /**
     * Verify feature
     *
     * @param string $featureName Feature name
     *
     * return bool Return true if is valid the feature, false otherwise
     */
    public function pmLicensedFeaturesVerifyFeature($featureName)
    {
        try {
            $this->initPropel(true);

            $flag = PMLicensedFeatures::getSingleton()->verifyfeature($featureName);

            $this->close();

            //Return
            return $flag;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Process files upgrade, store the information in the DB
     *
     * @param string $workspace
     *
     * return void
     */
    public function processFilesUpgrade($workspace)
    {
        try {
            if (!defined("PATH_DATA_MAILTEMPLATES")) {
                define("PATH_DATA_MAILTEMPLATES", PATH_DATA . 'sites' . PATH_SEP . $workspace . PATH_SEP . "mailTemplates" . PATH_SEP);
            }

            if (!defined("PATH_DATA_PUBLIC")) {
                define("PATH_DATA_PUBLIC", PATH_DATA . 'sites' . PATH_SEP . $workspace . PATH_SEP . "public" . PATH_SEP);
            }

            $this->initPropel(true);

            $filesManager = new \ProcessMaker\BusinessModel\FilesManager();

            $filesManager->processFilesUpgrade();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Register system tables in a file
     *
     * return void
     */
    public static function registerSystemTables($aSquema)
    {
        //Register all tables
        $sListTables = '';
        foreach ($aSquema as $key => $value) {
            $sListTables .= $key . '|';
        }

        $sysTablesIniFile = PATH_CONFIG . 'system-tables.ini';
        $contents = file_put_contents($sysTablesIniFile, sprintf("%s '%s'\n", "tables = ", $sListTables));
        if ($contents === null) {
            throw (new Exception(G::LoadTranslation('ID_FILE_NOT_WRITEABLE', SYS_LANG, array($sysTablesIniFile))));
        }
    }

    /**
     *return void
     */
    public function checkRbacPermissions()
    {
        CLI::logging("-> Remove the permissions deprecated in RBAC \n");
        $this->removePermission();
        CLI::logging("-> Verifying roles permissions in RBAC \n");
        //Update table RBAC permissions
        $RBAC = RBAC::getSingleton();
        $RBAC->initRBAC();
        $result = $RBAC->verifyPermissions();
        if (count($result) > 1) {
            foreach ($result as $item) {
                CLI::logging("    $item... \n");
            }
        } else {
            CLI::logging("    All roles permissions already updated \n");
        }
    }

    /**
     * Check SCHEDULER table integrity.
     * @return void
     */
    public function checkSchedulerTable(): void
    {
        CLI::logging("-> Check SCHEDULER table integrity.\n");
        TaskSchedulerBM::checkDataIntegrity();
        CLI::logging("    SCHEDULER table integrity was checked.\n");
    }

    /**
     * Add sequence numbers
     */
    public function checkSequenceNumber()
    {
        // Instance required class
        $appSequenceInstance = new AppSequence();

        // Get a record from APP_SEQUENCE table
        $criteria = new Criteria('workflow');
        $rsCriteria = AppSequencePeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $rsCriteria->next();
        $appSequenceRow = $rsCriteria->getRow();

        // If table APP_SEQUENCE is empty, insert two records
        if (empty($appSequenceRow)) {
            // Check if exist a value in old table SEQUENCES
            $sequenceInstance = SequencesPeer::retrieveByPK('APP_NUMBER');

            if (!is_null($sequenceInstance)) {
                // If exists a value in SEQUENCE table, copy the same to APP_SEQUENCES table
                $sequenceFields = $sequenceInstance->toArray(BasePeer::TYPE_FIELDNAME);
                $appSequenceInstance->updateSequenceNumber($sequenceFields['SEQ_VALUE']);
            } else {
                // If not exists a value in SEQUENCE table, insert a initial value
                $appSequenceInstance->updateSequenceNumber(0);
            }

            // Insert a initial value for the web entries
            $appSequenceInstance->updateSequenceNumber(0, AppSequence::APP_TYPE_WEB_ENTRY);
        } else {
            // Create a new instance of Criteria class
            $criteria = new Criteria('workflow');
            $criteria->add(AppSequencePeer::APP_TYPE, AppSequence::APP_TYPE_WEB_ENTRY);

            // Check if exists a record for the web entries, if not exist insert the initial value
            if (AppSequencePeer::doCount($criteria) === 0) {
                $appSequenceInstance->updateSequenceNumber(0, AppSequence::APP_TYPE_WEB_ENTRY);
            }
        }
    }

    public function hasMissingUsers()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/missing-users-" . $this->name . ".txt",
            "Missing Processes List.\n"
        );

        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addSelectColumn(AppCacheViewPeer::USR_UID);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::USR_UID, UsersPeer::USR_UID)
            ),
            Criteria::LEFT_JOIN
        );
        $criteria->add(UsersPeer::USR_UID, null, Criteria::ISNULL);
        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/missing-users-" . $this->name . ".txt",
                "APP_UID:[" . $item['APP_UID'] . "] - DEL_INDEX[" . $item['DEL_INDEX'] . "] have relation " .
                    "with invalid or non-existent user user with " .
                    "id [" . $item['USR_UID'] . "]"
            );
        }
        CLI::logging("> Number of user related inconsistencies for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
        return ($counter > 0);
    }

    public function hasMissingTasks()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/missing-tasks-" . $this->name . ".txt",
            "Missing Processes List\n"
        );

        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addSelectColumn(AppCacheViewPeer::TAS_UID);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::USR_UID, TaskPeer::TAS_UID)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(TaskPeer::TAS_UID, null, Criteria::ISNULL);
        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            file_put_contents(
                PATH_DATA . "/missing-tasks-" . $this->name . ".txt",
                "APP_UID:[" . $item['APP_UID'] . "] - DEL_INDEX[" . $item['DEL_INDEX'] . "] have relation " .
                    "with invalid or non-existent task with " .
                    "id [" . $item['TAS_UID'] . "]"
            );
        }

        CLI::logging("> Number of task related inconsistencies for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
        return ($counter > 0);
    }

    public function hasMissingProcesses()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/missing-processes-" . $this->name . ".txt",
            "Missing Processes List\n"
        );

        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addSelectColumn(AppCacheViewPeer::PRO_UID);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::USR_UID, ProcessPeer::PRO_UID)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(ProcessPeer::PRO_UID, null, Criteria::ISNULL);
        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            file_put_contents(
                PATH_DATA . "/missing-processes-" . $this->name . ".txt",
                "APP_UID:[" . $item['APP_UID'] . "] - DEL_INDEX[" . $item['DEL_INDEX'] . "] have relation " .
                    "with invalid or non-existent process with " .
                    "id [" . $item['PRO_UID'] . "]"
            );
        }
        CLI::logging("> Number of processes related data inconsistencies for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
        return ($counter > 0);
    }

    public function hasMissingAppDelegations()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/missing-app-delegation-" . $this->name . ".txt",
            "Missing AppDelegation List.\n"
        );

        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, AppCacheViewPeer::DEL_INDEX),
                array(AppDelegationPeer::APP_UID, AppDelegationPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );
        $criteria->add(AppDelegationPeer::APP_UID, null, Criteria::ISNULL);
        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/missing-app-delegation-" . $this->name . ".txt",
                "APP_UID:[" . $item['APP_UID'] . "] - DEL_INDEX[" . $item['DEL_INDEX'] . "] have relation " .
                    "with invalid or non-existent process with " .
                    "id [" . $item['PRO_UID'] . "]"
            );
        }
        CLI::logging("> Number of delegations related data inconsistencies for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
        return ($counter > 0);
    }


    public function verifyMissingCancelled()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-cancelled-" . $this->name . ".txt",
            "Missing Cancelled List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListCanceledPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListCanceledPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::APP_STATUS, 'CANCELLED', Criteria::EQUAL);
        $criteria->add(AppCacheViewPeer::DEL_LAST_INDEX, 1, Criteria::EQUAL);
        $criteria->add(ListCanceledPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;

            file_put_contents(
                PATH_DATA . "/post-missing-cancelled-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }

        CLI::logging("> Number of missing cancelled cases for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyMissingCompleted()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-completed-" . $this->name . ".txt",
            "Missing Completed List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListCompletedPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListCompletedPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::APP_STATUS, 'COMPLETED', Criteria::EQUAL);
        $criteria->add(AppCacheViewPeer::DEL_LAST_INDEX, 1, Criteria::EQUAL);
        $criteria->add(ListCompletedPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/post-missing-completed-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }

        CLI::logging("> Number of missing completed cases for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyMissingInbox()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-inbox-" . $this->name . ".txt",
            "Missing Inbox List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListInboxPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListInboxPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::DEL_THREAD_STATUS, 'OPEN', Criteria::EQUAL);
        $criteria->add(ListInboxPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/post-missing-inbox-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }

        CLI::logging("> Number of missing inbox cases for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyMissingParticipatedHistory()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-participated-history-" . $this->name . ".txt",
            "Missing ParticipatedHistory List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListParticipatedHistoryPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListParticipatedHistoryPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::DEL_THREAD_STATUS, 'OPEN', Criteria::NOT_EQUAL);
        $criteria->add(ListParticipatedHistoryPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/post-missing-participated-history-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }

        CLI::logging("> Number of missing participated history entries for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyMissingParticipatedLast()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-participated-last-" . $this->name . ".txt",
            "Missing ParticipatedLast List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListParticipatedLastPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListParticipatedLastPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::DEL_THREAD_STATUS, 'OPEN', Criteria::NOT_EQUAL);
        $criteria->add(ListParticipatedLastPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/post-missing-participated-last-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }

        CLI::logging("> Number of missing participated last entries for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyMissingMyInbox()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-my-inbox-" . $this->name . ".txt",
            "Missing MyInbox List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListMyInboxPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListMyInboxPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::DEL_INDEX, 1, Criteria::EQUAL);
        $criteria->add(ListMyInboxPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/post-missing-my-inbox-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }

        CLI::logging("> Number of missing my inbox entries for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyMissingUnassigned()
    {
        $this->initPropel(true);
        file_put_contents(
            PATH_DATA . "/post-missing-unassigned-" . $this->name . ".txt",
            "Missing MissingUnassigned List.\n"
        );
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppCacheViewPeer::APP_UID);
        $criteria->addSelectColumn(AppCacheViewPeer::DEL_INDEX);
        $criteria->addJoinMC(
            array(
                array(AppCacheViewPeer::APP_UID, ListUnassignedPeer::APP_UID),
                array(AppCacheViewPeer::DEL_INDEX, ListUnassignedPeer::DEL_INDEX)
            ),
            Criteria::LEFT_JOIN
        );

        $criteria->add(AppCacheViewPeer::USR_UID, '', Criteria::EQUAL);
        $criteria->add(ListUnassignedPeer::APP_UID, null, Criteria::ISNULL);

        $rsCriteria = AppCacheViewPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $counter = 0;
        while ($rsCriteria->next()) {
            $item = $rsCriteria->getRow();
            $counter++;
            file_put_contents(
                PATH_DATA . "/post-missing-unassigned-" . $this->name . ".txt",
                "[" . $item['APP_UID'] . "] has not been found"
            );
        }
        CLI::logging("> Number of unassigned cases for workspace " . CLI::info($this->name) . ": " . CLI::info($counter) . "\n");
    }

    public function verifyListData($type)
    {
        switch ($type) {
            case 'LIST_CANCELLED':
                $response = $this->verifyMissingCancelled();
                break;
            case 'LIST_COMPLETED':
                $response = $this->verifyMissingCompleted();
                break;
            case 'LIST_INBOX':
                $response = $this->verifyMissingInbox();
                break;
            case 'LIST_PARTICIPATED_HISTORY':
                $response = $this->verifyMissingParticipatedHistory();
                break;
            case 'LIST_PARTICIPATED_LAST':
                $response = $this->verifyMissingParticipatedLast();
                break;
            case 'LIST_MY_INBOX':
                $response = $this->verifyMissingMyInbox();
                break;
            case 'LIST_PAUSED':
                // The list implementation needs to be reestructured in order to
                // properly validate the list consistency, currently we are maintaining the
                // current LIST_PAUSED implementation.
                $response = '';
                break;
            case 'LIST_UNASSIGNED':
                $response = $this->verifyMissingUnassigned();
                break;
            case 'LIST_UNASSIGNED_GROUP':
                // There is still no need to validate this list since is not being
                // populated until the logic has been defined
                $response = '';
                break;
            default:
                $response = '';
                break;
        }
        return $response;
    }

    /**
     * Migrate texts/values from "CONTENT" table to the corresponding object tables
     *
     * @param string $lang
     */
    public function migrateContent($lang = SYS_LANG)
    {
        if ((!class_exists('Memcache') || !class_exists('Memcached')) && !defined('MEMCACHED_ENABLED')) {
            define('MEMCACHED_ENABLED', false);
        }
        $this->initPropel(true);
        $conf = new Configuration();
        $blackList = [];
        if ($bExist = $conf->exists('MIGRATED_CONTENT', 'content')) {
            $oConfig = $conf->load('MIGRATED_CONTENT', 'content');
            $blackList = $oConfig['CFG_VALUE'] == 'true' ? array('Groupwf', 'Process', 'Department', 'Task', 'InputDocument', 'Application') : unserialize($oConfig['CFG_VALUE']);
        }

        $blackList = $this->migrateContentRun($lang, $blackList);
        $data["CFG_UID"] = 'MIGRATED_CONTENT';
        $data["OBJ_UID"] = 'content';
        $data["CFG_VALUE"] = serialize($blackList);
        $data["PRO_UID"] = '';
        $data["USR_UID"] = '';
        $data["APP_UID"] = '';
        $conf->create($data);
    }

    /**
     * Generate update rows from Content sentence
     *
     * @param string $tableName
     * @param array $fields
     * @param string $lang
     *
     * @return string
     */
    public function generateUpdateFromContent($tableName, array $fields, $lang = SYS_LANG)
    {
        $sql = "UPDATE " . $tableName . " AS T";
        $i = 0;
        foreach ($fields['fields'] as $field) {
            $i++;
            $tableAlias = "C" . $i;
            $sql .= " LEFT JOIN CONTENT " . $tableAlias . " ON (";
            $sql .= $tableAlias . ".CON_CATEGORY = '" . $field . "' AND ";
            $sql .= $tableAlias . ".CON_ID = T." . $fields['uid'] . " AND ";
            $sql .= $tableAlias . ".CON_LANG = '" . $lang . "')";
        }
        $sql .= ' SET ';
        $i = 0;
        foreach ($fields['fields'] as $field) {
            $i++;
            $tableAlias = "C" . $i;
            $fieldName = !empty($fields['alias'][$field]) ? $fields['alias'][$field] : $field;
            $sql .= $fieldName . " = " . $tableAlias . ".CON_VALUE, ";
        }
        $sql = rtrim($sql, ', ');
        return $sql;
    }

    /**
     * Migrate from "CONTENT" table to the corresponding object tables
     *
     * @param string $lang
     * @param array $blackList
     *
     * @return array
     *
     * @throws Exception
     */
    public function migrateContentRun($lang = SYS_LANG, $blackList = [])
    {
        if ((!class_exists('Memcache') || !class_exists('Memcached')) && !defined('MEMCACHED_ENABLED')) {
            define('MEMCACHED_ENABLED', false);
        }
        $content = $this->getListContentMigrateTable();
        $contentQueries = [];

        foreach ($content as $className => $fields) {
            if (!in_array($className, $blackList)) {
                // Build class peer name
                if (class_exists($className . 'Peer')) {
                    $classNamePeer = $className . 'Peer';
                } else {
                    $classNamePeer = $fields['peer'];
                }

                // Build the query
                $query = $this->generateUpdateFromContent($classNamePeer::TABLE_NAME, $fields, $lang);

                // Instantiate the class to execute the query in background
                $contentQueries[] = new RunProcessUpgradeQuery($this->name, $query);

                // Add class to the control array
                $blackList[] = $className;
            }
        }
        // Run queries in multiple threads
        $processesManager = new ProcessesManager($contentQueries);
        $processesManager->run();

        // If exists an error throw an exception
        if (!empty($processesManager->getErrors())) {
            $errorMessage = '';
            foreach ($processesManager->getErrors() as $error) {
                $errorMessage .= $error['rawAnswer'] . PHP_EOL;
            }
            throw new Exception($errorMessage);
        }

        return $blackList;
    }

    /**
     * Clean the expired access and refresh tokens
     */
    public function cleanTokens()
    {
        $this->initPropel(true);
        $oCriteria = new Criteria();
        $oCriteria->add(OauthAccessTokensPeer::ACCESS_TOKEN, 0, Criteria::NOT_EQUAL);
        $accessToken = OauthAccessTokensPeer::doDelete($oCriteria);
        CLI::logging("|--> Clean data in table " . OauthAccessTokensPeer::TABLE_NAME . " rows " . $accessToken . "\n");
        $oCriteria = new Criteria();
        $oCriteria->add(OauthRefreshTokensPeer::REFRESH_TOKEN, 0, Criteria::NOT_EQUAL);
        $refreshToken = OauthRefreshTokensPeer::doDelete($oCriteria);
        CLI::logging("|--> Clean data in table " . OauthRefreshTokensPeer::TABLE_NAME . " rows " . $refreshToken . "\n");
    }

    /**
     * Migrate the Intermediate throw Email Event to Dummy task, specify the workspaces. 
     * The processes in this workspace will be updated.
     * 
     * @param string $workspaceName
     * @see workflow/engine/bin/tasks/cliWorkspaces.php::run_migrate_itee_to_dummytask()
     * @see workflow/engine/classes/WorkspaceTools.php->upgradeDatabase()
     * @link https://wiki.processmaker.com/3.3/processmaker_command#migrate-itee-to-dummytask
     */
    public function migrateIteeToDummytask($workspaceName)
    {
        $this->initPropel(true);
        $config = System::getSystemConfiguration('', '', $workspaceName);
        G::$sysSys = $workspaceName;
        G::$pathDataSite = PATH_DATA . "sites" . PATH_SEP . G::$sysSys . PATH_SEP;
        G::$pathDocument = PATH_DATA . 'sites' . DIRECTORY_SEPARATOR . $workspaceName . DIRECTORY_SEPARATOR . 'files';
        G::$memcachedEnabled = $config['memcached'];
        G::$pathDataPublic = G::$pathDataSite . "public" . PATH_SEP;
        G::$sysSkin = $config['default_skin'];
        if (is_file(G::$pathDataSite . PATH_SEP . ".server_info")) {
            $serverInfo = file_get_contents(G::$pathDataSite . PATH_SEP . ".server_info");
            $serverInfo = unserialize($serverInfo);
            $envHost = $serverInfo["SERVER_NAME"];
            $envPort = ($serverInfo["SERVER_PORT"] . "" != "80") ? ":" . $serverInfo["SERVER_PORT"] : "";
            if (!empty($envPort) && strpos($envHost, $envPort) === false) {
                $envHost = $envHost . $envPort;
            }
            G::$httpHost = $envHost;
        }

        //Search All process
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(ProcessPeer::PRO_UID);
        $criteria->addSelectColumn(ProcessPeer::PRO_ITEE);
        $criteria->add(ProcessPeer::PRO_ITEE, '0', Criteria::EQUAL);
        $resultSet = ProcessPeer::doSelectRS($criteria);
        $resultSet->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $message = "-> Migrating the Intermediate Email Event \n";
        CLI::logging($message);
        while ($resultSet->next()) {
            $row = $resultSet->getRow();
            $prjUid = $row['PRO_UID'];
            $process = new Process();
            if ($process->isBpmnProcess($prjUid)) {
                $project = new BpmnWorkflow();
                $diagram = $project->getStruct($prjUid);
                $project->updateFromStruct($prjUid, $diagram);
                $process->setProUid($prjUid);
                $updateProcess = new Process();
                $updateProcessData = [];
                $updateProcessData['PRO_UID'] = $prjUid;
                $updateProcessData['PRO_ITEE'] = '1';
                if ($updateProcess->processExists($prjUid)) {
                    $updateProcess->update($updateProcessData);
                }
                $message = "    Process updated " . $process->getProTitle() . "\n";
                CLI::logging($message);
            }
        }
        $message = "   Migrating Itee Done \n";
        CLI::logging($message);
    }

    public function upgradeAuditLog($workspace)
    {
        $conf = new Configurations();
        if (!$conf->exists('AUDIT_LOG', 'log')) {
            CLI::logging("> Updating Auditlog Config \n");
            $oServerConf = ServerConf::getSingleton();
            $sAudit = $oServerConf->getAuditLogProperty('AL_OPTION', $workspace);
            $conf->aConfig = ($sAudit == 1) ? 'true' : 'false';
            $conf->saveConfig('AUDIT_LOG', 'log');
        }
    }

    /**
     * Migrate the concatenated strings with UIDs from groups to the table "APP_ASSIGN_SELF_SERVICE_VALUE_GROUP"
     */
    public function migrateSelfServiceRecordsRun()
    {
        // Initializing
        $this->initPropel(true);

        // Get datat to migrate
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(AppAssignSelfServiceValuePeer::ID);
        $criteria->addSelectColumn(AppAssignSelfServiceValuePeer::GRP_UID);
        $criteria->add(AppAssignSelfServiceValuePeer::GRP_UID, '', Criteria::NOT_EQUAL);
        $rsCriteria = AppAssignSelfServiceValuePeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        // Migrating data
        CLI::logging("-> Migrating Self-Service by Value Cases \n");
        while ($rsCriteria->next()) {
            $row = $rsCriteria->getRow();
            $temp = @unserialize($row['GRP_UID']);
            if (is_array($temp)) {
                foreach ($temp as $groupUid) {
                    if ($groupUid != '') {
                        $appAssignSelfServiceValueGroup = new AppAssignSelfServiceValueGroup();
                        $appAssignSelfServiceValueGroup->setId($row['ID']);
                        $appAssignSelfServiceValueGroup->setGrpUid($groupUid);
                        $appAssignSelfServiceValueGroup->save();
                    }
                }
            } else {
                if ($temp != '') {
                    $appAssignSelfServiceValueGroup = new AppAssignSelfServiceValueGroup();
                    $appAssignSelfServiceValueGroup->setId($row['ID']);
                    $appAssignSelfServiceValueGroup->setGrpUid($temp);
                    $appAssignSelfServiceValueGroup->save();
                }
            }
            CLI::logging("    Migrating Record " . $row['ID'] . "\n");
        }

        // Updating processed records to empty
        $con = Propel::getConnection('workflow');
        $criteriaSet = new Criteria("workflow");
        $criteriaSet->add(AppAssignSelfServiceValuePeer::GRP_UID, '');
        BasePeer::doUpdate($criteria, $criteriaSet, $con);

        CLI::logging("   Migrating Self-Service by Value Cases Done \n");
    }

    /**
     * Remove the permissions deprecated
     */
    public function removePermission()
    {
        // Initializing
        $this->initPropel(true);
        $con = Propel::getConnection(RbacUsersPeer::DATABASE_NAME);
        // Remove the permission PM_SETUP_HEART_BEAT
        CLI::logging("->   Remove permission PM_SETUP_HEART_BEAT \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("DELETE FROM RBAC_ROLES_PERMISSIONS WHERE PER_UID = '00000000000000000000000000000025'");
        $con->commit();
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("DELETE FROM RBAC_PERMISSIONS WHERE PER_UID = '00000000000000000000000000000025'");
        $con->commit();
    }

    /**
     * Populate new fields used for avoiding the use of the "APP_CACHE_VIEW" table
     */
    public function migratePopulateIndexingACV()
    {
        // Migrating and populating new indexes
        CLI::logging("-> Migrating an populating indexing for avoiding the use of table APP_CACHE_VIEW Start \n");

        // Initializing
        $this->initPropel(true);
        $con = Propel::getConnection(AppDelegationPeer::DATABASE_NAME);

        // Populating APP_DELEGATION.APP_NUMBER
        CLI::logging("->   Populating APP_DELEGATION.APP_NUMBER \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELEGATION AS AD
                                   INNER JOIN (
                                       SELECT APPLICATION.APP_UID, APPLICATION.APP_NUMBER
                                       FROM APPLICATION
                                   ) AS APP
                                   ON (AD.APP_UID = APP.APP_UID)
                                   SET AD.APP_NUMBER = APP.APP_NUMBER
                                   WHERE AD.APP_NUMBER = 0");
        $con->commit();

        // Populating APP_DELEGATION.USR_ID
        CLI::logging("->   Populating APP_DELEGATION.USR_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELEGATION AS AD
                                   INNER JOIN (
                                       SELECT USERS.USR_UID, USERS.USR_ID
                                       FROM USERS
                                   ) AS USR
                                   ON (AD.USR_UID = USR.USR_UID)
                                   SET AD.USR_ID = USR.USR_ID
                                   WHERE AD.USR_ID = 0");
        $con->commit();

        // Populating APP_DELEGATION.PRO_ID
        CLI::logging("->   Populating APP_DELEGATION.PRO_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELEGATION AS AD
                                   INNER JOIN (
                                       SELECT PROCESS.PRO_UID, PROCESS.PRO_ID
                                       FROM PROCESS
                                   ) AS PRO
                                   ON (AD.PRO_UID = PRO.PRO_UID)
                                   SET AD.PRO_ID = PRO.PRO_ID
                                   WHERE AD.PRO_ID = 0");
        $con->commit();

        // Populating APP_DELEGATION.TAS_ID
        CLI::logging("->   Populating APP_DELEGATION.TAS_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELEGATION AS AD
                                   INNER JOIN (
                                       SELECT TASK.TAS_UID, TASK.TAS_ID
                                       FROM TASK
                                   ) AS TAS
                                   ON (AD.TAS_UID = TAS.TAS_UID)
                                   SET AD.TAS_ID = TAS.TAS_ID
                                   WHERE AD.TAS_ID = 0");
        $con->commit();

        // Populating APP_DELEGATION.DEL_THREAD_STATUS_ID with paused threads
        CLI::logging("->   Populating APP_DELEGATION.DEL_THREAD_STATUS_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELEGATION AS AD
                                   INNER JOIN (
                                       SELECT APP_DELAY.APP_NUMBER, APP_DELAY.APP_DEL_INDEX
                                       FROM APP_DELAY
                                       WHERE APP_TYPE = 'PAUSE' AND APP_DELAY.APP_DISABLE_ACTION_USER = '0'
                                   ) AS DELAY
                                   ON (AD.APP_NUMBER = DELAY.APP_NUMBER AND AD.DEL_INDEX = DELAY.APP_DEL_INDEX)
                                   SET AD.DEL_THREAD_STATUS_ID = 3, 
                                       AD.DEL_THREAD_STATUS = 'PAUSED'
                                   WHERE AD.DEL_THREAD_STATUS_ID = 0");
        $con->commit();

        // Populating APPLICATION.APP_STATUS_ID
        CLI::logging("->   Populating APPLICATION.APP_STATUS_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APPLICATION
                                    SET APP_STATUS_ID = (case
                                        when APP_STATUS = 'DRAFT' then 1
                                        when APP_STATUS = 'TO_DO' then 2
                                        when APP_STATUS = 'COMPLETED' then 3
                                        when APP_STATUS = 'CANCELLED' then 4
                                    end)
                                    WHERE APP_STATUS in ('DRAFT', 'TO_DO', 'COMPLETED', 'CANCELLED') AND
                                    APP_STATUS_ID = 0");
        $con->commit();

        // Populating APPLICATION.PRO_ID
        CLI::logging("->   Populating APPLICATION.PRO_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $stmt->executeQuery("UPDATE `APPLICATION` AS `AP`
                            INNER JOIN (
                                SELECT `PROCESS`.`PRO_UID`, `PROCESS`.`PRO_ID`
                                FROM `PROCESS`
                            ) AS `PRO`
                            ON (`AP`.`PRO_UID` = `PRO`.`PRO_UID`)
                            SET `AP`.`PRO_ID` = `PRO`.`PRO_ID`
                            WHERE `AP`.`PRO_ID` = 0");
        $con->commit();

        // Populating APPLICATION.APP_INIT_USER_ID
        CLI::logging("->   Populating APPLICATION.APP_INIT_USER_ID  \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APPLICATION AS AP
                                   INNER JOIN (
                                       SELECT USERS.USR_UID, USERS.USR_ID
                                       FROM USERS
                                   ) AS USR
                                   ON (AP.APP_INIT_USER = USR.USR_UID)
                                   SET AP.APP_INIT_USER_ID = USR.USR_ID
                                   WHERE AP.APP_INIT_USER_ID = 0");
        $con->commit();

        // Populating APPLICATION.APP_FINISH_DATE
        CLI::logging("->   Populating APPLICATION.APP_FINISH_DATE \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APPLICATION AS AP
                                   INNER JOIN (
                                       SELECT APP_DELEGATION.APP_NUMBER, APP_DELEGATION.DEL_FINISH_DATE
                                       FROM APP_DELEGATION
                                       ORDER BY DEL_FINISH_DATE DESC
                                   ) AS DEL
                                   ON (AP.APP_NUMBER = DEL.APP_NUMBER)
                                   SET AP.APP_FINISH_DATE = DEL.DEL_FINISH_DATE
                                   WHERE AP.APP_FINISH_DATE IS NULL AND AP.APP_STATUS_ID = 3");
        $con->commit();

        // Populating APP_DELAY.USR_ID
        CLI::logging("->   Populating APP_DELAY.USR_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELAY AS AD
                                   INNER JOIN (
                                       SELECT USERS.USR_UID, USERS.USR_ID
                                       FROM USERS
                                   ) AS USR
                                   ON (AD.APP_DELEGATION_USER = USR.USR_UID)
                                   SET AD.APP_DELEGATION_USER_ID = USR.USR_ID
                                   WHERE AD.APP_DELEGATION_USER_ID = 0");
        $con->commit();

        // Populating APP_DELAY.PRO_ID
        CLI::logging("->   Populating APP_DELAY.PRO_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELAY AS AD
                                   INNER JOIN (
                                       SELECT PROCESS.PRO_UID, PROCESS.PRO_ID
                                       FROM PROCESS
                                   ) AS PRO
                                   ON (AD.PRO_UID = PRO.PRO_UID)
                                   SET AD.PRO_ID = PRO.PRO_ID
                                   WHERE AD.PRO_ID = 0");
        $con->commit();

        // Populating APP_DELAY.APP_NUMBER
        CLI::logging("->   Populating APP_DELAY.APP_NUMBER \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_DELAY AS AD
                                   INNER JOIN (
                                       SELECT APPLICATION.APP_UID, APPLICATION.APP_NUMBER
                                       FROM APPLICATION
                                   ) AS APP
                                   ON (AD.APP_UID = APP.APP_UID)
                                   SET AD.APP_NUMBER = APP.APP_NUMBER
                                   WHERE AD.APP_NUMBER = 0");
        $con->commit();

        // Populating APP_MESSAGE.APP_NUMBER
        CLI::logging("->   Populating APP_MESSAGE.APP_NUMBER \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_MESSAGE AS AD
                                   INNER JOIN (
                                       SELECT APPLICATION.APP_UID, APPLICATION.APP_NUMBER
                                       FROM APPLICATION
                                   ) AS APP
                                   ON (AD.APP_UID = APP.APP_UID)
                                   SET AD.APP_NUMBER = APP.APP_NUMBER
                                   WHERE AD.APP_NUMBER = 0");
        $con->commit();

        // Populating APP_MESSAGE.TAS_ID
        CLI::logging("->   Populating APP_MESSAGE.TAS_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_MESSAGE AS AM
                                   INNER JOIN (
                                       SELECT APP_DELEGATION.APP_NUMBER,
                                              APP_DELEGATION.DEL_INDEX,
                                              APP_DELEGATION.TAS_ID
                                       FROM APP_DELEGATION
                                   ) AS DEL
                                   ON (AM.APP_NUMBER = DEL.APP_NUMBER AND AM.DEL_INDEX = DEL.DEL_INDEX)
                                   SET AM.TAS_ID = DEL.TAS_ID
                                   WHERE AM.TAS_ID = 0 AND AM.APP_NUMBER != 0 AND AM.DEL_INDEX != 0");
        $con->commit();

        // Populating APP_MESSAGE.PRO_ID
        CLI::logging("->   Populating APP_MESSAGE.PRO_ID\n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_MESSAGE AS AM
                                   INNER JOIN (
                                       SELECT APP_DELEGATION.APP_NUMBER,
                                              APP_DELEGATION.DEL_INDEX,
                                              APP_DELEGATION.PRO_ID
                                       FROM APP_DELEGATION
                                   ) AS DEL
                                   ON (AM.APP_NUMBER = DEL.APP_NUMBER)
                                   SET AM.PRO_ID = DEL.PRO_ID
                                   WHERE AM.PRO_ID = 0 AND AM.APP_NUMBER != 0");
        $con->commit();

        // Populating APP_MESSAGE.APP_MSG_STATUS_ID
        CLI::logging("->   Populating APP_MESSAGE.APP_MSG_STATUS_ID \n");
        $con->begin();
        $rs = $stmt->executeQuery("UPDATE APP_MESSAGE
                                    SET APP_MSG_STATUS_ID = (case
                                        when APP_MSG_STATUS = 'sent' then 1
                                        when APP_MSG_STATUS = 'pending' then 2
                                        when APP_MSG_STATUS = 'failed' then 3
                                    end)
                                    WHERE APP_MSG_STATUS in ('sent', 'pending', 'failed') AND
                                    APP_MSG_STATUS_ID = 0");
        $con->commit();

        // Populating APP_MESSAGE.APP_MSG_TYPE_ID
        CLI::logging("->   Populating APP_MESSAGE.APP_MSG_TYPE_ID \n");
        $con->begin();
        $rs = $stmt->executeQuery("UPDATE APP_MESSAGE
                                    SET APP_MSG_TYPE_ID = (case
                                        when APP_MSG_TYPE = 'TEST' then 1
                                        when APP_MSG_TYPE = 'TRIGGER' then 2
                                        when APP_MSG_TYPE = 'DERIVATION' then 3
                                        when APP_MSG_TYPE = 'EXTERNAL_REGISTRATION' then 4
                                    end)
                                    WHERE APP_MSG_TYPE in ('TEST', 'TRIGGER', 'DERIVATION', 'EXTERNAL_REGISTRATION') AND
                                    APP_MSG_TYPE_ID = 0");
        $con->commit();

        // Populating APP_NOTES.APP_NUMBER
        CLI::logging("->   Populating APP_NOTES.APP_NUMBER \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_NOTES AS AN
                                   INNER JOIN (
                                       SELECT APPLICATION.APP_UID, APPLICATION.APP_NUMBER
                                       FROM APPLICATION
                                   ) AS APP
                                   ON (AN.APP_UID = APP.APP_UID)
                                   SET AN.APP_NUMBER = APP.APP_NUMBER
                                   WHERE AN.APP_NUMBER = 0");
        $con->commit();

        // Populating TAS.TAS_TITLE with BPMN_EVENT.EVN_NAME

        // Populating PRO_ID, USR_ID IN LIST TABLES
        CLI::logging("->   Populating PRO_ID, USR_ID at LIST_* \n");
        $con->begin();
        $stmt = $con->createStatement();
        foreach (WorkspaceTools::$populateIdsQueries as $query) {
            $stmt->executeQuery($query);
        }
        $con->commit();

        // Populating APP_ASSIGN_SELF_SERVICE_VALUE.APP_NUMBER
        CLI::logging("->   Populating APP_ASSIGN_SELF_SERVICE_VALUE.APP_NUMBER \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_ASSIGN_SELF_SERVICE_VALUE AS APP_SELF
                                   INNER JOIN (
                                       SELECT APPLICATION.APP_UID, APPLICATION.APP_NUMBER
                                       FROM APPLICATION
                                   ) AS APP
                                   ON (APP_SELF.APP_UID = APP.APP_UID)
                                   SET APP_SELF.APP_NUMBER = APP.APP_NUMBER
                                   WHERE APP_SELF.APP_NUMBER = 0");
        $con->commit();

        // Populating APP_ASSIGN_SELF_SERVICE_VALUE.TAS_ID
        CLI::logging("->   Populating APP_ASSIGN_SELF_SERVICE_VALUE.TAS_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE APP_ASSIGN_SELF_SERVICE_VALUE AS APP_SELF
                                   INNER JOIN (
                                       SELECT TASK.TAS_UID, TASK.TAS_ID
                                       FROM TASK
                                   ) AS TASK
                                   ON (APP_SELF.TAS_UID = TASK.TAS_UID)
                                   SET APP_SELF.TAS_ID = TASK.TAS_ID
                                   WHERE APP_SELF.TAS_ID = 0");
        $con->commit();
        CLI::logging("-> Populating APP_ASSIGN_SELF_SERVICE_VALUE.TAS_ID  Done \n");

        // Populating PROCESS.CATEGORY_ID
        CLI::logging("->   Populating PROCESS.CATEGORY_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE PROCESS
                                   INNER JOIN (
                                       SELECT PROCESS_CATEGORY.CATEGORY_UID, PROCESS_CATEGORY.CATEGORY_ID
                                       FROM PROCESS_CATEGORY
                                   ) AS CAT
                                   ON (PROCESS.PRO_CATEGORY = CAT.CATEGORY_UID)
                                   SET PROCESS.CATEGORY_ID = CAT.CATEGORY_ID
                                   WHERE PROCESS.CATEGORY_ID = 0");
        $con->commit();
        CLI::logging("-> Populating PROCESS.CATEGORY_ID  Done \n");

        // Populating PROCESS_VARIABLES.PRO_ID
        CLI::logging("->   Populating PROCESS_VARIABLES.PRO_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE PROCESS_VARIABLES AS PV
                                   INNER JOIN (
                                       SELECT PROCESS.PRO_UID, PROCESS.PRO_ID
                                       FROM PROCESS
                                   ) AS PRO
                                   ON (PV.PRJ_UID = PRO.PRO_UID)
                                   SET PV.PRO_ID = PRO.PRO_ID
                                   WHERE PV.PRO_ID = 0");
        $con->commit();
        CLI::logging("-> Populating PROCESS_VARIABLES.PRO_ID  Done \n");

        // Populating PROCESS_VARIABLES.VAR_FIELD_TYPE_ID
        CLI::logging("->   Populating PROCESS_VARIABLES.VAR_FIELD_TYPE_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE PROCESS_VARIABLES
                                   SET VAR_FIELD_TYPE_ID = (case
                                        when VAR_FIELD_TYPE = 'string' then 1
                                        when VAR_FIELD_TYPE = 'integer' then 2
                                        when VAR_FIELD_TYPE = 'float' then 3
                                        when VAR_FIELD_TYPE = 'boolean' then 4
                                        when VAR_FIELD_TYPE = 'datetime' then 5
                                        when VAR_FIELD_TYPE = 'grid' then 6
                                        when VAR_FIELD_TYPE = 'array' then 7
                                        when VAR_FIELD_TYPE = 'file' then 8
                                        when VAR_FIELD_TYPE = 'multiplefile' then 9
                                        when VAR_FIELD_TYPE = 'object' then 10
                                    end)
                                   WHERE VAR_FIELD_TYPE_ID = 0");
        $con->commit();
        CLI::logging("-> Populating PROCESS_VARIABLES.VAR_FIELD_TYPE_ID Done \n");

        // Populating DB_SOURCE.PRO_ID
        CLI::logging("->   Populating DB_SOURCE.PRO_ID \n");
        $con->begin();
        $stmt = $con->createStatement();
        $rs = $stmt->executeQuery("UPDATE DB_SOURCE AS DS
                                   INNER JOIN (
                                       SELECT PROCESS.PRO_UID, PROCESS.PRO_ID
                                       FROM PROCESS
                                   ) AS PRO
                                   ON (DS.PRO_UID = PRO.PRO_UID)
                                   SET DS.PRO_ID = PRO.PRO_ID
                                   WHERE DS.PRO_ID = 0");
        $con->commit();
        CLI::logging("-> Populating DB_SOURCE.PRO_ID  Done \n");

        //Complete all migrations
        CLI::logging("-> Migrating And Populating Indexing for avoiding the use of table APP_CACHE_VIEW Done \n");
    }

    /**
     * It populates the WEB_ENTRY table for the classic processes, this procedure
     * is done to verify the execution of php files generated when the WebEntry
     * is configured.
     *
     * @param bool $force
     */
    public function updatingWebEntryClassicModel($force = false)
    {
        //We obtain from the configuration the list of proUids obtained so that
        //we do not go through again.
        $cfgUid = 'UPDATING_ROWS_WEB_ENTRY';
        $objUid = 'blackList';
        $blackList = [];
        $conf = new Configuration();
        $ifExists = $conf->exists($cfgUid, $objUid);
        if ($ifExists) {
            $oConfig = $conf->load($cfgUid, $objUid);
            $blackList = unserialize($oConfig['CFG_VALUE']);
        }

        //The following query returns all the classic processes that do not have
        //a record in the WEB_ENTRY table.
        $oCriteria = new Criteria("workflow");
        $oCriteria->addSelectColumn(ProcessPeer::PRO_UID);
        $oCriteria->addSelectColumn(BpmnProcessPeer::PRJ_UID);
        $oCriteria->addJoin(ProcessPeer::PRO_UID, BpmnProcessPeer::PRJ_UID, Criteria::LEFT_JOIN);
        $oCriteria->addJoin(ProcessPeer::PRO_UID, WebEntryPeer::PRO_UID, Criteria::LEFT_JOIN);
        $oCriteria->add(BpmnProcessPeer::PRJ_UID, null, Criteria::EQUAL);
        $oCriteria->add(WebEntryPeer::PRO_UID, null, Criteria::EQUAL);
        if ($force === false) {
            $oCriteria->add(ProcessPeer::PRO_UID, $blackList, Criteria::NOT_IN);
        }
        $rsCriteria = ProcessPeer::doSelectRS($oCriteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $process = new Process();
        while ($rsCriteria->next()) {
            $row = $rsCriteria->getRow();
            $proUid = $row['PRO_UID'];
            if (!in_array($proUid, $blackList)) {
                $blackList[] = $proUid;
            }
            $path = PATH_DATA . "sites" . PATH_SEP . $this->name . PATH_SEP . "public" . PATH_SEP . $proUid;
            if (is_dir($path)) {
                $dir = opendir($path);
                while ($fileName = readdir($dir)) {
                    if ($fileName !== "." && $fileName !== ".." && strpos($fileName, "wsClient.php") === false && strpos($fileName, "Post.php") === false) {
                        CLI::logging("Verifying if file: " . $fileName . " is a web entry\n");
                        $step = new Criteria("workflow");
                        $step->addSelectColumn(StepPeer::PRO_UID);
                        $step->addSelectColumn(StepPeer::TAS_UID);
                        $step->addSelectColumn(StepPeer::STEP_TYPE_OBJ);
                        $step->addSelectColumn(StepPeer::STEP_UID_OBJ);
                        $step->add(StepPeer::STEP_TYPE_OBJ, "DYNAFORM", Criteria::EQUAL);
                        $step->add(StepPeer::PRO_UID, $proUid, Criteria::EQUAL);
                        $stepRs = StepPeer::doSelectRS($step);
                        $stepRs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                        while ($stepRs->next()) {
                            $row1 = $stepRs->getRow();
                            $content = file_get_contents($path . "/" . $fileName);
                            if (strpos($content, $proUid . "/" . $row1["STEP_UID_OBJ"]) !== false) {
                                //The default user admin is set. This task is
                                //carried out by the system administrator.
                                $userUid = "00000000000000000000000000000001";
                                //save data in table WEB_ENTRY
                                $arrayData = [
                                    "PRO_UID" => $proUid,
                                    "DYN_UID" => $row1["STEP_UID_OBJ"],
                                    "TAS_UID" => $row1["TAS_UID"],
                                    "WE_DATA" => $fileName,
                                    "USR_UID" => $userUid,
                                    "WE_CREATE_USR_UID" => $userUid,
                                    "WE_UPDATE_USR_UID" => $userUid
                                ];
                                $webEntry = new \ProcessMaker\BusinessModel\WebEntry();
                                $webEntry->createClassic($arrayData);
                            }
                        }
                    }
                }
            }
        }

        //The list of proUids obtained is saved in the configuration so that it
        //does not go through again.
        $data = [
            "CFG_UID" => $cfgUid,
            "OBJ_UID" => $objUid,
            "CFG_VALUE" => serialize($blackList),
            "PRO_UID" => '',
            "USR_UID" => '',
            "APP_UID" => ''
        ];
        if ($ifExists) {
            $conf->update($data);
        } else {
            $conf->create($data);
        }
    }

    /**
     * Updating triggers
     *
     * @param bool $flagRecreate
     * @param string $lang
     */
    public function updateTriggers($flagRecreate, $lang)
    {
        $this->initPropel(true);
        $this->upgradeTriggersOfTables($flagRecreate, $lang);
    }

    /**
     * Migrate the data of the "plugin.singleton" file to the "PLUGIN_REGISTRY" table
     *
     * @param $workspace
     */
    public function migrateSingleton($workspace)
    {
        if ((!class_exists('Memcache') || !class_exists('Memcached')) && !defined('MEMCACHED_ENABLED')) {
            define('MEMCACHED_ENABLED', false);
        }
        $this->initPropel(true);
        $conf = new Configuration();
        $pathSingleton = PATH_DATA . 'sites' . PATH_SEP . $workspace . PATH_SEP . 'plugin.singleton';
        if ((!$bExist = $conf->exists('MIGRATED_PLUGIN', 'singleton')) && file_exists($pathSingleton)) {
            $oPluginRegistry = unserialize(file_get_contents($pathSingleton));
            $pluginAdapter = new PluginAdapter();
            $pluginAdapter->migrate($oPluginRegistry);
            $data["CFG_UID"] = 'MIGRATED_PLUGIN';
            $data["OBJ_UID"] = 'singleton';
            $data["CFG_VALUE"] = 'true';
            $data["PRO_UID"] = '';
            $data["USR_UID"] = '';
            $data["APP_UID"] = '';
            $conf->create($data);
        }
    }

    /**
     * This method finds all recursively PHP files that have the path PATH_DATA,
     * poorly referenced, this is caused by the import of processes where the data
     * directory of ProcessMaker has different routes. Modified files are backed
     * up with the extension '.backup' in the same directory.
     *
     * @return void
     */
    public function fixReferencePathFiles($pathClasses, $pathData)
    {
        try {
            $this->initPropel(true);
            $fixReferencePath = new FixReferencePath();
            $fixReferencePath->runProcess($pathClasses, $pathData);
            CLI::logging($fixReferencePath->getResumeDebug());
        } catch (Exception $e) {
            CLI::logging(CLI::error("Error:" . "Error updating generated class files for PM Tables, proceed to regenerate manually: " . $e));
        }
    }

    /**
     * Check/Create framework's directories
     *
     */
    public function checkFrameworkPaths()
    {
        $paths = [
            PATH_DATA . 'framework' => 0770,
            PATH_DATA . 'framework' . DIRECTORY_SEPARATOR . 'cache' => 0770,
        ];
        foreach ($paths as $path => $permission) {
            if (!file_exists($path)) {
                G::mk_dir($path, $permission);
            }
            CLI::logging("    $path [" . (file_exists($path) ? 'OK' : 'MISSING') . "]\n");
        }
    }

    /**
     * This function get the last table migrated for the labels
     * @param array $workspaceSchema , the current schema in the database
     * @return void
     */
    private function checkLastContentMigrate(array $workspaceSchema)
    {
        $listContent = $this->getListContentMigrateTable();
        $content = end($listContent);
        $lastContent = isset($content['peer']) ? $content['peer'] : null;
        if (!is_null($lastContent) && isset($workspaceSchema[$lastContent::TABLE_NAME][$content['fields'][0]])) {
            $this->setLastContentMigrateTable(true);
        }
    }

    /**
     * Remove the DYN_CONTENT_HISTORY from APP_HISTORY
     *
     * @param boolean $force
     * @param boolean $keepDynContent
     *
     * @return void
     */
    public function clearDynContentHistoryData($force = false, $keepDynContent = false)
    {
        $this->initPropel(true);
        $conf = new Configurations();
        $exist = $conf->exists('CLEAN_DYN_CONTENT_HISTORY', 'history');

        if ($force === false && $exist === true) {
            $config = (object)$conf->load('CLEAN_DYN_CONTENT_HISTORY', 'history');
            if ($config->updated) {
                CLI::logging("-> This was previously updated.\n");

                return;
            }
        }
        if ($force === false && $keepDynContent) {
            CLI::logging("-> Keep DYN_CONTENT_HISTORY.\n");

            return;
        }
        //We will to proceed to clean DYN_CONTENT_HISTORY
        $query = "UPDATE APP_HISTORY SET HISTORY_DATA = IF(LOCATE('DYN_CONTENT_HISTORY',HISTORY_DATA)>0, CONCAT( "
            . "    SUBSTRING_INDEX(HISTORY_DATA, ':', 1), "
            . "    ':', "
            . "    CAST( "
            . "        SUBSTRING( "
            . "            SUBSTRING_INDEX(HISTORY_DATA, ':{', 1), "
            . "             LOCATE(':', HISTORY_DATA)+1 "
            . "        ) AS SIGNED "
            . "    )-1, "
            . "    SUBSTRING( "
            . "        CONCAT( "
            . "            SUBSTRING_INDEX(HISTORY_DATA, 's:19:\"DYN_CONTENT_HISTORY\";s:', 1), "
            . "            SUBSTRING( "
            . "                SUBSTRING( "
            . "                    HISTORY_DATA, "
            . "                    LOCATE('s:19:\"DYN_CONTENT_HISTORY\";s:', HISTORY_DATA)+29 "
            . "                ), "
            . "                LOCATE( "
            . "                    '\";', "
            . "                    SUBSTRING( "
            . "                        HISTORY_DATA, "
            . "                        LOCATE('s:19:\"DYN_CONTENT_HISTORY\";s:', HISTORY_DATA)+29 "
            . "                    ) "
            . "                )+2 "
            . "            ) "
            . "        ), "
            . "        LOCATE(':{', HISTORY_DATA) "
            . "    ) "
            . "),   HISTORY_DATA)";

        $con = Propel::getConnection("workflow");
        $stmt = $con->createStatement();
        $stmt->executeQuery($query);
        CLI::logging("-> Table fixed for " . $this->dbName . ".APP_HISTORY\n");
        $stmt = $con->createStatement();

        $conf->aConfig = ['updated' => true];
        $conf->saveConfig('CLEAN_DYN_CONTENT_HISTORY', 'history');

    }


    /**
     * Upgrade APP_ASSIGN_SELF_SERVICE_VALUE_GROUP and GROUP_USER tables.
     * Before only the identification value of 32 characters was used, now the 
     * numerical value plus the type is used, 1 for the user and 2 for the group, 
     * if it is not found, it is updated with -1.
     * 
     * @param object $con
     * 
     * @return void
     */
    public function upgradeSelfServiceData($con = null)
    {
        if ($con === null) {
            $this->initPropel(true);
            $con = Propel::getConnection(AppDelegationPeer::DATABASE_NAME);
        }

        CLI::logging("->    Update table GROUP_USER\n");
        $con->begin();
        $stmt = $con->createStatement();
        $stmt->executeQuery(""
            . "UPDATE GROUPWF AS GW "
            . "INNER JOIN GROUP_USER AS GU ON "
            . "    GW.GRP_UID=GU.GRP_UID "
            . "SET GU.GRP_ID=GW.GRP_ID "
            . "WHERE GU.GRP_ID = 0");
        $con->commit();

        CLI::logging("->    Update table APP_ASSIGN_SELF_SERVICE_VALUE_GROUP\n");
        $con->begin();
        $stmt = $con->createStatement();
        $stmt->executeQuery(""
            . "UPDATE GROUPWF AS GW "
            . "INNER JOIN APP_ASSIGN_SELF_SERVICE_VALUE_GROUP AS GU ON "
            . "    GW.GRP_UID=GU.GRP_UID "
            . "SET "
            . "GU.ASSIGNEE_ID=GW.GRP_ID, "
            . "GU.ASSIGNEE_TYPE=2 "
            . "WHERE GU.ASSIGNEE_ID = 0");
        $con->commit();

        $con->begin();
        $stmt = $con->createStatement();
        $stmt->executeQuery(""
            . "UPDATE USERS AS U "
            . "INNER JOIN APP_ASSIGN_SELF_SERVICE_VALUE_GROUP AS GU ON "
            . "    U.USR_UID=GU.GRP_UID "
            . "SET "
            . "GU.ASSIGNEE_ID=U.USR_ID, "
            . "GU.ASSIGNEE_TYPE=1 "
            . "WHERE GU.ASSIGNEE_ID = 0");
        $con->commit();

        $con->begin();
        $stmt = $con->createStatement();
        $stmt->executeQuery(""
            . "UPDATE APP_ASSIGN_SELF_SERVICE_VALUE_GROUP "
            . "SET "
            . "ASSIGNEE_ID=-1, "
            . "ASSIGNEE_TYPE=-1 "
            . "WHERE ASSIGNEE_ID = 0");
        $con->commit();

    }

    /**
     * Remove deprecated files and directory.
     */
    public function removeDeprecatedFiles()
    {
        $deprecatedFiles = PATH_TRUNK . PATH_SEP . 'config' . PATH_SEP . 'deprecatedFiles.lst';
        if (file_exists($deprecatedFiles)) {
            $handle = fopen($deprecatedFiles, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line, "\n");
                    CLI::logging("> Remove file/folder " . $line . " ");
                    if (file_exists($line)) {
                        G::rm_dir($line);
                        CLI::logging("[OK]\n");
                    } else {
                        CLI::logging("[Already removed]\n");
                    }
                }
                fclose($handle);
            }
        }
    }

    /**
     * Sync JSON definition of the Forms with Input Documents information
     */
    public function syncFormsWithInputDocumentInfo()
    {
        // Initialize Propel and instance the required classes
        $this->initPropel(true);
        $processInstance = new Process();
        $bmProcessInstance = new BmProcess();
        $pmDynaform = new PmDynaform();

        // Get all active processes
        $processes = $processInstance->getAllProcesses(0, '');
        foreach ($processes as $process) {
            // Get all Input Documents from a process
            $inputDocuments = $bmProcessInstance->getInputDocuments($process['PRO_UID']);
            foreach ($inputDocuments as $inputDocument) {
                // Sync JSON definition of the Forms
                $pmDynaform->synchronizeInputDocument($process['PRO_UID'], $inputDocument);
            }
        }
    }

    /**
     * Delete the triggers MySQL that causes performance issues in the upgrade process
     */
    public function deleteTriggersMySql($triggersToDelete)
    {
        // Initialize Propel
        $this->initPropel(true);
        $con = Propel::getConnection('workflow');

        // Get statement instance
        $stmt = $con->createStatement();

        // Get MySQL DB instance class
        $dbInstance = $this->getDatabase();

        // Remove triggers MySQL
        foreach ($triggersToDelete as $triggerName) {
            $stmt->executeQuery($dbInstance->getDropTrigger($triggerName));
        }
    }

    /**
     * Delete indexes of specific tables
     *
     * @param array $tables
     */
    public function deleteIndexes($tables)
    {
        // Get MySQL DB instance class
        $database = $this->getDatabase();

        foreach ($tables as $table) {
            // Get all indexes of the table
            $indexes = $database->executeQuery($database->generateTableIndexSQL($table));
            $indexesDeleted = [];
            foreach ($indexes as $index) {
                if ($index['Key_name'] != 'PRIMARY') {
                    if (!in_array($index['Key_name'], $indexesDeleted)) {
                        // Remove index
                        $database->executeQuery($database->generateDropKeySQL($table, $index['Key_name']));
                        $indexesDeleted[] = $index['Key_name'];
                    }
                }
            }
        }
    }

    /**
     * Execute a query, used internally for the upgrade process
     *
     * @param string $query
     * @param bool $rbac
     */
    public function upgradeQuery($query, $rbac)
    {
        $database = $this->getDatabase($rbac);
        $database->executeQuery($query, true);
    }
    
    /**
     * This method regenerates data report with the APP_DATA data.
     * @param string $tableName
     * @param string $type
     * @param string $processUid
     * @param string $gridKey
     * @param string $addTabUid
     * @param string $className
     * @param string $pathWorkspace
     * @param int $start
     * @param int $limit
     * @throws Exception
     */
    public function generateDataReport(
        $tableName,
        $type = 'NORMAL',
        $processUid = '',
        $gridKey = '',
        $addTabUid = '',
        int $start = 0,
        int $limit = 10
    ) {
        // Initialize DB connections
        $this->initPropel();

        // Get fields and some specific field types
        $fields = [];
        $fieldsWithTypes = [];
        $fieldTypes = [];
        if ($addTabUid != '') {
            $fieldsAux = Fields::where('ADD_TAB_UID', '=', $addTabUid)->get();
            foreach ($fieldsAux as $field) {
                $fields[] = $field->FLD_NAME;
                $fieldsWithTypes[$field->FLD_NAME] = strtoupper($field->FLD_TYPE);
                switch ($field->FLD_TYPE) {
                    case 'FLOAT':
                    case 'DOUBLE':
                    case 'INTEGER':
                        $fieldTypes[] = [$field->FLD_NAME => $field->FLD_TYPE];
                        break;
                    default:
                        break;
                }
            }
        }

        // Initialize variables
        $context = Bootstrap::context();
        $case = new Cases();

        // Select cases of the related process, ordered by APP_NUMBER
        $applications = Application::query()
            ->where('PRO_UID', '=', $processUid)
            ->where('APP_NUMBER', '>', 0)
            ->orderBy('APP_NUMBER', 'asc')
            ->offset($start)
            ->limit($limit)
            ->get();

        // Process applications selected
        foreach ($applications as $application) {
            // Get case data
            $appData = $case->unserializeData($application->APP_DATA);

            // Quick fix, map all empty values as NULL for Database
            foreach ($appData as $appDataKey => $appDataValue) {
                if (is_array($appDataValue) && count($appDataValue)) {
                    $j = key($appDataValue);
                    $appDataValue = is_array($appDataValue[$j]) ? $appDataValue : $appDataValue[$j];
                }
                if (is_string($appDataValue)) {
                    foreach ($fieldTypes as $key => $fieldType) {
                        foreach ($fieldType as $fieldTypeKey => $fieldTypeValue) {
                            if (strtoupper($appDataKey) == $fieldTypeKey) {
                                $appDataValue = validateType($appDataValue, $fieldTypeValue);
                                unset($fieldTypeKey);
                            }
                        }
                    }
                    // Normal fields
                    $appData[$appDataKey] = $appDataValue === '' ? null : $appDataValue;
                } else {
                    // Grids
                    if (is_array($appData[$appDataKey])) {
                        foreach ($appData[$appDataKey] as $dIndex => $dRow) {
                            if (is_array($dRow)) {
                                foreach ($dRow as $k => $v) {
                                    if (is_string($v) && trim($v) === '') {
                                        $appData[$appDataKey][$dIndex][$k] = null;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Populate data
            if ($type === 'GRID') {
                list($gridName, $gridUid) = explode('-', $gridKey);
                $gridData = isset($appData[$gridName]) ? $appData[$gridName] : [];
                foreach ($gridData as $i => $gridRow) {
                    try {
                        // Change keys to uppercase
                        $gridRow = array_change_key_case($gridRow, CASE_UPPER);

                        // Completing some required values in row data
                        $gridRow['APP_UID'] = $application->APP_UID;
                        $gridRow['APP_NUMBER'] = $application->APP_NUMBER;
                        $gridRow['APP_STATUS'] = $application->APP_STATUS;
                        $gridRow['ROW'] = $i;

                        // Build sections of the query
                        $fieldsSection = $this->buildFieldsSection($fields);
                        $valuesSection = $this->buildValuesSection($fieldsWithTypes, $gridRow);

                        // Build insert query
                        $query = "INSERT INTO `$tableName` ($fieldsSection) VALUES ($valuesSection);";

                        // Execute the query
                        DB::connection()->statement(DB::raw($query));
                    } catch (Exception $e) {
                        $context["message"] = $e->getMessage();
                        $context["tableName"] = $tableName;
                        $context["appUid"] = $application->APP_UID;
                        $message = 'Sql Execution';
                        Log::channel(':sqlExecution')->critical($message, Bootstrap::context($context));
                    }
                }
            } else {
                try {
                    // Change keys to uppercase
                    $appData = array_change_key_case($appData, CASE_UPPER);

                    // Completing some required values in case data
                    $appData['APP_UID'] = $application->APP_UID;
                    $appData['APP_NUMBER'] = $application->APP_NUMBER;
                    $appData['APP_STATUS'] = $application->APP_STATUS;

                    // Build sections of the query
                    $fieldsSection = $this->buildFieldsSection($fields);
                    $valuesSection = $this->buildValuesSection($fieldsWithTypes, $appData);

                    // Build insert query
                    $query = "INSERT INTO `$tableName` ($fieldsSection) VALUES ($valuesSection);";

                    // Execute the query
                    DB::connection()->statement(DB::raw($query));
                } catch (Exception $e) {
                    $context["message"] = $e->getMessage();
                    $context["tableName"] = $tableName;
                    $context["appUid"] = $application->APP_UID;
                    $message = 'Sql Execution';
                    Log::channel(':sqlExecution')->critical($message, Bootstrap::context($context));
                }
            }
        }
    }

    /**
     * This method populates the table with a query string.
     * @param string $query
     * @param boolean $rbac
     */
    public function populateTableReport($query, $rbac)
    {
        $database = $this->getDatabase($rbac);
        $database->executeQuery($query, true);
    }

    /**
     * Add +async option to scheduler commands in table SCHEDULER.
     * @param boolean $force
     */
    public function addAsyncOptionToSchedulerCommands($force = false)
    {
        //read update status
        $this->initPropel(true);
        $conf = new Configurations();
        $exist = $conf->exists('ADDED_ASYNC_OPTION_TO_SCHEDULER', 'scheduler');
        if ($exist === true && $force === false) {
            $config = (object) $conf->load('ADDED_ASYNC_OPTION_TO_SCHEDULER', 'scheduler');
            if ($config->updated) {
                CLI::logging("-> This was previously updated.\n");
                return;
            }
        }

        //update process
        $updateQuery = ""
            . "UPDATE {$this->dbName}.SCHEDULER SET body = REPLACE(body, '+force\"', '+force +async\"') "
            . "WHERE body NOT LIKE '%+async%'"
            . "";
        $con = Propel::getConnection("workflow");
        $stmt = $con->createStatement();
        $stmt->executeQuery($updateQuery);
        CLI::logging("-> Adding +async option to scheduler commands in table {$this->dbName}.SCHEDULER\n");

        //save update status
        $conf->aConfig = ['updated' => true];
        $conf->saveConfig('ADDED_ASYNC_OPTION_TO_SCHEDULER', 'scheduler');
    }

    /**
     * Populate the column APP_DELEGATION.DEL_TITLE with the case title APPLICATION.APP_TITLE
     * @param array $args
     */
    public function migrateCaseTitleToThreads($args)
    {
        // Define the main query
        $query = "
            UPDATE
                `APP_DELEGATION`
            LEFT JOIN
                `APPLICATION` ON `APP_DELEGATION`.`APP_NUMBER` = `APPLICATION`.`APP_NUMBER`
            SET
                `APP_DELEGATION`.`DEL_TITLE` = `APPLICATION`.`APP_TITLE`
            WHERE
                (`APPLICATION`.`APP_STATUS_ID` IN (2) OR
                (`APPLICATION`.`APP_STATUS_ID` IN (3, 4) AND
                `APP_DELEGATION`.`DEL_LAST_INDEX` = 1))";

        // Add additional filters
        if (!empty($args[1]) && is_numeric($args[1])) {
            $query .= " AND `APP_DELEGATION`.`APP_NUMBER` >= {$args[1]}";
        }
        if (!empty($args[2]) && is_numeric($args[2])) {
            $query .= " AND `APP_DELEGATION`.`APP_NUMBER` <= {$args[2]}";
        }
        try {
            if (!empty($args)) {
                // Set workspace constants and initialize DB connection
                Bootstrap::setConstantsRelatedWs($args[0]);
                Propel::init(PATH_CONFIG . 'databases.php');

                // Execute the query
                $statement = Propel::getConnection('workflow')->createStatement();
                $statement->executeQuery($query);

                CLI::logging("The Case Title has been updated successfully in APP_DELEGATION table." . PHP_EOL);
            } else {
                CLI::logging("The workspace is required." . PHP_EOL . PHP_EOL);
            }
        } catch (Exception $e) {
            // Display the error message
            CLI::logging($e->getMessage() . PHP_EOL . PHP_EOL);
        }
    }

    /**
     * Convert Output Documents generator from 'HTML2PDF' to 'TCPDF', because thirdparty related is obsolete and doesn't work over PHP 7.x
     * @param array $args
     */
    public function convertOutDocsHtml2Ps2Pdf(array $args)
    {
        // Define query
        $query = "
            UPDATE
                `OUTPUT_DOCUMENT`
            SET
                `OUT_DOC_REPORT_GENERATOR` = 'TCPDF'
            WHERE
                `OUT_DOC_REPORT_GENERATOR` = 'HTML2PDF'
            ";

        try {
            // Set workspace constants and initialize DB connection
            Bootstrap::setConstantsRelatedWs($args[0]);
            Propel::init(PATH_CONFIG . 'databases.php');

            // Execute the query
            $statement = Propel::getConnection('workflow')->createStatement();
            $statement->executeQuery($query);

            CLI::logging("The report generator was updated to 'TCPDF' in OUTPUT_DOCUMENT table." . PHP_EOL);
        } catch (Exception $e) {
            // Display the error message
            CLI::logging($e->getMessage() . PHP_EOL . PHP_EOL);
        }
    }

    /**
     * Build the fields section for the insert query
     *
     * @param array $fields
     * @return string
     */
    private function buildFieldsSection($fields)
    {
        // Transform records to single array
        $fields = array_map(function($field) {
            return "`{$field}`";
        }, $fields);

        return implode(', ', $fields);
    }

    /**
     * Build values section for the insert query
     *
     * @param array $fieldsWithTypes
     * @param array $caseData
     * @return string
     */
    private function buildValuesSection($fieldsWithTypes, $caseData)
    {
        // Initialize variables
        $values = [];

        // Sanitize each value in case data according to the field type
        foreach ($fieldsWithTypes as $fieldName => $fieldType) {
            // Get the value
            $fieldName = strtoupper($fieldName);
            $value = isset($caseData[$fieldName]) ? $caseData[$fieldName] : null;

            // Sanitize data
            switch ($fieldType) {
                case 'BIGINT':
                case 'INTEGER':
                case 'SMALLINT':
                case 'TINYINT':
                case 'BOOLEAN':
                    $values[] = (int)$value;
                    break;
                case 'DECIMAL':
                case 'DOUBLE':
                case 'FLOAT':
                case 'REAL':
                    $values[] = (float)$value;
                    break;
                case 'DATE':
                case 'DATETIME':
                case 'TIME':
                    if (strtotime($value) === false) {
                        $value = '0000-00-00 00:00:00';
                    }
                    $values[] = "'{$value}'";
                    break;
                default: // char, mediumtext, varchar or another type
                    // Escape the strings
                    $values[] = DB::connection()->getPdo()->quote($value);
                    break;
            }
        }

        // Convert to string separated by commas
        return implode(', ', $values);
    }
}
