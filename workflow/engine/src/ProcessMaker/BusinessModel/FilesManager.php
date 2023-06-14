<?php
namespace ProcessMaker\BusinessModel;

use EmailEventPeer;
use Exception;
use G;
use Criteria;
use ProcessFiles;
use ProcessFilesPeer;
use ProcessPeer;
use ResultSet;
use TaskPeer;

class FilesManager
{
    /**
     * Return the Process Files Manager
     *
     * @param string $sProcessUID {@min 32} {@max 32}
     *
     * return array
     *
     * @access public
     */
    public function getProcessFilesManager($sProcessUID)
    {
        try {
            $aDirectories[] = array('name' => "templates",
                                    'type' => "folder",
                                    'path' => "/",
                                    'editable' => false);
            $aDirectories[] = array('name' => "public",
                                    'type' => "folder",
                                    'path' => "/",
                                    'editable' => false);
            return $aDirectories;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Return the Process Files Manager Path
     *
     * @param string $processUid
     * @param string $path
     * @param boolean $getContent
     *
     * @return array
     * @throws Exception
     *
     * @access public
     */
    public function getProcessFilesManagerPath($processUid, $path, $getContent = true)
    {
        try {
            $checkPath = substr($path, -1);
            if ($checkPath == '/') {
                $path = substr($path, 0, -1);
            }
            $mainDirectory = current(explode('/', $path));
            if (strstr($path,'/')) {
                $subDirectory = substr($path, strpos($path, '/') + 1) . PATH_SEP;
            } else {
                $subDirectory = '';
            }
            switch ($mainDirectory) {
                case 'templates':
                    $currentDirectory = PATH_DATA_MAILTEMPLATES . $processUid . PATH_SEP . $subDirectory;
                    break;
                case 'public':
                    $currentDirectory = PATH_DATA_PUBLIC . $processUid . PATH_SEP . $subDirectory;
                    break;
                default:
                    throw new Exception(G::LoadTranslation('ID_INVALID_VALUE_FOR', ['path']));
                    break;
            }
            G::verifyPath($currentDirectory, true);
            $filesToList = [];
            $files = [];
            $directory = dir($currentDirectory);
            while ($object = $directory->read()) {
                if (($object !== '.') && ($object !== '..')) {
                    // Skip files related to web entries
                    if ($object === 'wsClient.php' || WebEntry::isWebEntry($processUid, $object)) {
                        continue;
                    }
                    $path = $currentDirectory . $object;
                    if (is_dir($path)) {
                        $filesToList[] = [
                            'prf_name' => $object,
                            'prf_type' => 'folder',
                            'prf_path' => $mainDirectory
                        ];
                    } else {
                        $aux = pathinfo($path);
                        $aux['extension'] = (isset($aux['extension']) ? $aux['extension'] : '');
                        $files[] = ['FILE' => $object, 'EXT' => $aux['extension']];
                    }
                }
            }
            foreach ($files as $file) {
                $arrayFileUid = $this->getFileManagerUid($currentDirectory.$file['FILE'], $file['FILE']);
                $content = '';
                if ($getContent === true) {
                    $content = file_get_contents($currentDirectory . $file['FILE']);
                }
                $fileUid = isset($arrayFileUid['PRF_UID']) ? $arrayFileUid['PRF_UID'] : '';
                $derivationScreen = isset($arrayFileUid['DERIVATION_SCREEN_TPL']) ? true : false;
                if ($fileUid != null) {
                    $processFiles = ProcessFilesPeer::retrieveByPK($fileUid);
                    $editable = $processFiles->getPrfEditable();
                    if ($editable == '1') {
                        $editable = 'true';
                    } else {
                        $editable = 'false';
                    }
                    $filesToList[] = [
                        'prf_uid' => $processFiles->getPrfUid(),
                        'prf_filename' => $file['FILE'],
                        'usr_uid' => $processFiles->getUsrUid(),
                        'prf_update_usr_uid' => $processFiles->getPrfUpdateUsrUid(),
                        'prf_path' => $mainDirectory. PATH_SEP .$subDirectory,
                        'prf_type' => $processFiles->getPrfType(),
                        'prf_editable' => $editable,
                        'prf_create_date' => $processFiles->getPrfCreateDate(),
                        'prf_update_date' => $processFiles->getPrfUpdateDate(),
                        'prf_content' => $content,
                        'prf_derivation_screen' => $derivationScreen
                    ];
                } else {
                    $explodeExt = explode('.', $file['FILE']);
                    $extension = end($explodeExt);
                    if ($extension == 'docx' || $extension == 'doc' || $extension == 'html' || $extension == 'php' || $extension == 'jsp'
                        || $extension == 'xlsx' || $extension == 'xls' || $extension == 'js' || $extension == 'css' || $extension == 'txt') {
                        $editable = 'true';
                    } else {
                        $editable = 'false';
                    }
                    $filesToList[] = [
                        'prf_uid' => '',
                        'prf_filename' => $file['FILE'],
                        'usr_uid' => '',
                        'prf_update_usr_uid' => '',
                        'prf_path' => $mainDirectory. PATH_SEP .$subDirectory,
                        'prf_type' => 'file',
                        'prf_editable' => $editable,
                        'prf_create_date' => '',
                        'prf_update_date' => '',
                        'prf_content' => $content,
                        'prf_derivation_screen' => false
                    ];
                }
            }
            return $filesToList;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Upload process file manager related to templates or public
     *
     * @param string $userUid
     * @param string $proUid
     * @param string $mainPath
     *
     * @return array
     * @throws Exception
     *
     * @link https://wiki.processmaker.com/3.0/Process_Files_Manager
     */
    public function uploadFilesManager($userUid, $proUid, $mainPath)
    {
        try {
            $response = [];
            if (isset($_FILES["form"]["name"])) {
                // todo: can be upload one file, we can improve this if we can receive more than one file
                if (!is_array($_FILES["form"]["name"])) {
                    $arrayFileName = [
                        'name' => $_FILES["form"]["name"],
                        'tmp_name' => $_FILES["form"]["tmp_name"],
                        'error' => $_FILES["form"]["error"]
                    ];
                    $file = [
                        'filename' => $arrayFileName["name"],
                        'path' => $arrayFileName["tmp_name"]
                    ];
                    // Get the full path of the folder
                    $editable = false;
                    $folder = '';
                    switch ($mainPath) {
                        case 'templates': // Templates: only permitted to upload HTML files
                            $editable = true;
                            $folder = PATH_DATA_MAILTEMPLATES . $proUid . PATH_SEP;
                            break;
                        case 'public': // Public: does not permit the EXE files
                            $folder = PATH_DATA_PUBLIC . $proUid . PATH_SEP;
                            break;
                    }
                    // We will to review if we can upload the file in process files
                    $processFiles = $this->canUploadProcessFilesManager($userUid, $proUid, $mainPath, $file, $folder,
                        $editable);
                    // There is no error, the file uploaded with success
                    if ($arrayFileName["error"] === UPLOAD_ERR_OK) {
                        try {
                            $fileName = $file["filename"];
                            G::uploadFile(
                                $arrayFileName["tmp_name"],
                                $folder,
                                $fileName
                            );
                        } catch (Exception $e) {
                            // Delete the register from Database
                            $this->remove($processFiles->getPrfUid());

                            throw $e;
                        }
                    } else {
                        throw new UploadException($arrayFileName['error']);
                    }
                    // Prepare the results
                    $response = [
                        'prf_uid' => $processFiles->getPrfUid(),
                        'prf_filename' => $file["filename"],
                        'usr_uid' => $processFiles->getUsrUid(),
                        'prf_update_usr_uid' => $processFiles->getPrfUpdateUsrUid(),
                        'prf_path' => $folder . $file['filename'],
                        'prf_type' => $processFiles->getPrfType(),
                        'prf_editable' => $processFiles->getPrfEditable(),
                        'prf_create_date' => $processFiles->getPrfCreateDate(),
                        'prf_update_date' => $processFiles->getPrfUpdateDate(),
                        'prf_content' => '',
                    ];
                }
            } else {
                throw new Exception(G::LoadTranslation('ID_FIELD_REQUIRED', ['form']));
            }

            return $response;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Check if the file can be uploaded
     *
     * @param string $userUid
     * @param string $proUid
     * @param string $mainPath
     * @param array $file
     * @param string $folder
     * @param bool $editable
     *
     * @return object
     * @throws Exception
     *
     * @link https://wiki.processmaker.com/3.0/Process_Files_Manager
     */
    public function canUploadProcessFilesManager($userUid, $proUid, $mainPath, $file, $folder, $editable = false)
    {
        try {
            $path = pathinfo($file['filename']);
            if (!$file['filename'] || $path['dirname'] != '.') {
                throw new Exception(G::LoadTranslation("ID_INVALID_VALUE_FOR", ['filename']));
            }
            // Check if is a file will upload in the directory: public or mailTemplates
            if ($mainPath !== 'public' && $mainPath !== 'templates') {
                throw new Exception(G::LoadTranslation("ID_INVALID_PRF_PATH"));
            }
            // Get the extension of the file
            $info = pathinfo($file["filename"]);
            $extension = ((isset($info["extension"])) ? $info["extension"] : "");
            // In templates we can upload only HTML
            if ($mainPath === 'templates' && $extension !== 'html') {
                throw new Exception(G::LoadTranslation('ID_FILE_UPLOAD_INCORRECT_EXTENSION'));
            }
            // In public we can't upload executables
            if ($mainPath === 'public' && $extension === 'exe') {
                throw new Exception(G::LoadTranslation('ID_FILE_UPLOAD_INCORRECT_EXTENSION'));
            }
            // Get the file path
            $filePath = $folder . $file['filename'];
            // Check if the file exist
            if (file_exists($filePath)) {
                $filePath = $mainPath . PATH_SEP . $file['filename'];
                throw new Exception(G::LoadTranslation("ID_EXISTS_FILE", [$filePath]));
            }
            // Get the file path is defined
            if (empty($filePath)) {
                throw new Exception(G::LoadTranslation('ID_CAN_NOT_BE_EMPTY', ['filename']));
            }
            // Check if the file exist
            $processFiles = new ProcessFiles();
            $prfUid = G::generateUniqueID();
            $processFiles->setPrfUid($prfUid);
            $processFiles->setProUid($proUid);
            $processFiles->setUsrUid($userUid);
            $processFiles->setPrfUpdateUsrUid('');
            $processFiles->setPrfPath($filePath);
            $processFiles->setPrfType('file');
            $processFiles->setPrfEditable($editable);
            $processFiles->setPrfCreateDate(date('Y-m-d H:i:s'));
            $processFiles->save();

            return $processFiles;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Return the Process File Manager
     *
     * @param string $sProcessUID {@min 32} {@max 32}
     * @param string $userUID {@min 32} {@max 32}
     * @param array  $aData
     *
     * return array
     *
     * @access public
     */
    public function addProcessFilesManager($sProcessUID, $userUID, $aData, $isImport = false)
    {
        try {
            $aData['prf_path'] = rtrim($aData['prf_path'], '/') . '/';
            $path = pathinfo($aData['prf_filename']);
            if (!$aData['prf_filename'] || $path['dirname'] != '.') {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array('prf_filename')));
            }
            $extention = strstr($aData['prf_filename'], '.');
            if (!$extention) {
                $extention = '.html';
                $aData['prf_filename'] = $aData['prf_filename'].$extention;
            }
            if ($extention == '.docx' || $extention == '.doc' || $extention == '.html' || $extention == '.php' || $extention == '.jsp' ||
                $extention == '.xlsx' || $extention == '.xls' || $extention == '.js' || $extention == '.css' || $extention == '.txt') {
                $sEditable = true;
            } else {
                $sEditable = false;
            }
            $sMainDirectory = current(explode("/", $aData['prf_path']));
            if ($sMainDirectory != 'public' && $sMainDirectory != 'templates') {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_PRF_PATH"));
            }
            if (strstr($aData['prf_path'],'/')) {
                $sSubDirectory = substr($aData['prf_path'], strpos($aData['prf_path'], "/")+1) ;
            } else {
                $sSubDirectory = '';
            }
            switch ($sMainDirectory) {
                case 'templates':
                    $sDirectory = PATH_DATA_MAILTEMPLATES . $sProcessUID . PATH_SEP . $sSubDirectory . $aData['prf_filename'];
                    $sCheckDirectory = PATH_DATA_MAILTEMPLATES . $sProcessUID . PATH_SEP . $sSubDirectory;
                    if ($extention != '.html') {
                        throw new \Exception(\G::LoadTranslation('ID_FILE_UPLOAD_INCORRECT_EXTENSION'));
                    }
                    break;
                case 'public':
                    $sDirectory = PATH_DATA_PUBLIC . $sProcessUID . PATH_SEP . $sSubDirectory . $aData['prf_filename'];
                    $sCheckDirectory = PATH_DATA_PUBLIC . $sProcessUID . PATH_SEP . $sSubDirectory;
                    $sEditable = false;
                    if ($extention == '.exe') {
                        throw new \Exception(\G::LoadTranslation('ID_FILE_UPLOAD_INCORRECT_EXTENSION'));
                    }
                    break;
                default:
                    $sDirectory = PATH_DATA_MAILTEMPLATES . $sProcessUID . PATH_SEP . $sSubDirectory . $aData['prf_filename'];
                    break;
            }
            $content = $aData['prf_content'];
            if (file_exists($sDirectory) ) {
                $directory = $sMainDirectory. PATH_SEP . $sSubDirectory . $aData['prf_filename'];
                throw new \Exception(\G::LoadTranslation("ID_EXISTS_FILE", array($directory)));
            }

            if (!file_exists($sCheckDirectory)) {
                $sPkProcessFiles = \G::generateUniqueID();
                $oProcessFiles = new \ProcessFiles();
                $sDate = date('Y-m-d H:i:s');
                $oProcessFiles->setPrfUid($sPkProcessFiles);
                $oProcessFiles->setProUid($sProcessUID);
                $oProcessFiles->setUsrUid($userUID);
                $oProcessFiles->setPrfUpdateUsrUid('');
                $oProcessFiles->setPrfPath($sCheckDirectory);
                $oProcessFiles->setPrfType('folder');
                $oProcessFiles->setPrfEditable('');
                $oProcessFiles->setPrfCreateDate($sDate);
                $oProcessFiles->save();
            }
            \G::verifyPath($sCheckDirectory, true);
            $sPkProcessFiles = \G::generateUniqueID();
            $oProcessFiles = new \ProcessFiles();
            $sDate = date('Y-m-d H:i:s');
            $oProcessFiles->setPrfUid($sPkProcessFiles);
            $oProcessFiles->setProUid($sProcessUID);
            $oProcessFiles->setUsrUid($userUID);
            $oProcessFiles->setPrfUpdateUsrUid('');
            $oProcessFiles->setPrfPath($sDirectory);
            $oProcessFiles->setPrfType('file');
            $oProcessFiles->setPrfEditable($sEditable);
            $oProcessFiles->setPrfCreateDate($sDate);
            $oProcessFiles->save();
            $fp = fopen($sDirectory, 'w');
            $content = stripslashes($aData['prf_content']);
            $content = str_replace("@amp@", "&", $content);
            fwrite($fp, $content);
            fclose($fp);
            $oProcessFile = array('prf_uid' => $oProcessFiles->getPrfUid(),
                                  'prf_filename' => $aData['prf_filename'],
                                  'usr_uid' => $oProcessFiles->getUsrUid(),
                                  'prf_update_usr_uid' => $oProcessFiles->getPrfUpdateUsrUid(),
                                  'prf_path' => $sMainDirectory. PATH_SEP . $sSubDirectory,
                                  'prf_type' => $oProcessFiles->getPrfType(),
                                  'prf_editable' => $oProcessFiles->getPrfEditable(),
                                  'prf_create_date' => $oProcessFiles->getPrfCreateDate(),
                                  'prf_update_date' => $oProcessFiles->getPrfUpdateDate(),
                                  'prf_content' => $content);
            return $oProcessFile;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $aData
     * @throws Exception
     * @throws \Exception
     */
    public function addProcessFilesManagerInDb($aData)
    {
        try {
            $oProcessFiles = new \ProcessFiles();
            $aData = array_change_key_case($aData, CASE_UPPER);
            $oProcessFiles->fromArray($aData, \BasePeer::TYPE_FIELDNAME);

            $path = $aData['PRF_PATH'];

            $allDirectories = pathinfo($path);
            $path = explode('/',$allDirectories['dirname']);
            $fileDirectory = $path[count($path)-2];

            switch ($fileDirectory) {
                case 'mailTemplates':
                    $sDirectory = PATH_DATA_MAILTEMPLATES . $aData['PRO_UID'] . PATH_SEP . basename($aData['PRF_PATH']);
                    break;
                case 'public':
                    $sDirectory = PATH_DATA_PUBLIC . $aData['PRO_UID'] . PATH_SEP . basename($aData['PRF_PATH']);
                    break;
                default:
                    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                        error_log(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array($aData['PRF_PATH'])));
                    }
                    return;
                    break;
            }

            $oProcessFiles->setPrfPath($sDirectory);

            if($this->existsProcessFile($aData['PRF_UID'])) {
                $sPkProcessFiles = \G::generateUniqueID();
                $oProcessFiles->setPrfUid($sPkProcessFiles);

                $emailEvent = new \ProcessMaker\BusinessModel\EmailEvent();
                $emailEvent->updatePrfUid($aData['PRF_UID'], $sPkProcessFiles, $aData['PRO_UID']);
            }

            $result = $oProcessFiles->save();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $aData
     * @throws Exception
     */
    public function updateProcessFilesManagerInDb($aData)
    {
        try {
            //update database
            if ($this->existsProcessFile($aData['prf_uid'])) {
                $aData = array_change_key_case($aData, CASE_UPPER);
                $oProcessFiles = \ProcessFilesPeer::retrieveByPK($aData['PRF_UID']);
                $sDate = date('Y-m-d H:i:s');
                $oProcessFiles->setPrfUpdateDate($sDate);
                $oProcessFiles->setProUid($aData['PRO_UID']);
                $oProcessFiles->setPrfPath($aData['PRF_PATH']);
                $oProcessFiles->save();
            } else {
                $this->addProcessFilesManagerInDb($aData);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function existsProcessFile($prfUid)
    {
        try {
            $obj = \ProcessFilesPeer::retrieveByPK($prfUid);

            return (!is_null($obj))? true : false;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Return the Process Files Manager
     *
     * @param string $prjUid {@min 32} {@max 32}
     * @param string $prfUid {@min 32} {@max 32}
     *
     *
     * @access public
     */
    public function uploadProcessFilesManager($prjUid, $prfUid)
    {
        try {
            $path = '';
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\ProcessFilesPeer::PRF_PATH);
            $criteria->add(\ProcessFilesPeer::PRF_UID, $prfUid, \Criteria::EQUAL);
            $rsCriteria = \ProcessFilesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            $rsCriteria->next();
            while ($aRow = $rsCriteria->getRow()) {
                $path = $aRow['PRF_PATH'];
                $rsCriteria->next();
            }
            if ($path == '') {
                throw new \Exception(\G::LoadTranslation('ID_PMTABLE_UPLOADING_FILE_PROBLEM'));
            }
            $extention = strstr($_FILES['prf_file']['name'], '.');
            if (!$extention) {
                $extention = '.html';
                $_FILES['prf_file']['name'] = $_FILES['prf_file']['name'].$extention;
            }
            $explodePath = explode("/", $path);
            $file = end($explodePath);
            if(strpos($file,"\\") > 0) {
                $file = str_replace('\\', '/', $file);
                $explodeFile = explode("/", $file);
                $file = end($explodeFile);
            }
            $path = str_replace($file,'',$path);
            if ($file == $_FILES['prf_file']['name']) {
                if ($_FILES['prf_file']['error'] != 1) {
                    if ($_FILES['prf_file']['tmp_name'] != '') {
                        \G::uploadFile($_FILES['prf_file']['tmp_name'], $path, $_FILES['prf_file']['name']);
                    }
                }
            } else {
                throw new \Exception(\G::LoadTranslation('ID_PMTABLE_UPLOADING_FILE_PROBLEM'));
            }
            $oProcessFile = array('prf_uid' => $prfUid);
            return $oProcessFile;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get data of unique ids of a file and if the template is used in a derivation screen
     *
     * @param string $path
     * @param string $fileName the name of template
     * @throws Exception
     *
     * @return array
     */
    public function getFileManagerUid($path, $fileName = '')
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $path = str_replace("/", DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, $path);
            }
            $path = explode(DIRECTORY_SEPARATOR, $path);
            $baseName = $path[count($path) - 2] . "\\\\" . $path[count($path) - 1];
            $baseName2 = $path[count($path) - 2] . "/" . $path[count($path) - 1];
            $criteria = new Criteria("workflow");
            $criteria->addSelectColumn(ProcessFilesPeer::PRF_UID);
            $criteria->addSelectColumn(ProcessPeer::PRO_DERIVATION_SCREEN_TPL);
            $criteria->addSelectColumn(TaskPeer::TAS_DERIVATION_SCREEN_TPL);
            $criteria->addJoin(ProcessFilesPeer::PRO_UID, ProcessPeer::PRO_UID);
            $criteria->addJoin(ProcessPeer::PRO_UID, TaskPeer::PRO_UID, Criteria::LEFT_JOIN);
            $criteria->add($criteria->getNewCriterion(ProcessFilesPeer::PRF_PATH, '%' . $baseName . '%', Criteria::LIKE)->addOr($criteria->getNewCriterion(ProcessFilesPeer::PRF_PATH, '%' . $baseName2 . '%', Criteria::LIKE)));
            $rsCriteria = ProcessFilesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            $row = array();
            while ($rsCriteria->next()) {
                $row = $rsCriteria->getRow();
                if (!empty($row['PRO_DERIVATION_SCREEN_TPL']) && $row['PRO_DERIVATION_SCREEN_TPL'] == $fileName) {
                    $row['DERIVATION_SCREEN_TPL'] = true;
                    return $row;
                } elseif (!empty($row['TAS_DERIVATION_SCREEN_TPL']) && $row['TAS_DERIVATION_SCREEN_TPL'] == $fileName) {
                    $row['DERIVATION_SCREEN_TPL'] = true;
                    return $row;
                }
            }
            return $row;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Return the Process Files Manager
     *
     * @param string $sProcessUID {@min 32} {@max 32}
     * @param string $userUID {@min 32} {@max 32}
     * @param array  $aData
     * @param string $prfUid {@min 32} {@max 32}
     *
     * return array
     *
     * @access public
     */
    public function updateProcessFilesManager($sProcessUID, $userUID, $aData, $prfUid)
    {
        try {
            $path = '';
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\ProcessFilesPeer::PRF_PATH);
            $criteria->add(\ProcessFilesPeer::PRF_UID, $prfUid, \Criteria::EQUAL);
            $rsCriteria = \ProcessFilesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            $rsCriteria->next();
            while ($aRow = $rsCriteria->getRow()) {
                $path = $aRow['PRF_PATH'];
                $rsCriteria->next();
            }
            if ($path == '') {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array('prf_uid')));
            }
            $sFile = basename($path);
            $sPath = str_replace($sFile,'',$path);
            $sSubDirectory = substr(str_replace($sProcessUID,'',substr($sPath,(strpos($sPath, $sProcessUID)))),0,-1);
            $sMainDirectory = str_replace(substr($sPath, strpos($sPath, $sProcessUID)),'', $sPath);
            if ($sMainDirectory == PATH_DATA_MAILTEMPLATES) {
                $sMainDirectory = 'mailTemplates';
            } else {
                $sMainDirectory = 'public';
            }
            $explode = explode(".", $sFile);
            $extension = end($explode);
            if ($extension == 'docx' || $extension == 'doc' || $extension == 'html' || $extension == 'php' || $extension == 'jsp' ||
                $extension == 'xlsx' || $extension == 'xls' || $extension == 'js' || $extension == 'css' || $extension == 'txt') {
                $sEditable = true;
            } else {
                $sEditable = false;
            }
            if ($sEditable == false) {
                throw new \Exception(\G::LoadTranslation("ID_UNABLE_TO_EDIT"));
            }
            $oProcessFiles = \ProcessFilesPeer::retrieveByPK($prfUid);
            $sDate = date('Y-m-d H:i:s');
            $oProcessFiles->setPrfUpdateUsrUid($userUID);
            $oProcessFiles->setPrfUpdateDate($sDate);
            $oProcessFiles->save();

            $path = PATH_DATA_MAILTEMPLATES.$sProcessUID.DIRECTORY_SEPARATOR.$sFile;

            $fp = fopen($path, 'w');
            $content = stripslashes($aData['prf_content']);
            $content = str_replace("@amp@", "&", $content);
            fwrite($fp, $content);
            fclose($fp);
            $oProcessFile = array('prf_uid' => $oProcessFiles->getPrfUid(),
                                  'prf_filename' => $sFile,
                                  'usr_uid' => $oProcessFiles->getUsrUid(),
                                  'prf_update_usr_uid' => $oProcessFiles->getPrfUpdateUsrUid(),
                                  'prf_path' => $sMainDirectory.$sSubDirectory,
                                  'prf_type' => $oProcessFiles->getPrfType(),
                                  'prf_editable' => $sEditable,
                                  'prf_create_date' => $oProcessFiles->getPrfCreateDate(),
                                  'prf_update_date' => $oProcessFiles->getPrfUpdateDate(),
                                  'prf_content' => $content);
            return $oProcessFile;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Deletes the physical file and its corresponding record in the database.
     * @param string $proUid {@min 32} {@max 32}
     * @param string $prfUid {@min 32} {@max 32}
     * @param bool $verifyingRelationship
     * @access public
     * @throws Exception
     */
    public function deleteProcessFilesManager($proUid, $prfUid, $verifyingRelationship = false)
    {
        try {
            $relationshipEmailEvent = false;
            if ($verifyingRelationship) {
                $criteriaEmailEvent = new Criteria('workflow');
                $criteriaEmailEvent->addSelectColumn(EmailEventPeer::PRF_UID);
                $criteriaEmailEvent->add(EmailEventPeer::PRF_UID, $prfUid, Criteria::EQUAL);
                $resultSet1 = EmailEventPeer::doSelectRS($criteriaEmailEvent);
                $resultSet1->setFetchmode(ResultSet::FETCHMODE_ASSOC);

                if ($resultSet1->next()) {
                    $relationshipEmailEvent = true;
                }
            }

            $criteriaProcessFiles = new Criteria('workflow');
            $criteriaProcessFiles->addSelectColumn(ProcessFilesPeer::PRF_PATH);
            $criteriaProcessFiles->add(ProcessFilesPeer::PRF_UID, $prfUid, Criteria::EQUAL);
            $resultSet2 = ProcessFilesPeer::doSelectRS($criteriaProcessFiles);
            $resultSet2->setFetchmode(ResultSet::FETCHMODE_ASSOC);

            if ($resultSet2->next()) {
                $row = $resultSet2->getRow();
                $path = $row['PRF_PATH'];

                if (!empty($path)) {
                    $path = str_replace("\\", "/", $path);
                    $fileName = basename($path);
                    if ($relationshipEmailEvent) {
                        throw new Exception(G::LoadTranslation(
                            G::LoadTranslation('ID_CANNOT_REMOVE_TEMPLATE_EMAIL_EVENT',
                                [$fileName]
                            )));
                    }

                    $path = PATH_DATA_MAILTEMPLATES . $proUid . "/" . $fileName;

                    if (file_exists($path) && !is_dir($path)) {
                        unlink($path);
                    } else {
                        $path = PATH_DATA_PUBLIC . $proUid . "/" . $fileName;

                        if (file_exists($path) && !is_dir($path)) {
                            unlink($path);
                        }
                    }
                }
            }
            ProcessFilesPeer::doDelete($criteriaProcessFiles);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @param string $sProcessUID {@min 32} {@max 32}
     * @param string $prfUid {@min 32} {@max 32}
     *
     *
     * @access public
     */
    public function downloadProcessFilesManager($sProcessUID, $prfUid)
    {
        try {
            $path = '';
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\ProcessFilesPeer::PRF_PATH);
            $criteria->add(\ProcessFilesPeer::PRF_UID, $prfUid, \Criteria::EQUAL);
            $rsCriteria = \ProcessFilesPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);
            $rsCriteria->next();
            while ($aRow = $rsCriteria->getRow()) {
                $path = $aRow['PRF_PATH'];
                $rsCriteria->next();
            }
            if ($path == '') {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array('prf_uid')));
            }
            $explodePath =  explode("/",str_replace('\\', '/',$path));
            $sFile = end($explodePath);
            $sPath = str_replace($sFile,'',$path);
            $sSubDirectory = substr(str_replace($sProcessUID,'',substr($sPath,(strpos($sPath, $sProcessUID)))),0,-1);
            $sMainDirectory = str_replace(substr($sPath, strpos($sPath, $sProcessUID)),'', $sPath);
            if ($sMainDirectory == PATH_DATA_MAILTEMPLATES) {
                $sMainDirectory = 'mailTemplates';
            } else {
                $sMainDirectory = 'public';
            }
            if (file_exists($path)) {
                $oProcessMap = new \ProcessMap();
                $oProcessMap->downloadFile($sProcessUID,$sMainDirectory,$sSubDirectory,$sFile);
                die();
            } else {
                throw (new \Exception( 'Invalid value specified for path.'));
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @param string $sProcessUID {@min 32} {@max 32}
     * @param array  $path
     *
     * @access public
     */
    public function deleteFolderProcessFilesManager($sProcessUID, $path)
    {
        try {
            $explodePath = explode("/",$path);
            $sDirToDelete = end($explodePath);
            $sPath = str_replace($sDirToDelete,'',$path);
            $sSubDirectory = substr(str_replace($sProcessUID,'',substr($sPath,(strpos($sPath, $sProcessUID)))),0,-1);
            $sMainDirectory = current(explode("/", $path));
            $sSubDirectory = substr(str_replace($sMainDirectory,'',$sSubDirectory),1);
            switch ($sMainDirectory) {
                case 'templates':
                    $sDirectory = PATH_DATA_MAILTEMPLATES . $sProcessUID . PATH_SEP . ($sSubDirectory != '' ? $sSubDirectory . PATH_SEP : '');
                    break;
                case 'public':
                    $sDirectory = PATH_DATA_PUBLIC . $sProcessUID . PATH_SEP . ($sSubDirectory != '' ? $sSubDirectory . PATH_SEP : '');
                    break;
                default:
                    die();
                    break;
            }
            if (file_exists($sDirectory.$sDirToDelete)) {
                \G::rm_dir($sDirectory.$sDirToDelete);
            } else {
                throw new \Exception(\G::LoadTranslation("ID_INVALID_VALUE_FOR", array('path')));
            }
            $criteria = new \Criteria("workflow");
            $criteria->addSelectColumn(\ProcessFilesPeer::PRF_PATH);
            $criteria->add( \ProcessFilesPeer::PRF_PATH, '%' . $sDirectory.$sDirToDelete. PATH_SEP . '%', \Criteria::LIKE );
            $rs = \ProcessFilesPeer::doDelete($criteria);
            return $sDirectory.$sDirToDelete;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @param string $sProcessUID {@min 32} {@max 32}
     * @param string $prfUid {@min 32} {@max 32}
     *
     *
     * @access public
     */
    public function getProcessFileManager($sProcessUID, $prfUid)
    {
        try {
            $oProcessFiles = \ProcessFilesPeer::retrieveByPK($prfUid);
            $fcontent = file_get_contents($oProcessFiles->getPrfPath());
            $pth = $oProcessFiles->getPrfPath();
            $pth = str_replace("\\","/",$pth);
            $prfPath = explode("/",$pth);
            $sFile = end($prfPath);
            $path = $oProcessFiles->getPrfPath();
            $sPath = str_replace($sFile,'',$path);
            $sSubDirectory = substr(str_replace($sProcessUID,'',substr($sPath,(strpos($sPath, $sProcessUID)))),0,-1);
            $sMainDirectory = str_replace(substr($sPath, strpos($sPath, $sProcessUID)),'', $sPath);
            if ($sMainDirectory == PATH_DATA_MAILTEMPLATES) {
                $sMainDirectory = 'templates';
            } else {
                $sMainDirectory = 'public';
            }
            $oProcessFile = array('prf_uid' => $oProcessFiles->getPrfUid(),
                                  'prf_filename' => $sFile,
                                  'usr_uid' => $oProcessFiles->getUsrUid(),
                                  'prf_update_usr_uid' => $oProcessFiles->getPrfUpdateUsrUid(),
                                  'prf_path' => $sMainDirectory.$sSubDirectory,
                                  'prf_type' => $oProcessFiles->getPrfType(),
                                  'prf_editable' => $oProcessFiles->getPrfEditable(),
                                  'prf_create_date' => $oProcessFiles->getPrfCreateDate(),
                                  'prf_update_date' => $oProcessFiles->getPrfUpdateDate(),
                                  'prf_content' => $fcontent);
            return $oProcessFile;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Process-Files upgrade
     *
     * @param string $projectUid Unique id of Project
     *
     * return void
     */
    public function processFilesUpgrade($projectUid = "", $isImport = false)
    {
        try {
            //Set variables
            $conf = new \Configuration();

            //Create/Get PROCESS_FILES_CHECKED
            $arrayProjectUid = array();

            $configuration = \ConfigurationPeer::retrieveByPK("PROCESS_FILES_CHECKED", "", "", "", "");

            if (is_null($configuration)) {
                $result = $conf->create(array(
                    "CFG_UID"   => "PROCESS_FILES_CHECKED",
                    "OBJ_UID"   => "",
                    "CFG_VALUE" => serialize($arrayProjectUid),
                    "PRO_UID"   => "",
                    "USR_UID"   => "",
                    "APP_UID"   => ""
                ));
            } else {
                $arrayProjectUid = unserialize($configuration->getCfgValue());
            }

            //Set variables
            $arrayPath = array("templates" => PATH_DATA_MAILTEMPLATES, "public" => PATH_DATA_PUBLIC);
            $flagProjectUid = false;

            //Query
            $criteria = new \Criteria("workflow");

            $criteria->addSelectColumn(\BpmnProjectPeer::PRJ_UID);

            if ($projectUid != "") {
                $criteria->add(
                    $criteria->getNewCriterion(\BpmnProjectPeer::PRJ_UID, $arrayProjectUid, \Criteria::NOT_IN)->addAnd(
                    $criteria->getNewCriterion(\BpmnProjectPeer::PRJ_UID, $projectUid, \Criteria::EQUAL))
                );
            } else {
                $criteria->add(\BpmnProjectPeer::PRJ_UID, $arrayProjectUid, \Criteria::NOT_IN);
            }

            $rsCriteria = \BpmnProjectPeer::doSelectRS($criteria);
            $rsCriteria->setFetchmode(\ResultSet::FETCHMODE_ASSOC);

            while ($rsCriteria->next()) {
                $row = $rsCriteria->getRow();

                foreach ($arrayPath as $key => $value) {
                    $path = $key;
                    $dir  = $value . $row["PRJ_UID"];

                    if (is_dir($dir)) {
                        if ($dirh = opendir($dir)) {
                            while (($file = readdir($dirh)) !== false) {
                                if ($file != "" && $file != "." && $file != "..") {
                                    $f = $dir . PATH_SEP . $file;

                                    if (is_file($f)) {
                                        $arrayProcessFilesData = $this->getFileManagerUid($f);

                                        if (empty($arrayProcessFilesData["PRF_UID"])) {
                                            rename($dir . PATH_SEP . $file, $dir . PATH_SEP . $file . ".tmp");

                                            $arrayData = array(
                                                "prf_path"     => $path,
                                                "prf_filename" => $file,
                                                "prf_content"  => ""
                                            );

                                            $arrayData = $this->addProcessFilesManager($row["PRJ_UID"], "00000000000000000000000000000001", $arrayData, $isImport);

                                            rename($dir . PATH_SEP . $file . ".tmp", $dir . PATH_SEP . $file);
                                        }
                                    }
                                }
                            }

                            closedir($dirh);
                        }
                    }
                }

                $arrayProjectUid[$row["PRJ_UID"]] = $row["PRJ_UID"];
                $flagProjectUid = true;
            }

            //Update PROCESS_FILES_CHECKED
            if ($flagProjectUid) {
                $result = $conf->update(array(
                    "CFG_UID"   => "PROCESS_FILES_CHECKED",
                    "OBJ_UID"   => "",
                    "CFG_VALUE" => serialize($arrayProjectUid),
                    "PRO_UID"   => "",
                    "USR_UID"   => "",
                    "APP_UID"   => ""
                ));
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}

