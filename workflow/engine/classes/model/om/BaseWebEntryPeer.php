<?php

require_once 'propel/util/BasePeer.php';
// The object class -- needed for instanceof checks in this class.
// actual class may be a subclass -- as returned by WebEntryPeer::getOMClass()
include_once 'classes/model/WebEntry.php';

/**
 * Base static class for performing query and update operations on the 'WEB_ENTRY' table.
 *
 * 
 *
 * @package    workflow.classes.model.om
 */
abstract class BaseWebEntryPeer
{

    /** the default database name for this class */
    const DATABASE_NAME = 'workflow';

    /** the table name for this class */
    const TABLE_NAME = 'WEB_ENTRY';

    /** A class that can be returned by this peer. */
    const CLASS_DEFAULT = 'classes.model.WebEntry';

    /** The total number of columns. */
    const NUM_COLUMNS = 24;

    /** The number of lazy-loaded columns. */
    const NUM_LAZY_LOAD_COLUMNS = 0;


    /** the column name for the WE_UID field */
    const WE_UID = 'WEB_ENTRY.WE_UID';

    /** the column name for the PRO_UID field */
    const PRO_UID = 'WEB_ENTRY.PRO_UID';

    /** the column name for the TAS_UID field */
    const TAS_UID = 'WEB_ENTRY.TAS_UID';

    /** the column name for the DYN_UID field */
    const DYN_UID = 'WEB_ENTRY.DYN_UID';

    /** the column name for the USR_UID field */
    const USR_UID = 'WEB_ENTRY.USR_UID';

    /** the column name for the WE_METHOD field */
    const WE_METHOD = 'WEB_ENTRY.WE_METHOD';

    /** the column name for the WE_INPUT_DOCUMENT_ACCESS field */
    const WE_INPUT_DOCUMENT_ACCESS = 'WEB_ENTRY.WE_INPUT_DOCUMENT_ACCESS';

    /** the column name for the WE_DATA field */
    const WE_DATA = 'WEB_ENTRY.WE_DATA';

    /** the column name for the WE_CREATE_USR_UID field */
    const WE_CREATE_USR_UID = 'WEB_ENTRY.WE_CREATE_USR_UID';

    /** the column name for the WE_UPDATE_USR_UID field */
    const WE_UPDATE_USR_UID = 'WEB_ENTRY.WE_UPDATE_USR_UID';

    /** the column name for the WE_CREATE_DATE field */
    const WE_CREATE_DATE = 'WEB_ENTRY.WE_CREATE_DATE';

    /** the column name for the WE_UPDATE_DATE field */
    const WE_UPDATE_DATE = 'WEB_ENTRY.WE_UPDATE_DATE';

    /** the column name for the WE_TYPE field */
    const WE_TYPE = 'WEB_ENTRY.WE_TYPE';

    /** the column name for the WE_CUSTOM_TITLE field */
    const WE_CUSTOM_TITLE = 'WEB_ENTRY.WE_CUSTOM_TITLE';

    /** the column name for the WE_AUTHENTICATION field */
    const WE_AUTHENTICATION = 'WEB_ENTRY.WE_AUTHENTICATION';

    /** the column name for the WE_HIDE_INFORMATION_BAR field */
    const WE_HIDE_INFORMATION_BAR = 'WEB_ENTRY.WE_HIDE_INFORMATION_BAR';

    /** the column name for the WE_HIDE_ACTIVE_SESSION_WARNING field */
    const WE_HIDE_ACTIVE_SESSION_WARNING = 'WEB_ENTRY.WE_HIDE_ACTIVE_SESSION_WARNING';

    /** the column name for the WE_CALLBACK field */
    const WE_CALLBACK = 'WEB_ENTRY.WE_CALLBACK';

    /** the column name for the WE_CALLBACK_URL field */
    const WE_CALLBACK_URL = 'WEB_ENTRY.WE_CALLBACK_URL';

    /** the column name for the WE_LINK_GENERATION field */
    const WE_LINK_GENERATION = 'WEB_ENTRY.WE_LINK_GENERATION';

    /** the column name for the WE_LINK_SKIN field */
    const WE_LINK_SKIN = 'WEB_ENTRY.WE_LINK_SKIN';

    /** the column name for the WE_LINK_LANGUAGE field */
    const WE_LINK_LANGUAGE = 'WEB_ENTRY.WE_LINK_LANGUAGE';

    /** the column name for the WE_LINK_DOMAIN field */
    const WE_LINK_DOMAIN = 'WEB_ENTRY.WE_LINK_DOMAIN';

    /** the column name for the WE_SHOW_IN_NEW_CASE field */
    const WE_SHOW_IN_NEW_CASE = 'WEB_ENTRY.WE_SHOW_IN_NEW_CASE';

    /** The PHP to DB Name Mapping */
    private static $phpNameMap = null;


    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    private static $fieldNames = array (
        BasePeer::TYPE_PHPNAME => array ('WeUid', 'ProUid', 'TasUid', 'DynUid', 'UsrUid', 'WeMethod', 'WeInputDocumentAccess', 'WeData', 'WeCreateUsrUid', 'WeUpdateUsrUid', 'WeCreateDate', 'WeUpdateDate', 'WeType', 'WeCustomTitle', 'WeAuthentication', 'WeHideInformationBar', 'WeHideActiveSessionWarning', 'WeCallback', 'WeCallbackUrl', 'WeLinkGeneration', 'WeLinkSkin', 'WeLinkLanguage', 'WeLinkDomain', 'WeShowInNewCase', ),
        BasePeer::TYPE_COLNAME => array (WebEntryPeer::WE_UID, WebEntryPeer::PRO_UID, WebEntryPeer::TAS_UID, WebEntryPeer::DYN_UID, WebEntryPeer::USR_UID, WebEntryPeer::WE_METHOD, WebEntryPeer::WE_INPUT_DOCUMENT_ACCESS, WebEntryPeer::WE_DATA, WebEntryPeer::WE_CREATE_USR_UID, WebEntryPeer::WE_UPDATE_USR_UID, WebEntryPeer::WE_CREATE_DATE, WebEntryPeer::WE_UPDATE_DATE, WebEntryPeer::WE_TYPE, WebEntryPeer::WE_CUSTOM_TITLE, WebEntryPeer::WE_AUTHENTICATION, WebEntryPeer::WE_HIDE_INFORMATION_BAR, WebEntryPeer::WE_HIDE_ACTIVE_SESSION_WARNING, WebEntryPeer::WE_CALLBACK, WebEntryPeer::WE_CALLBACK_URL, WebEntryPeer::WE_LINK_GENERATION, WebEntryPeer::WE_LINK_SKIN, WebEntryPeer::WE_LINK_LANGUAGE, WebEntryPeer::WE_LINK_DOMAIN, WebEntryPeer::WE_SHOW_IN_NEW_CASE, ),
        BasePeer::TYPE_FIELDNAME => array ('WE_UID', 'PRO_UID', 'TAS_UID', 'DYN_UID', 'USR_UID', 'WE_METHOD', 'WE_INPUT_DOCUMENT_ACCESS', 'WE_DATA', 'WE_CREATE_USR_UID', 'WE_UPDATE_USR_UID', 'WE_CREATE_DATE', 'WE_UPDATE_DATE', 'WE_TYPE', 'WE_CUSTOM_TITLE', 'WE_AUTHENTICATION', 'WE_HIDE_INFORMATION_BAR', 'WE_HIDE_ACTIVE_SESSION_WARNING', 'WE_CALLBACK', 'WE_CALLBACK_URL', 'WE_LINK_GENERATION', 'WE_LINK_SKIN', 'WE_LINK_LANGUAGE', 'WE_LINK_DOMAIN', 'WE_SHOW_IN_NEW_CASE', ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[BasePeer::TYPE_PHPNAME]['Id'] = 0
     */
    private static $fieldKeys = array (
        BasePeer::TYPE_PHPNAME => array ('WeUid' => 0, 'ProUid' => 1, 'TasUid' => 2, 'DynUid' => 3, 'UsrUid' => 4, 'WeMethod' => 5, 'WeInputDocumentAccess' => 6, 'WeData' => 7, 'WeCreateUsrUid' => 8, 'WeUpdateUsrUid' => 9, 'WeCreateDate' => 10, 'WeUpdateDate' => 11, 'WeType' => 12, 'WeCustomTitle' => 13, 'WeAuthentication' => 14, 'WeHideInformationBar' => 15, 'WeHideActiveSessionWarning' => 16, 'WeCallback' => 17, 'WeCallbackUrl' => 18, 'WeLinkGeneration' => 19, 'WeLinkSkin' => 20, 'WeLinkLanguage' => 21, 'WeLinkDomain' => 22, 'WeShowInNewCase' => 23, ),
        BasePeer::TYPE_COLNAME => array (WebEntryPeer::WE_UID => 0, WebEntryPeer::PRO_UID => 1, WebEntryPeer::TAS_UID => 2, WebEntryPeer::DYN_UID => 3, WebEntryPeer::USR_UID => 4, WebEntryPeer::WE_METHOD => 5, WebEntryPeer::WE_INPUT_DOCUMENT_ACCESS => 6, WebEntryPeer::WE_DATA => 7, WebEntryPeer::WE_CREATE_USR_UID => 8, WebEntryPeer::WE_UPDATE_USR_UID => 9, WebEntryPeer::WE_CREATE_DATE => 10, WebEntryPeer::WE_UPDATE_DATE => 11, WebEntryPeer::WE_TYPE => 12, WebEntryPeer::WE_CUSTOM_TITLE => 13, WebEntryPeer::WE_AUTHENTICATION => 14, WebEntryPeer::WE_HIDE_INFORMATION_BAR => 15, WebEntryPeer::WE_HIDE_ACTIVE_SESSION_WARNING => 16, WebEntryPeer::WE_CALLBACK => 17, WebEntryPeer::WE_CALLBACK_URL => 18, WebEntryPeer::WE_LINK_GENERATION => 19, WebEntryPeer::WE_LINK_SKIN => 20, WebEntryPeer::WE_LINK_LANGUAGE => 21, WebEntryPeer::WE_LINK_DOMAIN => 22, WebEntryPeer::WE_SHOW_IN_NEW_CASE => 23, ),
        BasePeer::TYPE_FIELDNAME => array ('WE_UID' => 0, 'PRO_UID' => 1, 'TAS_UID' => 2, 'DYN_UID' => 3, 'USR_UID' => 4, 'WE_METHOD' => 5, 'WE_INPUT_DOCUMENT_ACCESS' => 6, 'WE_DATA' => 7, 'WE_CREATE_USR_UID' => 8, 'WE_UPDATE_USR_UID' => 9, 'WE_CREATE_DATE' => 10, 'WE_UPDATE_DATE' => 11, 'WE_TYPE' => 12, 'WE_CUSTOM_TITLE' => 13, 'WE_AUTHENTICATION' => 14, 'WE_HIDE_INFORMATION_BAR' => 15, 'WE_HIDE_ACTIVE_SESSION_WARNING' => 16, 'WE_CALLBACK' => 17, 'WE_CALLBACK_URL' => 18, 'WE_LINK_GENERATION' => 19, 'WE_LINK_SKIN' => 20, 'WE_LINK_LANGUAGE' => 21, 'WE_LINK_DOMAIN' => 22, 'WE_SHOW_IN_NEW_CASE' => 23, ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, )
    );

    /**
     * @return     MapBuilder the map builder for this peer
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function getMapBuilder()
    {
        include_once 'classes/model/map/WebEntryMapBuilder.php';
        return BasePeer::getMapBuilder('classes.model.map.WebEntryMapBuilder');
    }
    /**
     * Gets a map (hash) of PHP names to DB column names.
     *
     * @return     array The PHP to DB name map for this peer
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     * @deprecated Use the getFieldNames() and translateFieldName() methods instead of this.
     */
    public static function getPhpNameMap()
    {
        if (self::$phpNameMap === null) {
            $map = WebEntryPeer::getTableMap();
            $columns = $map->getColumns();
            $nameMap = array();
            foreach ($columns as $column) {
                $nameMap[$column->getPhpName()] = $column->getColumnName();
            }
            self::$phpNameMap = $nameMap;
        }
        return self::$phpNameMap;
    }
    /**
     * Translates a fieldname to another type
     *
     * @param      string $name field name
     * @param      string $fromType One of the class type constants TYPE_PHPNAME,
     *                         TYPE_COLNAME, TYPE_FIELDNAME, TYPE_NUM
     * @param      string $toType   One of the class type constants
     * @return     string translated name of the field.
     */
    static public function translateFieldName($name, $fromType, $toType)
    {
        $toNames = self::getFieldNames($toType);
        $key = isset(self::$fieldKeys[$fromType][$name]) ? self::$fieldKeys[$fromType][$name] : null;
        if ($key === null) {
            throw new PropelException("'$name' could not be found in the field names of type '$fromType'. These are: " . print_r(self::$fieldKeys[$fromType], true));
        }
        return $toNames[$key];
    }

    /**
     * Returns an array of of field names.
     *
     * @param      string $type The type of fieldnames to return:
     *                      One of the class type constants TYPE_PHPNAME,
     *                      TYPE_COLNAME, TYPE_FIELDNAME, TYPE_NUM
     * @return     array A list of field names
     */

    static public function getFieldNames($type = BasePeer::TYPE_PHPNAME)
    {
        if (!array_key_exists($type, self::$fieldNames)) {
            throw new PropelException('Method getFieldNames() expects the parameter $type to be one of the class constants TYPE_PHPNAME, TYPE_COLNAME, TYPE_FIELDNAME, TYPE_NUM. ' . $type . ' was given.');
        }
        return self::$fieldNames[$type];
    }

    /**
     * Convenience method which changes table.column to alias.column.
     *
     * Using this method you can maintain SQL abstraction while using column aliases.
     * <code>
     *      $c->addAlias("alias1", TablePeer::TABLE_NAME);
     *      $c->addJoin(TablePeer::alias("alias1", TablePeer::PRIMARY_KEY_COLUMN), TablePeer::PRIMARY_KEY_COLUMN);
     * </code>
     * @param      string $alias The alias for the current table.
     * @param      string $column The column name for current table. (i.e. WebEntryPeer::COLUMN_NAME).
     * @return     string
     */
    public static function alias($alias, $column)
    {
        return str_replace(WebEntryPeer::TABLE_NAME.'.', $alias.'.', $column);
    }

    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param      criteria object containing the columns to add.
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria)
    {

        $criteria->addSelectColumn(WebEntryPeer::WE_UID);

        $criteria->addSelectColumn(WebEntryPeer::PRO_UID);

        $criteria->addSelectColumn(WebEntryPeer::TAS_UID);

        $criteria->addSelectColumn(WebEntryPeer::DYN_UID);

        $criteria->addSelectColumn(WebEntryPeer::USR_UID);

        $criteria->addSelectColumn(WebEntryPeer::WE_METHOD);

        $criteria->addSelectColumn(WebEntryPeer::WE_INPUT_DOCUMENT_ACCESS);

        $criteria->addSelectColumn(WebEntryPeer::WE_DATA);

        $criteria->addSelectColumn(WebEntryPeer::WE_CREATE_USR_UID);

        $criteria->addSelectColumn(WebEntryPeer::WE_UPDATE_USR_UID);

        $criteria->addSelectColumn(WebEntryPeer::WE_CREATE_DATE);

        $criteria->addSelectColumn(WebEntryPeer::WE_UPDATE_DATE);

        $criteria->addSelectColumn(WebEntryPeer::WE_TYPE);

        $criteria->addSelectColumn(WebEntryPeer::WE_CUSTOM_TITLE);

        $criteria->addSelectColumn(WebEntryPeer::WE_AUTHENTICATION);

        $criteria->addSelectColumn(WebEntryPeer::WE_HIDE_INFORMATION_BAR);

        $criteria->addSelectColumn(WebEntryPeer::WE_HIDE_ACTIVE_SESSION_WARNING);

        $criteria->addSelectColumn(WebEntryPeer::WE_CALLBACK);

        $criteria->addSelectColumn(WebEntryPeer::WE_CALLBACK_URL);

        $criteria->addSelectColumn(WebEntryPeer::WE_LINK_GENERATION);

        $criteria->addSelectColumn(WebEntryPeer::WE_LINK_SKIN);

        $criteria->addSelectColumn(WebEntryPeer::WE_LINK_LANGUAGE);

        $criteria->addSelectColumn(WebEntryPeer::WE_LINK_DOMAIN);

        $criteria->addSelectColumn(WebEntryPeer::WE_SHOW_IN_NEW_CASE);

    }

    const COUNT = 'COUNT(WEB_ENTRY.WE_UID)';
    const COUNT_DISTINCT = 'COUNT(DISTINCT WEB_ENTRY.WE_UID)';

    /**
     * Returns the number of rows matching criteria.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns (You can also set DISTINCT modifier in Criteria).
     * @param      Connection $con
     * @return     int Number of matching rows.
     */
    public static function doCount(Criteria $criteria, $distinct = false, $con = null)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // clear out anything that might confuse the ORDER BY clause
        $criteria->clearSelectColumns()->clearOrderByColumns();
        if ($distinct || in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->addSelectColumn(WebEntryPeer::COUNT_DISTINCT);
        } else {
            $criteria->addSelectColumn(WebEntryPeer::COUNT);
        }

        // just in case we're grouping: add those columns to the select statement
        foreach ($criteria->getGroupByColumns() as $column) {
            $criteria->addSelectColumn($column);
        }

        $rs = WebEntryPeer::doSelectRS($criteria, $con);
        if ($rs->next()) {
            return $rs->getInt(1);
        } else {
            // no rows returned; we infer that means 0 matches.
            return 0;
        }
    }
    /**
     * Method to select one object from the DB.
     *
     * @param      Criteria $criteria object used to create the SELECT statement.
     * @param      Connection $con
     * @return     WebEntry
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function doSelectOne(Criteria $criteria, $con = null)
    {
        $critcopy = clone $criteria;
        $critcopy->setLimit(1);
        $objects = WebEntryPeer::doSelect($critcopy, $con);
        if ($objects) {
            return $objects[0];
        }
        return null;
    }
    /**
     * Method to do selects.
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      Connection $con
     * @return     array Array of selected Objects
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function doSelect(Criteria $criteria, $con = null)
    {
        return WebEntryPeer::populateObjects(WebEntryPeer::doSelectRS($criteria, $con));
    }
    /**
     * Prepares the Criteria object and uses the parent doSelect()
     * method to get a ResultSet.
     *
     * Use this method directly if you want to just get the resultset
     * (instead of an array of objects).
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      Connection $con the connection to use
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     * @return     ResultSet The resultset object with numerically-indexed fields.
     * @see        BasePeer::doSelect()
     */
    public static function doSelectRS(Criteria $criteria, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }

        if (!$criteria->getSelectColumns()) {
            $criteria = clone $criteria;
            WebEntryPeer::addSelectColumns($criteria);
        }

        // Set the correct dbName
        $criteria->setDbName(self::DATABASE_NAME);

        // BasePeer returns a Creole ResultSet, set to return
        // rows indexed numerically.
        return BasePeer::doSelect($criteria, $con);
    }
    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function populateObjects(ResultSet $rs)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = WebEntryPeer::getOMClass();
        $cls = Propel::import($cls);
        // populate the object(s)
        while ($rs->next()) {

            $obj = new $cls();
            $obj->hydrate($rs);
            $results[] = $obj;

        }
        return $results;
    }
    /**
     * Returns the TableMap related to this peer.
     * This method is not needed for general use but a specific application could have a need.
     * @return     TableMap
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getDatabaseMap(self::DATABASE_NAME)->getTable(self::TABLE_NAME);
    }

    /**
     * The class that the Peer will make instances of.
     *
     * This uses a dot-path notation which is tranalted into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @return     string path.to.ClassName
     */
    public static function getOMClass()
    {
        return WebEntryPeer::CLASS_DEFAULT;
    }

    /**
     * Method perform an INSERT on the database, given a WebEntry or Criteria object.
     *
     * @param      mixed $values Criteria or WebEntry object containing data that is used to create the INSERT statement.
     * @param      Connection $con the connection to use
     * @return     mixed The new primary key.
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function doInsert($values, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity
        } else {
            $criteria = $values->buildCriteria(); // build Criteria from WebEntry object
        }


        // Set the correct dbName
        $criteria->setDbName(self::DATABASE_NAME);

        try {
            // use transaction because $criteria could contain info
            // for more than one table (I guess, conceivably)
            $con->begin();
            $pk = BasePeer::doInsert($criteria, $con);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }

        return $pk;
    }

    /**
     * Method perform an UPDATE on the database, given a WebEntry or Criteria object.
     *
     * @param      mixed $values Criteria or WebEntry object containing data create the UPDATE statement.
     * @param      Connection $con The connection to use (specify Connection exert more control over transactions).
     * @return     int The number of affected rows (if supported by underlying database driver).
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function doUpdate($values, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }

        $selectCriteria = new Criteria(self::DATABASE_NAME);

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity

            $comparison = $criteria->getComparison(WebEntryPeer::WE_UID);
            $selectCriteria->add(WebEntryPeer::WE_UID, $criteria->remove(WebEntryPeer::WE_UID), $comparison);

        } else {
            $criteria = $values->buildCriteria(); // gets full criteria
            $selectCriteria = $values->buildPkeyCriteria(); // gets criteria w/ primary key(s)
        }

        // set the correct dbName
        $criteria->setDbName(self::DATABASE_NAME);

        return BasePeer::doUpdate($selectCriteria, $criteria, $con);
    }

    /**
     * Method to DELETE all rows from the WEB_ENTRY table.
     *
     * @return     int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll($con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->begin();
            $affectedRows += BasePeer::doDeleteAll(WebEntryPeer::TABLE_NAME, $con);
            $con->commit();
            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Method perform a DELETE on the database, given a WebEntry or Criteria object OR a primary key value.
     *
     * @param      mixed $values Criteria or WebEntry object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param      Connection $con the connection to use
     * @return     int  The number of affected rows (if supported by underlying database driver).
     *             This includes CASCADE-related rows
     *              if supported by native driver or if emulated using Propel.
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
    */
    public static function doDelete($values, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(WebEntryPeer::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity
        } elseif ($values instanceof WebEntry) {

            $criteria = $values->buildPkeyCriteria();
        } else {
            // it must be the primary key
            $criteria = new Criteria(self::DATABASE_NAME);
            $criteria->add(WebEntryPeer::WE_UID, (array) $values, Criteria::IN);
        }

        // Set the correct dbName
        $criteria->setDbName(self::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->begin();

            $affectedRows += BasePeer::doDelete($criteria, $con);
            $con->commit();
            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Validates all modified columns of given WebEntry object.
     * If parameter $columns is either a single column name or an array of column names
     * than only those columns are validated.
     *
     * NOTICE: This does not apply to primary or foreign keys for now.
     *
     * @param      WebEntry $obj The object to validate.
     * @param      mixed $cols Column name or array of column names.
     *
     * @return     mixed TRUE if all columns are valid or the error message of the first invalid column.
     */
    public static function doValidate(WebEntry $obj, $cols = null)
    {
        $columns = array();

        if ($cols) {
            $dbMap = Propel::getDatabaseMap(WebEntryPeer::DATABASE_NAME);
            $tableMap = $dbMap->getTable(WebEntryPeer::TABLE_NAME);

            if (! is_array($cols)) {
                $cols = array($cols);
            }

            foreach ($cols as $colName) {
                if ($tableMap->containsColumn($colName)) {
                    $get = 'get' . $tableMap->getColumn($colName)->getPhpName();
                    $columns[$colName] = $obj->$get();
                }
            }
        } else {

        if ($obj->isNew() || $obj->isColumnModified(WebEntryPeer::WE_TYPE))
            $columns[WebEntryPeer::WE_TYPE] = $obj->getWeType();

        if ($obj->isNew() || $obj->isColumnModified(WebEntryPeer::WE_AUTHENTICATION))
            $columns[WebEntryPeer::WE_AUTHENTICATION] = $obj->getWeAuthentication();

        if ($obj->isNew() || $obj->isColumnModified(WebEntryPeer::WE_CALLBACK))
            $columns[WebEntryPeer::WE_CALLBACK] = $obj->getWeCallback();

        if ($obj->isNew() || $obj->isColumnModified(WebEntryPeer::WE_LINK_GENERATION))
            $columns[WebEntryPeer::WE_LINK_GENERATION] = $obj->getWeLinkGeneration();

        }

        return BasePeer::doValidate(WebEntryPeer::DATABASE_NAME, WebEntryPeer::TABLE_NAME, $columns);
    }

    /**
     * Retrieve a single object by pkey.
     *
     * @param      mixed $pk the primary key.
     * @param      Connection $con the connection to use
     * @return     WebEntry
     */
    public static function retrieveByPK($pk, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }

        $criteria = new Criteria(WebEntryPeer::DATABASE_NAME);

        $criteria->add(WebEntryPeer::WE_UID, $pk);


        $v = WebEntryPeer::doSelect($criteria, $con);

        return !empty($v) > 0 ? $v[0] : null;
    }

    /**
     * Retrieve multiple objects by pkey.
     *
     * @param      array $pks List of primary keys
     * @param      Connection $con the connection to use
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function retrieveByPKs($pks, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }

        $objs = null;
        if (empty($pks)) {
            $objs = array();
        } else {
            $criteria = new Criteria();
            $criteria->add(WebEntryPeer::WE_UID, $pks, Criteria::IN);
            $objs = WebEntryPeer::doSelect($criteria, $con);
        }
        return $objs;
    }
}


// static code to register the map builder for this Peer with the main Propel class
if (Propel::isInit()) {
    // the MapBuilder classes register themselves with Propel during initialization
    // so we need to load them here.
    try {
        BaseWebEntryPeer::getMapBuilder();
    } catch (Exception $e) {
        Propel::log('Could not initialize Peer: ' . $e->getMessage(), Propel::LOG_ERR);
    }
} else {
    // even if Propel is not yet initialized, the map builder class can be registered
    // now and then it will be loaded when Propel initializes.
    require_once 'classes/model/map/WebEntryMapBuilder.php';
    Propel::registerMapBuilder('classes.model.map.WebEntryMapBuilder');
}

