<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Model\Translation as ModelTranslation;

class Translation extends BaseTranslation
{

    public static $meta;
    public static $localeSeparator = '-';

    private $envFilePath;

    public function __construct ()
    {
        $this->envFilePath = PATH_DATA . "META-INF" . PATH_SEP . "translations.env";
    }

    public function getAllCriteria ()
    {

        //SELECT * from TRANSLATION WHERE TRN_LANG = 'en' order by TRN_CATEGORY, TRN_ID
        $oCriteria = new Criteria( 'workflow' );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_ID );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_CATEGORY );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_LANG );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_VALUE );
        //$c->add(TranslationPeer::TRN_LANG, 'en');


        return $oCriteria;
    }

    public function getAll ($lang = 'en', $start = null, $limit = null, $search = null, $dateFrom = null, $dateTo = null)
    {
        $totalCount = 0;

        $oCriteria = new Criteria( 'workflow' );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_ID );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_CATEGORY );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_LANG );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_VALUE );
        $oCriteria->addSelectColumn( TranslationPeer::TRN_UPDATE_DATE );
        $oCriteria->add( TranslationPeer::TRN_LANG, $lang );
        $oCriteria->add( TranslationPeer::TRN_CATEGORY, 'LABEL' );
        //$oCriteria->addAscendingOrderByColumn ( 'TRN_CATEGORY' );
        $oCriteria->addAscendingOrderByColumn( 'TRN_ID' );

        if ($search) {
            $oCriteria->add( $oCriteria->getNewCriterion( TranslationPeer::TRN_ID, "%$search%", Criteria::LIKE )->addOr( $oCriteria->getNewCriterion( TranslationPeer::TRN_VALUE, "%$search%", Criteria::LIKE ) ) );
        }
        // for date filter
        if (($dateFrom) && ($dateTo)) {
            $oCriteria->add(
                $oCriteria->getNewCriterion(
                    TranslationPeer::TRN_UPDATE_DATE,
                    "$dateFrom",
                    Criteria::GREATER_EQUAL
                )->addAnd(
                    $oCriteria->getNewCriterion(
                        TranslationPeer::TRN_UPDATE_DATE,
                        "$dateTo",
                        Criteria::LESS_EQUAL
                    )
                )
            );
        }
        // end filter
        $c = clone $oCriteria;
        $c->clearSelectColumns();
        $c->addSelectColumn( 'COUNT(*)' );
        $oDataset = TranslationPeer::doSelectRS( $c );
        $oDataset->next();
        $aRow = $oDataset->getRow();

        if (is_array( $aRow )) {
            $totalCount = $aRow[0];
        }
        if ($start) {
            $oCriteria->setOffset( $start );
        }
        if ($limit) {
            //&& !isset($seach) && !isset($search))
            $oCriteria->setLimit( $limit );
        }
        $rs = TranslationPeer::doSelectRS( $oCriteria );
        $rs->setFetchmode( ResultSet::FETCHMODE_ASSOC );
        $rows = Array ();
        while ($rs->next()) {
            $rows[] = $rs->getRow();
        }

        $result = new StdClass();
        $result->data = $rows;
        $result->totalCount = $totalCount;

        return $result;
    }

    /* Load strings from a Database .
     * @author Fernando Ontiveros <fernando@colosa.com>
     * @parameter $languageId   (es|en|...).
    */
    public function generateFileTranslation ($languageId = '')
    {
        $translation = Array ();
        $translationJS = Array ();

        if ($languageId === '') {
            $languageId = defined( 'SYS_LANG' ) ? SYS_LANG : 'en';
        }
        $c = new Criteria();
        $c->add( TranslationPeer::TRN_LANG, $languageId );
        $c->addAscendingOrderByColumn( 'TRN_CATEGORY' );
        $c->addAscendingOrderByColumn( 'TRN_ID' );
        $tranlations = TranslationPeer::doSelect( $c );

        $cacheFile = PATH_LANGUAGECONT . "translation." . $languageId;
        $cacheFileJS = PATH_CORE . 'js' . PATH_SEP . 'labels' . PATH_SEP . $languageId . ".js";

        foreach ($tranlations as $key => $row) {
            if ($row->getTrnCategory() === 'LABEL') {
                $translation[$row->getTrnId()] = $row->getTrnValue();
            }
            if ($row->getTrnCategory() === 'JAVASCRIPT') {
                $translationJS[$row->getTrnId()] = $row->getTrnValue();
            }
        }

        try {

            if (! is_dir( dirname( $cacheFile ) )) {
                G::mk_dir( dirname( $cacheFile ) );
            }
            if (! is_dir( dirname( $cacheFileJS ) )) {
                G::mk_dir( dirname( $cacheFileJS ) );
            }

            $f = fopen( $cacheFile, 'w+' );
            fwrite( $f, "<?php\n" );
            fwrite( $f, '$translation =' . 'unserialize(\'' . addcslashes( serialize( $translation ), '\\\'' ) . "');\n" );
            fwrite( $f, "?>" );
            fclose( $f );

            //$json = new Services_JSON(); DEPRECATED
            $f = fopen( $cacheFileJS, 'w' );
            if ($f == false) {
                error_log("Error: Cannot write into cachefilejs: $cacheFileJS\n");
            } else {
                fwrite( $f, "var G_STRINGS =" . Bootstrap::json_encode( $translationJS ) . ";\n");
                fclose( $f );
            }

            $res['cacheFile'] = $cacheFile;
            $res['cacheFileJS'] = $cacheFileJS;
            $res['rows'] = count( $translation );
            $res['rowsJS'] = count( $translationJS );
            return $res;
        } catch (Exception $e) {
            $token = strtotime("now");
            PMException::registerErrorLog($e, $token);
            G::outRes( G::LoadTranslation("ID_EXCEPTION_LOG_INTERFAZ", array($token)) );
        }
    }

    /**
     * Load strings from a Database for labels MAFE.
     * @return array
     */
    public function generateFileTranslationMafe()
    {
        try {
            $translation = [];
            $result = ModelTranslation::select()
                    ->where('TRN_ID', 'LIKE', '%ID_MAFE_%')
                    ->where('TRN_CATEGORY', '=', 'LABEL')
                    ->orderBy('TRN_CATEGORY', 'asc')
                    ->orderBy('TRN_ID', 'asc')
                    ->get();
            foreach ($result as $object) {
                $translation[$object->TRN_LANG][$object->TRN_ID] = $object->TRN_VALUE;
            }

            $mafeFolder = PATH_HTML . "translations";
            G::verifyPath($mafeFolder, true);
            if (!is_dir($mafeFolder)) {
                G::mk_dir($mafeFolder);
            }

            $cacheFileMafe = PATH_HTML . "translations" . PATH_SEP . 'translationsMafe' . ".js";
            $status = file_put_contents($cacheFileMafe, "var __TRANSLATIONMAFE = " . Bootstrap::json_encode($translation) . ";\n");
            if ($status === false) {
                Log::channel(':generateFileTranslationMafe')->error("Cannot write into cacheFileMafe: {$cacheFileMafe}", Bootstrap::context());
            }

            return [
                'cacheFileMafe' => $cacheFileMafe,
                'languague' => 0, //must be deprecated
                'rowsMafeJS' => count($translation)
            ];
        } catch (Exception $e) {
            Log::channel(':generateFileTranslationMafe')->error($e->getMessage(), Bootstrap::context());
            G::outRes(G::LoadTranslation("ID_EXCEPTION_LOG_INTERFAZ", [strtotime("now")]));
        }
    }

    /**
     * returns an array with
     * codError 0 - no error, < 0 error
     * rowsAffected 0,1 the number of rows affected
     * message message error.
     */
    public function addTranslation ($category, $id, $languageId, $value)
    {
        //if exists the row in the database propel will update it, otherwise will insert.
        $tr = TranslationPeer::retrieveByPK( $category, $id, $languageId );
        if (! (is_object( $tr ) && get_class( $tr ) == 'Translation')) {
            $tr = new Translation();
        }
        $tr->setTrnCategory( $category );
        $tr->setTrnId( $id );
        $tr->setTrnLang( $languageId );
        $tr->setTrnValue( $value );
        $tr->setTrnUpdateDate( date( 'Y-m-d' ) );

        if ($tr->validate()) {
            // we save it, since we get no validation errors, or do whatever else you like.
            $res = $tr->save();
        } else {
            // Something went wrong. We can now get the validationFailures and handle them.
            $msg = '';
            $validationFailuresArray = $tr->getValidationFailures();
            foreach ($validationFailuresArray as $objValidationFailure) {
                $msg .= $objValidationFailure->getMessage() . "\n";
            }
            return array ('codError' => - 100,'rowsAffected' => 0,'message' => $msg);
        }
        return array ('codError' => 0,'rowsAffected' => $res,'message' => '');
        //to do: uniform  coderror structures for all classes
    }

    /* Load strings from plugin translation.php.
     * @parameter $languageId   (es|en|...).
    */
    public static function generateFileTranslationPlugin ($plugin, $languageId = '')
    {
        if (!file_exists(PATH_PLUGINS . $plugin . PATH_SEP . 'translations' . PATH_SEP . 'translations.php')) {
            return;
        }

        if (!file_exists(PATH_PLUGINS . $plugin . PATH_SEP . 'translations' . PATH_SEP . $plugin . '.' . $languageId . '.po')) {
            if (!file_exists(PATH_PLUGINS . $plugin . PATH_SEP . 'translations' . PATH_SEP . $plugin . '.en.po')) {
                return;
            }
            $languageFile = PATH_PLUGINS . $plugin . PATH_SEP . 'translations' . PATH_SEP . $plugin . '.en.po' ;
        } else {
            $languageFile = PATH_PLUGINS . $plugin . PATH_SEP . 'translations' . PATH_SEP . $plugin . '.' . $languageId . '.po' ;
        }
        $translation = Array ();
        $translationJS = Array ();

        if ($languageId === '') {
            $languageId = defined( 'SYS_LANG' ) ? SYS_LANG : 'en';
        }
        include PATH_PLUGINS . $plugin . PATH_SEP . 'translations'. PATH_SEP . 'translations.php';

        $cacheFile = PATH_LANGUAGECONT . $plugin . "." . $languageId;
        $cacheFileJS = PATH_CORE . 'js' . PATH_SEP . 'labels' . PATH_SEP . $languageId . ".js";

        foreach ($translations as $key => $row) {
            $translation[$key] = $row;
        }


        $POFile = new i18n_PO( $languageFile );
        $POFile->readInit();
        while ($rowTranslation = $POFile->getTranslation()) {
            $context = '';
            foreach ($POFile->translatorComments as $a => $aux) {
                $aux = trim( $aux );
                if ($aux == 'TRANSLATION') {
                    $identifier = $aux;
                } else {
                    $var = explode( '/', $aux );
                    if ($var[0] == 'LABEL') {
                        $context = $aux;
                    }
                    if ($var[0] == 'JAVASCRIPT') {
                        $context = $aux;
                    }
                }
                if (preg_match( '/^([\w-]+)\/([\w-]+\/*[\w-]*\.xml\?)/', $aux, $match )) {
                    $identifier = $aux;
                } else {
                    if (preg_match( '/^([\w-]+)\/([\w-]+\/*[\w-]*\.xml$)/', $aux, $match )) {
                        $context = $aux;
                    }
                }
            }
            if ($identifier == 'TRANSLATION' && $context != '') {
                list ($category, $id) = explode( '/', $context );
                $translation[$id] = $rowTranslation['msgstr'] ;
            }
        }

        try {
            if (! is_dir( dirname( $cacheFile ) )) {
                G::mk_dir( dirname( $cacheFile ) );
            }
            if (! is_dir( dirname( $cacheFileJS ) )) {
                G::mk_dir( dirname( $cacheFileJS ) );
            }

            $f = fopen( $cacheFile, 'w+' );
            fwrite( $f, "<?php\n" );
            fwrite( $f, '$translation' . $plugin . ' =' . 'unserialize(\'' . addcslashes( serialize( $translation ), '\\\'' ) . "');\n" );
            fwrite( $f, "?>" );
            fclose( $f );

            $res['cacheFile'] = $cacheFile;
            $res['cacheFileJS'] = $cacheFileJS;
            $res['rows'] = count( $translation );
            $res['rowsJS'] = count( $translationJS );
            return $res;
        } catch (Exception $e) {
            $token = strtotime("now");
            PMException::registerErrorLog($e, $token);
            G::outRes( G::LoadTranslation("ID_EXCEPTION_LOG_INTERFAZ", array($token)) );
        }
    }

    public function addTranslationEnvironmentPlugins ($plugin, $locale, $headers, $numRecords)
    {
        $filePath = PATH_DATA . "META-INF" . PATH_SEP . $plugin . ".env";
        $environments = Array ();

        if (file_exists( $filePath )) {
            $environments = unserialize( file_get_contents( $filePath ) );
        }

        $environment['LOCALE'] = $locale;
        $environment['HEADERS'] = $headers;
        $environment['DATE'] = date( 'Y-m-d H:i:s' );
        $environment['NUM_RECORDS'] = $numRecords;
        $environment['LANGUAGE'] = $headers['X-Poedit-Language'];

        if (strpos( $locale, self::$localeSeparator ) !== false) {
            list ($environment['LAN_ID'], $environment['IC_UID']) = explode( self::$localeSeparator, strtoupper( $locale ) );
            $environments[$environment['LAN_ID']][$environment['IC_UID']] = $environment;
        } else {
            $environment['LAN_ID'] = strtoupper( $locale );
            $environment['IC_UID'] = '';
            $environments[$locale]['__INTERNATIONAL__'] = $environment;
        }

        file_put_contents( $filePath, serialize( $environments ) );
    }

    public function remove ($sCategory, $sId, $sLang)
    {
        $oTranslation = TranslationPeer::retrieveByPK( $sCategory, $sId, $sLang );
        if ((is_object( $oTranslation ) && get_class( $oTranslation ) == 'Translation')) {
            $oTranslation->delete();
        }
    }

    public function addTranslationEnvironment ($locale, $headers, $numRecords)
    {
        $filePath = $this->envFilePath;
        $environments = Array ();

        if (file_exists( $filePath )) {
            $environments = unserialize( file_get_contents( $filePath ) );
        }

        $environment['LOCALE'] = $locale;
        $environment['HEADERS'] = $headers;
        $environment['DATE'] = date( 'Y-m-d H:i:s' );
        $environment['NUM_RECORDS'] = $numRecords;
        $environment['LANGUAGE'] = $headers['X-Poedit-Language'];
        $environment['COUNTRY'] = $headers['X-Poedit-Country'];

        if (strpos( $locale, self::$localeSeparator ) !== false) {
            list ($environment['LAN_ID'], $environment['IC_UID']) = explode( self::$localeSeparator, strtoupper( $locale ) );
            $environments[$environment['LAN_ID']][$environment['IC_UID']] = $environment;
        } else {
            $environment['LAN_ID'] = strtoupper( $locale );
            $environment['IC_UID'] = '';
            $environments[$locale]['__INTERNATIONAL__'] = $environment;
        }

        file_put_contents( $filePath, serialize( $environments ) );
    }

    public function removeTranslationEnvironment ($locale)
    {
        $filePath = $this->envFilePath;
        if (strpos( $locale, self::$localeSeparator ) !== false) {
            list ($LAN_ID, $IC_UID) = explode( '-', strtoupper( $locale ) );
        } else {
            $LAN_ID = $locale;
            $IC_UID = '__INTERNATIONAL__';
        }

        if (file_exists( $filePath )) {
            $environments = unserialize( file_get_contents( $filePath ) );
            if (! isset( $environments[$LAN_ID][$IC_UID] )) {
                return null;
            }

            unset( $environments[$LAN_ID][$IC_UID] );
            file_put_contents( $filePath, serialize( $environments ) );

            if (file_exists( PATH_CORE . "META-INF" . PATH_SEP . "translation." . $locale )) {
                G::rm_dir( PATH_DATA . "META-INF" . PATH_SEP . "translation." . $locale );
            }
            if (file_exists( PATH_CORE . PATH_SEP . 'content' . PATH_SEP . 'translations' . PATH_SEP . 'processmaker' . $locale . '.po' )) {
                G::rm_dir( PATH_CORE . PATH_SEP . 'content' . PATH_SEP . 'translations' . PATH_SEP . 'processmaker' . $locale . '.po' );
            }
            G::auditLog("DeleteLanguage", "Language: ".$locale);
        }
    }

    public function getTranslationEnvironments ()
    {
        $filePath = $this->envFilePath;
        $envs = Array ();

        if (! file_exists( $filePath )) {
            //the transaltions table file doesn't exist, then build it

            if (! is_dir( dirname( $this->envFilePath ) )) {
                G::mk_dir( dirname( $this->envFilePath ) );
            }
            $translationsPath = PATH_CORE . "content" . PATH_SEP . 'translations' . PATH_SEP;
            $basePOFile = $translationsPath . 'english' . PATH_SEP . 'processmaker.en.po';

            $params = self::getInfoFromPOFile( $basePOFile );
            $this->addTranslationEnvironment( $params['LOCALE'], $params['HEADERS'], $params['COUNT'] );
            //getting more language translations
            $files = glob( $translationsPath . "*.po" );
            if(is_array($files)){
                foreach ($files as $file) {
                    $params = self::getInfoFromPOFile( $file );
                    $this->addTranslationEnvironment( $params['LOCALE'], $params['HEADERS'], $params['COUNT'] );
                }
            }
        }
        $envs = unserialize( file_get_contents( $filePath ) );

        $environments = Array ();
        foreach ($envs as $LAN_ID => $rec1) {
            foreach ($rec1 as $IC_UID => $rec2) {
                $environments[] = $rec2;
            }
        }

        return $this->sortByColumn($environments, 'LANGUAGE');

        /*

        $o = new DataBaseMaintenance('localhost', 'root', 'atopml2005');
        $o->connect('wf_os');
        $r = $o->query('select * from ISO_COUNTRY');
        foreach ($r as $i=>$v) {
            $r[$i]['IC_NAME'] = utf8_encode($r[$i]['IC_NAME']);
            unset($r[$i]['IC_SORT_ORDER']);
        }
        $r1 = $o->query('select * from LANGUAGE');
        $r2 = Array();
        foreach ($r1 as $i=>$v) {
            $r2[$i]['LAN_NAME'] = utf8_encode($r1[$i]['LAN_NAME']);
            $r2[$i]['LAN_ID'] = utf8_encode($r1[$i]['LAN_ID']);
        }
        $s = Array('ISO_COUNTRY'=>$r, 'LANGUAGE'=>$r2);
        file_put_contents($translationsPath . 'pmos-translations.meta', serialize($s));
        */
    }

    /**
     * Sorts an array according to a specified column
     * Params : array  $table
     *          string $colname
     *          bool   $numeric
     **/
    public function sortByColumn($table, $colname) {
        $tn = $ts = $temp_num = $temp_str = array();
        foreach ($table as $key => $row) {
            if(is_numeric(substr($row[$colname], 0, 1))) {
                $tn[$key] = $row[$colname];
                $temp_num[$key] = $row;
            }
            else {
                $ts[$key] = $row[$colname];
                $temp_str[$key] = $row;
            }
        }
        unset($table);

        array_multisort($tn, SORT_ASC, SORT_NUMERIC, $temp_num);
        array_multisort($ts, SORT_ASC, SORT_STRING, $temp_str);
        return array_merge($temp_num, $temp_str);
    }

    public function getInfoFromPOFile ($file)
    {
        $POFile = new i18n_PO( $file );
        $POFile->readInit();
        $POHeaders = $POFile->getHeaders();

        if ($POHeaders['X-Poedit-Country'] != '.') {
            $country = self::getTranslationMetaByCountryName( $POHeaders['X-Poedit-Country'] );
        } else {
            $country = '.';
        }
        $language = self::getTranslationMetaByLanguageName( $POHeaders['X-Poedit-Language'] );

        if ($language !== false) {
            if ($country !== false) {
                if ($country != '.') {
                    $LOCALE = $language['LAN_ID'] . '-' . $country['IC_UID'];
                } elseif ($country == '.') {
                    //this a trsnlation file with a language international, no country name was set
                    $LOCALE = $language['LAN_ID'];
                } else {
                    throw new Exception( 'PO File Error: "' . $file . '" has a invalid country definition!' );
                }
            } else {
                throw new Exception( 'PO File Error: "' . $file . '" has a invalid country definition!' );
            }
        } else {
            throw new Exception( 'PO File Error: "' . $file . '" has a invalid language definition!' );
        }
        $countItems = 0;
        try {
            while ($rowTranslation = $POFile->getTranslation()) {
                $countItems ++;
            }
        } catch (Exception $e) {
            $countItems = '-';
        }
        return Array ('LOCALE' => $LOCALE,'HEADERS' => $POHeaders,'COUNT' => $countItems);
    }

    public function getTranslationEnvironment ($locale)
    {
        $filePath = $this->envFilePath;
        $environments = Array ();

        if (! file_exists( $filePath )) {
            throw new Exception( "The file $filePath doesn't exist" );
        }

        $environments = unserialize( file_get_contents( $filePath ) );
        if (strpos( $locale, self::$localeSeparator ) !== false) {
            list ($LAN_ID, $IC_UID) = explode( self::localeSeparator, strtoupper( $locale ) );
        } else {
            $LAN_ID = $locale;
            $IC_UID = '__INTERNATIONAL__';
        }

        if (isset( $environments[$LAN_ID][$IC_UID] )) {
            return $environments[$LAN_ID][$IC_UID];
        } else {
            return false;
        }
    }

    public function saveTranslationEnvironment ($locale, $data)
    {
        $filePath = $this->envFilePath;
        $environments = Array ();

        if (! file_exists( $filePath )) {
            throw new Exception( "The file $filePath doesn't exist" );
        }

        $environments = unserialize( file_get_contents( $filePath ) );
        if (strpos( $locale, self::$localeSeparator ) !== false) {
            list ($LAN_ID, $IC_UID) = explode( self::localeSeparator, strtoupper( $locale ) );
        } else {
            $LAN_ID = $locale;
            $IC_UID = '__INTERNATIONAL__';
        }

        $environments[$LAN_ID][$IC_UID] = $data;
        file_put_contents( $filePath, serialize( $environments ) );
    }

    public function getTranslationMeta ()
    {
        $translationsPath = PATH_CORE . "content" . PATH_SEP . 'translations' . PATH_SEP;
        $translationsTable = unserialize( file_get_contents( $translationsPath . 'pmos-translations.meta' ) );
        return $translationsTable;
    }

    public function getTranslationMetaByCountryName ($IC_NAME)
    {
        $translationsTable = self::getTranslationMeta();

        foreach ($translationsTable['ISO_COUNTRY'] as $row) {
            if ($row['IC_NAME'] == $IC_NAME) {
                return $row;
            }
        }
        return false;
    }

    public function getTranslationMetaByLanguageName ($LAN_NAME)
    {
        $translationsTable = self::getTranslationMeta();

        foreach ($translationsTable['LANGUAGE'] as $row) {
            if ($row['LAN_NAME'] == $LAN_NAME) {
                return $row;
            }
        }
        return false;
    }

    public function generateTransaltionMafe ($lang='en')
    {
        if (!file_exists(PATH_TRUNK .'vendor/colosa/MichelangeloFE/' . 'labels.php')) {
            throw new Exception( 'labels.php not exist in MAFE ');
        }

        include PATH_TRUNK .'vendor/colosa/MichelangeloFE/' . 'labels.php';

        foreach ($labels as $key => $row) {
            $this->addTranslation ('LABEL', 'ID_MAFE_'.G::encryptOld($row), $lang, $row);
        }
    }
}
