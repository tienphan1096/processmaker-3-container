<?php

namespace ProcessMaker\BusinessModel;

use AdditionalTables;
use Cases as ClassesCases;
use Exception;
use G;
use PmDynaform;
use ProcessMaker\Model\AdditionalTables as AT;
use ProcessMaker\Model\Fields;
use ProcessMaker\Model\ProcessVariables;
use ProcessMaker\Util\Common;

class Variable
{
    private $variableTypes = ['string', 'integer', 'float', 'boolean', 'datetime', 'grid', 'array', 'file', 'multiplefile', 'object'];
    public static $varTypesValues = [
        'string' => 1, 
        'integer' => 2,
        'float' => 3,
        'boolean' => 4,
        'datetime' => 5,
        'grid' => 6,
        'array' => 7,
        'file' => 8,
        'multiplefile' => 9,
        'object'  => 10
    ];

    /**
     * Get the variables types accepted
     * 
     * @return array 
     */
    public function getVariableTypes()
    {
        return $this->variableTypes;
    }

    /**
     * Create Variable for a Process
     *
     * @param string $proUid Unique id of Process
     * @param array $arrayData Data
     *
     * @return array, return data of the new Variable created
     * @throws Exception
     */
    public function create($proUid, array $arrayData)
    {
        try {
            $attributes = [];
            // Verify the process
            $proId = Validator::proUid($proUid, '$prj_uid');
            $attributes["PRJ_UID"] = $proUid;
            $attributes["PRO_ID"] = $proId;
            // Get the unique varUid
            $varUid = Common::generateUID();
            $attributes["VAR_UID"] = $varUid;
            // Get the attributes
            $arrayData = array_change_key_case($arrayData, CASE_UPPER);
            // Validate properties that cannot be empty
            if (!empty($arrayData["VAR_NAME"])) {
                $attributes["VAR_NAME"] = $arrayData["VAR_NAME"];
            } else {
                throw new Exception(G::LoadTranslation("ID_CAN_NOT_BE_NULL", ['$var_name']));
            }
            if (!empty($arrayData["VAR_FIELD_TYPE"])) {
                $attributes["VAR_FIELD_TYPE"] = $this->validateVarFieldType($arrayData["VAR_FIELD_TYPE"]);
                $attributes["VAR_FIELD_TYPE_ID"] = self::$varTypesValues[$arrayData["VAR_FIELD_TYPE"]];
            } else {
                throw new Exception(G::LoadTranslation("ID_CAN_NOT_BE_NULL", ['$var_field_type']));
            }
            if (!empty($arrayData["VAR_LABEL"])) {
                $attributes["VAR_LABEL"] = $arrayData["VAR_LABEL"];
            } else {
                throw new Exception(G::LoadTranslation("ID_CAN_NOT_BE_NULL", ['$var_label']));
            }
            if (!empty($arrayData["VAR_FIELD_SIZE"])) {
                $attributes["VAR_FIELD_SIZE"] = $arrayData["VAR_FIELD_SIZE"];
            }
            if (!empty($arrayData["VAR_DBCONNECTION"])) {
                $attributes["VAR_DBCONNECTION"] = $arrayData["VAR_DBCONNECTION"];
            }
            if (!empty($arrayData["VAR_SQL"])) {
                $attributes["VAR_SQL"] = $arrayData["VAR_SQL"];
            }
            if (!empty($arrayData["VAR_NULL"])) {
                $attributes["VAR_NULL"] = $arrayData["VAR_NULL"];
            }
            if (!empty($arrayData["VAR_DEFAULT"])) {
                $attributes["VAR_DEFAULT"] = $arrayData["VAR_DEFAULT"];
            }
            if (!empty($arrayData["VAR_ACCEPTED_VALUES"])) {
                $attributes["VAR_ACCEPTED_VALUES"] = G::json_encode($arrayData["VAR_ACCEPTED_VALUES"]);
            }
            if (!empty($arrayData["INP_DOC_UID"])) {
                $attributes["INP_DOC_UID"] = $arrayData["INP_DOC_UID"];
            }
            // Additional validations over the data
            $this->existsName($proUid, $arrayData["VAR_NAME"], "");
            $this->throwExceptionFieldDefinition($arrayData);
            // Register the new variable
            $processVariables = ProcessVariables::create($attributes);
            // Return theriable created
            $variable = $this->getVariable($proUid, $varUid);
            return $variable;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Update Variable
     *
     * @param string $processUid Unique id of Process
     * @param string $variableUid Unique id of Variable
     * @param array $arrayData Data
     *
     * @return array,return data of the Variable updated
     * @throws Exception
     */
    public function update($processUid, $variableUid, $arrayData)
    {
        try {
            //Verify data
            Validator::proUid($processUid, '$prj_uid');
            $arrayData = array_change_key_case($arrayData, CASE_UPPER);
            $this->throwExceptionFieldDefinition($arrayData);

            //Update
            $cnn = \Propel::getConnection("workflow");
            try {
                $variable = \ProcessVariablesPeer::retrieveByPK($variableUid);
                $dbConnection = \DbSourcePeer::retrieveByPK($variable->getVarDbconnection(), $variable->getPrjUid());

                $oldVariable = array(
                    "VAR_UID" => $variable->getVarUid(),
                    "VAR_NAME" => $variable->getVarName(),
                    "VAR_FIELD_TYPE" => $variable->getVarFieldType(),
                    "VAR_DBCONNECTION" => $variable->getVarDbconnection(),
                    "VAR_DBCONNECTION_LABEL" => $dbConnection !== null ? '[' . $dbConnection->getDbsServer() . ':' . $dbConnection->getDbsPort() . '] ' . $dbConnection->getDbsType() . ': ' . $dbConnection->getDbsDatabaseName() : 'PM Database',
                    "VAR_SQL" => $variable->getVarSql(),
                    "VAR_ACCEPTED_VALUES" => $variable->getVarAcceptedValues()
                );
                if ($variable->validate()) {
                    $cnn->begin();
                    if (isset($arrayData["VAR_NAME"])) {
                        $this->existsName($processUid, $arrayData["VAR_NAME"], $variableUid);
                        $variable->setVarName($arrayData["VAR_NAME"]);
                    }
                    if (isset($arrayData["VAR_FIELD_TYPE"])) {
                        $arrayData["VAR_FIELD_TYPE"] = $this->validateVarFieldType($arrayData["VAR_FIELD_TYPE"]);
                        $variable->setVarFieldType($arrayData["VAR_FIELD_TYPE"]);
                        $fielTypeId = self::$varTypesValues[$arrayData["VAR_FIELD_TYPE"]];
                        $variable->setVarFieldTypeId($fielTypeId);
                    }
                    if (isset($arrayData["VAR_FIELD_SIZE"])) {
                        $variable->setVarFieldSize($arrayData["VAR_FIELD_SIZE"]);
                    }
                    if (isset($arrayData["VAR_LABEL"])) {
                        $variable->setVarLabel($arrayData["VAR_LABEL"]);
                    }
                    if (isset($arrayData["VAR_DBCONNECTION"])) {
                        $variable->setVarDbconnection($arrayData["VAR_DBCONNECTION"]);
                    }
                    if (isset($arrayData["VAR_SQL"])) {
                        $variable->setVarSql($arrayData["VAR_SQL"]);
                    }
                    if (isset($arrayData["VAR_NULL"])) {
                        $variable->setVarNull($arrayData["VAR_NULL"]);
                    }
                    if (isset($arrayData["VAR_DEFAULT"])) {
                        $variable->setVarDefault($arrayData["VAR_DEFAULT"]);
                    }
                    if (isset($arrayData["VAR_ACCEPTED_VALUES"])) {
                        $encodeAcceptedValues = G::json_encode($arrayData["VAR_ACCEPTED_VALUES"]);
                        $variable->setVarAcceptedValues($encodeAcceptedValues);
                    }
                    if (isset($arrayData["INP_DOC_UID"])) {
                        $variable->setInpDocUid($arrayData["INP_DOC_UID"]);
                    }
                    $variable->save();
                    $cnn->commit();
                    //update dynaforms
                    $dbConnection = \DbSourcePeer::retrieveByPK($variable->getVarDbconnection(), $variable->getPrjUid());
                    $newVariable = array(
                        "VAR_UID" => $variable->getVarUid(),
                        "VAR_NAME" => $variable->getVarName(),
                        "VAR_FIELD_TYPE" => $variable->getVarFieldType(),
                        "VAR_DBCONNECTION" => $variable->getVarDbconnection(),
                        "VAR_DBCONNECTION_LABEL" => $dbConnection !== null ? '[' . $dbConnection->getDbsServer() . ':' . $dbConnection->getDbsPort() . '] ' . $dbConnection->getDbsType() . ': ' . $dbConnection->getDbsDatabaseName() : 'PM Database',
                        "VAR_SQL" => $variable->getVarSql(),
                        "VAR_ACCEPTED_VALUES" => $variable->getVarAcceptedValues()
                    );

                    $pmDynaform = new PmDynaform();
                    $pmDynaform->synchronizeVariable($processUid, $newVariable, $oldVariable);
                } else {
                    $msg = "";

                    foreach ($variable->getValidationFailures() as $validationFailure) {
                        $msg = $msg . (($msg != "") ? "\n" : "") . $validationFailure->getMessage();
                    }

                    throw new Exception(G::LoadTranslation("ID_RECORD_CANNOT_BE_CREATED") . "\n" . $msg);
                }
            } catch (Exception $e) {
                $cnn->rollback();

                throw $e;
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Delete Variable
     *
     * @param string $processUid Unique id of Process
     * @param string $variableUid Unique id of Variable
     *
     * @return void
     * @throws Exception
     */
    public function delete($processUid, $variableUid)
    {
        try {
            //Verify data
            Validator::proUid($processUid, '$prj_uid');
            $this->throwExceptionIfNotExistsVariable($variableUid);
            //Verify variable
            $this->throwExceptionIfVariableIsAssociatedAditionalTable($variableUid);
            $variable = $this->getVariable($processUid, $variableUid);

            $pmDynaform = new PmDynaform();
            $isUsed = $pmDynaform->isUsed($processUid, $variable);
            if ($isUsed !== false) {
                $titleDynaform = $pmDynaform->getDynaformTitle($isUsed);
                throw new Exception(G::LoadTranslation("ID_VARIABLE_IN_USE", array($titleDynaform)));
            }
            //Delete
            $criteria = new \Criteria("workflow");
            $criteria->add(\ProcessVariablesPeer::VAR_UID, $variableUid);
            \ProcessVariablesPeer::doDelete($criteria);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get data of a Variable
     * @param string $processUid Unique id of Process
     * @param string $variableUid Unique id of Variable
     *
     * @return array, return an array with data of a Variable
     * @throws Exception
     */
    public function getVariable($processUid, $variableUid)
    {
        try {
            //Verify data
            Validator::proUid($processUid, '$prj_uid');
            $this->throwExceptionIfNotExistsVariable($variableUid);

            //Get data
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_UID);
            $criteria->addSelectColumn(\ProcessVariablesPeer::PRJ_UID);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_NAME);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_FIELD_TYPE);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_FIELD_SIZE);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_LABEL);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_DBCONNECTION);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_SQL);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_NULL);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_DEFAULT);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_ACCEPTED_VALUES);
            $criteria->addSelectColumn(\ProcessVariablesPeer::INP_DOC_UID);
            $criteria->addSelectColumn(\DbSourcePeer::DBS_SERVER);
            $criteria->addSelectColumn(\DbSourcePeer::DBS_PORT);
            $criteria->addSelectColumn(\DbSourcePeer::DBS_DATABASE_NAME);
            $criteria->addSelectColumn(\DbSourcePeer::DBS_TYPE);
            $criteria->add(\ProcessVariablesPeer::PRJ_UID, $processUid, \Criteria::EQUAL);
            $criteria->add(\ProcessVariablesPeer::VAR_UID, $variableUid, \Criteria::EQUAL);
            $criteria->addJoin(\ProcessVariablesPeer::VAR_DBCONNECTION, \DbSourcePeer::DBS_UID, \Criteria::LEFT_JOIN);
            $rsCriteria = \ProcessVariablesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            $rsCriteria->next();
            $arrayVariables = array();
            while ($aRow = $rsCriteria->getRow()) {
                $VAR_ACCEPTED_VALUES = G::json_decode($aRow['VAR_ACCEPTED_VALUES'], true);
                if (!empty($VAR_ACCEPTED_VALUES)) {
                    $encodeAcceptedValues = preg_replace_callback("/\\\\u([a-f0-9]{4})/", function ($m) {
                        return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec('U' . $m[1])));
                    }, G::json_encode($VAR_ACCEPTED_VALUES));
                } else {
                    $encodeAcceptedValues = $aRow['VAR_ACCEPTED_VALUES'];
                }

                $arrayVariables = array('var_uid' => $aRow['VAR_UID'],
                    'prj_uid' => $aRow['PRJ_UID'],
                    'var_name' => $aRow['VAR_NAME'],
                    'var_field_type' => $aRow['VAR_FIELD_TYPE'],
                    'var_field_size' => (int)$aRow['VAR_FIELD_SIZE'],
                    'var_label' => $aRow['VAR_LABEL'],
                    'var_dbconnection' => $aRow['VAR_DBCONNECTION'] === 'none' ? 'workflow' : $aRow['VAR_DBCONNECTION'],
                    'var_dbconnection_label' => $aRow['DBS_SERVER'] !== null ? '[' . $aRow['DBS_SERVER'] . ':' . $aRow['DBS_PORT'] . '] ' . $aRow['DBS_TYPE'] . ': ' . $aRow['DBS_DATABASE_NAME'] : 'PM Database',
                    'var_sql' => $aRow['VAR_SQL'],
                    'var_null' => (int)$aRow['VAR_NULL'],
                    'var_default' => $aRow['VAR_DEFAULT'],
                    'var_accepted_values' => $encodeAcceptedValues,
                    'inp_doc_uid' => $aRow['INP_DOC_UID']);
                $rsCriteria->next();
            }
            //Return
            return $arrayVariables;
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * Get data of Variables
     *
     * @param string $processUid Unique id of Process
     *
     * @return array, return an array with data of a DynaForm
     * @throws Exception
     */
    public function getVariables($processUid)
    {
        //Verify data
        $proId = Validator::proUid($processUid, '$prj_uid');
        $variables = ProcessVariables::getVariables($proId);
        $arrayVariables = [];
        foreach ($variables as $var) {
            $varAcceptedValues = G::json_decode($var['VAR_ACCEPTED_VALUES'], true);
            if (count($varAcceptedValues)) {
                $encodeAcceptedValues = preg_replace_callback("/\\\\u([a-f0-9]{4})/", function ($m) {
                    return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($m[1])));
                }, G::json_encode($varAcceptedValues));
            } else {
                $encodeAcceptedValues = $var['VAR_ACCEPTED_VALUES'];
            }
            $dbconnectionLabel = !is_null($var['DBS_SERVER']) ?
            '[' . $var['DBS_SERVER'] . ':' . $var['DBS_PORT'] . '] ' . $var['DBS_TYPE'] . ': ' . $var['DBS_DATABASE_NAME'] : 'PM Database';
            $arrayVariables[] = [
                'var_uid' => $var['VAR_UID'],
                'prj_uid' => $var['PRJ_UID'],
                'var_name' => $var['VAR_NAME'],
                'var_field_type' => $var['VAR_FIELD_TYPE'],
                'var_field_size' => (int)$var['VAR_FIELD_SIZE'],
                'var_label' => $var['VAR_LABEL'],
                'var_dbconnection' => $var['VAR_DBCONNECTION'] === 'none' ? 'workflow' : $var['VAR_DBCONNECTION'],
                'var_dbconnection_label' => !is_null($var['DBS_SERVER']) ?
                    '[' . $var['DBS_SERVER'] . ':' . $var['DBS_PORT'] . '] ' . $var['DBS_TYPE'] . ': ' . $var['DBS_DATABASE_NAME'] : 'PM Database',
                'var_sql' => $var['VAR_SQL'],
                'var_null' => (int)$var['VAR_NULL'],
                'var_default' => $var['VAR_DEFAULT'],
                'var_accepted_values' => $encodeAcceptedValues,
                'inp_doc_uid' => $var['INP_DOC_UID']
            ];
        }

        return $arrayVariables;
    }

     /**
     * Get data of Variables related to the specific type
     *
     * @param string $processUid Unique id of Process
     * @param int $typeVarId
     * @param int $start
     * @param int $limit
     * @param string $search
     * @param string $prefix
     *
     * @return array, return an array with varaibles filter by type
     */
    public function getVariablesByType($processUid, $typeVarId = 0, $start = null, $limit = null, $search = null, $prefix = null)
    {
        //Verify data
        $proId = Validator::proUid($processUid, '$prj_uid');
        $variables = ProcessVariables::getVariablesByType($proId, $typeVarId, $start, $limit, $search);
        $arrayVariables = [];
        foreach ($variables as $var) {
            $arrayVariables[] = [
                'value' => is_null($prefix) ? $var['VAR_NAME'] : $prefix . $var['VAR_NAME'],
            ];
        }

        return $arrayVariables;
    }

    /**
     * Verify field definition
     *
     * @param array $aData Unique id of Variable to exclude
     * @return void
     * @throws Exception
     */
    public function throwExceptionFieldDefinition($aData)
    {
        try {
            if (isset($aData["VAR_NAME"])) {
                Validator::isString($aData['VAR_NAME'], '$var_name');
                Validator::isNotEmpty($aData['VAR_NAME'], '$var_name');
                Validator::isValidVariableName($aData['VAR_NAME']);
            }
            if (isset($aData["VAR_FIELD_TYPE"])) {
                Validator::isString($aData['VAR_FIELD_TYPE'], '$var_field_type');
                Validator::isNotEmpty($aData['VAR_FIELD_TYPE'], '$var_field_type');
            }
            if (isset($aData["VAR_FIELD_SIZE"])) {
                Validator::isInteger($aData["VAR_FIELD_SIZE"], '$var_field_size');
            }
            if (isset($aData["VAR_LABEL"])) {
                Validator::isString($aData['VAR_LABEL'], '$var_label');
                Validator::isNotEmpty($aData['VAR_LABEL'], '$var_label');
            }
            if (isset($aData["VAR_DBCONNECTION"])) {
                Validator::isString($aData['VAR_DBCONNECTION'], '$var_dbconnection');
            }
            if (isset($aData["VAR_SQL"])) {
                Validator::isString($aData['VAR_SQL'], '$var_sql');
            }
            if (isset($aData["VAR_NULL"])) {
                Validator::isInteger($aData['VAR_NULL'], '$var_null');
                if ($aData["VAR_NULL"] != 0 && $aData["VAR_NULL"] != 1) {
                    throw new Exception(G::LoadTranslation("ID_INVALID_VALUE_ONLY_ACCEPTS_VALUES", array('$var_null', '0, 1')));
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify if exists the name of a variable
     *
     * @param string $processUid , unique id of Process
     * @param string $variableName , name of variable
     * @param string $variableUidToExclude
     * @throws Exception
     *
     */
    public function existsName($processUid, $variableName, $variableUidToExclude = "")
    {
        try {
            $criteria = new \Criteria("workflow");

            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_UID);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_NAME);
            if ($variableUidToExclude != "") {
                $criteria->add(\ProcessVariablesPeer::VAR_UID, $variableUidToExclude, \Criteria::NOT_EQUAL);
            }
            $criteria->add(\ProcessVariablesPeer::PRJ_UID, $processUid, \Criteria::EQUAL);
            $rsCriteria = \ProcessVariablesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);

            while ($rsCriteria->next()) {
                $row = $rsCriteria->getRow();
                if ($variableName === $row["VAR_NAME"]) {
                    throw new Exception(G::LoadTranslation("DYNAFIELD_ALREADY_EXIST"));
                }
                if (strtolower(AdditionalTables::getPHPName($variableName)) === strtolower(AdditionalTables::getPHPName($row["VAR_NAME"]))) {
                    throw new Exception(G::LoadTranslation("DYNAFIELD_PHPNAME_ALREADY_EXIST", array($row["VAR_NAME"])));
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get required variables in the SQL
     *
     * @param string $sql SQL
     *
     * @return array, return an array with required variables in the SQL
     * @throws Exception
     */
    public function sqlGetRequiredVariables($sql)
    {
        try {
            $arrayVariableRequired = array();
            preg_match_all("/@[@%#\?\x24\=]([A-Za-z_]\w*)/", $sql, $arrayMatch, PREG_SET_ORDER);
            foreach ($arrayMatch as $value) {
                $arrayVariableRequired[] = $value[1];
            }

            return $arrayVariableRequired;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify if some required variable in the SQL is missing in the variables
     *
     * @param string $variableName Variable name
     * @param string $variableSql SQL
     * @param array $arrayVariable The variables
     *
     * @return void Throw exception if some required variable in the SQL is missing in the variables
     * @throws Exception
     */
    public function throwExceptionIfSomeRequiredVariableSqlIsMissingInVariables($variableName, $variableSql, array $arrayVariable)
    {
        try {
            $arrayResult = array_diff(array_unique($this->sqlGetRequiredVariables($variableSql)), array_keys($arrayVariable));
            if (count($arrayResult) > 0) {
                throw new Exception(G::LoadTranslation("ID_PROCESS_VARIABLE_REQUIRED_VARIABLES_FOR_QUERY", array($variableName, implode(", ", $arrayResult))));
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get all records by execute SQL
     *
     * @param string $processUid Unique id of Process
     * @param string $variableName Variable name
     * @param array $arrayVariable The variables
     *
     * @return array, return an array with all records
     * @throws Exception
     */
    public function executeSql($processUid, $variableName, array $arrayVariable = array())
    {
        try {
            return $this->executeSqlControl($processUid, $arrayVariable);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify if does not exist the variable in table PROCESS_VARIABLES
     *
     * @param string $variableUid Unique id of variable
     *
     * @return void
     * @throws Exception, throw exception if does not exist the variable in table PROCESS_VARIABLES
     */
    public function throwExceptionIfNotExistsVariable($variableUid)
    {
        try {
            $obj = \ProcessVariablesPeer::retrieveByPK($variableUid);

            if (is_null($obj)) {
                throw new Exception('var_uid: ' . $variableUid . ' ' . G::LoadTranslation("ID_DOES_NOT_EXIST"));
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Check if the variable is associated to Report Table
     *
     * @param string $variableUid Unique id of variable
     *
     * @return void Throw exception
     * @throws Exception
     */
    public function throwExceptionIfVariableIsAssociatedAditionalTable($variableUid)
    {
        try {
            // Get variable name
            $varInfo = ProcessVariables::getVariable($variableUid);
            $varName = $varInfo['VAR_NAME'];
            $proUid = $varInfo['PRJ_UID'];
            // Get the tables related to the process
            $tables = AT::getTables($proUid);
            if (!empty($tables)) {
                foreach ($tables as $value) {
                    $exist = Fields::searchVariable($value['ADD_TAB_UID'], $varName);
                    if ($exist) {
                        throw new Exception(G::LoadTranslation('ID_VARIABLE_ASSOCIATED_WITH_REPORT_TABLE', [$varName]));
                    }
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify if the variable is being used in a Dynaform
     *
     * @param string $processUid , Unique id of Process
     * @param string $variableUid , Unique id of Variable
     * @return void
     * @throws Exception
     *
     */
    public function verifyUse($processUid, $variableUid)
    {
        try {
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\DynaformPeer::DYN_CONTENT);
            $criteria->addSelectColumn(\DynaformPeer::DYN_UID);
            $criteria->add(\DynaformPeer::PRO_UID, $processUid, \Criteria::EQUAL);
            $rsCriteria = \DynaformPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);

            while ($rsCriteria->next()) {
                $row = $rsCriteria->getRow();
                $contentDecode = G::json_decode($row["DYN_CONTENT"], true);
                $content = $contentDecode['items'][0]['items'];
                if (is_array($content)) {
                    foreach ($content as $key => $value) {
                        if (isset($value[0]["variable"])) {
                            $criteria = new \Criteria("workflow");
                            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_NAME);
                            $criteria->add(\ProcessVariablesPeer::PRJ_UID, $processUid, \Criteria::EQUAL);
                            $criteria->add(\ProcessVariablesPeer::VAR_NAME, $value[0]["variable"], \Criteria::EQUAL);
                            $criteria->add(\ProcessVariablesPeer::VAR_UID, $variableUid, \Criteria::EQUAL);
                            $rsCriteria = \ProcessVariablesPeer::doSelectRS($criteria);
                            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
                            $rsCriteria->next();

                            if ($rsCriteria->getRow()) {
                                throw new Exception(G::LoadTranslation("ID_VARIABLE_IN_USE", array($variableUid, $row["DYN_UID"])));
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get all records by execute SQL suggest
     *
     * @param string $processUid Unique id of Process
     * @param string $variableName Variable name
     * @param array $arrayVariable The variables
     *
     * @return array, return an array with all records
     * @throws Exception
     */
    public function executeSqlSuggest($processUid, $variableName, array $arrayVariable = array())
    {
        try {
            return $this->executeSqlControl($processUid, $arrayVariable);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getVariableTypeByName($processUid, $variableName)
    {
        try {
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_UID);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_NAME);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_FIELD_TYPE);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_DBCONNECTION);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_SQL);
            $criteria->addSelectColumn(\ProcessVariablesPeer::VAR_ACCEPTED_VALUES);
            $criteria->add(\ProcessVariablesPeer::VAR_NAME, $variableName);
            $criteria->add(\ProcessVariablesPeer::PRJ_UID, $processUid);
            $rsCriteria = \ProcessVariablesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            if ($rsCriteria->next()) {
                $row = $rsCriteria->getRow();
                return count($row) ? $row : false;
            }
            return false;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get Variable record by name
     *
     * @param string $projectUid Unique id of Project
     * @param string $variableName Variable name
     * @param array $arrayVariableNameForException Variable name for exception
     * @param bool $throwException Flag to throw the exception if the main parameters are invalid or do not exist
     *                               (TRUE: throw the exception; FALSE: returns FALSE)
     *
     * @return array, returns an array with Variable record
     * @throws Exception, ThrowTheException/FALSE otherwise
     */
    public function getVariableRecordByName(
        $projectUid,
        $variableName,
        array $arrayVariableNameForException,
        $throwException = true
    )
    {
        try {
            $criteria = new \Criteria('workflow');
            $criteria->add(\ProcessVariablesPeer::PRJ_UID, $projectUid, \Criteria::EQUAL);
            $criteria->add(\ProcessVariablesPeer::VAR_NAME, $variableName, \Criteria::EQUAL);
            $rsCriteria = \ProcessVariablesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            if ($rsCriteria->next()) {
                $arrayVariableData = $rsCriteria->getRow();
            } else {
                if ($throwException) {
                    throw new Exception(
                        $arrayVariableNameForException['$variableName'] . ': ' . $variableName . ' ' .
                        G::LoadTranslation('ID_DOES_NOT_EXIST')
                    );
                } else {
                    return false;
                }
            }

            //Return
            return $arrayVariableData;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function validateVarFieldType($type)
    {
        $vType = strtolower($type);
        if (!in_array($vType, $this->variableTypes)) {
            throw new Exception(G::LoadTranslation("ID_RECORD_CANNOT_BE_CREATED"));
        }
        return $vType;
    }

    /**
     * Executes the sql string of a control and returns the data in the queryOutputData
     * property of the control. The control returned by the pmDynaform :: searchField
     * function is the php representation of the json definition, which can be
     * supported by the pmDynaform :: jsonr function.
     * The params parameter must contain: dyn_uid, field_id and optionally
     * app_uid, del_index, filter, start, limit, and so many related control variables
     * to be sent and their corresponding value.
     * The parameters: filter, start and limit, are only necessary for the suggest
     * control.
     * If app_uid is not sent you can not get the appData in an environment where
     * only endPoint is used, it is always advisable to send the app_uid and _index.
     * Note: You do not get trigger execution values where only endPoint is used.
     * @param string $proUid
     * @param array $params
     *
     * @return array
     * @throws Exception
     * @see ProcessMaker\BusinessModel\Variable->executeSql()
     * @see ProcessMaker\BusinessModel\Variable->executeSqlSuggest()
     */
    public function executeSqlControl($proUid, array $params = [])
    {
        try {
            //Get and clear vector data that does not correspond to variables
            //related to a control.
            $dynUid = $params["dyn_uid"];
            $fieldId = $params["field_id"];
            $filter = isset($params["filter"]) ? $params["filter"] : "";
            $query = isset($params["query"]) ? $params["query"] : [];
            $start = isset($params["start"]) ? $params["start"] : 0;
            $limit = isset($params["limit"]) ? $params["limit"] : 10;
            $appUid = empty($params["app_uid"]) ? null : $params["app_uid"];
            $delIndex = (int) isset($params["del_index"]) ? $params["del_index"] : 0;
            $gridName = isset($params['grid_name']) ? $params['grid_name'] : null;

            unset($params["dyn_uid"]);
            unset($params["field_id"]);
            unset($params["app_uid"]);
            unset($params["del_index"]);
            unset($params["filter"]);
            unset($params["query"]);
            unset($params["start"]);
            unset($params["limit"]);
            unset($params["grid_name"]);

            //Get appData and system variables
            $paramsAndGlobal = $params;
            $globalVariables = [];
            if ($appUid !== null) {
                $case = new ClassesCases();
                $fields = $case->loadCase($appUid, $delIndex);
                $appData = $fields["APP_DATA"];
                $globalVariables = Cases::getGlobalVariables($appData);
                $appDataAndGlobal = array_merge($appData, $globalVariables);
                //Set the global variables and app data when this is not a grid
                $params = array_merge($appDataAndGlobal, $params);
                //Set the global variables for the grid
                $paramsAndGlobal = array_merge($globalVariables, $paramsAndGlobal);
            }

            //This value is required to be able to query the database.
            $_SESSION["PROCESS"] = $proUid;
            //The pmdynaform class is instantiated
            $pmDynaform = new PmDynaform(["APP_DATA" => $params]);

            //Get control from dynaform.
            //The parameters: queryFilter, queryStart, queryLimit, are only necessary
            //for the suggest control, the rest of the controls are ignored.
            $parameters = [$dynUid, $fieldId, $proUid];
            if (!empty($gridName)) {
                $parameters[] = ['gridName' => $gridName];
            }
            $field = $pmDynaform->searchField(...$parameters);
            $field->queryField = true;
            $field->queryInputData = $params;
            $field->queryFilter = $filter;
            $field->querySearch = $query;
            $field->queryStart = $start;
            $field->queryLimit = $limit;
            //Grids only access the global variables of 'ProcessMaker', other variables are removed.
            //The property 'columnWidth' is only present in the controls of a grid,
            //in the current change there is no specific property that indicates
            //if the control is in the grid.
            if (isset($field->columnWidth)) {
                if (!empty($field->dataVariable)) {
                    //this support the global variable for @?, only access grids
                    //for access to all variables, replace with '/^\s*@.(.+)\s*$/'
                    $dataVariable = preg_match('/^\s*@\?(.+)\s*$/', $field->dataVariable, $arrayMatch) ? $arrayMatch[1] : $json->dataVariable;
                    if (isset($params[$dataVariable]) && is_array($params[$dataVariable])) {
                        $globalVariables[$dataVariable] = $params[$dataVariable];
                        $paramsAndGlobal[$dataVariable] = $params[$dataVariable];
                    }
                }
                $pmDynaform->fields["APP_DATA"] = $globalVariables;
                $field->queryInputData = $paramsAndGlobal;
            }

            //Populate control data
            $pmDynaform->clearLastQueryError();
            $pmDynaform->jsonr($field);
            $error = $pmDynaform->getLastQueryError();
            if (!empty($error) && is_object($error)) {
                throw new Exception(G::LoadTranslation("ID_ERROR_IN_THE_QUERY"));
            }
            $result = [];
            if (isset($field->queryOutputData) && is_array($field->queryOutputData)) {
                foreach ($field->queryOutputData as $item) {
                    $result[] = ["value" => $item->value, "text" => $item->label];
                }
            }
            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
