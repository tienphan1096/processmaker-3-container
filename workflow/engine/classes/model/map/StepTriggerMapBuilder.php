<?php

require_once 'propel/map/MapBuilder.php';
include_once 'creole/CreoleTypes.php';


/**
 * This class adds structure of 'STEP_TRIGGER' table to 'workflow' DatabaseMap object.
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
class StepTriggerMapBuilder
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'classes.model.map.StepTriggerMapBuilder';

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

        $tMap = $this->dbMap->addTable('STEP_TRIGGER');
        $tMap->setPhpName('StepTrigger');

        $tMap->setUseIdGenerator(false);

        $tMap->addPrimaryKey('STEP_UID', 'StepUid', 'string', CreoleTypes::VARCHAR, true, 32);

        $tMap->addPrimaryKey('TAS_UID', 'TasUid', 'string', CreoleTypes::VARCHAR, true, 32);

        $tMap->addPrimaryKey('TRI_UID', 'TriUid', 'string', CreoleTypes::VARCHAR, true, 32);

        $tMap->addPrimaryKey('ST_TYPE', 'StType', 'string', CreoleTypes::VARCHAR, true, 20);

        $tMap->addColumn('ST_CONDITION', 'StCondition', 'string', CreoleTypes::LONGVARCHAR, true, null);

        $tMap->addColumn('ST_POSITION', 'StPosition', 'int', CreoleTypes::INTEGER, true, null);

        $tMap->addValidator('ST_TYPE', 'validValues', 'propel.validator.ValidValuesValidator', 'BEFORE|AFTER', 'Please select a valid value for Trigger Type ST_TYPE.');

    } // doBuild()

} // StepTriggerMapBuilder
