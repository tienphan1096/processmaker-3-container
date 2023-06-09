<?php

require_once 'propel/map/MapBuilder.php';
include_once 'creole/CreoleTypes.php';


/**
 * This class adds structure of 'APP_MESSAGE' table to 'workflow' DatabaseMap object.
 *
 *
 *
 * These statically-built map classes are used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 * @package    workflow.classes.model.map
 */
class AppMessageMapBuilder
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'classes.model.map.AppMessageMapBuilder';

    /**
     * The database map.
     */
    private $dbMap;

    /**
     * Tells us if this DatabaseMapBuilder is built so that we
     * don't have to re-build it every time.
     *
     * @return     boolean true if this DatabaseMapBuilder is built, false otherwise.
     */
    public function isBuilt()
    {
        return ($this->dbMap !== null);
    }

    /**
     * Gets the databasemap this map builder built.
     *
     * @return     the databasemap
     */
    public function getDatabaseMap()
    {
        return $this->dbMap;
    }

    /**
     * The doBuild() method builds the DatabaseMap
     *
     * @return     void
     * @throws     PropelException
     */
    public function doBuild()
    {
        $this->dbMap = Propel::getDatabaseMap('workflow');

        $tMap = $this->dbMap->addTable('APP_MESSAGE');
        $tMap->setPhpName('AppMessage');

        $tMap->setUseIdGenerator(true);

        $tMap->addColumn('APP_MSG_ID', 'AppMsgId', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addPrimaryKey('APP_MSG_UID', 'AppMsgUid', 'string', CreoleTypes::VARCHAR, true, 32);

        $tMap->addColumn('MSG_UID', 'MsgUid', 'string', CreoleTypes::VARCHAR, false, 32);

        $tMap->addColumn('APP_UID', 'AppUid', 'string', CreoleTypes::VARCHAR, true, 32);

        $tMap->addColumn('DEL_INDEX', 'DelIndex', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addColumn('APP_MSG_TYPE', 'AppMsgType', 'string', CreoleTypes::VARCHAR, true, 100);

        $tMap->addColumn('APP_MSG_TYPE_ID', 'AppMsgTypeId', 'int', CreoleTypes::TINYINT, false, null);

        $tMap->addColumn('APP_MSG_SUBJECT', 'AppMsgSubject', 'string', CreoleTypes::VARCHAR, true, 998);

        $tMap->addColumn('APP_MSG_FROM', 'AppMsgFrom', 'string', CreoleTypes::VARCHAR, true, 100);

        $tMap->addColumn('APP_MSG_TO', 'AppMsgTo', 'string', CreoleTypes::LONGVARCHAR, true, null);

        $tMap->addColumn('APP_MSG_BODY', 'AppMsgBody', 'string', CreoleTypes::LONGVARCHAR, true, null);

        $tMap->addColumn('APP_MSG_DATE', 'AppMsgDate', 'int', CreoleTypes::TIMESTAMP, true, null);

        $tMap->addColumn('APP_MSG_CC', 'AppMsgCc', 'string', CreoleTypes::LONGVARCHAR, false, null);

        $tMap->addColumn('APP_MSG_BCC', 'AppMsgBcc', 'string', CreoleTypes::LONGVARCHAR, false, null);

        $tMap->addColumn('APP_MSG_TEMPLATE', 'AppMsgTemplate', 'string', CreoleTypes::LONGVARCHAR, false, null);

        $tMap->addColumn('APP_MSG_STATUS', 'AppMsgStatus', 'string', CreoleTypes::VARCHAR, false, 20);

        $tMap->addColumn('APP_MSG_STATUS_ID', 'AppMsgStatusId', 'int', CreoleTypes::TINYINT, false, null);

        $tMap->addColumn('APP_MSG_ATTACH', 'AppMsgAttach', 'string', CreoleTypes::LONGVARCHAR, false, null);

        $tMap->addColumn('APP_MSG_SEND_DATE', 'AppMsgSendDate', 'int', CreoleTypes::TIMESTAMP, true, null);

        $tMap->addColumn('APP_MSG_SHOW_MESSAGE', 'AppMsgShowMessage', 'int', CreoleTypes::TINYINT, true, null);

        $tMap->addColumn('APP_MSG_ERROR', 'AppMsgError', 'string', CreoleTypes::LONGVARCHAR, false, null);

        $tMap->addColumn('PRO_ID', 'ProId', 'int', CreoleTypes::INTEGER, false, null);

        $tMap->addColumn('TAS_ID', 'TasId', 'int', CreoleTypes::INTEGER, false, null);

        $tMap->addColumn('APP_NUMBER', 'AppNumber', 'int', CreoleTypes::INTEGER, false, null);

    } // doBuild()

} // AppMessageMapBuilder
