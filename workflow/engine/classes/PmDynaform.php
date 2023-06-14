<?php

use Illuminate\Support\Facades\Log;
use PhpMyAdmin\SqlParser\Parser;
use ProcessMaker\Core\System;
use ProcessMaker\BusinessModel\DynaForm\SuggestTrait;
use ProcessMaker\BusinessModel\Cases;
use ProcessMaker\BusinessModel\DynaForm\ValidatorFactory;
use ProcessMaker\Model\Dynaform as ModelDynaform;

/**
 * Implementing pmDynaform library in the running case.
 *
 * @package engine.classes
 */
class PmDynaform
{
    use SuggestTrait;

    private $cache = [];
    private $context = [];
    private $databaseProviders = null;
    private $dataSources = null;
    private $lastQueryError = null;
    private $propertiesToExclude = [];
    private $sysSys = null;
    private $fieldsAppData;
    public $credentials = null;
    public $displayMode = null;
    public $fields = null;
    public $isRTL = false;
    public $lang = SYS_LANG;
    public $translations = null;
    public $onPropertyRead = "onPropertyReadFormInstance";
    public $onAfterPropertyRead = "onAfterPropertyReadFormInstance";
    public $pathRTLCss = '';
    public $record = null;
    public $records = null;
    public $serverConf = null;
    public static $instance = null;
    public static $prefixs = ["@@", "@#", "@%", "@?", "@$", "@="];

    /**
     * Constructor
     * 
     * @param array $fields
     * @see workflow/engine/classes/class.pmFunctions.php PMFDynaFormFields()
     * @see workflow/engine/classes/class.pmFunctions.php PMFgetLabelOption()
     * @see \ConsolidatedCases->processConsolidated()
     * @see \WorkspaceTools->syncFormsWithInputDocumentInfo()
     * @see workflow/engine/methods/cases/ajaxListener.php Ajax->dynaformViewFromHistory()
     * @see workflow/engine/methods/cases/caseConsolidated.php 
     * @see workflow/engine/methods/cases/cases_SaveData.php
     * @see workflow/engine/methods/cases/cases_Step.php
     * @see workflow/engine/methods/cases/cases_StepToRevise.php
     * @see workflow/engine/methods/cases/casesHistoryDynaformPage_Ajax.php 
     * @see workflow/engine/methods/cases/pmDynaform.php
     * @see workflow/engine/methods/cases/summary.php
     * @see workflow/engine/methods/services/ActionsByEmailDataForm.php
     * @see workflow/engine/plugins/EnterpriseSearch/display_dynaform.php
     * @see workflow/engine/plugins/EnterpriseSearch/dynaform_view1.php
     * @see \ProcessMaker\BusinessModel\ActionsByEmail->viewFormBpmn()
     * @see \ProcessMaker\BusinessModel\Cases->getCaseVariables()
     * @see \ProcessMaker\BusinessModel\Consolidated->getDataGenerate()
     * @see \ProcessMaker\BusinessModel\InputDocument->update()
     * @see \ProcessMaker\BusinessModel\Light\Tracker->showObjects()
     * @see \ProcessMaker\BusinessModel\Variable->delete()
     * @see \ProcessMaker\BusinessModel\Variable->executeSqlControl()
     * @see \ProcessMaker\BusinessModel\Variable->update()
     * @see \ProcessMaker\Core\System\ActionsByEmailCoreClass->sendActionsByEmail()
     * @see \ProcessMaker\Services\Api\Light->doGetDynaForm()
     * @see \ProcessMaker\Services\Api\Light->doGetDynaformProcessed()
     * @see \ProcessMaker\Services\Api\Light->doGetDynaForms()
     * @see \ProcessMaker\Services\Api\Light->doGetDynaFormsId()
     * @see \ProcessMaker\Services\Api\Project\DynaForm->doDeleteDynaFormLanguage()
     * @see \ProcessMaker\Services\Api\Project\DynaForm->doGetDynaFormLanguage()
     * @see \ProcessMaker\Services\Api\Project\DynaForm->doGetListDynaFormLanguage()
     * @see \ProcessMaker\Services\Api\Project\DynaForm->doPostDynaFormLanguage()
     */
    public function __construct($fields = [])
    {
        $this->sysSys = (!empty(config("system.workspace"))) ? config("system.workspace") : "Undefined";
        $this->context = Bootstrap::context();
        $this->dataSources = array("database", "dataVariable");
        $this->pathRTLCss = '/lib/pmdynaform/build/css/PMDynaform-rtl.css';
        $this->serverConf = ServerConf::getSingleton();
        $this->isRTL = ($this->serverConf->isRtl(SYS_LANG)) ? 'true' : 'false';
        $this->fields = $fields;
        $this->propertiesToExclude = array('dataVariable');
        $this->getDynaform();
        $this->getDynaforms();
        $this->synchronizeSubDynaform();
        $this->getCredentials();
        if (is_array($this->fields) && !isset($this->fields["APP_UID"])) {
            $this->fields["APP_UID"] = null;
        }
        $this->fieldsAppData = isset($this->fields["APP_DATA"]) ? $this->fields["APP_DATA"] : [];

        //todo: compatibility checkbox
        if ($this->record !== null && isset($this->record["DYN_CONTENT"]) && $this->record["DYN_CONTENT"] !== "") {
            $json = G::json_decode($this->record["DYN_CONTENT"]);
            $fields = $this->jsonsf2($json, "checkbox", "type");
            foreach ($fields as $field) {
                if (isset($field->dataType) && $field->dataType === "string") {
                    $field->type = "checkgroup";
                    $field->dataType = "array";
                }
                $this->jsonReplace($json, $field->id, "id", $field);
            }
            $this->record["DYN_CONTENT"] = G::json_encode($json);

            //to do, this line should be removed. Related to PMC-196.
            $this->record['DYN_CONTENT'] = G::fixStringCorrupted($this->record['DYN_CONTENT']);
        }
    }

    /**
     * Get the translation defined in the dynaform
     *
     * @return object
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * Set the translations defined in the dynaform
     *
     * @param string $dynUid
     *
     * @return void
     */
    public function setTranslations($dynUid)
    {
        $dynaForm = ModelDynaform::getByDynUid($dynUid);
        $this->translations = empty($dynaForm->DYN_LABEL) ? null : G::json_decode($dynaForm->DYN_LABEL);
    }

    /**
     * Get the labels from a specific language defined in the dynaform, if does not exist will return null
     *
     * @param string $language
     *
     * @return object|null
     */
    public function getLabelsPo($language)
    {
        $labelsPo = null;
        if (!is_null($this->translations) && !empty($this->translations->{$language}->{'Labels'})) {
            $labelsPo = $this->translations->{$language}->{'Labels'};
        }

        return $labelsPo;
    }

    /**
     * Get the title of a Dynaform
     *
     * @param string $dynUid
     * @return string
     */
    public function getDynaformTitle($dynUid)
    {
        $dynaform = ModelDynaform::getByDynUid($dynUid);
        return $dynaform->DYN_TITLE;
    }

    /**
     * Get a dynaform.
     *
     * @return array|null
     *
     * @see workflow/engine/methods/cases/caseConsolidated.php
     * @see ConsolidatedCases::processConsolidated()
     * @see PmDynaform::__construct()
     * @see \ProcessMaker\BusinessModel\Cases::getCaseVariables()
     */
    public function getDynaform()
    {
        if (!isset($this->fields["CURRENT_DYNAFORM"])) {
            return;
        }
        if ($this->record != null) {
            return $this->record;
        }
        $dynaform = ModelDynaform::getByDynUid($this->fields["CURRENT_DYNAFORM"]);
        if (empty($dynaform)) {
            $this->translations = null;
            return null;
        }
        $this->translations = empty($dynaform->DYN_LABEL) ? null : G::json_decode($dynaform->DYN_LABEL);
        $this->record = (array) $dynaform;
        return $this->record;
    }

    /**
     * Get all dynaforms except this dynaform, related to process.
     * @return array
     * @see PmDynaform->__construct()
     */
    public function getDynaforms()
    {
        if ($this->record === null) {
            return;
        }
        if ($this->records != null) {
            return $this->records;
        }
        $result = ModelDynaform::getByProUidExceptDynUid($this->record["PRO_UID"], $this->record["DYN_UID"]);
        $result->transform(function($item) {
            return (array) $item;
        });
        $this->records = $result->toArray();
        return $this->records;
    }

    public function getCredentials()
    {
        $flagTrackerUser = false;

        if (!isset($_SESSION['USER_LOGGED'])) {
            if (!preg_match("/^.*\/" . SYS_SKIN . "\/tracker\/.*$/", $_SERVER["REQUEST_URI"]) &&
                !preg_match("/^.*\/" . SYS_SKIN . "\/[a-z0-9A-Z]+\/[a-z0-9A-Z]+\.php$/", $_SERVER["REQUEST_URI"]) &&
                !preg_match("/^.*\/" . SYS_SKIN . "\/services\/ActionsByEmailDataForm.*$/", $_SERVER["REQUEST_URI"])
            ) {
                return;
            }

            $_SESSION["USER_LOGGED"] = "00000000000000000000000000000001";
            $flagTrackerUser = true;
        }
        if ($this->credentials != null) {
            // Destroy variable "USER_LOGGED" in session if is a not authenticated user
            if ($flagTrackerUser) {
                unset($_SESSION["USER_LOGGED"]);
            }
            return $this->credentials;
        }
        if (isset($_SESSION["PMDYNAFORM_CREDENTIALS"]) && isset($_SESSION["PMDYNAFORM_CREDENTIALS_EXPIRES"])) {
            $time1 = strtotime(date('Y-m-d H:i:s'));
            $time2 = strtotime($_SESSION["PMDYNAFORM_CREDENTIALS_EXPIRES"]);
            if ($time1 < $time2) {
                $this->credentials = $_SESSION["PMDYNAFORM_CREDENTIALS"];

                // Destroy variable "USER_LOGGED" in session if is a not authenticated user
                if ($flagTrackerUser) {
                    unset($_SESSION["USER_LOGGED"]);
                }

                return $this->credentials;
            }
        }
        $a = $this->clientToken();
        $this->credentials = array(
            "accessToken" => $a["access_token"],
            "expiresIn" => $a["expires_in"],
            "tokenType" => $a["token_type"],
            "scope" => $a["scope"],
            "refreshToken" => $a["refresh_token"],
            "clientId" => $a["client_id"],
            "clientSecret" => $a["client_secret"]
        );

        // Destroy variable "USER_LOGGED" in session if is a not authenticated user
        if ($flagTrackerUser) {
            unset($_SESSION["USER_LOGGED"]);
        }

        $expires = date("Y-m-d H:i:s") . " +" . $this->credentials["expiresIn"] . " seconds";
        $_SESSION["PMDYNAFORM_CREDENTIALS"] = $this->credentials;
        $_SESSION["PMDYNAFORM_CREDENTIALS_EXPIRES"] = date("Y-m-d H:i:s", strtotime($expires));
        return $this->credentials;
    }

    public function jsonr(&$json, $clearCache = true)
    {
        if ($clearCache === true) {
            $this->cache = [];
        }
        if (empty($json)) {
            return;
        }
        $dataGridEnvironment = [];
        foreach ($json as $key => &$value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->jsonr($value, false);
            }
            if (!$sw1 && !$sw2) {
                //read event
                $fn = $this->onPropertyRead;
                if (is_callable($fn) || function_exists($fn)) {
                    $fn($json, $key, $value);
                }
                //set properties from trigger
                if (is_string($value) && in_array(substr($value, 0, 2), self::$prefixs)) {
                    $triggerValue = substr($value, 2);
                    if (isset($this->fields["APP_DATA"][$triggerValue])) {
                        if (!in_array($key, $this->propertiesToExclude)) {
                            $json->{$key} = $this->fields["APP_DATA"][$triggerValue];
                        }
                    } else {
                        if (!in_array($key, $this->propertiesToExclude)) {
                            $json->{$key} = "";
                        }
                    }
                }
                //set properties from 'formInstance' variable
                if (isset($this->fields["APP_DATA"]["formInstance"])) {
                    $formInstance = $this->fields["APP_DATA"]["formInstance"];
                    if (!is_array($formInstance)) {
                        $formInstance = array($formInstance);
                    }
                    $nfi = count($formInstance);
                    for ($ifi = 0; $ifi < $nfi; $ifi++) {
                        $fi = $formInstance[$ifi];
                        if (is_object($fi) && isset($fi->id) && $key === "id" && $json->{$key} === $fi->id) {
                            foreach ($fi as $keyfi => $valuefi) {
                                if (isset($json->{$keyfi})) {
                                    $json->{$keyfi} = $valuefi;
                                }
                            }
                        }
                    }
                }
                //options & query options
                if ($key === "type" && ($value === "text" || $value === "textarea" || $value === "hidden" || $value === "dropdown" || $value === "checkgroup" || $value === "radio" || $value === "suggest")) {
                    if (!isset($json->dbConnection)) {
                        $json->dbConnection = "none";
                    }
                    if (!isset($json->sql)) {
                        $json->sql = "";
                    }
                    if (!isset($json->datasource)) {
                        $json->datasource = "database";
                    }
                    if (!in_array($json->datasource, $this->dataSources)) {
                        $json->datasource = "database";
                    }

                    $json->optionsSql = array();

                    if ($json->datasource === "database" && $json->dbConnection !== "" && $json->dbConnection !== "none" && $json->sql !== "") {
                        if (isset($json->queryField)) {
                            $dtFields = $json->queryInputData;
                        } else {
                            $dtFields = $this->getValuesDependentFields($json);
                            foreach ($dtFields as $keyF => $valueF) {
                                if (isset($this->fields["APP_DATA"][$keyF])) {
                                    $dtFields[$keyF] = $this->fields["APP_DATA"][$keyF];
                                }
                            }
                        }
                        $sql = $this->replaceDataField($json->sql, $dtFields);
                        if ($value === "suggest") {
                            $sql = $this->prepareSuggestSql($sql, $json);
                        }
                        $dt = $this->getCacheQueryData($json->dbConnection, $sql, $json->type);
                        foreach ($dt as $row) {
                            $option = new stdClass();
                            $option->value = isset($row[0]) ? $row[0] : "";
                            $option->label = isset($row[1]) ? $row[1] : "";
                            $json->optionsSql[] = $option;
                        }
                        if (isset($json->queryField)) {
                            $json->queryOutputData = $json->optionsSql;
                        }
                    }

                    if ($json->datasource === "dataVariable") {
                        $dataVariable = preg_match('/^\s*@.(.+)\s*$/', $json->dataVariable, $arrayMatch) ? $arrayMatch[1] : $json->dataVariable;
                        if (isset($this->fields['APP_DATA'][$dataVariable]) && is_array($this->fields['APP_DATA'][$dataVariable])) {
                            foreach ($this->fields['APP_DATA'][$dataVariable] as $row) {
                                $option = new stdClass();
                                $option->value = isset($row[0]) ? $row[0] : "";
                                $option->label = isset($row[1]) ? $row[1] : "";
                                $json->optionsSql[] = $option;
                                $json->queryOutputData[] = $option;
                            }
                        }
                        if ($value === "suggest" && isset($json->queryField) && $json->queryField == true) {
                            $this->searchResultInDataSource($json);
                        }
                    }
                }
                //data
                if ($key === "type" && ($value === "text" || $value === "textarea" || $value === "hidden")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "";
                    if (isset($json->optionsSql[0])) {
                        $json->data->value = $json->optionsSql[0]->value;
                        $json->data->label = $json->optionsSql[0]->value;
                    }
                    if ($json->defaultValue !== "") {
                        $json->data->value = $json->defaultValue;
                        $json->data->label = $json->defaultValue;
                    }
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                        $json->data->label = $this->fields["APP_DATA"][$json->name];
                    }
                }
                if ($key === "type" && ($value === "dropdown")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "";
                    if ($json->defaultValue !== "") {
                        foreach ($json->optionsSql as $os) {
                            if ($os->value === $json->defaultValue) {
                                $json->data->value = $os->value;
                                $json->data->label = $os->label;
                            }
                        }
                        foreach ($json->options as $os) {
                            if ($os->value === $json->defaultValue) {
                                $json->data->value = $os->value;
                                $json->data->label = $os->label;
                            }
                        }
                    }
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name . "_label"])) {
                        $json->data->label = $this->fields["APP_DATA"][$json->name . "_label"];
                    }
                }
                if ($key === "type" && ($value === "suggest")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "";
                    if ($json->defaultValue !== "") {
                        $json->data->value = $json->defaultValue;
                        $json->data->label = $json->defaultValue;
                        foreach ($json->optionsSql as $os) {
                            if ($os->value === $json->defaultValue) {
                                $json->data->value = $os->value;
                                $json->data->label = $os->label;
                            }
                        }
                        foreach ($json->options as $os) {
                            if ($os->value === $json->defaultValue) {
                                $json->data->value = $os->value;
                                $json->data->label = $os->label;
                            }
                        }
                    }
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name . "_label"])) {
                        $json->data->label = $this->fields["APP_DATA"][$json->name . "_label"];
                    }
                }
                if ($key === "type" && ($value === "radio")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "";
                    if ($json->defaultValue !== "") {
                        foreach ($json->optionsSql as $os) {
                            if ($os->value === $json->defaultValue) {
                                $json->data->value = $os->value;
                                $json->data->label = $os->label;
                            }
                        }
                        foreach ($json->options as $os) {
                            if ($os->value === $json->defaultValue) {
                                $json->data->value = $os->value;
                                $json->data->label = $os->label;
                            }
                        }
                    }
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name . "_label"])) {
                        $json->data->label = $this->fields["APP_DATA"][$json->name . "_label"];
                    }
                }
                if ($key === "type" && ($value === "checkbox")) {
                    $json->data = new stdClass();
                    $json->data->value = "0";
                    $json->data->label = "";
                    foreach ($json->options as $os) {
                        if ($os->value === false || $os->value === 0 || $os->value === "0") {
                            $json->data->label = $os->label;
                        }
                    }
                    if ($json->defaultValue !== "") {
                        $json->data->value = $json->defaultValue;
                        foreach ($json->options as $os) {
                            if (($json->data->value === "true" || $json->data->value === "1") &&
                                    ($os->value === true || $os->value === 1 || $os->value === "1")) {
                                $json->data->label = $os->label;
                            }
                            if (($json->data->value === "false" || $json->data->value === "0") &&
                                    ($os->value === false || $os->value === 0 || $os->value === "0")) {
                                $json->data->label = $os->label;
                            }
                        }
                    }
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                        if (is_array($json->data->value) && isset($json->data->value[0])) {
                            $json->data->value = $json->data->value[0];
                        }
                        foreach ($json->options as $os) {
                            if (($json->data->value === true || $json->data->value === 1 || $json->data->value === "1") &&
                                    ($os->value === true || $os->value === 1 || $os->value === "1")) {
                                $json->data->label = $os->label;
                            }
                            if (($json->data->value === false || $json->data->value === 0 || $json->data->value === "0") &&
                                    ($os->value === false || $os->value === 0 || $os->value === "0")) {
                                $json->data->label = $os->label;
                            }
                        }
                    }
                }
                if ($key === "type" && ($value === "checkgroup")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "[]";
                    if ($json->defaultValue !== "") {
                        $dataValue = array();
                        $dataLabel = array();
                        $dv = explode("|", $json->defaultValue);
                        foreach ($dv as $idv) {
                            foreach ($json->optionsSql as $os) {
                                if ($os->value === trim($idv)) {
                                    array_push($dataValue, $os->value);
                                    array_push($dataLabel, $os->label);
                                }
                            }
                            foreach ($json->options as $os) {
                                if ($os->value === trim($idv)) {
                                    array_push($dataValue, $os->value);
                                    array_push($dataLabel, $os->label);
                                }
                            }
                        }
                        $json->data->value = $dataValue;
                        $json->data->label = G::json_encode($dataLabel);
                    }
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name . "_label"])) {
                        $json->data->label = $this->fields["APP_DATA"][$json->name . "_label"];
                    }
                }
                if ($key === "type" && ($value === "datetime")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "";
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name . "_label"])) {
                        $json->data->label = $this->fields["APP_DATA"][$json->name . "_label"];
                    }
                    $this->setDependentOptionsForDatetime($json, $this->fields);
                }
                if ($key === "type" && ($value === "file") && isset($this->fields["APP_DATA"]["APPLICATION"])) {
                    $oCriteriaAppDocument = new Criteria("workflow");
                    $oCriteriaAppDocument->addSelectColumn(AppDocumentPeer::APP_DOC_UID);
                    $oCriteriaAppDocument->addSelectColumn(AppDocumentPeer::DOC_VERSION);
                    $oCriteriaAppDocument->add(AppDocumentPeer::APP_UID, $this->fields["APP_DATA"]["APPLICATION"]);
                    $oCriteriaAppDocument->add(AppDocumentPeer::APP_DOC_FIELDNAME, $json->name);
                    $oCriteriaAppDocument->add(AppDocumentPeer::APP_DOC_STATUS, 'ACTIVE');
                    $oCriteriaAppDocument->addDescendingOrderByColumn(AppDocumentPeer::APP_DOC_CREATE_DATE);
                    $oCriteriaAppDocument->setLimit(1);
                    $rs = AppDocumentPeer::doSelectRS($oCriteriaAppDocument);
                    $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                    $rs->next();

                    $links = array();
                    $labelsFromDb = array();
                    $appDocUids = array();
                    $oAppDocument = new AppDocument();

                    if ($row = $rs->getRow()) {
                        $oAppDocument->load($row["APP_DOC_UID"], $row["DOC_VERSION"]);
                        $links[] = "../cases/cases_ShowDocument?a=" . $row["APP_DOC_UID"] . "&v=" . $row["DOC_VERSION"];
                        $labelsFromDb[] = $oAppDocument->getAppDocFilename();
                        $appDocUids[] = $row["APP_DOC_UID"];
                    }
                    $json->data = new stdClass();
                    $json->data->value = $links;
                    $json->data->app_doc_uid = $appDocUids;

                    if (sizeof($labelsFromDb)) {
                        $json->data->label = G::json_encode($labelsFromDb);
                    } else {
                        $json->data->label = isset($this->fields["APP_DATA"][$json->name . "_label"]) ? $this->fields["APP_DATA"][$json->name . "_label"] : (isset($this->fields["APP_DATA"][$json->name]) ? $this->fields["APP_DATA"][$json->name] : "[]");
                    }
                }
                if ($key === "type" && ($value === "file") && isset($json->variable)) {
                    //todo
                    $oCriteria = new Criteria("workflow");
                    $oCriteria->addSelectColumn(ProcessVariablesPeer::INP_DOC_UID);
                    $oCriteria->add(ProcessVariablesPeer::VAR_UID, $json->var_uid);
                    $rs = ProcessVariablesPeer::doSelectRS($oCriteria);
                    $rs->setFetchmode(ResultSet::FETCHMODE_ASSOC);
                    $rs->next();
                    $row = $rs->getRow();
                    if (isset($row["INP_DOC_UID"])) {
                        $json->inputDocuments = array($row["INP_DOC_UID"]);
                    }
                }
                if ($key === "type" && ($value === "multipleFile")) {
                    $json->data = new stdClass();
                    $json->data->value = "";
                    $json->data->label = "";
                    if (isset($this->fields["APP_DATA"][$json->name])) {
                        $json->data->value = $this->fields["APP_DATA"][$json->name];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name . "_label"])) {
                        $json->data->label = $this->fields["APP_DATA"][$json->name . "_label"];
                    }
                }
                //synchronize var_label
                if ($key === "type" && ($value === "dropdown" || $value === "suggest" || $value === "radio")) {
                    if (isset($this->fields["APP_DATA"]["__VAR_CHANGED__"]) && in_array($json->name, explode(",", $this->fields["APP_DATA"]["__VAR_CHANGED__"]))) {
                        foreach ($json->optionsSql as $io) {
                            if ($this->toStringNotNullValues($json->data->value) === $io->value) {
                                $json->data->label = $io->label;
                            }
                        }
                        foreach ($json->options as $io) {
                            if ($this->toStringNotNullValues($json->data->value) === $io->value) {
                                $json->data->label = $io->label;
                            }
                        }
                        $_SESSION["TRIGGER_DEBUG"]["DATA"][] = array("key" => $json->name . "_label", "value" => $json->data->label);
                    }
                }
                if ($key === "type" && ($value === "checkgroup")) {
                    if (isset($this->fields["APP_DATA"]["__VAR_CHANGED__"]) && in_array($json->name, explode(",", $this->fields["APP_DATA"]["__VAR_CHANGED__"]))) {
                        $dataValue = array();
                        $dataLabel = array();
                        $dv = array();
                        if (isset($this->fields["APP_DATA"][$json->name])) {
                            $dv = $this->fields["APP_DATA"][$json->name];
                        }
                        if (!is_array($dv)) {
                            $dv = explode(",", $dv);
                        }
                        foreach ($dv as $idv) {
                            foreach ($json->optionsSql as $os) {
                                if ($os->value === $idv) {
                                    $dataValue[] = $os->value;
                                    $dataLabel[] = $os->label;
                                }
                            }
                            foreach ($json->options as $os) {
                                if ($os->value === $idv) {
                                    $dataValue[] = $os->value;
                                    $dataLabel[] = $os->label;
                                }
                            }
                        }
                        $json->data->value = $dataValue;
                        $json->data->label = G::json_encode($dataLabel);
                        $_SESSION["TRIGGER_DEBUG"]["DATA"][] = array("key" => $json->name . "_label", "value" => $json->data->label);
                    }
                }
                if ($key === "type" && ($value === "datetime")) {
                    if (isset($this->fields["APP_DATA"]["__VAR_CHANGED__"]) && in_array($json->name, explode(",", $this->fields["APP_DATA"]["__VAR_CHANGED__"]))) {
                        $json->data->label = $json->data->value;
                        $_SESSION["TRIGGER_DEBUG"]["DATA"][] = array("key" => $json->name . "_label", "value" => $json->data->label);
                    }
                }
                //clear optionsSql
                if ($key === "type" && ($value === "text" || $value === "textarea" || $value === "hidden" || $value === "suggest")) {
                    $json->optionsSql = array();
                }
                //grid
                if ($key === "type" && ($value === "grid")) {
                    $columnsDataVariable = [];
                    //todo compatibility 'columnWidth'
                    foreach ($json->columns as $column) {
                        if (!isset($column->columnWidth) && $column->type !== "hidden") {
                            $json->layout = "static";
                            $column->columnWidth = "";
                        }
                        $column->parentIsGrid = true;
                        //save dataVariable value, only for columns control
                        if (!empty($column->dataVariable) && is_string($column->dataVariable)) {
                            if (in_array(substr($column->dataVariable, 0, 2), self::$prefixs)) {
                                $dataVariableValue = substr($column->dataVariable, 2);
                                if (!in_array($dataVariableValue, $columnsDataVariable)) {
                                    $columnsDataVariable[] = $dataVariableValue;
                                }
                            }
                        }
                    }
                    //data grid environment
                    $json->dataGridEnvironment = "onDataGridEnvironment";
                    if (isset($this->fields["APP_DATA"])) {
                        $dataGridEnvironment = $this->fields["APP_DATA"];
                        //Grids only access the global variables of 'ProcessMaker', other variables are removed.
                        $this->fields["APP_DATA"] = Cases::getGlobalVariables($this->fields["APP_DATA"]);
                        //restore AppData with dataVariable definition, only for columns control
                        foreach ($columnsDataVariable as $dge) {
                            if (isset($dataGridEnvironment[$dge])) {
                                $this->fields["APP_DATA"][$dge] = $dataGridEnvironment[$dge];
                            }
                        }
                    }
                }
                if ($key === "dataGridEnvironment" && ($value === "onDataGridEnvironment")) {
                    if (isset($this->fields["APP_DATA"])) {
                        $this->fields["APP_DATA"] = $dataGridEnvironment;
                        $dataGridEnvironment = [];
                    }
                    if (isset($this->fields["APP_DATA"][$json->name]) && is_array($this->fields["APP_DATA"][$json->name])) {
                        //rows
                        $rows = $this->fields["APP_DATA"][$json->name];
                        foreach ($rows as $keyRow => $row) {
                            //cells
                            $cells = array();
                            foreach ($json->columns as $column) {
                                //data
                                if ($column->type === "text" || $column->type === "textarea" || $column->type === "dropdown" || $column->type === "suggest" || $column->type === "datetime" || $column->type === "checkbox" || $column->type === "file" || $column->type === "multipleFile" || $column->type === "link" || $column->type === "hidden") {
                                    array_push($cells, array(
                                        "value" => isset($row[$column->name]) ? $row[$column->name] : "",
                                        "label" => isset($row[$column->name . "_label"]) ? $row[$column->name . "_label"] : (isset($row[$column->name]) ? $row[$column->name] : "")
                                    ));
                                }
                            }
                            $rows[$keyRow] = $cells;
                        }
                        $json->rows = count($rows);
                        $json->data = $rows;

                        $this->setDataSchema($json, $this->fields["APP_DATA"][$json->name]);
                    }
                }
                // Set the language defined in the json
                if ($this->lang === null && $key === "language" && isset($json->language)) {
                    $this->lang = $json->language;
                }

                // Get the translations related to the language
                if (!is_null($this->translations)) {
                    $labelsPo = $this->getLabelsPo($this->lang);
                    $translatableLabels = [
                        "label",
                        "title",
                        "hint",
                        "placeholder",
                        "validateMessage",
                        "alternateText",
                        "comment",
                        "alt"
                    ];
                    if ((in_array($key, $translatableLabels)) && !is_null($labelsPo)) {
                        foreach ($labelsPo as $langsValue) {
                            if (is_object($json) && $json->{$key} === $langsValue->msgid) {
                                $json->{$key} = $langsValue->msgstr;
                            }
                            if (is_array($json) && $json[$key] === $langsValue->msgid) {
                                $json[$key] = $langsValue->msgstr;
                            }
                        }
                    }
                }
                //EDIT,VIEW
                if (isset($this->fields["STEP_MODE"]) && $this->fields["STEP_MODE"] === "VIEW" && isset($json->mode)) {
                    $json->mode = "view";
                }
                if ($this->displayMode !== null && isset($json->mode)) {
                    $json->mode = $this->displayMode;
                }
                if ($key === "type" && ($value === "form") && $this->records != null) {
                    foreach ($this->records as $ri) {
                        if ($json->id === $ri["DYN_UID"] && !isset($json->jsonUpdate)) {
                            $jsonUpdate = G::json_decode($ri["DYN_CONTENT"]);
                            $jsonUpdate = $jsonUpdate->items[0];
                            $jsonUpdate->colSpan = $json->colSpan;
                            $jsonUpdate->mode = $json->mode;
                            $jsonUpdate->jsonUpdate = true;
                            $json = $jsonUpdate;
                            $this->jsonr($json);
                        }
                    }
                }
                //read event after
                $fn = $this->onAfterPropertyRead;
                if (is_callable($fn) || function_exists($fn)) {
                    $fn($json, $key, $value);
                }
            }
        }
    }

    /**
     * This function will be search in the dataSource and will be add the new row in the queryOutputData property
     *
     * @param object $json
     *
     * @return void
    */
    private function searchResultInDataSource($json)
    {
        $json->queryOutputData = [];
        foreach ($json->optionsSql as $option) {
            //We will to check the limit parameter
            if (count($json->queryOutputData) < $json->queryLimit) {
                //Searching by filter parameter
                if ($json->queryFilter !== '') {
                    if (stripos($option->label, $json->queryFilter) !== false) {
                        $json->queryOutputData[] = $option;
                    }
                } elseif (isset($json->querySearch) && is_array($json->querySearch) && !empty($json->querySearch)) {
                    //Searching by query parameter
                    $dataSearch = $json->querySearch;
                    $valueAdded = false;
                    //The match has priority
                    //We will to search match in the dataSource
                    if (isset($dataSearch['match'])) {
                        $value = isset($dataSearch['match']['value']) ? $dataSearch['match']['value'] : '';
                        $label = isset($dataSearch['match']['text']) ? $dataSearch['match']['text'] : '';
                        if (!empty($value) && $option->value === $value) {
                            $valueAdded = true;
                            $json->queryOutputData[] = $option;
                        }
                        if (!empty($label) && $option->label === $label && !$valueAdded) {
                            $json->queryOutputData[] = $option;
                        }
                    } elseif (isset($dataSearch['term'])) {
                        //We will to search term in the dataSource
                        $value = isset($dataSearch['term']['value']) ? $dataSearch['term']['value'] : '';
                        $label = isset($dataSearch['term']['text']) ? $dataSearch['term']['text'] : '';
                        if (!empty($value) && stripos($option->value, $value) !== false) {
                            $valueAdded = true;
                            $json->queryOutputData[] = $option;
                        }
                        if (!empty($label) && stripos($option->label, $label) !== false && !$valueAdded) {
                            $json->queryOutputData[] = $option;
                        }
                    }
                } else {
                    $json->queryOutputData[] = $option;
                }
            }
        }
    }

    /**
     * Get the values of the dependent references.
     * @param object $json
     * @return array
     */
    private function getValuesDependentFields($json): array
    {
        if (!isset($this->record["DYN_CONTENT"])) {
            return array();
        }
        $data = [];
        if (isset($this->fields["APP_DATA"])) {
            foreach ($this->fields["APP_DATA"] as $keyF => $valueF) {
                if (!isset($data[$keyF]) && !is_array($valueF)) {
                    $data[$keyF] = $valueF;
                }
            }
        }
        if (isset($json->dbConnection) && isset($json->sql)) {
            $result = array();
            preg_match_all('/\@(?:([\@\%\#\=\?\!Qq])([a-zA-Z\_]\w*)|([a-zA-Z\_][\w\-\>\:]*)\(((?:[^\\\\\)]*?)*)\))/', $json->sql, $result, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);
            $variables = isset($result[2]) ? $result[2] : array();
            foreach ($variables as $key => $value) {
                //Prevents an infinite cycle. If the name of the variable is used within its own dependent.
                if ($value[0] === $json->variable) {
                    continue;
                }
                $jsonDecode = G::json_decode($this->record["DYN_CONTENT"]);
                $jsonSearch = $this->jsonsf($jsonDecode, $value[0], $json->variable === "" ? "id" : "variable");
                $a = $this->getValuesDependentFields($jsonSearch);
                foreach ($a as $i => $v) {
                    $data[$i] = $v;
                }
            }
            if ($json->dbConnection !== "" && $json->dbConnection !== "none" && $json->sql !== "") {
                $sql = $this->replaceDataField($json->sql, $data);
                $dt = $this->getCacheQueryData($json->dbConnection, $sql, $json->type);
                $row = isset($dt[0]) ? $dt[0] : [];
                $index = $json->variable === "" ? $json->id : $json->variable;
                if (!isset($data[$index]) && isset($row[0]) && $json->type !== "suggest" && $json->type !== "radio") {
                    $data[$index] = $row[0];
                }
            }
        }
        if (isset($json->options) && isset($json->options[0])) {
            $data[$json->variable === "" ? $json->id : $json->variable] = $json->options[0]->value;
        }
        if (isset($json->placeholder) && $json->placeholder !== "") {
            $data[$json->variable === "" ? $json->id : $json->variable] = "";
        }
        if (isset($json->defaultValue) && $json->defaultValue !== "") {
            $data[$json->variable === "" ? $json->id : $json->variable] = $json->defaultValue;
        }
        return $data;
    }

    /**
     * Get data from cache query.
     * 
     * @param string $connection
     * @param string $sql
     * @param string $type
     * @param boolean $clearCache
     * @return array
     * @see \PmDynaform->jsonr()
     * @see \PmDynaform->getValuesDependentFields()
     */
    private function getCacheQueryData($connection, $sql, $type = "", $clearCache = false)
    {
        $data = [];
        if (!empty($type)) {
            $type = "-" . $type;
        }
        try {
            if ($clearCache === true) {
                unset($this->cache[md5($sql)]);
            }
            if (isset($this->cache[md5($sql)])) {
                $data = $this->cache[md5($sql)];
            } else {
                $cnn = Propel::getConnection($connection);
                $stmt = $cnn->createStatement();
                $rs = $stmt->executeQuery($sql, \ResultSet::FETCHMODE_NUM);
                while ($rs->next()) {
                    $data[] = $rs->getRow();
                }
                $this->cache[md5($sql)] = $data;

                $this->context["action"] = "execute-sql" . $type;
                $this->context["sql"] = $sql;
                $message = 'Sql Execution';
                Log::channel(':sqlExecution')->info($message, Bootstrap::context($this->context));
            }
        } catch (Exception $e) {
            $this->context["action"] = "execute-sql" . $type;
            $this->context["exception"] = (array) $e;
            $this->lastQueryError = $e;
            $message = 'Sql Execution';
            $context = $this->basicExceptionData($e, $sql);
            Log::channel(':sqlExecution')->error($message, Bootstrap::context($context));
        }
        return $data;
    }

    public function getDatabaseProvider($dbConnection)
    {
        if ($dbConnection === "workflow" || $dbConnection === "rbac" || $dbConnection === "rp") {
            return "mysql";
        }
        if ($this->databaseProviders === null) {
            $a = new Criteria("workflow");
            $a->addSelectColumn(DbSourcePeer::DBS_UID);
            $a->addSelectColumn(DbSourcePeer::DBS_TYPE);
            $ds = DbSourcePeer::doSelectRS($a);
            $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $this->databaseProviders = [];
            while ($ds->next()) {
                $this->databaseProviders[] = $ds->getRow();
            }
        }
        foreach ($this->databaseProviders as $key => $value) {
            if ($value["DBS_UID"] === $dbConnection) {
                return $value["DBS_TYPE"];
            }
        }
        return null;
    }

    public function sqlParse($sql, $fn = null)
    {
        $sqlParser = new \PHPSQLParser($sql);
        $parsed = $sqlParser->parsed;
        if (!empty($parsed["SELECT"])) {
            $options = isset($parsed["OPTIONS"]) && count($parsed["OPTIONS"]) > 0 ? implode(" ", $parsed["OPTIONS"]) : "";
            if (!empty($options)) {
                $options = $options . " ";
            }
            $select = "SELECT " . $options;
            $dt = $parsed["SELECT"];
            foreach ($dt as $key => $value) {
                if ($key != 0) {
                    $select .= ", ";
                }
                $sAlias = str_replace("`", "", $dt[$key]["alias"]);
                $sBaseExpr = $dt[$key]["base_expr"];
                if (strpos(strtoupper($sBaseExpr), "TOP") !== false) {
                    $dt[$key]["expr_type"] = "";
                    $sBaseExpr = trim($sBaseExpr) . " " . trim($sAlias);
                }
                switch ($dt[$key]["expr_type"]) {
                    case "colref":
                        if ($sAlias === $sBaseExpr) {
                            $select .= $sAlias;
                        } else {
                            $select .= $sBaseExpr . " AS " . $sAlias;
                        }
                        break;
                    case "expression":
                        if ($sAlias === $sBaseExpr) {
                            $select .= $sBaseExpr;
                        } else {
                            $select .= $sBaseExpr . " AS " . $sAlias;
                        }
                        break;
                    case "subquery":
                        if (strpos($sAlias, $sBaseExpr, 0) !== 0) {
                            $select .= $sAlias;
                        } else {
                            $select .= $sBaseExpr . " AS " . $sAlias;
                        }
                        break;
                    case "operator":
                        $select .= $sBaseExpr;
                        break;
                    default:
                        $select .= $sBaseExpr;
                        break;
                }
            }
            $select = trim($select);

            $isOffsetWord = false;

            $from = "";
            if (!empty($parsed["FROM"])) {
                $from = "FROM ";
                $dt = $parsed["FROM"];
                foreach ($dt as $key => $value) {
                    //reserved word: OFFSET
                    if ($dt[$key]["alias"] === "OFFSET") {
                        $isOffsetWord = true;
                        $dt[$key]["alias"] = "";
                    }
                    if ($key == 0) {
                        //compatibility with table name alias when uses the sentence 'AS'
                        if (strtoupper($dt[$key]["alias"]) === 'AS') {
                            $parser = new Parser($sql);
                            if (isset($parser->statements[$key]) && isset($parser->statements[$key]->from[$key])) {
                                $obj1 = $parser->statements[$key]->from[$key];
                                if (!empty($obj1->alias)) {
                                    $dt[$key]["alias"] = $dt[$key]["alias"] . ' ' . $obj1->alias;
                                }
                            }
                        }
                        $from .= $dt[$key]["table"]
                                . ($dt[$key]["table"] == $dt[$key]["alias"] ? "" : " " . $dt[$key]["alias"]);
                    } else {
                        $from .= " "
                                . ($dt[$key]["join_type"] == "JOIN" ? "INNER" : $dt[$key]["join_type"])
                                . " JOIN "
                                . $dt[$key]["table"]
                                . ($dt[$key]["table"] == $dt[$key]["alias"] ? "" : " " . $dt[$key]["alias"]) . " "
                                . $dt[$key]["ref_type"] . " ";

                        // Get the last 6 chars of the string
                        $tempString = mb_substr($dt[$key]["ref_clause"], -6);

                        // If the last section is a "INNER" statement we need to remove it
                        if (strcasecmp($tempString, " INNER") === 0) {
                            $from .= mb_substr($dt[$key]["ref_clause"], 0, mb_strlen($dt[$key]["ref_clause"]) - 6);
                        } else {
                            $from .= $dt[$key]["ref_clause"];
                        }
                    }
                }
            }
            $from = trim($from);

            $where = "";
            if (!empty($parsed["WHERE"])) {
                $where = "WHERE ";
                $dt = ($parsed['WHERE'][0]['expr_type'] == 'expression') ? $parsed['WHERE'][0]['sub_tree'] : $parsed["WHERE"];
                $nw = count($dt);
                //reserved word: OFFSET
                if ($dt[$nw - 2]["base_expr"] === "OFFSET") {
                    $isOffsetWord = true;
                    if ($dt[$nw - 2]["expr_type"] === "colref") {
                        $dt[$nw - 2]["base_expr"] = "";
                    }
                    if ($dt[$nw - 1]["expr_type"] === "const") {
                        if (isset($parsed["LIMIT"]["start"])) {
                            $parsed["LIMIT"]["start"] = $dt[$nw - 1]["base_expr"];
                        }
                        $dt[$nw - 1]["base_expr"] = "";
                    }
                }
                foreach ($dt as $key => $value) {
                    $where .= $value["base_expr"] . " ";
                }
            }
            $where = trim($where);

            $groupBy = "";
            if (!empty($parsed["GROUP"])) {
                $groupBy = "GROUP BY ";
                $dt = $parsed["GROUP"];
                foreach ($dt as $key => $value) {
                    $search = preg_replace("/ ASC$/i", "", $value["base_expr"]);
                    $groupBy .= $search . ", ";
                }
                $groupBy = rtrim($groupBy, ", ");
            }
            $groupBy = trim($groupBy);

            $having = "";
            if (!empty($parsed["HAVING"])) {
                $having = "HAVING ";
                $dt = $parsed["HAVING"];
                foreach ($dt as $key => $value) {
                    $having .= $value["base_expr"] . " ";
                }
            }
            $having = trim($having);

            $orderBy = "";
            if (!empty($parsed["ORDER"])) {
                $orderBy = "ORDER BY ";
                $dt = $parsed["ORDER"];
                foreach ($dt as $key => $value) {
                    $search = preg_replace("/ ASC$/i", "", $value["base_expr"]);
                    $orderBy .= $search . " " . $value["direction"] . ", ";
                }
                $orderBy = rtrim($orderBy, ", ");
            }
            $orderBy = trim($orderBy);

            $limit = "";
            if (!empty($parsed["LIMIT"])) {
                if ($isOffsetWord == false) {
                    $limit = "LIMIT " . $parsed["LIMIT"]["start"] . ", " . $parsed["LIMIT"]["end"];
                }
                if ($isOffsetWord == true) {
                    $limit = "OFFSET " . $parsed["LIMIT"]["start"] . " LIMIT " . $parsed["LIMIT"]["end"];
                }
            }

            if ($fn !== null && (is_callable($fn) || function_exists($fn))) {
                $fn($parsed, $select, $from, $where, $groupBy, $having, $orderBy, $limit);
            }

            $dt = [$select, $from, $where, $groupBy, $having, $orderBy, $limit];
            $query = "";
            foreach ($dt as $val) {
                $val = trim($val);
                if (!empty($val)) {
                    $query = $query . $val . " ";
                }
            }
            return $query;
        }
        if (!empty($parsed["CALL"])) {
            $sCall = "CALL ";
            $aCall = $parsed["CALL"];
            foreach ($aCall as $key => $value) {
                $sCall .= $value . " ";
            }
            return $sCall;
        }
        if (!empty($parsed["EXECUTE"])) {
            $sCall = "EXECUTE ";
            $aCall = $parsed["EXECUTE"];
            foreach ($aCall as $key => $value) {
                $sCall .= $value . " ";
            }
            return $sCall;
        }
        return $sql;
    }

    public function isResponsive()
    {
        return $this->record != null && $this->record["DYN_VERSION"] == 2 ? true : false;
    }

    public function printTracker()
    {
        ob_clean();

        $this->fields["STEP_MODE"] = "VIEW";
        $json = G::json_decode($this->record["DYN_CONTENT"]);

        foreach ($json->items[0]->items as $key => $value) {
            $n = count($json->items[0]->items[$key]);
            for ($i = 0; $i < $n; $i++) {
                if (isset($json->items[0]->items[$key][$i]->type) && $json->items[0]->items[$key][$i]->type === "submit") {
                    $cols = new stdClass();
                    $cols->colSpan = $json->items[0]->items[$key][$i]->colSpan;
                    $json->items[0]->items[$key][$i] = $cols;
                }
            }
        }

        $this->jsonr($json);

        $javascript = "
            <script type=\"text/javascript\">
                var jsondata = " . G::json_encode($json) . ";
                var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";
                var pm_run_outside_main_app = \"\";
                var dyn_uid = \"" . $this->fields["CURRENT_DYNAFORM"] . "\";
                var __DynaformName__ = \"" . $this->record["PRO_UID"] . "_" . $this->record["DYN_UID"] . "\";
                var app_uid = \"" . $this->fields["APP_UID"] . "\";
                var prj_uid = \"" . $this->fields["PRO_UID"] . "\";
                var step_mode = \"\";
                var workspace = \"" . config("system.workspace") . "\";
                var credentials = " . G::json_encode($this->credentials) . ";
                var filePost = \"\";
                var fieldsRequired = null;
                var triggerDebug = false;
                var sysLang = \"" . SYS_LANG . "\";
                var isRTL = \"" . $this->isRTL . "\";
                var pathRTLCss = \"" . $this->pathRTLCss . "\";
                var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";
                " . $this->getTheStringVariableForGoogleMaps() . "\n
                $(window).load(function ()
                {
                    var data = jsondata;

                    window.dynaform = new PMDynaform.core.Project({
                        data: data,
                        delIndex: delIndex,
                        dynaformUid:  dyn_uid,
                        keys: {
                            server: httpServerHostname,
                            projectId: prj_uid,
                            workspace: workspace
                        },
                        token: credentials,
                        submitRest: false,
                        googleMaps: typeof googleMaps !== 'undefined' ? googleMaps : null
                    });
                    $(document).find(\"form\").submit(function (e) {
                        e.preventDefault();
                        return false;
                    });
                });
            </script>

            <div style=\"margin: 10px 20px 10px 0;\">
                <div style=\"float: right\"><a href=\"javascript: window.history.go(-1);\" style=\"text-decoration: none;\">&lt; " . G::LoadTranslation("ID_BACK") . "</a></div>
                <div style=\"clear: both\"></div>
            </div>
        ";

        $file = file_get_contents(PATH_HOME . "public_html" . PATH_SEP . "lib" . PATH_SEP . "pmdynaform" . PATH_SEP . "build" . PATH_SEP . "pmdynaform.html");
        $file = str_replace("{javascript}", $javascript, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit(0);
    }

    public function printView()
    {
        ob_clean();
        $this->displayMode = "disabled";
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $javascrip = "" .
                "<script type='text/javascript'>\n" .
                "var jsondata = " . G::json_encode($json) . ";\n" .
                "var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";\n" .
                "var pm_run_outside_main_app = null;\n" .
                "var dyn_uid = '" . $this->fields["CURRENT_DYNAFORM"] . "';\n" .
                "var __DynaformName__ = '" . $this->record["PRO_UID"] . "_" . $this->record["DYN_UID"] . "';\n" .
                "var app_uid = '" . $this->fields["APP_UID"] . "';\n" .
                "var prj_uid = '" . $this->fields["PRO_UID"] . "';\n" .
                "var step_mode = null;\n" .
                "var workspace = '" . config("system.workspace") . "';\n" .
                "var credentials = " . G::json_encode($this->credentials) . ";\n" .
                "var filePost = null;\n" .
                "var fieldsRequired = null;\n" .
                "var triggerDebug = null;\n" .
                "var sysLang = '" . SYS_LANG . "';\n" .
                "var isRTL = " . $this->isRTL . ";\n" .
                "var pathRTLCss = '" . $this->pathRTLCss . "';\n" .
                "var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";\n" .
                "var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";\n" .
                $this->getTheStringVariableForGoogleMaps() . "\n" .
                "$(window).load(function () {\n" .
                "    var data = jsondata;\n" .
                "    window.dynaform = new PMDynaform.core.Project({\n" .
                "        data: data,\n" .
                "        delIndex: delIndex,\n" .
                "        dynaformUid:  dyn_uid,\n" .
                "        keys: {\n" .
                "            server: httpServerHostname,\n" .
                "            projectId: prj_uid,\n" .
                "            workspace: workspace\n" .
                "        },\n" .
                "        token: credentials,\n" .
                "        submitRest: false,\n" .
                "        googleMaps: typeof googleMaps !== 'undefined' ? googleMaps : null\n" .
                "    });\n" .
                "    $(document).find('form').find('button').on('click', function (e) {\n" .
                "        e.preventDefault();\n" .
                "        return false;\n" .
                "    });\n" .
                "    $(document).find('form').submit(function (e) {\n" .
                "        e.preventDefault();\n" .
                "        return false;\n" .
                "    });\n" .
                "});\n" .
                "</script>\n";

        $file = file_get_contents(PATH_HOME . 'public_html/lib/pmdynaform/build/pmdynaform.html');
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit();
    }

    public function printEdit()
    {
        ob_clean();
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        if (!isset($this->fields["APP_DATA"]["__DYNAFORM_OPTIONS"]["PREVIOUS_STEP"])) {
            $this->fields["APP_DATA"]["__DYNAFORM_OPTIONS"]["PREVIOUS_STEP"] = "";
        }
        $title = $this->getSessionMessage() .
                "<table width='100%' align='center'>\n" .
                "    <tr class='userGroupTitle'>\n" .
                "        <td width='100%' align='center'>" . G::LoadTranslation('ID_CASE') . " #: " . $this->fields["APP_NUMBER"] . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . G::LoadTranslation('ID_TITLE') . ": " . $this->fields["APP_TITLE"] . "</td>\n" .
                "    </tr>\n" .
                "</table>\n";
        $javascrip = "" .
                "<script type='text/javascript'>\n" .
                "var jsondata = " . $this->json_encode($json) . ";\n" .
                "var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";\n" .
                "var pm_run_outside_main_app = '" . $this->fields["PM_RUN_OUTSIDE_MAIN_APP"] . "';\n" .
                "var dyn_uid = '" . $this->fields["CURRENT_DYNAFORM"] . "';\n" .
                "var __DynaformName__ = '" . $this->record["PRO_UID"] . "_" . $this->record["DYN_UID"] . "';\n" .
                "var app_uid = '" . $this->fields["APP_UID"] . "';\n" .
                "var prj_uid = '" . $this->fields["PRO_UID"] . "';\n" .
                "var step_mode = '" . $this->fields["STEP_MODE"] . "';\n" .
                "var workspace = '" . config("system.workspace") . "';\n" .
                "var credentials = " . G::json_encode($this->credentials) . ";\n" .
                "var filePost = null;\n" .
                "var fieldsRequired = null;\n" .
                "var triggerDebug = " . ($this->fields["TRIGGER_DEBUG"] === 1 ? "true" : "false") . ";\n" .
                "var sysLang = '" . SYS_LANG . "';\n" .
                "var isRTL = " . $this->isRTL . ";\n" .
                "var pathRTLCss = '" . $this->pathRTLCss . "';\n" .
                "var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";\n" .
                "var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";\n" .
                $this->getTheStringVariableForGoogleMaps() . "\n" .
                "</script>\n" .
                "<script type='text/javascript' src='/jscore/cases/core/cases_Step.js'></script>\n" .
                "<script type='text/javascript' src='/jscore/cases/core/pmDynaform.js'></script>\n" .
                ($this->fields["PRO_SHOW_MESSAGE"] === 1 ? '' : $title) .
                "<div style='width:100%;padding:0px 10px 0px 10px;margin:15px 0px 0px 0px;'>\n" .
                "    <img src='/images/bulletButtonLeft.gif' style='float:left;'>&nbsp;\n" .
                "    <a id='dyn_backward' href='" . $this->fields["APP_DATA"]["__DYNAFORM_OPTIONS"]["PREVIOUS_STEP"] . "' style='float:left;font-size:12px;line-height:1;margin:0px 0px 1px 5px;'>\n" .
                "    " . $this->fields["APP_DATA"]["__DYNAFORM_OPTIONS"]["PREVIOUS_STEP_LABEL"] . "" .
                "    </a>\n" .
                "    <img src='/images/bulletButton.gif' style='float:right;'>&nbsp;\n" .
                "    <a id='dyn_forward' href='" . $this->fields["APP_DATA"]["__DYNAFORM_OPTIONS"]["NEXT_STEP"] . "' style='float:right;font-size:12px;line-height:1;margin:0px 5px 1px 0px;'>\n" .
                "    " . $this->fields["APP_DATA"]["__DYNAFORM_OPTIONS"]["NEXT_STEP_LABEL"] . "" .
                "    </a>\n" .
                "</div>";
        $file = file_get_contents(PATH_HOME . 'public_html/lib/pmdynaform/build/pmdynaform.html');
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit();
    }

    /**
     * Print edit supervisor forms.
     * @param array $param
     */
    public function printEditSupervisor(array $param = [])
    {
        $navbar = '';
        if (isset($param['DEL_INDEX'])) {
            $navbar = self::navigationBarForStepsToRevise($this->fields["APP_UID"], $this->fields["CURRENT_DYNAFORM"], $param['DEL_INDEX']);
        }
        ob_clean();
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $javascrip = "
        <script type=\"text/javascript\">
            var jsondata = " . G::json_encode($json) . ";
            var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";
            var pm_run_outside_main_app = null;
            var dyn_uid = \"" . $this->fields["CURRENT_DYNAFORM"] . "\";
            var __DynaformName__ = \"" . $this->fields["PRO_UID"] . "_" . $this->fields["CURRENT_DYNAFORM"] . "\";
            var app_uid = \"" . $this->fields["APP_UID"] . "\";
            var prj_uid = \"" . $this->fields["PRO_UID"] . "\";
            var step_mode = null;
            var workspace = \"" . config("system.workspace") . "\";
            var credentials = " . G::json_encode($this->credentials) . ";
            var filePost = \"cases_SaveDataSupervisor?UID=" . $this->fields["CURRENT_DYNAFORM"] . "\";
            var fieldsRequired = null;
            var triggerDebug   = null;
            var sysLang = \"" . SYS_LANG . "\";
            var isRTL = \"" . $this->isRTL . "\";
            var pathRTLCss = \"" . $this->pathRTLCss . "\";
            var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";
            var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";
            " . $this->getTheStringVariableForGoogleMaps() . "
        </script>
        <script type=\"text/javascript\" src=\"/jscore/cases/core/pmDynaform.js\"></script>
        {$navbar}
        <div>
            " . $this->getSessionMessageForSupervisor() . "
            <div style=\"display: none;\">
                <a id=\"dyn_forward\" href=\"javascript:;\"></a>
            </div>
        </div>
        ";

        $file = file_get_contents(PATH_HOME . "public_html" . PATH_SEP . "lib" . PATH_SEP . "pmdynaform" . PATH_SEP . "build" . PATH_SEP . "pmdynaform.html");
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit(0);
    }

    public function printWebEntry($filename)
    {
        ob_clean();
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $javascrip = "" .
                "<script type='text/javascript'>\n" .
                "var jsondata = " . G::json_encode($json) . ";\n" .
                "var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";\n" .
                "var pm_run_outside_main_app = null;\n" .
                "var dyn_uid = '" . $this->fields["CURRENT_DYNAFORM"] . "';\n" .
                "var __DynaformName__ = null;\n" .
                "var app_uid = null;\n" .
                "var prj_uid = '" . $this->record["PRO_UID"] . "';\n" .
                "var step_mode = null;\n" .
                "var workspace = '" . config("system.workspace") . "';\n" .
                "var credentials = " . G::json_encode($this->credentials) . ";\n" .
                "var filePost = '" . $filename . "';\n" .
                "var fieldsRequired = " . G::json_encode(array()) . ";\n" .
                "var triggerDebug = null;\n" .
                "var sysLang = '" . SYS_LANG . "';\n" .
                "var isRTL = " . $this->isRTL . ";\n" .
                "var pathRTLCss = '" . $this->pathRTLCss . "';\n" .
                "var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";\n" .
                "var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";\n" .
                $this->getTheStringVariableForGoogleMaps() . "\n" .
                "</script>\n" .
                "<script type='text/javascript' src='/jscore/cases/core/pmDynaform.js'></script>\n" .
                "<div style='width:100%;padding: 0px 10px 0px 10px;margin:15px 0px 0px 0px;'>\n" .
                "    <img src='/images/bulletButton.gif' style='float:right;'>&nbsp;\n" .
                "    <a id='dyn_forward' href='' style='float:right;font-size:12px;line-height:1;margin:0px 5px 1px 0px;'>\n" .
                "        Next Step\n" .
                "    </a>\n" .
                "</div>";

        $file = file_get_contents(PATH_HOME . 'public_html/lib/pmdynaform/build/pmdynaform.html');
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit();
    }

    public function printABE($filename, $record)
    {
        ob_clean();
        $this->record = $record;
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $javascrip = "" .
                "<script type='text/javascript'>\n" .
                "var jsondata = " . G::json_encode($json) . ";\n" .
                "var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";\n" .
                "var pm_run_outside_main_app = null;\n" .
                "var dyn_uid = '" . $this->fields["CURRENT_DYNAFORM"] . "';\n" .
                "var __DynaformName__ = null;\n" .
                "var app_uid = '" . G::decrypt($record['APP_UID'], URL_KEY) . "';\n" .
                "var prj_uid = '" . $this->record["PRO_UID"] . "';\n" .
                "var step_mode = null;\n" .
                "var workspace = '" . config("system.workspace") . "';\n" .
                "var credentials = " . G::json_encode($this->credentials) . ";\n" .
                "var filePost = '" . $filename . "';\n" .
                "var fieldsRequired = " . G::json_encode(array()) . ";\n" .
                "var triggerDebug = null;\n" .
                "var sysLang = '" . SYS_LANG . "';\n" .
                "var isRTL = " . $this->isRTL . ";\n" .
                "var pathRTLCss = '" . $this->pathRTLCss . "';\n" .
                "var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? G::decrypt($this->fields["DEL_INDEX"], URL_KEY) : "0") . ";\n" .
                "var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";\n" .
                $this->getTheStringVariableForGoogleMaps() . "\n" .
                "</script>\n" .
                "<script type='text/javascript' src='/jscore/cases/core/pmDynaform.js'></script>\n" .
                $this->getSessionMessage() .
                "<div style='width:100%;padding: 0px 10px 0px 10px;margin:15px 0px 0px 0px;'>\n" .
                "    <a id='dyn_forward' href='' style='float:right;font-size:12px;line-height:1;margin:0px 5px 1px 0px;'>\n" .
                "    </a>\n" .
                "</div>";

        $file = file_get_contents(PATH_HOME . 'public_html/lib/pmdynaform/build/pmdynaform.html');
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit();
    }

    public function printPmDynaform($js = "")
    {
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $javascrip = "" .
                "<script type='text/javascript'>" .
                "var sysLang = '" . SYS_LANG . "';\n" .
                "var isRTL = " . $this->isRTL . ";\n" .
                "var pathRTLCss = '" . $this->pathRTLCss . "';\n" .
                "var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";\n" .
                "var jsonData = " . $this->json_encode($json) . ";\n" .
                "var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";\n" .
                "var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";\n" .
                $this->getTheStringVariableForGoogleMaps() . "\n" .
                $js .
                "</script>";

        $file = file_get_contents(PATH_HOME . 'public_html/lib/pmdynaform/build/pmdynaform.html');
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        echo $file;
        exit();
    }

    /**
     * Print PmDynaform for Action by Email.
     * 
     * @param array $record
     * @return string
     * 
     * @see ActionsByEmailCoreClass->sendActionsByEmail()
     * @link https://wiki.processmaker.com/3.3/Actions_by_Email
     */
    public function printPmDynaformAbe($record)
    {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        $this->record = $record;
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $currentDynaform = (isset($this->fields['CURRENT_DYNAFORM']) && $this->fields['CURRENT_DYNAFORM'] != '') ? $this->fields['CURRENT_DYNAFORM'] : '';
        $javascrip = "" .
                "<script type='text/javascript'>\n" .
                "var jsondata = " . G::json_encode($json) . ";\n" .
                "var httpServerHostname = \"" . System::getHttpServerHostnameRequestsFrontEnd() . "\";\n" .
                "var pm_run_outside_main_app = null;\n" .
                "var dyn_uid = '" . $currentDynaform . "';\n" .
                "var __DynaformName__ = null;\n" .
                "var app_uid = null;\n" .
                "var prj_uid = '" . $this->record["PRO_UID"] . "';\n" .
                "var step_mode = null;\n" .
                "var workspace = '" . config("system.workspace") . "';\n" .
                "var credentials = " . G::json_encode($this->credentials) . ";\n" .
                "var fieldsRequired = " . G::json_encode(array()) . ";\n" .
                "var triggerDebug = null;\n" .
                "var sysLang = '" . SYS_LANG . "';\n" .
                "var isRTL = " . $this->isRTL . ";\n" .
                "var pathRTLCss = '" . $this->pathRTLCss . "';\n" .
                "var delIndex = " . (isset($this->fields["DEL_INDEX"]) ? $this->fields["DEL_INDEX"] : "0") . ";\n" .
                "var leaveCaseWarning = " . $this->getLeaveCaseWarning() . ";\n" .
                $this->getTheStringVariableForGoogleMaps() . "\n" .
                "</script>\n" .
                "<script type='text/javascript' src='/jscore/cases/core/pmDynaform.js'></script>\n" .
                "<div style='width:100%;padding: 0px 10px 0px 10px;margin:15px 0px 0px 0px;'>\n" .
                "</div>";
        $file = file_get_contents(PATH_HOME . 'public_html/lib/pmdynaform/build/pmdynaform.html');
        $file = str_replace("{javascript}", $javascrip, $file);
        $file = str_replace("{sys_skin}", SYS_SKIN, $file);
        return $file;
    }

    public function synchronizeSubDynaform()
    {
        if (!isset($this->record["DYN_CONTENT"])) {
            return;
        }
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        foreach ($this->records as $ri) {
            $jsonSearch = $this->jsonsf($json, $ri["DYN_UID"], "id");
            if ($jsonSearch === null) {
                continue;
            }
            $jsonUpdate = G::json_decode($ri["DYN_CONTENT"]);
            $jsonUpdate = $jsonUpdate->items[0];
            $jsonUpdate->colSpan = $jsonSearch->colSpan;
            $jsonUpdate->mode = $jsonSearch->mode;
            $this->jsonReplace($json, $ri["DYN_UID"], "id", $jsonUpdate);
        }
        $this->record["DYN_CONTENT"] = G::json_encode($json);
    }

    private function jsonReplace(&$json, $id, $for = "id", $update = null)
    {
        foreach ($json as $key => &$value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->jsonReplace($value, $id, $for, $update);
            }
            if (!$sw1 && !$sw2) {
                if ($key === $for && $id === $value) {
                    $json = $update;
                }
            }
        }
    }

    public function synchronizeVariable($processUid, $newVariable, $oldVariable)
    {
        $criteria = new Criteria("workflow");
        $criteria->addSelectColumn(DynaformPeer::DYN_UID);
        $criteria->addSelectColumn(DynaformPeer::DYN_CONTENT);
        $criteria->add(DynaformPeer::PRO_UID, $processUid, Criteria::EQUAL);
        $rsCriteria = DynaformPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        while ($rsCriteria->next()) {
            $aRow = $rsCriteria->getRow();
            $json = G::json_decode($aRow['DYN_CONTENT']);
            $this->jsons($json, $newVariable, $oldVariable);
            $json2 = G::json_encode($json);
            //update dynaform
            if ($json2 !== $aRow['DYN_CONTENT']) {
                $con = Propel::getConnection(DynaformPeer::DATABASE_NAME);
                $con->begin();
                $oPro = DynaformPeer::retrieveByPk($aRow["DYN_UID"]);
                $oPro->setDynContent($json2);
                $oPro->save();
                $con->commit();
            }
        }
    }

    private function jsons(&$json, $newVariable, $oldVariable)
    {
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->jsons($value, $newVariable, $oldVariable);
            }
            if (!$sw1 && !$sw2) {
                if ($key === "variable" && $json->variable === $oldVariable["VAR_NAME"]) {
                    $json->variable = $newVariable["VAR_NAME"];
                    if (isset($json->dataType)) {
                        $json->dataType = $newVariable["VAR_FIELD_TYPE"];
                    }
                    if (isset($json->name)) {
                        $json->name = $newVariable["VAR_NAME"];
                    }
                    if (isset($json->dbConnection) && $json->dbConnection === $oldVariable["VAR_DBCONNECTION"]) {
                        $json->dbConnection = $newVariable["VAR_DBCONNECTION"];
                    }
                    if (isset($json->dbConnectionLabel) && $json->dbConnectionLabel === $oldVariable["VAR_DBCONNECTION_LABEL"]) {
                        $json->dbConnectionLabel = $newVariable["VAR_DBCONNECTION_LABEL"];
                    }
                    if (isset($json->sql) && $json->sql === $oldVariable["VAR_SQL"]) {
                        $json->sql = $newVariable["VAR_SQL"];
                    }
                    if (isset($json->options) && G::json_encode($json->options) === $oldVariable["VAR_ACCEPTED_VALUES"]) {
                        $json->options = G::json_decode($newVariable["VAR_ACCEPTED_VALUES"]);
                    }
                }
                //update variable
                if ($key === "var_name" && $json->var_uid === $oldVariable["VAR_UID"]) {
                    $json->var_name = $newVariable["VAR_NAME"];
                }
                if ($key === "var_field_type" && $json->var_uid === $oldVariable["VAR_UID"]) {
                    $json->var_field_type = $newVariable["VAR_FIELD_TYPE"];
                }
                if ($key === "var_dbconnection" && $json->var_uid === $oldVariable["VAR_UID"]) {
                    $json->var_dbconnection = $newVariable["VAR_DBCONNECTION"];
                }
                if ($key === "var_dbconnection_label" && $json->var_uid === $oldVariable["VAR_UID"]) {
                    $json->var_dbconnection_label = $newVariable["VAR_DBCONNECTION_LABEL"];
                }
                if ($key === "var_sql" && $json->var_uid === $oldVariable["VAR_UID"]) {
                    $json->var_sql = $newVariable["VAR_SQL"];
                }
                if ($key === "var_accepted_values" && $json->var_uid === $oldVariable["VAR_UID"]) {
                    $json->var_accepted_values = G::json_decode($newVariable["VAR_ACCEPTED_VALUES"]);
                }
            }
        }
    }

    /**
     * Sync JSON definition of the Forms with Input Document information
     * in all forms from a process
     *
     * @param string $processUid
     * @param array $inputDocument
     */
    public function synchronizeInputDocument($processUid, $inputDocument)
    {
        $criteria = new Criteria('workflow');
        $criteria->addSelectColumn(DynaformPeer::DYN_UID);
        $criteria->addSelectColumn(DynaformPeer::DYN_CONTENT);
        $criteria->add(DynaformPeer::PRO_UID, $processUid, Criteria::EQUAL);
        // Only select the forms with an input document related to a field
        $criteria->add(DynaformPeer::DYN_CONTENT, '%"sizeUnity":%', Criteria::LIKE);
        $rsCriteria = DynaformPeer::doSelectRS($criteria);
        $rsCriteria->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        while ($rsCriteria->next()) {
            $aRow = $rsCriteria->getRow();
            $json = G::json_decode($aRow['DYN_CONTENT']);
            $this->jsonsid($json, $inputDocument);
            $json2 = G::json_encode($json);
            //update dynaform
            if ($json2 !== $aRow['DYN_CONTENT']) {
                $con = Propel::getConnection(DynaformPeer::DATABASE_NAME);
                $con->begin();
                $oPro = DynaformPeer::retrieveByPk($aRow['DYN_UID']);
                $oPro->setDynContent($json2);
                $oPro->save();
                $con->commit();
            }
        }
    }

    /**
     * Replace values from an Input Document related to the form,
     * for fields of type "file" and "multipleFile"
     *
     * @param object $json
     * @param array $inputDocument
     */
    private function jsonsid(&$json, $inputDocument)
    {
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->jsonsid($value, $inputDocument);
            }
            if (!$sw1 && !$sw2) {
                if ($key === "type" && ($json->type === "file" || $json->type === "multipleFile") && isset($json->inp_doc_uid)) {
                    if ($json->inp_doc_uid === $inputDocument["INP_DOC_UID"]) {
                        if (isset($json->size)) {
                            $json->size = $inputDocument["INP_DOC_MAX_FILESIZE"];
                        }
                        if (isset($json->sizeUnity)) {
                            $json->sizeUnity = $inputDocument["INP_DOC_MAX_FILESIZE_UNIT"];
                        }
                        if (isset($json->extensions)) {
                            $json->extensions = $inputDocument["INP_DOC_TYPE_FILE"];
                        }
                    }
                } else if ($key === "type" && $json->type === "grid" && !empty($json->columns)) {
                    $this->jsonsid($json->columns, $inputDocument);
                }
            }
        }
    }

    /**
     * Verify the use of the variable in all the forms of the process.
     * 
     * @param string $processUid
     * @param string $variable
     * @return boolean | string
     * 
     * @see ProcessMaker\BusinessModel\Variable->delete()
     * @link https://wiki.processmaker.com/3.2/Variables#Managing_Variables
     */
    public function isUsed($processUid, $variable)
    {
        $result = ModelDynaform::getByProUid($processUid);
        if (empty($result)) {
            return false;
        }
        foreach ($result as $row) {
            $json = G::json_decode($row->DYN_CONTENT);
            if ($this->jsoni($json, $variable)) {
                return $row->DYN_UID;
            }
        }
        return false;
    }

    private function jsoni(&$json, $variable)
    {
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                if ($this->jsoni($value, $variable)) {
                    return true;
                }
            }
            if (!$sw1 && !$sw2) {
                if ($key === "variable" && $json->variable === $variable["var_name"]) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * This funtion will get the DYN_CONTENT from the dynaform then
     * Get the field and the properties defined, it's considerate the sub-forms
     *
     * @param string $dynUid
     * @param string $fieldId
     * @param string $proUid
     * @param array $and
     * @return object
     * @see \ProcessMaker\BusinessModel\Variable::executeSqlControl()
     */
    public function searchField($dynUid, $fieldId, $proUid = null, array $and = [])
    {
        //get pro_uid if empty
        if (empty($proUid)) {
            $a = new Criteria("workflow");
            $a->addSelectColumn(DynaformPeer::PRO_UID);
            $a->add(DynaformPeer::DYN_UID, $dynUid, Criteria::EQUAL);
            $ds = DynaformPeer::doSelectRS($a);
            $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $ds->next();
            $row = $ds->getRow();
            $proUid = $row["PRO_UID"];
        }
        //get dynaforms
        $a = new Criteria("workflow");
        $a->addSelectColumn(DynaformPeer::DYN_UID);
        $a->addSelectColumn(DynaformPeer::DYN_CONTENT);
        $a->add(DynaformPeer::PRO_UID, $proUid, Criteria::EQUAL);
        $ds = DynaformPeer::doSelectRS($a);
        $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);

        $json = new stdClass();
        $dynaforms = [];
        while ($ds->next()) {
            $row = $ds->getRow();
            if ($row["DYN_UID"] === $dynUid) {
                $json = G::json_decode($row["DYN_CONTENT"]);
            } else {
                $dynaforms[] = G::json_decode($row["DYN_CONTENT"]);
            }
        }
        //get  subforms
        $fields = $this->jsonsf2($json, "form", "type");
        foreach ($fields as $key => $value) {
            if ($json->items[0]->id !== $value->id) {
                foreach ($dynaforms as $dynaform) {
                    if ($value->id === $dynaform->items[0]->id) {
                        $form = $dynaform->items[0];
                        $this->jsonReplace($json, $value->id, "id", $form);
                    }
                }
            }
        }
        $this->completeAdditionalHelpInformationOnControls($json);
        return $this->jsonsf($json, $fieldId, "id", $and);
    }

    public function searchFieldByName($dyn_uid, $name)
    {
        $a = new Criteria("workflow");
        $a->addSelectColumn(DynaformPeer::DYN_CONTENT);
        $a->add(DynaformPeer::DYN_UID, $dyn_uid, Criteria::EQUAL);
        $ds = ProcessPeer::doSelectRS($a);
        $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $ds->next();
        $row = $ds->getRow();
        $json = G::json_decode($row["DYN_CONTENT"]);
        $this->jsonr($json);
        return $this->jsonsf($json, $name, "name");
    }

    /**
     * Replace data field with custom variables.
     * @param string $sql
     * @param array $data
     * @return string
     */
    private function replaceDataField(string $sql, array $data): string
    {
        $textParse = '';
        $dbEngine = 'mysql';
        $start = 0;

        $prefix = '\?';
        $pattern = '/\@(?:([' . $prefix . 'Qq\!])([a-zA-Z\_]\w*)|([a-zA-Z\_][\w\-\>\:]*)\(((?:[^\\\\\)]*(?:[\\\\][\w\W])?)*)\))((?:\s*\[[\'"]?\w+[\'"]?\])+|\-\>([a-zA-Z\_]\w*))?/';
        $result = preg_match_all($pattern, $sql, $match, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);
        for ($r = 0; $result !== false && $r < $result; $r++) {
            $dataGlobal = array_merge($this->fieldsAppData, $data);
            if (!isset($dataGlobal[$match[2][$r][0]])) {
                $dataGlobal[$match[2][$r][0]] = '';
            }
            if (!is_array($dataGlobal[$match[2][$r][0]])) {
                $textParse = $textParse . substr($sql, $start, $match[0][$r][1] - $start);
                $start = $match[0][$r][1] + strlen($match[0][$r][0]);
                if (($match[1][$r][0] == '?') && (isset($dataGlobal[$match[2][$r][0]]))) {
                    $textParse = $textParse . $dataGlobal[$match[2][$r][0]];
                    continue;
                }
            }
        }
        $textParse = $textParse . substr($sql, $start);

        $sqlResult = G::replaceDataField($textParse, $data, $dbEngine, false);
        return $sqlResult;
    }

    /**
     * complete additional help information on controls.
     * @param object $json
     */
    private function completeAdditionalHelpInformationOnControls(&$json)
    {
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->completeAdditionalHelpInformationOnControls($value);
            }
            if (!$sw1 && !$sw2) {
                if ($key === "type" && ($value === "grid")) {
                    foreach ($json->columns as $column) {
                        $column->gridName = $json->id;
                    }
                }
            }
        }
    }

    /**
     * Gets an element within an object that represents the dynaform. Search is 
     * done by 'id', 'property' and additional filters.
     * @param object $json
     * @param string $id
     * @param string $for
     * @param array $and
     * @return mixed
     */
    private function jsonsf(&$json, string $id, string $for = "id", array $and = [])
    {
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $val = $this->jsonsf($value, $id, $for, $and);
                if ($val !== null) {
                    return $val;
                }
            }
            if (!$sw1 && !$sw2) {
                $filter = empty($and);
                foreach ($and as $keyAnd => $valueAnd) {
                    $filter = isset($json->{$keyAnd}) && $json->{$keyAnd} === $valueAnd;
                    if ($filter === false) {
                        break;
                    }
                }
                if ($key === $for && $id === $value && $filter) {
                    return $json;
                }
            }
        }
        return null;
    }

    /**
     * You obtain an array of elements according to search criteria.
     *
     * @param object $json
     * @param string $id
     * @param string $for
     * @return array
     */
    private function jsonsf2(&$json, $id, $for = "id")
    {
        $result = array();
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $fields = $this->jsonsf2($value, $id, $for);
                foreach ($fields as $field) {
                    $result[] = $field;
                }
            }
            if (!$sw1 && !$sw2) {
                if ($key === $for && $id === $value) {
                    $result[] = $json;
                }
            }
        }
        return $result;
    }

    public function downloadLanguage($dyn_uid, $lang)
    {
        if ($lang === "en") {
            $a = new Criteria("workflow");
            $a->addSelectColumn(DynaformPeer::DYN_CONTENT);
            $a->add(DynaformPeer::DYN_UID, $dyn_uid, Criteria::EQUAL);
            $ds = ProcessPeer::doSelectRS($a);
            $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $ds->next();
            $row = $ds->getRow();
            if ($row["DYN_CONTENT"] !== null && $row["DYN_CONTENT"] !== "") {
                $json = \G::json_decode($row["DYN_CONTENT"]);
                $this->jsonl($json);
            }
            $string = "";
            $string = $string . "msgid \"\"\n";
            $string = $string . "msgstr \"\"\n";
            $string = $string . "\"Project-Id-Version: PM 4.0.1\\n\"\n";
            $string = $string . "\"POT-Creation-Date: \\n\"\n";
            $string = $string . "\"PO-Revision-Date: 2010-12-02 11:44+0100 \\n\"\n";
            $string = $string . "\"Last-Translator: Colosa<colosa@colosa.com>\\n\"\n";
            $string = $string . "\"Language-Team: Colosa Developers Team <developers@colosa.com>\\n\"\n";
            $string = $string . "\"MIME-Version: 1.0\\n\"\n";
            $string = $string . "\"Content-Type: text/plain; charset=utf-8\\n\"\n";
            $string = $string . "\"Content-Transfer_Encoding: 8bit\\n\"\n";
            $string = $string . "\"X-Poedit-Language: English\\n\"\n";
            $string = $string . "\"X-Poedit-Country: United States\\n\"\n";
            $string = $string . "\"X-Poedit-SourceCharset: utf-8\\n\"\n";
            $string = $string . "\"Content-Transfer-Encoding: 8bit\\n\"\n";
            $string = $string . "\"File-Name: processmaker.en.po\\n\"\n\n";

            $n = count($this->dyn_conten_labels);
            for ($i = 0; $i < $n; $i++) {
                $string = $string . "msgid \"" . $this->dyn_conten_labels[$i] . "\"\n";
                $string = $string . "msgstr \"" . $this->dyn_conten_labels[$i] . "\"\n\n";
            }
            return array("labels" => $string, "lang" => $lang);
        } else {
            $a = new Criteria("workflow");
            $a->addSelectColumn(DynaformPeer::DYN_LABEL);
            $a->add(DynaformPeer::DYN_UID, $dyn_uid, Criteria::EQUAL);
            $ds = ProcessPeer::doSelectRS($a);
            $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);
            $ds->next();
            $row = $ds->getRow();
            $data = G::json_decode($row["DYN_LABEL"]);
            $string = "";
            $string = $string . "msgid \"\"\n";
            $string = $string . "msgstr \"\"\n";
            foreach ($data->{$lang} as $key => $value) {
                if (is_string($value)) {
                    $string = $string . "\"" . $key . ": " . $value . "\\n\"\n";
                }
            }
            $string = $string . "\n";
            foreach ($data->{$lang}->Labels as $key => $value) {
                $string = $string . "msgid \"" . $value->msgid . "\"\n";
                $string = $string . "msgstr \"" . $value->msgstr . "\"\n\n";
            }
            return array("labels" => $string, "lang" => $lang);
        }
    }

    public function uploadLanguage($dyn_uid)
    {
        if (!isset($_FILES["LANGUAGE"])) {
            throw new Exception(G::LoadTranslation("ID_ERROR_UPLOADING_FILENAME"));
        }
        if (pathinfo($_FILES["LANGUAGE"]["name"], PATHINFO_EXTENSION) != "po") {
            throw new Exception(G::LoadTranslation("ID_FILE_UPLOAD_INCORRECT_EXTENSION"));
        }
        $translation = array();

        $i18n = new i18n_PO($_FILES["LANGUAGE"]["tmp_name"]);
        $i18n->readInit();
        while ($rowTranslation = $i18n->getTranslation()) {
            array_push($translation, $rowTranslation);
        }
        $name = $_FILES["LANGUAGE"]["name"];
        $name = explode(".", $name);
        if (isset($name[1]) && isset($name[2]) && $name[1] . "." . $name[2] === "en.po") {
            return;
        }
        $content = $i18n->getHeaders();
        $content["File-Name"] = $_FILES["LANGUAGE"]["name"];
        $content["Labels"] = $translation;

        $con = Propel::getConnection(DynaformPeer::DATABASE_NAME);
        $con->begin();
        $oPro = DynaformPeer::retrieveByPk($dyn_uid);

        $dyn_labels = new stdClass();
        if ($oPro->getDynLabel() !== null && $oPro->getDynLabel() !== "") {
            $dyn_labels = G::json_decode($oPro->getDynLabel());
        }
        $dyn_labels->{$name[count($name) - 2]} = $content;

        $oPro->setDynLabel(G::json_encode($dyn_labels));
        $oPro->save();
        $con->commit();
    }

    public function listLanguage($dyn_uid)
    {
        $list = array();
        $a = new Criteria("workflow");
        $a->addSelectColumn(DynaformPeer::DYN_LABEL);
        $a->add(DynaformPeer::DYN_UID, $dyn_uid, Criteria::EQUAL);
        $ds = ProcessPeer::doSelectRS($a);
        $ds->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $ds->next();
        $row = $ds->getRow();

        if ($row["DYN_LABEL"] === null || $row["DYN_LABEL"] === "") {
            return $list;
        }

        $dyn_label = \G::json_decode($row["DYN_LABEL"]);
        foreach ($dyn_label as $key => $value) {
            array_push($list, array(
                "Lang" => $key,
                "File-Name" => isset($value->{"File-Name"}) ? $value->{"File-Name"} : "",
                "Project-Id-Version" => isset($value->{"Project-Id-Version"}) ? $value->{"Project-Id-Version"} : "",
                "POT-Creation-Date" => isset($value->{"POT-Creation-Date"}) ? $value->{"POT-Creation-Date"} : "",
                "PO-Revision-Date" => isset($value->{"PO-Revision-Date"}) ? $value->{"PO-Revision-Date"} : "",
                "Last-Translator" => isset($value->{"Last-Translator"}) ? $value->{"Last-Translator"} : "",
                "Language-Team" => isset($value->{"Language-Team"}) ? $value->{"Language-Team"} : "",
                "MIME-Version" => isset($value->{"MIME-Version"}) ? $value->{"MIME-Version"} : "",
                "Content-Type" => isset($value->{"Content-Type"}) ? $value->{"Content-Type"} : "",
                "Content-Transfer_Encoding" => isset($value->{"Content-Transfer_Encoding"}) ? $value->{"Content-Transfer_Encoding"} : "",
                "X-Poedit-Language" => isset($value->{"X-Poedit-Language"}) ? $value->{"X-Poedit-Language"} : "",
                "X-Poedit-Country" => isset($value->{"X-Poedit-Country"}) ? $value->{"X-Poedit-Country"} : "",
                "X-Poedit-SourceCharset" => isset($value->{"X-Poedit-SourceCharset"}) ? $value->{"X-Poedit-SourceCharset"} : "",
                "Content-Transfer-Encoding" => isset($value->{"Content-Transfer-Encoding"}) ? $value->{"Content-Transfer-Encoding"} : ""
            ));
        }
        return $list;
    }

    private $dyn_conten_labels = array();

    private function jsonl(&$json)
    {
        foreach ($json as $key => $value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->jsonl($value);
            }
            if (!$sw1 && !$sw2) {
                if ($key === "label") {
                    array_push($this->dyn_conten_labels, $json->label);
                }
                if ($key === "hint") {
                    array_push($this->dyn_conten_labels, $json->hint);
                }
                if ($key === "placeholder") {
                    array_push($this->dyn_conten_labels, $json->placeholder);
                }
                if ($key === "validateMessage") {
                    array_push($this->dyn_conten_labels, $json->validateMessage);
                }
                if ($key === "alternateText") {
                    array_push($this->dyn_conten_labels, $json->alternateText);
                }
                if ($key === "comment") {
                    array_push($this->dyn_conten_labels, $json->comment);
                }
                if ($key === "alt") {
                    array_push($this->dyn_conten_labels, $json->alt);
                }
            }
        }
    }

    public function deleteLanguage($dyn_uid, $lang)
    {
        $con = Propel::getConnection(DynaformPeer::DATABASE_NAME);
        $con->begin();
        $oPro = DynaformPeer::retrieveByPk($dyn_uid);

        $dyn_labels = \G::json_decode($oPro->getDynLabel());
        unset($dyn_labels->{$lang});

        $oPro->setDynLabel(G::json_encode($dyn_labels));
        $oPro->save();
        $con->commit();
    }

    /**
     * Remove the posted values that are not in the definition of Dynaform.
     * 
     * @param array $post
     * 
     * @return array
     */
    public function validatePost($post = [])
    {
        $result = array();
        $previusFunction = $this->onPropertyRead;
        $this->onPropertyRead = function ($json, $key, $value) use (&$post) {
            if ($key === "type" && isset($json->variable) && !empty($json->variable)) {
                //clears the data in the appData for grids
                $isThereIdIntoPost = array_key_exists($json->id, $post);
                $isThereIdIntoFields = array_key_exists($json->id, $this->fields);
                if ($json->type === 'grid' && !$isThereIdIntoPost && $isThereIdIntoFields) {
                    $post[$json->variable] = [[]];
                }
                //validate 'protectedValue' property
                if (isset($json->protectedValue) && $json->protectedValue === true) {
                    if (isset($this->fields[$json->variable])) {
                        $post[$json->variable] = $this->fields[$json->variable];
                    }
                    if (isset($this->fields[$json->variable . "_label"])) {
                        $post[$json->variable . "_label"] = $this->fields[$json->variable . "_label"];
                    }
                }
                //validator data
                $validatorClass = ValidatorFactory::createValidatorClass($json->type, $json);
                if ($validatorClass !== null) {
                    $validatorClass->validatePost($post);
                }
            }
        };
        $json = G::json_decode($this->record["DYN_CONTENT"]);
        $this->jsonr($json);
        $this->onPropertyRead = $previusFunction;
        return $post;
    }

    private function clientToken()
    {
        $client = $this->getClientCredentials();
        $authCode = $this->getAuthorizationCode($client);


        $request = array(
            'grant_type' => 'authorization_code',
            'code' => $authCode
        );
        $server = array(
            'REQUEST_METHOD' => 'POST'
        );
        $headers = array(
            "PHP_AUTH_USER" => $client['CLIENT_ID'],
            "PHP_AUTH_PW" => $client['CLIENT_SECRET'],
            "Content-Type" => "multipart/form-data;",
            "Authorization" => "Basic " . base64_encode($client['CLIENT_ID'] . ":" . $client['CLIENT_SECRET'])
        );

        $request = new \OAuth2\Request(array(), $request, array(), array(), array(), $server, null, $headers);
        $oauthServer = new \ProcessMaker\Services\OAuth2\Server();
        $response = $oauthServer->getServer()->handleTokenRequest($request);
        $clientToken = $response->getParameters();
        $clientToken["client_id"] = $client['CLIENT_ID'];
        $clientToken["client_secret"] = $client['CLIENT_SECRET'];

        return $clientToken;
    }

    protected $clientId = 'x-pm-local-client';

    protected function getClientCredentials()
    {
        $oauthQuery = new ProcessMaker\Services\OAuth2\PmPdo($this->getDsn());
        return $oauthQuery->getClientDetails($this->clientId);
    }

    protected function getAuthorizationCode($client)
    {
        \ProcessMaker\Services\OAuth2\Server::setDatabaseSource($this->getDsn());
        \ProcessMaker\Services\OAuth2\Server::setPmClientId($client['CLIENT_ID']);

        $oauthServer = new \ProcessMaker\Services\OAuth2\Server();
        $userId = $_SESSION['USER_LOGGED'];
        $authorize = true;
        $_GET = array_merge($_GET, array(
            'response_type' => 'code',
            'client_id' => $client['CLIENT_ID'],
            'scope' => implode(' ', $oauthServer->getScope())
        ));

        $response = $oauthServer->postAuthorize($authorize, $userId, true, array('USER_LOGGED' => $_SESSION['USER_LOGGED']));
        $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=') + 5, 40);

        return $code;
    }

    private function getDsn()
    {
        list($host, $port) = strpos(DB_HOST, ':') !== false ? explode(':', DB_HOST) : array(DB_HOST, '');
        $port = empty($port) ? '' : ";port=$port";
        $dsn = DB_ADAPTER . ':host=' . $host . ';dbname=' . DB_NAME . $port;

        return array('dsn' => $dsn, 'username' => DB_USER, 'password' => DB_PASS);
    }

    /**
     * Returns the value converted to string if it is not null.
     *
     * @param string $string
     * @return string
     */
    private function toStringNotNullValues($value)
    {
        if (is_null($value)) {
            return "";
        }
        return (string) $value;
    }

    /**
     * Get grids and fields
     *
     * @param mixed $form
     * @param bool  $flagGridAssocToVar
     *
     * @return array Return an array, FALSE otherwise
     */
    public static function getGridsAndFields($form, $flagGridAssocToVar = true)
    {
        try {
            if (!is_object($form)) {
                //This code it runs only in the first call to the method
                $object = \ProcessMaker\Util\Common::stringToJson($form);

                if ($object !== false) {
                    $form = $object->items[0];
                } else {
                    return false;
                }
            }

            $arrayGrid = [];

            foreach ($form->items as $value) {
                foreach ($value as $value2) {
                    $field = $value2;

                    if (isset($field->type)) {
                        switch ($field->type) {
                            case 'grid':
                                $flagInsert = ($flagGridAssocToVar)? (isset($field->var_uid) && $field->var_uid != '' && isset($field->variable) && $field->variable != '') : true;

                                if ($flagInsert) {
                                    $arrayGrid[$field->id] = $field;
                                }
                                break;
                            case 'form':
                                $arrayGrid = array_merge(
                                    $arrayGrid,
                                    self::getGridsAndFields($field, $flagGridAssocToVar)
                                );
                                break;
                        }
                    }
                }
            }

            //Return
            return $arrayGrid;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns a string containing the JSON representation of the object
     *
     * @param object $json The object being encoded
     *
     * @return string Returns a string
     */
    public function json_encode($json)
    {
        $jsonData = G::json_encode($json);

        if ($jsonData === false) {
            $jsonLastError = json_last_error();
            $jsonLastErrorMsg = json_last_error_msg();
            $token = time();

            $obj = new stdClass();
            $obj->type = 'panel';
            $obj->id = '__json_encode_error__';
            $obj->content = '
                <div style="border: 1px solid #9A3A1F; background: #F7DBCE; color: #8C0000; font:0.9em arial, verdana, helvetica, sans-serif;">
                <div style="margin: 0.5em;">
                <img src="/images/documents/_log_error.png" alt="" style="margin-right: 0.8em; vertical-align: middle;" />' .
                G::LoadTranslation('ID_EXCEPTION_LOG_INTERFAZ', [$token]) .
                '</div></div>';
            $obj->border = 0;

            $json->items[0]->items = [[$obj]];

            $jsonData = G::json_encode($json);

            //Log
            $message = 'JSON encoded string error ' . $jsonLastError . ': ' . $jsonLastErrorMsg;
            $context = [
                'token' => $token,
                'projectUid' => $this->record['PRO_UID'],
                'dynaFormUid' => $this->record['DYN_UID']
            ];
            Log::channel(':RenderDynaForm')->error($message, Bootstrap::context($context));
        }

        //Return
        return $jsonData;
    }
    
    public function getLeaveCaseWarning()
    {
        return defined("LEAVE_CASE_WARNING") ? LEAVE_CASE_WARNING : 0;
    }

    /**
     * Unset a json property from the following controls: text, textarea, dropdown,
     * checkbox, checkgroup, radio, datetime, suggest, hidden, file, grid.
     * @param stdClass $json
     * @param string $property
     * @return void
     */
    public function jsonUnsetProperty(&$json, $property)
    {
        if (empty($json)) {
            return;
        }
        foreach ($json as $key => &$value) {
            $sw1 = is_array($value);
            $sw2 = is_object($value);
            if ($sw1 || $sw2) {
                $this->jsonUnsetProperty($value, $property);
            }
            if (!$sw1 && !$sw2) {
                if ($key === "type" && (
                        $value === "text" ||
                        $value === "textarea" ||
                        $value === "dropdown" ||
                        $value === "checkbox" ||
                        $value === "checkgroup" ||
                        $value === "radio" ||
                        $value === "datetime" ||
                        $value === "suggest" ||
                        $value === "hidden" ||
                        $value === "file" ||
                        $value === "grid"
                )) {
                    if ($value === "grid" && $property === "data") {
                        $json->{$property} = [];
                    } else {
                        unset($json->{$property});
                    }
                }
            }
        }
    }

    /**
     * Returns an array with the basic fields of the Exception class. It isn't returned any extra fields information
     * of any derivated Exception class. This way we have a lightweight version of the exception data that can
     * be used when logging the exception, for example.
     * @param $e an Exception class derivate
     * @param $sql query that was executed when the exception was generated
     * @return array
     */
    private function basicExceptionData($e, $sql)
    {
        $result = [];
        $result['code'] = $e->getCode();
        $result['file'] = $e->getFile();
        $result['line'] = $e->getLine();
        $result['message'] = $e->getMessage();
        $result['nativeQuery'] = $sql;

        if (property_exists($e, 'nativeError')) {
            $result['nativeError'] = $e->getNativeError();
        }

        if (property_exists($e, 'userInfo')) {
            $result['userInfo'] = $e->getUserInfo();
        }

        return $result;
    }

    /**
     * Get the string variable for google maps
     * 
     * @return string
     */
    private function getTheStringVariableForGoogleMaps()
    {
        $config = Bootstrap::getSystemConfiguration();
        $googleMaps = new stdClass();
        $googleMaps->key = $config['google_map_api_key'];
        $googleMaps->signature = $config['google_map_signature'];
        $result = 'var googleMaps = ' . G::json_encode($googleMaps) . ';';
        return $result;
    }    

    /**
     * Get last query error.
     * 
     * @return object
     * @see ProcessMaker\BusinessModel\Variable->executeSqlControl()
     */
    public function getLastQueryError()
    {
        return $this->lastQueryError;
    }

    /**
     * Clear last query error.
     * 
     * @see ProcessMaker\BusinessModel\Variable->executeSqlControl()
     */
    public function clearLastQueryError()
    {
        $this->lastQueryError = null;
    }

    /**
     * Get session message.
     * 
     * @return string
     * 
     * @see PmDynaform->printEdit()
     * @see PmDynaform->printABE()
     * @link https://wiki.processmaker.com/3.1/Multiple_File_Uploader#File_Extensions
     */
    public function getSessionMessage()
    {
        $message = "";
        if (isset($_SESSION['G_MESSAGE_TYPE']) && isset($_SESSION['G_MESSAGE'])) {
            $color = "green";
            if ($_SESSION['G_MESSAGE_TYPE'] === "ERROR") {
                $color = "red";
            }
            if ($_SESSION['G_MESSAGE_TYPE'] === "WARNING") {
                $color = "#C3C380";
            }
            if ($_SESSION['G_MESSAGE_TYPE'] === "INFO") {
                $color = "green";
            }
            $message = "<div style='background-color:" . $color . ";color: white;padding: 1px 2px 1px 5px;' class='userGroupTitle'>" . $_SESSION['G_MESSAGE_TYPE'] . ": " . $_SESSION['G_MESSAGE'] . "</div>";
            unset($_SESSION['G_MESSAGE_TYPE']);
            unset($_SESSION['G_MESSAGE']);
        }
        return $message;
    }

    /**
     * Get session message for supervisor.
     * 
     * @return string
     * 
     * @see PmDynaform->printEditSupervisor();
     * @link https://wiki.processmaker.com/3.1/Multiple_File_Uploader#File_Extensions
     */
    public function getSessionMessageForSupervisor()
    {
        $message = "";
        if (isset($_SESSION["G_MESSAGE_TYPE"]) && isset($_SESSION["G_MESSAGE"])) {
            $message = "<div style=\"margin: 1.2em; border: 1px solid #3C763D; padding: 0.5em; background: #B2D3B3;\"><strong>" . G::LoadTranslation("ID_INFO") . "</strong>: " . $_SESSION["G_MESSAGE"] . "</div>";
            unset($_SESSION["G_MESSAGE_TYPE"]);
            unset($_SESSION["G_MESSAGE"]);
        }
        return $message;
    }

    /**
     * This adds a new definition on the json dynaform
     * @param json $json
     *
     * @link https://wiki.processmaker.com/3.0/Grid_Control
     * @see workflow/engine/classes/PmDynaform->jsonr
     */
    public function setDataSchema($json, $appDataVariables)
    {
        foreach ($json->data as $key => $value) {
            $columnsData = [];
            foreach ($json->columns as $keyData => $valueData) {
                foreach ($appDataVariables as $keyAppData => $valueAppData) {
                    if (array_key_exists($valueData->id, $valueAppData) || array_key_exists($valueData->id . "_label",
                            $valueAppData) || array_key_exists($valueData->name,
                            $valueAppData) || array_key_exists($valueData->name . "_label", $valueAppData)) {
                        array_push($columnsData, ["defined" => true]);
                        break;
                    } else {
                        array_push($columnsData, ["defined" => false]);
                        break;
                    }
                }
            }
            $json->dataSchema[$key] = $columnsData;
        }
    }

    /**
     * Sets the dependentOptions property for datetime control, if it contains dependent fields.
     * @param stdClass $json
     * @param array $fields
     * @return void
     */
    private function setDependentOptionsForDatetime(stdClass &$json, array $fields = []): void
    {
        if (!isset($json->type)) {
            return;
        }
        if ($json->type !== 'datetime') {
            return;
        }
        $json->dependentOptions = '';
        $backup = $this->onAfterPropertyRead;
        $properties = [
            'defaultDate' => $json->defaultDate,
            'minDate' => $json->minDate,
            'maxDate' => $json->maxDate
        ];
        $this->onAfterPropertyRead = function(stdClass &$json, $key, $value) use($backup, $properties) {
            if (isset($json->type) && $json->type === 'datetime' && $key === "dependentOptions") {
                $json->dependentOptions = new stdClass();
                foreach ($properties as $property => $value) {
                    if (is_string($value) && in_array(substr($value, 0, 2), self::$prefixs)) {
                        $json->dependentOptions->{$property} = $value;
                    }
                }
                $this->onAfterPropertyRead = $backup;
            }
        };
    }

    /**
     * Get html navigation bar for steps to revise.
     * @param string $appUid
     * @param string $uid
     * @param int $delIndex
     * @return string
     */
    public static function navigationBarForStepsToRevise(string $appUid, string $uid, int $delIndex): string
    {
        $navbar = '';
        $cases = new Cases();
        $steps = $cases->getAllUrlStepsToRevise($appUid, $delIndex);
        $n = count($steps);
        foreach ($steps as $key => $step) {
            if ($step['uid'] === $uid) {
                $previousLabel = '';
                $previousUrl = '';
                $nextLabel = '';
                $nextUrl = '';
                if ($key - 1 >= 0) {
                    $previousLabel = G::LoadTranslation('ID_PREVIOUS');
                    $previousUrl = $steps[$key - 1]['url'];
                }
                if ($key + 1 < $n) {
                    $nextLabel = G::LoadTranslation('ID_NEXT');
                    $nextUrl = $steps[$key + 1]['url'];
                }
                if (empty($nextUrl)) {
                    $nextLabel = G::LoadTranslation('ID_FINISH');
                    $nextUrl = 'javascript:if(window.parent && window.parent.parent){window.parent.parent.postMessage("redirect=MyCases","*");}';
                }
                //this condition modify the next Url for submit action
                if ($step['type'] === 'DYNAFORM') {
                    $nextUrl = 'javascript:document.querySelector(".pmdynaform-container .pmdynaform-form").submit();';
                }
                $navbar = "<div style='width:100%;padding:0px 10px 0px 10px;margin:15px 0px 0px 0px;'>" .
                        "    <img src='/images/bulletButtonLeft.gif' style='float:left;'>&nbsp;" .
                        "    <a href='{$previousUrl}' style='float:left;font-size:12px;line-height:1;margin:0px 0px 1px 5px;text-decoration:none;!important;'>" .
                        "    {$previousLabel}" .
                        "    </a>" .
                        "    <img src='/images/bulletButton.gif' style='float:right;'>&nbsp;" .
                        "    <a href='{$nextUrl}' style='float:right;font-size:12px;line-height:1;margin:0px 5px 1px 0px;text-decoration:none;!important;'>" .
                        "    {$nextLabel}" .
                        "    </a>" .
                        "</div>";
            }
        }
        return $navbar;
    }
}
