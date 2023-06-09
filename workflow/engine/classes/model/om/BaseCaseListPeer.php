<?php

require_once 'propel/util/BasePeer.php';
// The object class -- needed for instanceof checks in this class.
// actual class may be a subclass -- as returned by CaseListPeer::getOMClass()
include_once 'classes/model/CaseList.php';

/**
 * Base static class for performing query and update operations on the 'CASE_LIST' table.
 *
 * 
 *
 * @package    workflow.classes.model.om
 */
abstract class BaseCaseListPeer
{

    /** the default database name for this class */
    const DATABASE_NAME = 'workflow';

    /** the table name for this class */
    const TABLE_NAME = 'CASE_LIST';

    /** A class that can be returned by this peer. */
    const CLASS_DEFAULT = 'classes.model.CaseList';

    /** The total number of columns. */
    const NUM_COLUMNS = 12;

    /** The number of lazy-loaded columns. */
    const NUM_LAZY_LOAD_COLUMNS = 0;


    /** the column name for the CAL_ID field */
    const CAL_ID = 'CASE_LIST.CAL_ID';

    /** the column name for the CAL_TYPE field */
    const CAL_TYPE = 'CASE_LIST.CAL_TYPE';

    /** the column name for the CAL_NAME field */
    const CAL_NAME = 'CASE_LIST.CAL_NAME';

    /** the column name for the CAL_DESCRIPTION field */
    const CAL_DESCRIPTION = 'CASE_LIST.CAL_DESCRIPTION';

    /** the column name for the ADD_TAB_UID field */
    const ADD_TAB_UID = 'CASE_LIST.ADD_TAB_UID';

    /** the column name for the CAL_COLUMNS field */
    const CAL_COLUMNS = 'CASE_LIST.CAL_COLUMNS';

    /** the column name for the USR_ID field */
    const USR_ID = 'CASE_LIST.USR_ID';

    /** the column name for the CAL_ICON_LIST field */
    const CAL_ICON_LIST = 'CASE_LIST.CAL_ICON_LIST';

    /** the column name for the CAL_ICON_COLOR field */
    const CAL_ICON_COLOR = 'CASE_LIST.CAL_ICON_COLOR';

    /** the column name for the CAL_ICON_COLOR_SCREEN field */
    const CAL_ICON_COLOR_SCREEN = 'CASE_LIST.CAL_ICON_COLOR_SCREEN';

    /** the column name for the CAL_CREATE_DATE field */
    const CAL_CREATE_DATE = 'CASE_LIST.CAL_CREATE_DATE';

    /** the column name for the CAL_UPDATE_DATE field */
    const CAL_UPDATE_DATE = 'CASE_LIST.CAL_UPDATE_DATE';

    /** The PHP to DB Name Mapping */
    private static $phpNameMap = null;


    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    private static $fieldNames = array (
        BasePeer::TYPE_PHPNAME => array ('CalId', 'CalType', 'CalName', 'CalDescription', 'AddTabUid', 'CalColumns', 'UsrId', 'CalIconList', 'CalIconColor', 'CalIconColorScreen', 'CalCreateDate', 'CalUpdateDate', ),
        BasePeer::TYPE_COLNAME => array (CaseListPeer::CAL_ID, CaseListPeer::CAL_TYPE, CaseListPeer::CAL_NAME, CaseListPeer::CAL_DESCRIPTION, CaseListPeer::ADD_TAB_UID, CaseListPeer::CAL_COLUMNS, CaseListPeer::USR_ID, CaseListPeer::CAL_ICON_LIST, CaseListPeer::CAL_ICON_COLOR, CaseListPeer::CAL_ICON_COLOR_SCREEN, CaseListPeer::CAL_CREATE_DATE, CaseListPeer::CAL_UPDATE_DATE, ),
        BasePeer::TYPE_FIELDNAME => array ('CAL_ID', 'CAL_TYPE', 'CAL_NAME', 'CAL_DESCRIPTION', 'ADD_TAB_UID', 'CAL_COLUMNS', 'USR_ID', 'CAL_ICON_LIST', 'CAL_ICON_COLOR', 'CAL_ICON_COLOR_SCREEN', 'CAL_CREATE_DATE', 'CAL_UPDATE_DATE', ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[BasePeer::TYPE_PHPNAME]['Id'] = 0
     */
    private static $fieldKeys = array (
        BasePeer::TYPE_PHPNAME => array ('CalId' => 0, 'CalType' => 1, 'CalName' => 2, 'CalDescription' => 3, 'AddTabUid' => 4, 'CalColumns' => 5, 'UsrId' => 6, 'CalIconList' => 7, 'CalIconColor' => 8, 'CalIconColorScreen' => 9, 'CalCreateDate' => 10, 'CalUpdateDate' => 11, ),
        BasePeer::TYPE_COLNAME => array (CaseListPeer::CAL_ID => 0, CaseListPeer::CAL_TYPE => 1, CaseListPeer::CAL_NAME => 2, CaseListPeer::CAL_DESCRIPTION => 3, CaseListPeer::ADD_TAB_UID => 4, CaseListPeer::CAL_COLUMNS => 5, CaseListPeer::USR_ID => 6, CaseListPeer::CAL_ICON_LIST => 7, CaseListPeer::CAL_ICON_COLOR => 8, CaseListPeer::CAL_ICON_COLOR_SCREEN => 9, CaseListPeer::CAL_CREATE_DATE => 10, CaseListPeer::CAL_UPDATE_DATE => 11, ),
        BasePeer::TYPE_FIELDNAME => array ('CAL_ID' => 0, 'CAL_TYPE' => 1, 'CAL_NAME' => 2, 'CAL_DESCRIPTION' => 3, 'ADD_TAB_UID' => 4, 'CAL_COLUMNS' => 5, 'USR_ID' => 6, 'CAL_ICON_LIST' => 7, 'CAL_ICON_COLOR' => 8, 'CAL_ICON_COLOR_SCREEN' => 9, 'CAL_CREATE_DATE' => 10, 'CAL_UPDATE_DATE' => 11, ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
    );

    /**
     * @return     MapBuilder the map builder for this peer
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function getMapBuilder()
    {
        include_once 'classes/model/map/CaseListMapBuilder.php';
        return BasePeer::getMapBuilder('classes.model.map.CaseListMapBuilder');
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
            $map = CaseListPeer::getTableMap();
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
     * @param      string $column The column name for current table. (i.e. CaseListPeer::COLUMN_NAME).
     * @return     string
     */
    public static function alias($alias, $column)
    {
        return str_replace(CaseListPeer::TABLE_NAME.'.', $alias.'.', $column);
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

        $criteria->addSelectColumn(CaseListPeer::CAL_ID);

        $criteria->addSelectColumn(CaseListPeer::CAL_TYPE);

        $criteria->addSelectColumn(CaseListPeer::CAL_NAME);

        $criteria->addSelectColumn(CaseListPeer::CAL_DESCRIPTION);

        $criteria->addSelectColumn(CaseListPeer::ADD_TAB_UID);

        $criteria->addSelectColumn(CaseListPeer::CAL_COLUMNS);

        $criteria->addSelectColumn(CaseListPeer::USR_ID);

        $criteria->addSelectColumn(CaseListPeer::CAL_ICON_LIST);

        $criteria->addSelectColumn(CaseListPeer::CAL_ICON_COLOR);

        $criteria->addSelectColumn(CaseListPeer::CAL_ICON_COLOR_SCREEN);

        $criteria->addSelectColumn(CaseListPeer::CAL_CREATE_DATE);

        $criteria->addSelectColumn(CaseListPeer::CAL_UPDATE_DATE);

    }

    const COUNT = 'COUNT(CASE_LIST.CAL_ID)';
    const COUNT_DISTINCT = 'COUNT(DISTINCT CASE_LIST.CAL_ID)';

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
            $criteria->addSelectColumn(CaseListPeer::COUNT_DISTINCT);
        } else {
            $criteria->addSelectColumn(CaseListPeer::COUNT);
        }

        // just in case we're grouping: add those columns to the select statement
        foreach ($criteria->getGroupByColumns() as $column) {
            $criteria->addSelectColumn($column);
        }

        $rs = CaseListPeer::doSelectRS($criteria, $con);
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
     * @return     CaseList
     * @throws     PropelException Any exceptions caught during processing will be
     *       rethrown wrapped into a PropelException.
     */
    public static function doSelectOne(Criteria $criteria, $con = null)
    {
        $critcopy = clone $criteria;
        $critcopy->setLimit(1);
        $objects = CaseListPeer::doSelect($critcopy, $con);
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
        return CaseListPeer::populateObjects(CaseListPeer::doSelectRS($criteria, $con));
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
            CaseListPeer::addSelectColumns($criteria);
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
        $cls = CaseListPeer::getOMClass();
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
        return CaseListPeer::CLASS_DEFAULT;
    }

    /**
     * Method perform an INSERT on the database, given a CaseList or Criteria object.
     *
     * @param      mixed $values Criteria or CaseList object containing data that is used to create the INSERT statement.
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
            $criteria = $values->buildCriteria(); // build Criteria from CaseList object
        }

                //$criteria->remove(CaseListPeer::CAL_ID); // remove pkey col since this table uses auto-increment
                

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
     * Method perform an UPDATE on the database, given a CaseList or Criteria object.
     *
     * @param      mixed $values Criteria or CaseList object containing data create the UPDATE statement.
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

            $comparison = $criteria->getComparison(CaseListPeer::CAL_ID);
            $selectCriteria->add(CaseListPeer::CAL_ID, $criteria->remove(CaseListPeer::CAL_ID), $comparison);

        } else {
            $criteria = $values->buildCriteria(); // gets full criteria
            $selectCriteria = $values->buildPkeyCriteria(); // gets criteria w/ primary key(s)
        }

        // set the correct dbName
        $criteria->setDbName(self::DATABASE_NAME);

        return BasePeer::doUpdate($selectCriteria, $criteria, $con);
    }

    /**
     * Method to DELETE all rows from the CASE_LIST table.
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
            $affectedRows += BasePeer::doDeleteAll(CaseListPeer::TABLE_NAME, $con);
            $con->commit();
            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Method perform a DELETE on the database, given a CaseList or Criteria object OR a primary key value.
     *
     * @param      mixed $values Criteria or CaseList object or primary key or array of primary keys
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
            $con = Propel::getConnection(CaseListPeer::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity
        } elseif ($values instanceof CaseList) {

            $criteria = $values->buildPkeyCriteria();
        } else {
            // it must be the primary key
            $criteria = new Criteria(self::DATABASE_NAME);
            $criteria->add(CaseListPeer::CAL_ID, (array) $values, Criteria::IN);
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
     * Validates all modified columns of given CaseList object.
     * If parameter $columns is either a single column name or an array of column names
     * than only those columns are validated.
     *
     * NOTICE: This does not apply to primary or foreign keys for now.
     *
     * @param      CaseList $obj The object to validate.
     * @param      mixed $cols Column name or array of column names.
     *
     * @return     mixed TRUE if all columns are valid or the error message of the first invalid column.
     */
    public static function doValidate(CaseList $obj, $cols = null)
    {
        $columns = array();

        if ($cols) {
            $dbMap = Propel::getDatabaseMap(CaseListPeer::DATABASE_NAME);
            $tableMap = $dbMap->getTable(CaseListPeer::TABLE_NAME);

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

        }

        return BasePeer::doValidate(CaseListPeer::DATABASE_NAME, CaseListPeer::TABLE_NAME, $columns);
    }

    /**
     * Retrieve a single object by pkey.
     *
     * @param      mixed $pk the primary key.
     * @param      Connection $con the connection to use
     * @return     CaseList
     */
    public static function retrieveByPK($pk, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(self::DATABASE_NAME);
        }

        $criteria = new Criteria(CaseListPeer::DATABASE_NAME);

        $criteria->add(CaseListPeer::CAL_ID, $pk);


        $v = CaseListPeer::doSelect($criteria, $con);

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
            $criteria->add(CaseListPeer::CAL_ID, $pks, Criteria::IN);
            $objs = CaseListPeer::doSelect($criteria, $con);
        }
        return $objs;
    }
}


// static code to register the map builder for this Peer with the main Propel class
if (Propel::isInit()) {
    // the MapBuilder classes register themselves with Propel during initialization
    // so we need to load them here.
    try {
        BaseCaseListPeer::getMapBuilder();
    } catch (Exception $e) {
        Propel::log('Could not initialize Peer: ' . $e->getMessage(), Propel::LOG_ERR);
    }
} else {
    // even if Propel is not yet initialized, the map builder class can be registered
    // now and then it will be loaded when Propel initializes.
    require_once 'classes/model/map/CaseListMapBuilder.php';
    Propel::registerMapBuilder('classes.model.map.CaseListMapBuilder');
}

