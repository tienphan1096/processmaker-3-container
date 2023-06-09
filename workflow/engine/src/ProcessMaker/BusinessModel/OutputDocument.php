<?php

namespace ProcessMaker\BusinessModel;

use Exception;
use G;
use ProcessMaker\Model\OutputDocument as ModelOutputDocument;

class OutputDocument
{
    /**
     * Return output documents of a project.
     * @param string $proUid
     * @return array
     * @access public
     */
    public function getOutputDocuments($proUid = '')
    {
        try {
            $result = [];
            $outputDocuments = ModelOutputDocument::select()
                    ->where('PRO_UID', '=', $proUid)
                    ->get();
            foreach ($outputDocuments as $value) {
                if (!empty($value->OUT_DOC_TITLE)) {
                    $result[] = [
                        'out_doc_uid' => $value->OUT_DOC_UID,
                        'out_doc_title' => $value->OUT_DOC_TITLE,
                        'out_doc_description' => $value->OUT_DOC_DESCRIPTION,
                        'out_doc_filename' => $value->OUT_DOC_FILENAME,
                        'out_doc_template' => $value->OUT_DOC_TEMPLATE,
                        'out_doc_report_generator' => $value->OUT_DOC_REPORT_GENERATOR,
                        'out_doc_landscape' => $value->OUT_DOC_LANDSCAPE,
                        'out_doc_media' => $value->OUT_DOC_MEDIA,
                        'out_doc_left_margin' => $value->OUT_DOC_LEFT_MARGIN,
                        'out_doc_right_margin' => $value->OUT_DOC_RIGHT_MARGIN,
                        'out_doc_top_margin' => $value->OUT_DOC_TOP_MARGIN,
                        'out_doc_bottom_margin' => $value->OUT_DOC_BOTTOM_MARGIN,
                        'out_doc_generate' => $value->OUT_DOC_GENERATE,
                        'out_doc_type' => $value->OUT_DOC_TYPE,
                        'out_doc_current_revision' => $value->OUT_DOC_CURRENT_REVISION,
                        'out_doc_field_mapping' => $value->OUT_DOC_FIELD_MAPPING,
                        'out_doc_versioning' => $value->OUT_DOC_VERSIONING,
                        'out_doc_destination_path' => $value->OUT_DOC_DESTINATION_PATH,
                        'out_doc_tags' => $value->OUT_DOC_TAGS,
                        'out_doc_pdf_security_enabled' => $value->OUT_DOC_PDF_SECURITY_ENABLED,
                        'out_doc_pdf_security_permissions' => $value->OUT_DOC_PDF_SECURITY_PERMISSIONS,
                        'out_doc_open_type' => $value->OUT_DOC_OPEN_TYPE,
                        'out_doc_header' => json_decode($value->OUT_DOC_HEADER),
                        'out_doc_footer' => json_decode($value->OUT_DOC_FOOTER)
                    ];
                }
            }
            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Return a single output document of a project
     * @param string $proUid
     * @param string $outDocUid
     * @return array
     *
     * @access public
     */
    public function getOutputDocument($proUid = '', $outDocUid = '')
    {
        try {
            $result = [];
            $outputDocuments = ModelOutputDocument::select()
                    ->where('PRO_UID', '=', $proUid)
                    ->where('OUT_DOC_UID', '=', $outDocUid)
                    ->get();
            foreach ($outputDocuments as $value) {
                if (!empty($value->OUT_DOC_TITLE)) {
                    $result = [
                        'out_doc_uid' => $value->OUT_DOC_UID,
                        'out_doc_title' => $value->OUT_DOC_TITLE,
                        'out_doc_description' => $value->OUT_DOC_DESCRIPTION,
                        'out_doc_filename' => $value->OUT_DOC_FILENAME,
                        'out_doc_template' => $value->OUT_DOC_TEMPLATE,
                        'out_doc_report_generator' => $value->OUT_DOC_REPORT_GENERATOR,
                        'out_doc_landscape' => $value->OUT_DOC_LANDSCAPE,
                        'out_doc_media' => $value->OUT_DOC_MEDIA,
                        'out_doc_left_margin' => $value->OUT_DOC_LEFT_MARGIN,
                        'out_doc_right_margin' => $value->OUT_DOC_RIGHT_MARGIN,
                        'out_doc_top_margin' => $value->OUT_DOC_TOP_MARGIN,
                        'out_doc_bottom_margin' => $value->OUT_DOC_BOTTOM_MARGIN,
                        'out_doc_generate' => $value->OUT_DOC_GENERATE,
                        'out_doc_type' => $value->OUT_DOC_TYPE,
                        'out_doc_current_revision' => $value->OUT_DOC_CURRENT_REVISION,
                        'out_doc_field_mapping' => $value->OUT_DOC_FIELD_MAPPING,
                        'out_doc_versioning' => $value->OUT_DOC_VERSIONING,
                        'out_doc_destination_path' => $value->OUT_DOC_DESTINATION_PATH,
                        'out_doc_tags' => $value->OUT_DOC_TAGS,
                        'out_doc_pdf_security_enabled' => $value->OUT_DOC_PDF_SECURITY_ENABLED,
                        'out_doc_pdf_security_permissions' => $value->OUT_DOC_PDF_SECURITY_PERMISSIONS,
                        'out_doc_open_type' => $value->OUT_DOC_OPEN_TYPE,
                        'out_doc_header' => json_decode($value->OUT_DOC_HEADER),
                        'out_doc_footer' => json_decode($value->OUT_DOC_FOOTER)
                    ];
                }
            }
            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Create a new output document for a project
     * @param string $sProcessUID
     * @param array  $outputDocumentData
     * @return array
     *
     * @access public
     */
    public function addOutputDocument($sProcessUID, $outputDocumentData)
    {
        if (empty($outputDocumentData['out_doc_header'])) {
            $outputDocumentData['out_doc_header'] = [];
        }
        if (isset($outputDocumentData['out_doc_header'])) {
            $outputDocumentData['out_doc_header'] = json_encode($outputDocumentData['out_doc_header']);
        }
        if (empty($outputDocumentData['out_doc_footer'])) {
            $outputDocumentData['out_doc_footer'] = [];
        }
        if (isset($outputDocumentData['out_doc_footer'])) {
            $outputDocumentData['out_doc_footer'] = json_encode($outputDocumentData['out_doc_footer']);
        }

        $pemission = $outputDocumentData['out_doc_pdf_security_permissions'];
        $pemission = explode("|", $pemission);
        foreach ($pemission as $row) {
            if ($row == "print" || $row == "modify" || $row == "copy" || $row == "forms" || $row == "") {
                $outputDocumentData['out_doc_pdf_security_permissions'] = $outputDocumentData['out_doc_pdf_security_permissions'];
            } else {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array('out_doc_pdf_security_permissions')));
            }
        }
        try {
            require_once(PATH_TRUNK . "workflow" . PATH_SEP . "engine" . PATH_SEP . "classes" . PATH_SEP . "model" . PATH_SEP . "OutputDocument.php");
            $outputDocumentData = array_change_key_case($outputDocumentData, CASE_UPPER);
            $outputDocumentData['PRO_UID'] = $sProcessUID;
            //Verify data
            Validator::proUid($sProcessUID, '$pro_uid');
            if ($outputDocumentData["OUT_DOC_TITLE"]=="") {
                throw new \Exception(\G::LoadTranslation("ID_CAN_NOT_BE_NULL", array('out_doc_title')));
            }
            if (isset($outputDocumentData["OUT_DOC_TITLE"]) && $this->existsTitle($sProcessUID, $outputDocumentData["OUT_DOC_TITLE"])) {
                throw (new \Exception(\G::LoadTranslation("ID_OUTPUT_NOT_SAVE")));
            }
            $oOutputDocument = new \OutputDocument();
            if (isset( $outputDocumentData['OUT_DOC_TITLE'] ) && $outputDocumentData['OUT_DOC_TITLE'] != '') {
                if (isset( $outputDocumentData['OUT_DOC_PDF_SECURITY_ENABLED'] ) && $outputDocumentData['OUT_DOC_PDF_SECURITY_ENABLED'] == "0") {
                    $outputDocumentData['OUT_DOC_PDF_SECURITY_OPEN_PASSWORD'] = "";
                    $outputDocumentData['OUT_DOC_PDF_SECURITY_OWNER_PASSWORD'] = "";
                    $outputDocumentData['OUT_DOC_PDF_SECURITY_PERMISSIONS'] = "";
                }
            }
            if (isset($outputDocumentData['OUT_DOC_CURRENT_REVISION'])) {
                $oOutputDocument->setOutDocCurrentRevision($outputDocumentData['OUT_DOC_CURRENT_REVISION']);
            } else {
                $oOutputDocument->setOutDocCurrentRevision(0);
            }
            if (isset($outputDocumentData['OUT_DOC_FIELD_MAPPING'])) {
                $oOutputDocument->setOutDocFieldMapping($outputDocumentData['OUT_DOC_FIELD_MAPPING']);
            } else {
                $oOutputDocument->setOutDocFieldMapping(null);
            }
            $outDocUid = $oOutputDocument->create($outputDocumentData);
            $outputDocumentData = array_change_key_case($outputDocumentData, CASE_LOWER);
            $outputDocumentData['out_doc_header'] = json_decode($outputDocumentData['out_doc_header']);
            $outputDocumentData['out_doc_footer'] = json_decode($outputDocumentData['out_doc_footer']);

            $this->updateOutputDocument($sProcessUID, $outputDocumentData, 1, $outDocUid);
            //Return
            unset($outputDocumentData["PRO_UID"]);
            $outputDocumentData = array_change_key_case($outputDocumentData, CASE_LOWER);
            $outputDocumentData["out_doc_uid"] = $outDocUid;
            return $outputDocumentData;
        } catch (\Exception $e) {
                throw $e;
        }
    }

    /**
     * Update a output document for a project
     * @param string $sProcessUID
     * @param array  $outputDocumentData
     * @param string $sOutputDocumentUID
     * @param int $sFlag
     *
     * @access public
     */
    public function updateOutputDocument($sProcessUID, $outputDocumentData, $sFlag, $sOutputDocumentUID = '')
    {
        if (empty($outputDocumentData['out_doc_header'])) {
            $outputDocumentData['out_doc_header'] = [];
        }
        if (isset($outputDocumentData['out_doc_header'])) {
            $outputDocumentData['out_doc_header'] = json_encode($outputDocumentData['out_doc_header']);
        }
        if (empty($outputDocumentData['out_doc_footer'])) {
            $outputDocumentData['out_doc_footer'] = [];
        }
        if (isset($outputDocumentData['out_doc_footer'])) {
            $outputDocumentData['out_doc_footer'] = json_encode($outputDocumentData['out_doc_footer']);
        }

        $oConnection = \Propel::getConnection(\OutputDocumentPeer::DATABASE_NAME);
        $pemission = $outputDocumentData['out_doc_pdf_security_permissions'];
        $pemission = explode("|", $pemission);
        foreach ($pemission as $row) {
            if ($row == "print" || $row == "modify" || $row == "copy" || $row == "forms" || $row == "") {
                $outputDocumentData['out_doc_pdf_security_permissions'] = $outputDocumentData['out_doc_pdf_security_permissions'];
            } else {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array('out_doc_pdf_security_permissions')));
            }
        }
        try {
            $oOutputDocument = \OutputDocumentPeer::retrieveByPK($sOutputDocumentUID);
            if (!is_null($oOutputDocument)) {
                if (isset( $outputDocumentData['out_doc_pdf_security_open_password'] ) && $outputDocumentData['out_doc_pdf_security_open_password'] != "") {
                  $outputDocumentData['out_doc_pdf_security_open_password'] = \G::encrypt( $outputDocumentData['out_doc_pdf_security_open_password'], $sOutputDocumentUID);
                  $outputDocumentData['out_doc_pdf_security_owner_password'] = \G::encrypt( $outputDocumentData['out_doc_pdf_security_owner_password'], $sOutputDocumentUID);
                }else{
                  unset($outputDocumentData['out_doc_pdf_security_open_password']);
                  unset($outputDocumentData['out_doc_pdf_security_owner_password']);
                }
                $outputDocumentData = array_change_key_case($outputDocumentData, CASE_UPPER);
                $oOutputDocument->fromArray($outputDocumentData, \BasePeer::TYPE_FIELDNAME);
                if ($oOutputDocument->validate()) {
                    $oConnection->begin();
                    if (isset($outputDocumentData['OUT_DOC_TITLE'])) {
                        $uid = $this->titleExists($sProcessUID, $outputDocumentData["OUT_DOC_TITLE"]);
                        if ($uid != '') {
                            if ($uid != $sOutputDocumentUID && $sFlag == 0) {
                                throw (new \Exception(\G::LoadTranslation("ID_OUTPUT_NOT_SAVE")));
                            }
                        }
                        $oOutputDocument->setOutDocTitleContent($outputDocumentData['OUT_DOC_TITLE']);
                    }
                    if (isset($outputDocumentData['OUT_DOC_DESCRIPTION'])) {
                        $oOutputDocument->setOutDocDescriptionContent($outputDocumentData['OUT_DOC_DESCRIPTION']);
                    }
                    if (isset($outputDocumentData['OUT_DOC_FILENAME'])) {
                        $oOutputDocument->setOutDocFilenameContent($outputDocumentData['OUT_DOC_FILENAME']);
                    }
                    if (isset($outputDocumentData['OUT_DOC_TEMPLATE'])) {
                        $outputDocumentData['OUT_DOC_TEMPLATE'] = stripslashes($outputDocumentData['OUT_DOC_TEMPLATE']);
                        $outputDocumentData['OUT_DOC_TEMPLATE'] = str_replace("@amp@", "&", $outputDocumentData['OUT_DOC_TEMPLATE']);
                        $oOutputDocument->setOutDocTemplate($outputDocumentData['OUT_DOC_TEMPLATE']);
                        $oOutputDocument->setOutDocTemplateContent($outputDocumentData['OUT_DOC_TEMPLATE']);
                    }
                    $oOutputDocument->save();
                    $oConnection->commit();
                } else {
                    $sMessage = '';
                    $aValidationFailures = $oOutputDocument->getValidationFailures();
                    foreach ($aValidationFailures as $oValidationFailure) {
                        $sMessage .= $oValidationFailure->getMessage();
                    }
                    throw (new \Exception(\G::LoadTranslation("ID_REGISTRY_CANNOT_BE_UPDATED") . $sMessage));
                }
            } else {
                throw new \Exception(\G::LoadTranslation("ID_ROW_DOES_NOT_EXIST"));
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Delete a output document of a project
     *
     * @param string $sProcessUID
     * @param string $sOutputDocumentUID
     *
     * @access public
     */
    public function deleteOutputDocument($sProcessUID, $sOutputDocumentUID)
    {
        try {

            $this->throwExceptionIfItsAssignedInOtherObjects($sOutputDocumentUID, "outputDocumentUid");

            $oOutputDocument = new \OutputDocument();
            $fields = $oOutputDocument->load( $sOutputDocumentUID );
            $oOutputDocument->remove( $sOutputDocumentUID );
            $oStep = new \Step();
            $oStep->removeStep( 'OUTPUT_DOCUMENT', $sOutputDocumentUID );
            $oOP = new \ObjectPermission();
            $oOP->removeByObject( 'OUTPUT', $sOutputDocumentUID );
            //refresh dbarray with the last change in outputDocument
            $oMap = new \ProcessMap();
            $oCriteria = $oMap->getOutputDocumentsCriteria( $fields['PRO_UID'] );
        } catch (\Exception $e) {
                throw $e;
        }
    }

     /**
     * Checks if the title exists in the OutputDocuments of Process
     *
     * @param string $processUid Unique id of Process
     * @param string $title      Title
     *
     * return bool Return true if the title exists in the OutputDocuments of Process, false otherwise
     */
    public function existsTitle($processUid, $title)
    {
        try {
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\OutputDocumentPeer::OUT_DOC_UID);
            $criteria->addSelectColumn(\OutputDocumentPeer::OUT_DOC_TITLE);
            $criteria->add(\OutputDocumentPeer::PRO_UID, $processUid, \Criteria::EQUAL);
            $criteria->add(\OutputDocumentPeer::OUT_DOC_TITLE, $title, \Criteria::EQUAL);
            $rsCriteria = \OutputDocumentPeer::doSelectRS($criteria);
            if ($rsCriteria->next()) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Checks if the title exists in the OutputDocuments of Process
     *
     * @param string $processUid Unique id of Process
     * @param string $title      Title
     *
     */
    public function titleExists($processUid, $title)
    {
        try {
            $aResp = '';
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\OutputDocumentPeer::OUT_DOC_UID);
            $criteria->add(\OutputDocumentPeer::PRO_UID, $processUid, \Criteria::EQUAL);
            $criteria->add(\OutputDocumentPeer::OUT_DOC_TITLE, $title, \Criteria::EQUAL);
            $rsCriteria = \OutputDocumentPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            $rsCriteria->next();
            while ($aRow = $rsCriteria->getRow()) {
                $aResp = $aRow['OUT_DOC_UID'];
                $rsCriteria->next();
            }
            return $aResp;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
    * Verify if the OutputDocument it's assigned in other objects
    *
    * @param string $outputDocumentUid Unique id of OutputDocument
    *
    * return array Return array (true if it's assigned or false otherwise and data)
    */
    public function itsAssignedInOtherObjects($outputDocumentUid)
    {
        try {
            $flagAssigned = false;
            $arrayData = array();
            //Step
            $criteria = new \Criteria("workflow");

            $criteria->addSelectColumn(\StepPeer::STEP_UID);
            $criteria->add(\StepPeer::STEP_TYPE_OBJ, "OUTPUT_DOCUMENT", \Criteria::EQUAL);
            $criteria->add(\StepPeer::STEP_UID_OBJ, $outputDocumentUid, \Criteria::EQUAL);

            $rsCriteria = \StepPeer::doSelectRS($criteria);

            if ($rsCriteria->next()) {
                $flagAssigned = true;
                $arrayData[] = \G::LoadTranslation("ID_STEPS");
            }

            //StepSupervisor
            $criteria = new \Criteria("workflow");

            $criteria->addSelectColumn(\StepSupervisorPeer::STEP_UID);
            $criteria->add(\StepSupervisorPeer::STEP_TYPE_OBJ, "OUTPUT_DOCUMENT", \Criteria::EQUAL);
            $criteria->add(\StepSupervisorPeer::STEP_UID_OBJ, $outputDocumentUid, \Criteria::EQUAL);

            $rsCriteria = \StepSupervisorPeer::doSelectRS($criteria);

            if ($rsCriteria->next()) {
                $flagAssigned = true;
                $arrayData[] = \G::LoadTranslation("ID_CASES_MENU_ADMIN");
            }

            //ObjectPermission
            $criteria = new \Criteria("workflow");

            $criteria->addSelectColumn(\ObjectPermissionPeer::OP_UID);
            $criteria->add(\ObjectPermissionPeer::OP_OBJ_TYPE, "OUTPUT", \Criteria::EQUAL);
            $criteria->add(\ObjectPermissionPeer::OP_OBJ_UID, $outputDocumentUid, \Criteria::EQUAL);

            $rsCriteria = \ObjectPermissionPeer::doSelectRS($criteria);

            if ($rsCriteria->next()) {
                $flagAssigned = true;
                $arrayData[] = \G::LoadTranslation("ID_PROCESS_PERMISSIONS");
            }

            //Return
            return array($flagAssigned, $arrayData);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
    * Verify if the OutputDocument it's assigned in other objects
    *
    * @param string $outputDocumentUid      Unique id of OutputDocument
    * @param string $fieldNameForException Field name for the exception
    *
    * return void Throw exception if the OutputDocument it's assigned in other objects
    */
    public function throwExceptionIfItsAssignedInOtherObjects($outputDocumentUid, $fieldNameForException)
    {
        try {
            list($flagAssigned, $arrayData) = $this->itsAssignedInOtherObjects($outputDocumentUid);

            if ($flagAssigned) {
                throw new \Exception(\G::LoadTranslation("ID_OUTPUT_DOCUMENT_ITS_ASSIGNED", array($fieldNameForException, $outputDocumentUid, implode(", ", $arrayData))));
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}

