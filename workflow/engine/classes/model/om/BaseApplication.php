<?php

require_once 'propel/om/BaseObject.php';

require_once 'propel/om/Persistent.php';


include_once 'propel/util/Criteria.php';

include_once 'classes/model/ApplicationPeer.php';

/**
 * Base class that represents a row from the 'APPLICATION' table.
 *
 * 
 *
 * @package    workflow.classes.model.om
 */
abstract class BaseApplication extends BaseObject implements Persistent
{

    /**
     * The Peer class.
     * Instance provides a convenient way of calling static methods on a class
     * that calling code may not be able to identify.
     * @var        ApplicationPeer
    */
    protected static $peer;

    /**
     * The value for the app_uid field.
     * @var        string
     */
    protected $app_uid = '';

    /**
     * The value for the app_title field.
     * @var        string
     */
    protected $app_title;

    /**
     * The value for the app_description field.
     * @var        string
     */
    protected $app_description;

    /**
     * The value for the app_number field.
     * @var        int
     */
    protected $app_number = 0;

    /**
     * The value for the app_parent field.
     * @var        string
     */
    protected $app_parent = '0';

    /**
     * The value for the app_status field.
     * @var        string
     */
    protected $app_status = '';

    /**
     * The value for the app_status_id field.
     * @var        int
     */
    protected $app_status_id = 0;

    /**
     * The value for the pro_uid field.
     * @var        string
     */
    protected $pro_uid = '';

    /**
     * The value for the app_proc_status field.
     * @var        string
     */
    protected $app_proc_status = '';

    /**
     * The value for the app_proc_code field.
     * @var        string
     */
    protected $app_proc_code = '';

    /**
     * The value for the app_parallel field.
     * @var        string
     */
    protected $app_parallel = 'NO';

    /**
     * The value for the app_init_user field.
     * @var        string
     */
    protected $app_init_user = '';

    /**
     * The value for the app_cur_user field.
     * @var        string
     */
    protected $app_cur_user = '';

    /**
     * The value for the app_create_date field.
     * @var        int
     */
    protected $app_create_date;

    /**
     * The value for the app_init_date field.
     * @var        int
     */
    protected $app_init_date;

    /**
     * The value for the app_finish_date field.
     * @var        int
     */
    protected $app_finish_date;

    /**
     * The value for the app_update_date field.
     * @var        int
     */
    protected $app_update_date;

    /**
     * The value for the app_data field.
     * @var        string
     */
    protected $app_data;

    /**
     * The value for the app_pin field.
     * @var        string
     */
    protected $app_pin = '';

    /**
     * The value for the app_duration field.
     * @var        double
     */
    protected $app_duration = 0.0;

    /**
     * The value for the app_delay_duration field.
     * @var        double
     */
    protected $app_delay_duration = 0.0;

    /**
     * The value for the app_drive_folder_uid field.
     * @var        string
     */
    protected $app_drive_folder_uid = '';

    /**
     * The value for the app_routing_data field.
     * @var        string
     */
    protected $app_routing_data;

    /**
     * The value for the pro_id field.
     * @var        int
     */
    protected $pro_id = 0;

    /**
     * The value for the app_init_user_id field.
     * @var        int
     */
    protected $app_init_user_id = 0;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     * @var        boolean
     */
    protected $alreadyInSave = false;

    /**
     * Flag to prevent endless validation loop, if this object is referenced
     * by another object which falls in this transaction.
     * @var        boolean
     */
    protected $alreadyInValidation = false;

    /**
     * Get the [app_uid] column value.
     * 
     * @return     string
     */
    public function getAppUid()
    {

        return $this->app_uid;
    }

    /**
     * Get the [app_title] column value.
     * 
     * @return     string
     */
    public function getAppTitle()
    {

        return $this->app_title;
    }

    /**
     * Get the [app_description] column value.
     * 
     * @return     string
     */
    public function getAppDescription()
    {

        return $this->app_description;
    }

    /**
     * Get the [app_number] column value.
     * 
     * @return     int
     */
    public function getAppNumber()
    {

        return $this->app_number;
    }

    /**
     * Get the [app_parent] column value.
     * 
     * @return     string
     */
    public function getAppParent()
    {

        return $this->app_parent;
    }

    /**
     * Get the [app_status] column value.
     * 
     * @return     string
     */
    public function getAppStatus()
    {

        return $this->app_status;
    }

    /**
     * Get the [app_status_id] column value.
     * 
     * @return     int
     */
    public function getAppStatusId()
    {

        return $this->app_status_id;
    }

    /**
     * Get the [pro_uid] column value.
     * 
     * @return     string
     */
    public function getProUid()
    {

        return $this->pro_uid;
    }

    /**
     * Get the [app_proc_status] column value.
     * 
     * @return     string
     */
    public function getAppProcStatus()
    {

        return $this->app_proc_status;
    }

    /**
     * Get the [app_proc_code] column value.
     * 
     * @return     string
     */
    public function getAppProcCode()
    {

        return $this->app_proc_code;
    }

    /**
     * Get the [app_parallel] column value.
     * 
     * @return     string
     */
    public function getAppParallel()
    {

        return $this->app_parallel;
    }

    /**
     * Get the [app_init_user] column value.
     * 
     * @return     string
     */
    public function getAppInitUser()
    {

        return $this->app_init_user;
    }

    /**
     * Get the [app_cur_user] column value.
     * 
     * @return     string
     */
    public function getAppCurUser()
    {

        return $this->app_cur_user;
    }

    /**
     * Get the [optionally formatted] [app_create_date] column value.
     * 
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                          If format is NULL, then the integer unix timestamp will be returned.
     * @return     mixed Formatted date/time value as string or integer unix timestamp (if format is NULL).
     * @throws     PropelException - if unable to convert the date/time to timestamp.
     */
    public function getAppCreateDate($format = 'Y-m-d H:i:s')
    {

        if ($this->app_create_date === null || $this->app_create_date === '') {
            return null;
        } elseif (!is_int($this->app_create_date)) {
            // a non-timestamp value was set externally, so we convert it
            $ts = strtotime($this->app_create_date);
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse value of [app_create_date] as date/time value: " .
                    var_export($this->app_create_date, true));
            }
        } else {
            $ts = $this->app_create_date;
        }
        if ($format === null) {
            return $ts;
        } elseif (strpos($format, '%') !== false) {
            return strftime($format, $ts);
        } else {
            return date($format, $ts);
        }
    }

    /**
     * Get the [optionally formatted] [app_init_date] column value.
     * 
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                          If format is NULL, then the integer unix timestamp will be returned.
     * @return     mixed Formatted date/time value as string or integer unix timestamp (if format is NULL).
     * @throws     PropelException - if unable to convert the date/time to timestamp.
     */
    public function getAppInitDate($format = 'Y-m-d H:i:s')
    {

        if ($this->app_init_date === null || $this->app_init_date === '') {
            return null;
        } elseif (!is_int($this->app_init_date)) {
            // a non-timestamp value was set externally, so we convert it
            $ts = strtotime($this->app_init_date);
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse value of [app_init_date] as date/time value: " .
                    var_export($this->app_init_date, true));
            }
        } else {
            $ts = $this->app_init_date;
        }
        if ($format === null) {
            return $ts;
        } elseif (strpos($format, '%') !== false) {
            return strftime($format, $ts);
        } else {
            return date($format, $ts);
        }
    }

    /**
     * Get the [optionally formatted] [app_finish_date] column value.
     * 
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                          If format is NULL, then the integer unix timestamp will be returned.
     * @return     mixed Formatted date/time value as string or integer unix timestamp (if format is NULL).
     * @throws     PropelException - if unable to convert the date/time to timestamp.
     */
    public function getAppFinishDate($format = 'Y-m-d H:i:s')
    {

        if ($this->app_finish_date === null || $this->app_finish_date === '') {
            return null;
        } elseif (!is_int($this->app_finish_date)) {
            // a non-timestamp value was set externally, so we convert it
            $ts = strtotime($this->app_finish_date);
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse value of [app_finish_date] as date/time value: " .
                    var_export($this->app_finish_date, true));
            }
        } else {
            $ts = $this->app_finish_date;
        }
        if ($format === null) {
            return $ts;
        } elseif (strpos($format, '%') !== false) {
            return strftime($format, $ts);
        } else {
            return date($format, $ts);
        }
    }

    /**
     * Get the [optionally formatted] [app_update_date] column value.
     * 
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                          If format is NULL, then the integer unix timestamp will be returned.
     * @return     mixed Formatted date/time value as string or integer unix timestamp (if format is NULL).
     * @throws     PropelException - if unable to convert the date/time to timestamp.
     */
    public function getAppUpdateDate($format = 'Y-m-d H:i:s')
    {

        if ($this->app_update_date === null || $this->app_update_date === '') {
            return null;
        } elseif (!is_int($this->app_update_date)) {
            // a non-timestamp value was set externally, so we convert it
            $ts = strtotime($this->app_update_date);
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse value of [app_update_date] as date/time value: " .
                    var_export($this->app_update_date, true));
            }
        } else {
            $ts = $this->app_update_date;
        }
        if ($format === null) {
            return $ts;
        } elseif (strpos($format, '%') !== false) {
            return strftime($format, $ts);
        } else {
            return date($format, $ts);
        }
    }

    /**
     * Get the [app_data] column value.
     * 
     * @return     string
     */
    public function getAppData()
    {

        return $this->app_data;
    }

    /**
     * Get the [app_pin] column value.
     * 
     * @return     string
     */
    public function getAppPin()
    {

        return $this->app_pin;
    }

    /**
     * Get the [app_duration] column value.
     * 
     * @return     double
     */
    public function getAppDuration()
    {

        return $this->app_duration;
    }

    /**
     * Get the [app_delay_duration] column value.
     * 
     * @return     double
     */
    public function getAppDelayDuration()
    {

        return $this->app_delay_duration;
    }

    /**
     * Get the [app_drive_folder_uid] column value.
     * 
     * @return     string
     */
    public function getAppDriveFolderUid()
    {

        return $this->app_drive_folder_uid;
    }

    /**
     * Get the [app_routing_data] column value.
     * 
     * @return     string
     */
    public function getAppRoutingData()
    {

        return $this->app_routing_data;
    }

    /**
     * Get the [pro_id] column value.
     * 
     * @return     int
     */
    public function getProId()
    {

        return $this->pro_id;
    }

    /**
     * Get the [app_init_user_id] column value.
     * 
     * @return     int
     */
    public function getAppInitUserId()
    {

        return $this->app_init_user_id;
    }

    /**
     * Set the value of [app_uid] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppUid($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_uid !== $v || $v === '') {
            $this->app_uid = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_UID;
        }

    } // setAppUid()

    /**
     * Set the value of [app_title] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppTitle($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_title !== $v) {
            $this->app_title = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_TITLE;
        }

    } // setAppTitle()

    /**
     * Set the value of [app_description] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppDescription($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_description !== $v) {
            $this->app_description = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_DESCRIPTION;
        }

    } // setAppDescription()

    /**
     * Set the value of [app_number] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppNumber($v)
    {

        // Since the native PHP type for this column is integer,
        // we will cast the input value to an int (if it is not).
        if ($v !== null && !is_int($v) && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->app_number !== $v || $v === 0) {
            $this->app_number = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_NUMBER;
        }

    } // setAppNumber()

    /**
     * Set the value of [app_parent] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppParent($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_parent !== $v || $v === '0') {
            $this->app_parent = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_PARENT;
        }

    } // setAppParent()

    /**
     * Set the value of [app_status] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppStatus($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_status !== $v || $v === '') {
            $this->app_status = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_STATUS;
        }

    } // setAppStatus()

    /**
     * Set the value of [app_status_id] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppStatusId($v)
    {

        // Since the native PHP type for this column is integer,
        // we will cast the input value to an int (if it is not).
        if ($v !== null && !is_int($v) && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->app_status_id !== $v || $v === 0) {
            $this->app_status_id = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_STATUS_ID;
        }

    } // setAppStatusId()

    /**
     * Set the value of [pro_uid] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setProUid($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->pro_uid !== $v || $v === '') {
            $this->pro_uid = $v;
            $this->modifiedColumns[] = ApplicationPeer::PRO_UID;
        }

    } // setProUid()

    /**
     * Set the value of [app_proc_status] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppProcStatus($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_proc_status !== $v || $v === '') {
            $this->app_proc_status = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_PROC_STATUS;
        }

    } // setAppProcStatus()

    /**
     * Set the value of [app_proc_code] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppProcCode($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_proc_code !== $v || $v === '') {
            $this->app_proc_code = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_PROC_CODE;
        }

    } // setAppProcCode()

    /**
     * Set the value of [app_parallel] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppParallel($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_parallel !== $v || $v === 'NO') {
            $this->app_parallel = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_PARALLEL;
        }

    } // setAppParallel()

    /**
     * Set the value of [app_init_user] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppInitUser($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_init_user !== $v || $v === '') {
            $this->app_init_user = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_INIT_USER;
        }

    } // setAppInitUser()

    /**
     * Set the value of [app_cur_user] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppCurUser($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_cur_user !== $v || $v === '') {
            $this->app_cur_user = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_CUR_USER;
        }

    } // setAppCurUser()

    /**
     * Set the value of [app_create_date] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppCreateDate($v)
    {

        if ($v !== null && !is_int($v)) {
            $ts = strtotime($v);
            //Date/time accepts null values
            if ($v == '') {
                $ts = null;
            }
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse date/time value for [app_create_date] from input: " .
                    var_export($v, true));
            }
        } else {
            $ts = $v;
        }
        if ($this->app_create_date !== $ts) {
            $this->app_create_date = $ts;
            $this->modifiedColumns[] = ApplicationPeer::APP_CREATE_DATE;
        }

    } // setAppCreateDate()

    /**
     * Set the value of [app_init_date] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppInitDate($v)
    {

        if ($v !== null && !is_int($v)) {
            $ts = strtotime($v);
            //Date/time accepts null values
            if ($v == '') {
                $ts = null;
            }
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse date/time value for [app_init_date] from input: " .
                    var_export($v, true));
            }
        } else {
            $ts = $v;
        }
        if ($this->app_init_date !== $ts) {
            $this->app_init_date = $ts;
            $this->modifiedColumns[] = ApplicationPeer::APP_INIT_DATE;
        }

    } // setAppInitDate()

    /**
     * Set the value of [app_finish_date] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppFinishDate($v)
    {

        if ($v !== null && !is_int($v)) {
            $ts = strtotime($v);
            //Date/time accepts null values
            if ($v == '') {
                $ts = null;
            }
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse date/time value for [app_finish_date] from input: " .
                    var_export($v, true));
            }
        } else {
            $ts = $v;
        }
        if ($this->app_finish_date !== $ts) {
            $this->app_finish_date = $ts;
            $this->modifiedColumns[] = ApplicationPeer::APP_FINISH_DATE;
        }

    } // setAppFinishDate()

    /**
     * Set the value of [app_update_date] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppUpdateDate($v)
    {

        if ($v !== null && !is_int($v)) {
            $ts = strtotime($v);
            //Date/time accepts null values
            if ($v == '') {
                $ts = null;
            }
            if ($ts === -1 || $ts === false) {
                throw new PropelException("Unable to parse date/time value for [app_update_date] from input: " .
                    var_export($v, true));
            }
        } else {
            $ts = $v;
        }
        if ($this->app_update_date !== $ts) {
            $this->app_update_date = $ts;
            $this->modifiedColumns[] = ApplicationPeer::APP_UPDATE_DATE;
        }

    } // setAppUpdateDate()

    /**
     * Set the value of [app_data] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppData($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_data !== $v) {
            $this->app_data = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_DATA;
        }

    } // setAppData()

    /**
     * Set the value of [app_pin] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppPin($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_pin !== $v || $v === '') {
            $this->app_pin = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_PIN;
        }

    } // setAppPin()

    /**
     * Set the value of [app_duration] column.
     * 
     * @param      double $v new value
     * @return     void
     */
    public function setAppDuration($v)
    {

        if ($this->app_duration !== $v || $v === 0.0) {
            $this->app_duration = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_DURATION;
        }

    } // setAppDuration()

    /**
     * Set the value of [app_delay_duration] column.
     * 
     * @param      double $v new value
     * @return     void
     */
    public function setAppDelayDuration($v)
    {

        if ($this->app_delay_duration !== $v || $v === 0.0) {
            $this->app_delay_duration = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_DELAY_DURATION;
        }

    } // setAppDelayDuration()

    /**
     * Set the value of [app_drive_folder_uid] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppDriveFolderUid($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_drive_folder_uid !== $v || $v === '') {
            $this->app_drive_folder_uid = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_DRIVE_FOLDER_UID;
        }

    } // setAppDriveFolderUid()

    /**
     * Set the value of [app_routing_data] column.
     * 
     * @param      string $v new value
     * @return     void
     */
    public function setAppRoutingData($v)
    {

        // Since the native PHP type for this column is string,
        // we will cast the input to a string (if it is not).
        if ($v !== null && !is_string($v)) {
            $v = (string) $v;
        }

        if ($this->app_routing_data !== $v) {
            $this->app_routing_data = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_ROUTING_DATA;
        }

    } // setAppRoutingData()

    /**
     * Set the value of [pro_id] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setProId($v)
    {

        // Since the native PHP type for this column is integer,
        // we will cast the input value to an int (if it is not).
        if ($v !== null && !is_int($v) && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->pro_id !== $v || $v === 0) {
            $this->pro_id = $v;
            $this->modifiedColumns[] = ApplicationPeer::PRO_ID;
        }

    } // setProId()

    /**
     * Set the value of [app_init_user_id] column.
     * 
     * @param      int $v new value
     * @return     void
     */
    public function setAppInitUserId($v)
    {

        // Since the native PHP type for this column is integer,
        // we will cast the input value to an int (if it is not).
        if ($v !== null && !is_int($v) && is_numeric($v)) {
            $v = (int) $v;
        }

        if ($this->app_init_user_id !== $v || $v === 0) {
            $this->app_init_user_id = $v;
            $this->modifiedColumns[] = ApplicationPeer::APP_INIT_USER_ID;
        }

    } // setAppInitUserId()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (1-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param      ResultSet $rs The ResultSet class with cursor advanced to desired record pos.
     * @param      int $startcol 1-based offset column which indicates which restultset column to start with.
     * @return     int next starting column
     * @throws     PropelException  - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate(ResultSet $rs, $startcol = 1)
    {
        try {

            $this->app_uid = $rs->getString($startcol + 0);

            $this->app_title = $rs->getString($startcol + 1);

            $this->app_description = $rs->getString($startcol + 2);

            $this->app_number = $rs->getInt($startcol + 3);

            $this->app_parent = $rs->getString($startcol + 4);

            $this->app_status = $rs->getString($startcol + 5);

            $this->app_status_id = $rs->getInt($startcol + 6);

            $this->pro_uid = $rs->getString($startcol + 7);

            $this->app_proc_status = $rs->getString($startcol + 8);

            $this->app_proc_code = $rs->getString($startcol + 9);

            $this->app_parallel = $rs->getString($startcol + 10);

            $this->app_init_user = $rs->getString($startcol + 11);

            $this->app_cur_user = $rs->getString($startcol + 12);

            $this->app_create_date = $rs->getTimestamp($startcol + 13, null);

            $this->app_init_date = $rs->getTimestamp($startcol + 14, null);

            $this->app_finish_date = $rs->getTimestamp($startcol + 15, null);

            $this->app_update_date = $rs->getTimestamp($startcol + 16, null);

            $this->app_data = $rs->getString($startcol + 17);

            $this->app_pin = $rs->getString($startcol + 18);

            $this->app_duration = $rs->getFloat($startcol + 19);

            $this->app_delay_duration = $rs->getFloat($startcol + 20);

            $this->app_drive_folder_uid = $rs->getString($startcol + 21);

            $this->app_routing_data = $rs->getString($startcol + 22);

            $this->pro_id = $rs->getInt($startcol + 23);

            $this->app_init_user_id = $rs->getInt($startcol + 24);

            $this->resetModified();

            $this->setNew(false);

            // FIXME - using NUM_COLUMNS may be clearer.
            return $startcol + 25; // 25 = ApplicationPeer::NUM_COLUMNS - ApplicationPeer::NUM_LAZY_LOAD_COLUMNS).

        } catch (Exception $e) {
            throw new PropelException("Error populating Application object", $e);
        }
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      Connection $con
     * @return     void
     * @throws     PropelException
     * @see        BaseObject::setDeleted()
     * @see        BaseObject::isDeleted()
     */
    public function delete($con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getConnection(ApplicationPeer::DATABASE_NAME);
        }

        try {
            $con->begin();
            ApplicationPeer::doDelete($this, $con);
            $this->setDeleted(true);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Stores the object in the database.  If the object is new,
     * it inserts it; otherwise an update is performed.  This method
     * wraps the doSave() worker method in a transaction.
     *
     * @param      Connection $con
     * @return     int The number of rows affected by this insert/update
     * @throws     PropelException
     * @see        doSave()
     */
    public function save($con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getConnection(ApplicationPeer::DATABASE_NAME);
        }

        try {
            $con->begin();
            $affectedRows = $this->doSave($con);
            $con->commit();
            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Stores the object in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      Connection $con
     * @return     int The number of rows affected by this insert/update and any referring
     * @throws     PropelException
     * @see        save()
     */
    protected function doSave($con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;


            // If this object has been modified, then save it to the database.
            if ($this->isModified()) {
                if ($this->isNew()) {
                    $pk = ApplicationPeer::doInsert($this, $con);
                    $affectedRows += 1; // we are assuming that there is only 1 row per doInsert() which
                                         // should always be true here (even though technically
                                         // BasePeer::doInsert() can insert multiple rows).

                    $this->setNew(false);
                } else {
                    $affectedRows += ApplicationPeer::doUpdate($this, $con);
                }
                $this->resetModified(); // [HL] After being saved an object is no longer 'modified'
            }

            $this->alreadyInSave = false;
        }
        return $affectedRows;
    } // doSave()

    /**
     * Array of ValidationFailed objects.
     * @var        array ValidationFailed[]
     */
    protected $validationFailures = array();

    /**
     * Gets any ValidationFailed objects that resulted from last call to validate().
     *
     *
     * @return     array ValidationFailed[]
     * @see        validate()
     */
    public function getValidationFailures()
    {
        return $this->validationFailures;
    }

    /**
     * Validates the objects modified field values and all objects related to this table.
     *
     * If $columns is either a column name or an array of column names
     * only those columns are validated.
     *
     * @param      mixed $columns Column name or an array of column names.
     * @return     boolean Whether all columns pass validation.
     * @see        doValidate()
     * @see        getValidationFailures()
     */
    public function validate($columns = null)
    {
        $res = $this->doValidate($columns);
        if ($res === true) {
            $this->validationFailures = array();
            return true;
        } else {
            $this->validationFailures = $res;
            return false;
        }
    }

    /**
     * This function performs the validation work for complex object models.
     *
     * In addition to checking the current object, all related objects will
     * also be validated.  If all pass then <code>true</code> is returned; otherwise
     * an aggreagated array of ValidationFailed objects will be returned.
     *
     * @param      array $columns Array of column names to validate.
     * @return     mixed <code>true</code> if all validations pass; 
                   array of <code>ValidationFailed</code> objects otherwise.
     */
    protected function doValidate($columns = null)
    {
        if (!$this->alreadyInValidation) {
            $this->alreadyInValidation = true;
            $retval = null;

            $failureMap = array();


            if (($retval = ApplicationPeer::doValidate($this, $columns)) !== true) {
                $failureMap = array_merge($failureMap, $retval);
            }



            $this->alreadyInValidation = false;
        }

        return (!empty($failureMap) ? $failureMap : true);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TYPE_PHPNAME,
     *                     TYPE_COLNAME, TYPE_FIELDNAME, TYPE_NUM
     * @return     mixed Value of field.
     */
    public function getByName($name, $type = BasePeer::TYPE_PHPNAME)
    {
        $pos = ApplicationPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
        return $this->getByPosition($pos);
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return     mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch($pos) {
            case 0:
                return $this->getAppUid();
                break;
            case 1:
                return $this->getAppTitle();
                break;
            case 2:
                return $this->getAppDescription();
                break;
            case 3:
                return $this->getAppNumber();
                break;
            case 4:
                return $this->getAppParent();
                break;
            case 5:
                return $this->getAppStatus();
                break;
            case 6:
                return $this->getAppStatusId();
                break;
            case 7:
                return $this->getProUid();
                break;
            case 8:
                return $this->getAppProcStatus();
                break;
            case 9:
                return $this->getAppProcCode();
                break;
            case 10:
                return $this->getAppParallel();
                break;
            case 11:
                return $this->getAppInitUser();
                break;
            case 12:
                return $this->getAppCurUser();
                break;
            case 13:
                return $this->getAppCreateDate();
                break;
            case 14:
                return $this->getAppInitDate();
                break;
            case 15:
                return $this->getAppFinishDate();
                break;
            case 16:
                return $this->getAppUpdateDate();
                break;
            case 17:
                return $this->getAppData();
                break;
            case 18:
                return $this->getAppPin();
                break;
            case 19:
                return $this->getAppDuration();
                break;
            case 20:
                return $this->getAppDelayDuration();
                break;
            case 21:
                return $this->getAppDriveFolderUid();
                break;
            case 22:
                return $this->getAppRoutingData();
                break;
            case 23:
                return $this->getProId();
                break;
            case 24:
                return $this->getAppInitUserId();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param      string $keyType One of the class type constants TYPE_PHPNAME,
     *                        TYPE_COLNAME, TYPE_FIELDNAME, TYPE_NUM
     * @return     an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = BasePeer::TYPE_PHPNAME)
    {
        $keys = ApplicationPeer::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getAppUid(),
            $keys[1] => $this->getAppTitle(),
            $keys[2] => $this->getAppDescription(),
            $keys[3] => $this->getAppNumber(),
            $keys[4] => $this->getAppParent(),
            $keys[5] => $this->getAppStatus(),
            $keys[6] => $this->getAppStatusId(),
            $keys[7] => $this->getProUid(),
            $keys[8] => $this->getAppProcStatus(),
            $keys[9] => $this->getAppProcCode(),
            $keys[10] => $this->getAppParallel(),
            $keys[11] => $this->getAppInitUser(),
            $keys[12] => $this->getAppCurUser(),
            $keys[13] => $this->getAppCreateDate(),
            $keys[14] => $this->getAppInitDate(),
            $keys[15] => $this->getAppFinishDate(),
            $keys[16] => $this->getAppUpdateDate(),
            $keys[17] => $this->getAppData(),
            $keys[18] => $this->getAppPin(),
            $keys[19] => $this->getAppDuration(),
            $keys[20] => $this->getAppDelayDuration(),
            $keys[21] => $this->getAppDriveFolderUid(),
            $keys[22] => $this->getAppRoutingData(),
            $keys[23] => $this->getProId(),
            $keys[24] => $this->getAppInitUserId(),
        );
        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param      string $name peer name
     * @param      mixed $value field value
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TYPE_PHPNAME,
     *                     TYPE_COLNAME, TYPE_FIELDNAME, TYPE_NUM
     * @return     void
     */
    public function setByName($name, $value, $type = BasePeer::TYPE_PHPNAME)
    {
        $pos = ApplicationPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @param      mixed $value field value
     * @return     void
     */
    public function setByPosition($pos, $value)
    {
        switch($pos) {
            case 0:
                $this->setAppUid($value);
                break;
            case 1:
                $this->setAppTitle($value);
                break;
            case 2:
                $this->setAppDescription($value);
                break;
            case 3:
                $this->setAppNumber($value);
                break;
            case 4:
                $this->setAppParent($value);
                break;
            case 5:
                $this->setAppStatus($value);
                break;
            case 6:
                $this->setAppStatusId($value);
                break;
            case 7:
                $this->setProUid($value);
                break;
            case 8:
                $this->setAppProcStatus($value);
                break;
            case 9:
                $this->setAppProcCode($value);
                break;
            case 10:
                $this->setAppParallel($value);
                break;
            case 11:
                $this->setAppInitUser($value);
                break;
            case 12:
                $this->setAppCurUser($value);
                break;
            case 13:
                $this->setAppCreateDate($value);
                break;
            case 14:
                $this->setAppInitDate($value);
                break;
            case 15:
                $this->setAppFinishDate($value);
                break;
            case 16:
                $this->setAppUpdateDate($value);
                break;
            case 17:
                $this->setAppData($value);
                break;
            case 18:
                $this->setAppPin($value);
                break;
            case 19:
                $this->setAppDuration($value);
                break;
            case 20:
                $this->setAppDelayDuration($value);
                break;
            case 21:
                $this->setAppDriveFolderUid($value);
                break;
            case 22:
                $this->setAppRoutingData($value);
                break;
            case 23:
                $this->setProId($value);
                break;
            case 24:
                $this->setAppInitUserId($value);
                break;
        } // switch()
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TYPE_PHPNAME, TYPE_COLNAME, TYPE_FIELDNAME,
     * TYPE_NUM. The default key type is the column's phpname (e.g. 'authorId')
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return     void
     */
    public function fromArray($arr, $keyType = BasePeer::TYPE_PHPNAME)
    {
        $keys = ApplicationPeer::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setAppUid($arr[$keys[0]]);
        }

        if (array_key_exists($keys[1], $arr)) {
            $this->setAppTitle($arr[$keys[1]]);
        }

        if (array_key_exists($keys[2], $arr)) {
            $this->setAppDescription($arr[$keys[2]]);
        }

        if (array_key_exists($keys[3], $arr)) {
            $this->setAppNumber($arr[$keys[3]]);
        }

        if (array_key_exists($keys[4], $arr)) {
            $this->setAppParent($arr[$keys[4]]);
        }

        if (array_key_exists($keys[5], $arr)) {
            $this->setAppStatus($arr[$keys[5]]);
        }

        if (array_key_exists($keys[6], $arr)) {
            $this->setAppStatusId($arr[$keys[6]]);
        }

        if (array_key_exists($keys[7], $arr)) {
            $this->setProUid($arr[$keys[7]]);
        }

        if (array_key_exists($keys[8], $arr)) {
            $this->setAppProcStatus($arr[$keys[8]]);
        }

        if (array_key_exists($keys[9], $arr)) {
            $this->setAppProcCode($arr[$keys[9]]);
        }

        if (array_key_exists($keys[10], $arr)) {
            $this->setAppParallel($arr[$keys[10]]);
        }

        if (array_key_exists($keys[11], $arr)) {
            $this->setAppInitUser($arr[$keys[11]]);
        }

        if (array_key_exists($keys[12], $arr)) {
            $this->setAppCurUser($arr[$keys[12]]);
        }

        if (array_key_exists($keys[13], $arr)) {
            $this->setAppCreateDate($arr[$keys[13]]);
        }

        if (array_key_exists($keys[14], $arr)) {
            $this->setAppInitDate($arr[$keys[14]]);
        }

        if (array_key_exists($keys[15], $arr)) {
            $this->setAppFinishDate($arr[$keys[15]]);
        }

        if (array_key_exists($keys[16], $arr)) {
            $this->setAppUpdateDate($arr[$keys[16]]);
        }

        if (array_key_exists($keys[17], $arr)) {
            $this->setAppData($arr[$keys[17]]);
        }

        if (array_key_exists($keys[18], $arr)) {
            $this->setAppPin($arr[$keys[18]]);
        }

        if (array_key_exists($keys[19], $arr)) {
            $this->setAppDuration($arr[$keys[19]]);
        }

        if (array_key_exists($keys[20], $arr)) {
            $this->setAppDelayDuration($arr[$keys[20]]);
        }

        if (array_key_exists($keys[21], $arr)) {
            $this->setAppDriveFolderUid($arr[$keys[21]]);
        }

        if (array_key_exists($keys[22], $arr)) {
            $this->setAppRoutingData($arr[$keys[22]]);
        }

        if (array_key_exists($keys[23], $arr)) {
            $this->setProId($arr[$keys[23]]);
        }

        if (array_key_exists($keys[24], $arr)) {
            $this->setAppInitUserId($arr[$keys[24]]);
        }

    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return     Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(ApplicationPeer::DATABASE_NAME);

        if ($this->isColumnModified(ApplicationPeer::APP_UID)) {
            $criteria->add(ApplicationPeer::APP_UID, $this->app_uid);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_TITLE)) {
            $criteria->add(ApplicationPeer::APP_TITLE, $this->app_title);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_DESCRIPTION)) {
            $criteria->add(ApplicationPeer::APP_DESCRIPTION, $this->app_description);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_NUMBER)) {
            $criteria->add(ApplicationPeer::APP_NUMBER, $this->app_number);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_PARENT)) {
            $criteria->add(ApplicationPeer::APP_PARENT, $this->app_parent);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_STATUS)) {
            $criteria->add(ApplicationPeer::APP_STATUS, $this->app_status);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_STATUS_ID)) {
            $criteria->add(ApplicationPeer::APP_STATUS_ID, $this->app_status_id);
        }

        if ($this->isColumnModified(ApplicationPeer::PRO_UID)) {
            $criteria->add(ApplicationPeer::PRO_UID, $this->pro_uid);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_PROC_STATUS)) {
            $criteria->add(ApplicationPeer::APP_PROC_STATUS, $this->app_proc_status);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_PROC_CODE)) {
            $criteria->add(ApplicationPeer::APP_PROC_CODE, $this->app_proc_code);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_PARALLEL)) {
            $criteria->add(ApplicationPeer::APP_PARALLEL, $this->app_parallel);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_INIT_USER)) {
            $criteria->add(ApplicationPeer::APP_INIT_USER, $this->app_init_user);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_CUR_USER)) {
            $criteria->add(ApplicationPeer::APP_CUR_USER, $this->app_cur_user);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_CREATE_DATE)) {
            $criteria->add(ApplicationPeer::APP_CREATE_DATE, $this->app_create_date);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_INIT_DATE)) {
            $criteria->add(ApplicationPeer::APP_INIT_DATE, $this->app_init_date);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_FINISH_DATE)) {
            $criteria->add(ApplicationPeer::APP_FINISH_DATE, $this->app_finish_date);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_UPDATE_DATE)) {
            $criteria->add(ApplicationPeer::APP_UPDATE_DATE, $this->app_update_date);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_DATA)) {
            $criteria->add(ApplicationPeer::APP_DATA, $this->app_data);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_PIN)) {
            $criteria->add(ApplicationPeer::APP_PIN, $this->app_pin);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_DURATION)) {
            $criteria->add(ApplicationPeer::APP_DURATION, $this->app_duration);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_DELAY_DURATION)) {
            $criteria->add(ApplicationPeer::APP_DELAY_DURATION, $this->app_delay_duration);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_DRIVE_FOLDER_UID)) {
            $criteria->add(ApplicationPeer::APP_DRIVE_FOLDER_UID, $this->app_drive_folder_uid);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_ROUTING_DATA)) {
            $criteria->add(ApplicationPeer::APP_ROUTING_DATA, $this->app_routing_data);
        }

        if ($this->isColumnModified(ApplicationPeer::PRO_ID)) {
            $criteria->add(ApplicationPeer::PRO_ID, $this->pro_id);
        }

        if ($this->isColumnModified(ApplicationPeer::APP_INIT_USER_ID)) {
            $criteria->add(ApplicationPeer::APP_INIT_USER_ID, $this->app_init_user_id);
        }


        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @return     Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = new Criteria(ApplicationPeer::DATABASE_NAME);

        $criteria->add(ApplicationPeer::APP_UID, $this->app_uid);

        return $criteria;
    }

    /**
     * Returns the primary key for this object (row).
     * @return     string
     */
    public function getPrimaryKey()
    {
        return $this->getAppUid();
    }

    /**
     * Generic method to set the primary key (app_uid column).
     *
     * @param      string $key Primary key.
     * @return     void
     */
    public function setPrimaryKey($key)
    {
        $this->setAppUid($key);
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of Application (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @throws     PropelException
     */
    public function copyInto($copyObj, $deepCopy = false)
    {

        $copyObj->setAppTitle($this->app_title);

        $copyObj->setAppDescription($this->app_description);

        $copyObj->setAppNumber($this->app_number);

        $copyObj->setAppParent($this->app_parent);

        $copyObj->setAppStatus($this->app_status);

        $copyObj->setAppStatusId($this->app_status_id);

        $copyObj->setProUid($this->pro_uid);

        $copyObj->setAppProcStatus($this->app_proc_status);

        $copyObj->setAppProcCode($this->app_proc_code);

        $copyObj->setAppParallel($this->app_parallel);

        $copyObj->setAppInitUser($this->app_init_user);

        $copyObj->setAppCurUser($this->app_cur_user);

        $copyObj->setAppCreateDate($this->app_create_date);

        $copyObj->setAppInitDate($this->app_init_date);

        $copyObj->setAppFinishDate($this->app_finish_date);

        $copyObj->setAppUpdateDate($this->app_update_date);

        $copyObj->setAppData($this->app_data);

        $copyObj->setAppPin($this->app_pin);

        $copyObj->setAppDuration($this->app_duration);

        $copyObj->setAppDelayDuration($this->app_delay_duration);

        $copyObj->setAppDriveFolderUid($this->app_drive_folder_uid);

        $copyObj->setAppRoutingData($this->app_routing_data);

        $copyObj->setProId($this->pro_id);

        $copyObj->setAppInitUserId($this->app_init_user_id);


        $copyObj->setNew(true);

        $copyObj->setAppUid(''); // this is a pkey column, so set to default value

    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return     Application Clone of current object.
     * @throws     PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);
        return $copyObj;
    }

    /**
     * Returns a peer instance associated with this om.
     *
     * Since Peer classes are not to have any instance attributes, this method returns the
     * same instance for all member of this class. The method could therefore
     * be static, but this would prevent one from overriding the behavior.
     *
     * @return     ApplicationPeer
     */
    public function getPeer()
    {
        if (self::$peer === null) {
            self::$peer = new ApplicationPeer();
        }
        return self::$peer;
    }
}

