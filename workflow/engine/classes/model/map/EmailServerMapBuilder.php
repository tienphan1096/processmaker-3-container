<?php

require_once 'propel/map/MapBuilder.php';
include_once 'creole/CreoleTypes.php';


/**
 * This class adds structure of 'EMAIL_SERVER' table to 'workflow' DatabaseMap object.
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
class EmailServerMapBuilder
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'classes.model.map.EmailServerMapBuilder';

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

        $tMap = $this->dbMap->addTable('EMAIL_SERVER');
        $tMap->setPhpName('EmailServer');

        $tMap->setUseIdGenerator(false);

        $tMap->addPrimaryKey('MESS_UID', 'MessUid', 'string', CreoleTypes::VARCHAR, true, 32);

        $tMap->addColumn('MESS_ENGINE', 'MessEngine', 'string', CreoleTypes::VARCHAR, true, 256);

        $tMap->addColumn('MESS_SERVER', 'MessServer', 'string', CreoleTypes::VARCHAR, true, 256);

        $tMap->addColumn('MESS_PORT', 'MessPort', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addColumn('MESS_INCOMING_SERVER', 'MessIncomingServer', 'string', CreoleTypes::VARCHAR, true, 256);

        $tMap->addColumn('MESS_INCOMING_PORT', 'MessIncomingPort', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addColumn('MESS_RAUTH', 'MessRauth', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addColumn('MESS_ACCOUNT', 'MessAccount', 'string', CreoleTypes::VARCHAR, true, 256);

        $tMap->addColumn('MESS_PASSWORD', 'MessPassword', 'string', CreoleTypes::VARCHAR, true, 256);

        $tMap->addColumn('MESS_FROM_MAIL', 'MessFromMail', 'string', CreoleTypes::VARCHAR, false, 256);

        $tMap->addColumn('MESS_FROM_NAME', 'MessFromName', 'string', CreoleTypes::VARCHAR, false, 256);

        $tMap->addColumn('SMTPSECURE', 'Smtpsecure', 'string', CreoleTypes::VARCHAR, true, 3);

        $tMap->addColumn('MESS_TRY_SEND_INMEDIATLY', 'MessTrySendInmediatly', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addColumn('MAIL_TO', 'MailTo', 'string', CreoleTypes::VARCHAR, false, 256);

        $tMap->addColumn('MESS_DEFAULT', 'MessDefault', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addColumn('OAUTH_CLIENT_ID', 'OauthClientId', 'string', CreoleTypes::VARCHAR, true, 512);

        $tMap->addColumn('OAUTH_CLIENT_SECRET', 'OauthClientSecret', 'string', CreoleTypes::VARCHAR, true, 512);

        $tMap->addColumn('OAUTH_REFRESH_TOKEN', 'OauthRefreshToken', 'string', CreoleTypes::LONGVARCHAR, false, null);

    } // doBuild()

} // EmailServerMapBuilder
