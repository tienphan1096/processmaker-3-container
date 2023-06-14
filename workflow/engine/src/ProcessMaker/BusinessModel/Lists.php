<?php

namespace ProcessMaker\BusinessModel;

use G;
use Criteria;
use PMLicensedFeatures;
use ProcessMaker\Model\Delegation;
use ProcessMaker\Model\User;
use UsersPeer;

/**
 * @copyright Colosa - Bolivia
 */
class Lists
{

    /**
     * @var array
     */
    private $mapList;
    /**
     * @var \ListInbox
     */
    private $ListInbox;
    /**
     * @var \ListInbox
     */
    private $ListDraft;
    /**
     * @var \ListCanceled
     */
    private $ListCanceled;
    /**
     * @var \ListParticipatedLast
     */
    private $ListParticipated;
    /**
     * @var \ListPaused
     */
    private $ListPaused;
    /**
     * @var \ListCompleted
     */
    private $ListCompleted;
    /**
     * @var Consolidated
     */
    private $ListConsolidated;
    /**
     * @var \ListUnassigned
     */
    private $ListSelfService;

    /**
     * Lists constructor.
     */
    public function __construct()
    {
        $this->mapList = [
            'ListInbox' => 'CASES_INBOX',
            'ListDraft' => 'CASES_DRAFT',
            'ListCanceled' => 'CASES_CANCELLED',
            'ListParticipated' => 'CASES_SENT',
            'ListPaused' => 'CASES_PAUSED',
            'ListCompleted' => 'CASES_COMPLETED',
        ];

        // If the feature for highlight the home folders is disabled, add self-service list to the map
        $flag = defined('HIGHLIGHT_HOME_FOLDER_ENABLE') ? HIGHLIGHT_HOME_FOLDER_ENABLE : 0;
        if (!$flag) {
            $this->mapList['ListSelfService'] = 'CASES_SELFSERVICE';
        }

        $this->ListInbox = new \ListInbox();
        $this->ListDraft = new \ListInbox();
        $this->ListCanceled = new \ListCanceled();
        $this->ListParticipated = new \ListParticipatedLast();
        $this->ListPaused = new \ListPaused();
        $this->ListCompleted = new \ListCompleted();
        $this->ListSelfService = new \ListUnassigned();
    }


    /**
     * Get list for Cases
     *
     * @access public
     * @param array $dataList , Data for list
     * @return array
     *
     * @author Brayan Pereyra (Cochalo) <brayan@colosa.com>
     * @copyright Colosa - Bolivia
     */
    public function getList($listName = 'inbox', $dataList = array(), $total = false)
    {
        Validator::isArray($dataList, '$dataList');
        if (!isset($dataList["userId"])) {
            throw (new \Exception(\G::LoadTranslation("ID_USER_NOT_EXIST", array('userId', ''))));
        } else {
            Validator::usrUid($dataList["userId"], "userId");
        }

        $userUid = $dataList["userId"];
        $filters["paged"] = isset($dataList["paged"]) ? $dataList["paged"] : true;
        $filters['count'] = isset($dataList['count']) ? $dataList['count'] : true;
        $filters["category"] = isset($dataList["category"]) ? $dataList["category"] : "";
        $filters["process"] = isset($dataList["process"]) ? $dataList["process"] : "";
        $filters["search"] = isset($dataList["search"]) ? $dataList["search"] : "";
        $filters["filter"] = isset($dataList["filter"]) ? $dataList["filter"] : "";
        $filters["dateFrom"] = (!empty($dataList["dateFrom"])) ? substr($dataList["dateFrom"], 0, 10) : "";
        $filters["dateTo"] = (!empty($dataList["dateTo"])) ? substr($dataList["dateTo"], 0, 10) : "";
        $filters["start"] = isset($dataList["start"]) ? $dataList["start"] : "0";
        $filters["limit"] = isset($dataList["limit"]) ? $dataList["limit"] : "25";
        $filters["sort"] = isset($dataList["sort"]) ? $dataList["sort"] : "";
        $filters["dir"] = isset($dataList["dir"]) ? $dataList["dir"] : "ASC";
        $filters["action"] = isset($dataList["action"]) ? $dataList["action"] : "";
        $filters['newestthan'] = isset($dataList['newerThan']) ? $dataList['newerThan'] : '';
        $filters['oldestthan'] = isset($dataList['oldestthan']) ? $dataList['oldestthan'] : '';
        $filters['appUidCheck'] = isset($dataList['appUidCheck']) ? $dataList['appUidCheck'] : array();

        // Select list
        switch ($listName) {
            case 'inbox':
                $list = new \ListInbox();
                $listpeer = 'ListInboxPeer';
                break;
            case 'participated_history':
                $list = new \ListParticipatedHistory();
                $listpeer = 'ListParticipatedHistoryPeer';
                break;
            case 'participated_last':
                $list = new \ListParticipatedLast();
                $listpeer = 'ListParticipatedLastPeer';
                break;
            case 'completed':
                $list = new \ListCompleted();
                $listpeer = 'ListCompletedPeer';
                break;
            case 'paused':
                $list = new \ListPaused();
                $listpeer = 'ListPausedPeer';
                break;
            case 'canceled':
                $list = new \ListCanceled();
                $listpeer = 'ListCanceledPeer';
                break;
            case 'my_inbox':
                $list = new \ListMyInbox();
                $listpeer = 'ListMyInboxPeer';
                break;
            case 'unassigned':
                $list = new \ListUnassigned();
                $listpeer = 'ListUnassignedPeer';
                break;
        }


        // Validate filters
        $filters["start"] = (int)$filters["start"];
        $filters["start"] = abs($filters["start"]);
        if ($filters["start"] != 0) {
            $filters["start"] + 1;
        }

        $filters["limit"] = (int)$filters["limit"];
        $filters["limit"] = abs($filters["limit"]);
        if ($filters["limit"] == 0) {
            $conf = new \Configurations();
            $generalConfCasesList = $conf->getConfiguration('ENVIRONMENT_SETTINGS', '');
            if (isset($generalConfCasesList['casesListRowNumber'])) {
                $filters["limit"] = (int)$generalConfCasesList['casesListRowNumber'];
            } else {
                $filters["limit"] = 25;
            }
        } else {
            $filters["limit"] = (int)$filters["limit"];
        }

        $filters["sort"] = G::toUpper($filters["sort"]);
        $columnsList = $listpeer::getFieldNames(\BasePeer::TYPE_FIELDNAME);
        if (!(in_array($filters["sort"], $columnsList))) {
            $filters["sort"] = '';
        }

        $filters["dir"] = G::toUpper($filters["dir"]);
        if (!($filters["dir"] == 'DESC' || $filters["dir"] == 'ASC')) {
            $filters["dir"] = 'DESC';
        }
        if ($filters["process"] != '') {
            Validator::proUid($filters["process"], '$pro_uid');
        }
        if ($filters["category"] != '') {
            Validator::catUid($filters["category"], '$cat_uid');
        }
        if ($filters["dateFrom"] != '') {
            Validator::isDate($filters["dateFrom"], 'Y-m-d', '$date_from');
        }
        if ($filters["dateTo"] != '') {
            Validator::isDate($filters["dateTo"], 'Y-m-d', '$date_to');
        }

        if ($total) {
            $total = $list->getCountList($userUid, $filters);
            return $total;
        }

        $result = $list->loadList($userUid, $filters);
        if (!empty($result)) {
            foreach ($result as &$value) {
                // For backward compatibility with "light" endpoints, we need to cast to string
                $value['APP_NUMBER'] = (string)$value['APP_NUMBER'];
                $value['DEL_INDEX'] = (string)$value['DEL_INDEX'];

                if (isset($value['DEL_PREVIOUS_USR_UID'])) {
                    $value['PREVIOUS_USR_UID'] = $value['DEL_PREVIOUS_USR_UID'];
                    $value['PREVIOUS_USR_USERNAME'] = $value['DEL_PREVIOUS_USR_USERNAME'];
                    $value['PREVIOUS_USR_FIRSTNAME'] = $value['DEL_PREVIOUS_USR_FIRSTNAME'];
                    $value['PREVIOUS_USR_LASTNAME'] = $value['DEL_PREVIOUS_USR_LASTNAME'];
                } elseif (!empty($value["USR_ID"])) {
                    $user = User::where("USR_ID", $value["USR_ID"])->first();
                    $value["PREVIOUS_USR_UID"] = $value["DEL_PREVIOUS_USR_UID"] = $user->USR_UID;
                    $value["PREVIOUS_USR_USERNAME"] = $value["DEL_PREVIOUS_USR_USERNAME"] = $user->USR_USERNAME;
                    $value["PREVIOUS_USR_FIRSTNAME"] = $value["DEL_PREVIOUS_USR_FIRSTNAME"] = $user->USR_FIRSTNAME;
                    $value["PREVIOUS_USR_LASTNAME"] = $value["DEL_PREVIOUS_USR_LASTNAME"] = $user->USR_LASTNAME;
                }

                if (isset($value['DEL_DUE_DATE'])) {
                    $value['DEL_TASK_DUE_DATE'] = $value['DEL_DUE_DATE'];
                }

                if (isset($value['APP_PAUSED_DATE'])) {
                    $value['APP_UPDATE_DATE'] = $value['APP_PAUSED_DATE'];
                }

                if (isset($value['DEL_CURRENT_USR_USERNAME'])) {
                    $value['USR_USERNAME'] = $value['DEL_CURRENT_USR_USERNAME'];
                    $value['USR_FIRSTNAME'] = $value['DEL_CURRENT_USR_FIRSTNAME'];
                    $value['USR_LASTNAME'] = $value['DEL_CURRENT_USR_LASTNAME'];
                    $value['APP_UPDATE_DATE'] = $value['DEL_DELEGATE_DATE'];
                }

                if (isset($value['DEL_CURRENT_TAS_TITLE']) && $value['DEL_CURRENT_TAS_TITLE'] != '') {
                    $value['APP_TAS_TITLE'] = $value['DEL_CURRENT_TAS_TITLE'];
                } elseif (!empty($value["TAS_TITLE"]) && empty($value["APP_TAS_TITLE"])) {
                    $value["APP_TAS_TITLE"] = $value["TAS_TITLE"];
                }

                if (isset($value['APP_STATUS'])) {
                    $value['APP_STATUS_LABEL'] = G::LoadTranslation("ID_{$value['APP_STATUS']}");
                }

                if (!empty($value["PRO_TITLE"]) && empty($value["APP_PRO_TITLE"])) {
                    $value["APP_PRO_TITLE"] = $value["PRO_TITLE"];
                }
            }
        }
        $response = array();
        if ($filters["paged"]) {
            $filtersData = array();
            $filtersData['start'] = $filters["start"];
            $filtersData['limit'] = $filters["limit"];
            $filtersData['sort'] = G::toLower($filters["sort"]);
            $filtersData['dir'] = G::toLower($filters["dir"]);
            $filtersData['cat_uid'] = $filters["category"];
            $filtersData['pro_uid'] = $filters["process"];
            $filtersData['search'] = $filters["search"];
            $filtersData['date_from'] = $filters["dateFrom"];
            $filtersData['date_to'] = $filters["dateTo"];
            $response['filters'] = $filtersData;
            $response['data'] = $result;
            $filtersData['action'] = $filters["action"];
            $response['totalCount'] = $list->getCountList($userUid, $filtersData);
        } else {
            $response = $result;
        }
        return $response;
    }

    /**
     * Get counters for lists
     * 
     * @param $userId
     * @return array
     */
    public function getCounters($userId)
    {
        $list = $this->mapList;
        $response = array();
        foreach ($list as $listObject => $item) {
            switch ($listObject) {
                case 'ListDraft':
                    $total = $this->$listObject->getCountList($userId, array('action' => 'draft'));
                    array_push($response, (array('count' => $total, 'item' => $item)));
                    break;
                case 'ListSelfService':
                    $total = Delegation::countSelfService($userId);
                    array_push($response, ([
                        'count' => $total,
                        'item' => $item
                    ]));
                    break;
                default:
                    $totalInbox = $this->$listObject->getCountList($userId);
                    array_push($response, (array('count' => $totalInbox, 'item' => $item)));
                    break;
            }
        }
        return $response;
    }
}
