<?php
namespace Frontend\Worklistopt\Models;

use Cores\TreeModel,
    Cores\Text,
    Frontend\Common\Models\SysUser,
    Frontend\Worklistopt\Models\WorklistProTaskUser,
    Frontend\Worklistopt\Models\WorklistRefStatus,
    Frontend\Worklistopt\Models\WorklistRefPriority,
    Frontend\Worklistopt\Models\WorklistTrxFile;

class WorklistTrxTask extends TreeModel {

	public function initialize() {
		parent::initialize();
        
        $this->hasMany(
            'wtt_id',
            'Frontend\Worklistopt\Models\WorklistProTaskUser',
            'wptu_wtt_id',
            array(
                'alias' => 'UserProfiles'
            )
        );

        $this->hasManyToMany(
            'wtt_id',
            'Frontend\Worklistopt\Models\WorklistProTaskUser',
            'wptu_wtt_id',
            'wptu_su_id',
            'Frontend\Common\Models\SysUser',
            'su_id',
            array(
                'alias' => 'Users'
            )
        );

        $this->hasMany(
            'wtt_id',
            'Frontend\Worklistopt\Models\WorklistTrxFile',
            'wtf_wtt_id',
            array(
                'alias' => 'Files'
            )
        );

        $this->hasMany(
            'wtt_id',
            'Frontend\Worklistopt\Models\WorklistTrxUpdate',
            'wtu_wtt_id',
            array(
                'alias' => 'Updates'
            )
        );

	}

	public function onConstruct() {
		$this->oldofme = new \StdClass();
		$this->setupNode(array(
			'fields' => array(
				'root'     => 'wtt_root',
				'level'    => 'wtt_level',
				'left'     => 'wtt_left',
				'right'    => 'wtt_right',
				'identity' => 'wtt_id',
				'parent'   => 'wtt_pid'
			)
		));

	}

    public function toScalar($related = TRUE, $fields = NULL) {

        $data = (object) $this->toArray();

        // organics
        $data->wtt_is_leaf = (int) $this->isLeaf();
        $data->wtt_is_parent = (int) $this->isParent();
        $data->wtt_has_children = (int) $this->hasChildren();
        $data->wtt_depth = (int) $this->getDepthValue();
        $data->wtt_path = $this->getPathValue();
        $data->wtt_parent_id = $this->getParentValue();
        $data->wtt_title = strip_tags($data->wtt_title);

        if ( ! empty($fields)) {
            foreach($fields as $field) {
                if ( ! isset($data->$field) && isset($this->$field)) {
                    $data->$field = $this->$field;
                }
            }
        }

        // we need creator
        if ( ! isset($data->creator_su_id)) {
            $enums = array('creator_su_id', 'creator_su_email', 'creator_su_fullname', 'creator_su_avatar_file');

            foreach($enums as $val) {
                $data->$val = NULL;
            }

            if ( ! empty($this->wtt_creator)) {
                $creator = SysUser::findFirst($this->wtt_creator);    
                if ($creator) {
                    foreach($enums as $val) {
                        $data->$val = $creator->{substr($val, 8)};
                    }
                }
            }
            
        }

        $data->creator_su_byname = 'by '.Text::elipsize(($data->creator_su_fullname ?: $data->creator_su_email), 12);

        // we need status
        if ( ! isset($data->status_wrs_id)) {
            $enums = array('status_wrs_id', 'status_wrs_caption', 'status_wrs_icon', 'status_wrs_color', 'status_wrs_data');
            
            foreach($enums as $val) {
                $data->$val = NULL;
            }

            if ( ! empty($this->wtt_wrs_id)) {
                $status = WorklistRefStatus::findFirst($this->wtt_wrs_id);
                if ($status) {
                    foreach($enums as $val) {
                        $data->$val = $status->{substr($val, 7)};
                    }
                }
            }
        }

        // we need priority
        if ( ! isset($data->priority_wrp_id)) {
            $enums = array('priority_wrp_id', 'priority_wrp_name', 'priority_wrp_caption', 'priority_wrp_weight');
            
            foreach($enums as $val) {
                $data->$val = NULL;
            }

            if ( ! empty($this->priority_wrp_id)) {
                $status = WorklistRefPriority::findFirst($this->priority_wrp_id);
                if ($status) {
                    foreach($enums as $val) {
                        $data->$val = $status->{substr($val, 9)};
                    }
                }
            }
        }

        // placeholders
        $data->wtt_users = array();
        $data->wtt_files_count = 0;
        $data->wtt_updates_count = 0;
        $data->wtt_updates_unread = 0;
        $data->parent = NULL;

        return $data;
    }

	public static function fetchScalar($params = array()) {
        
        $start = 0;
        $limit = FALSE;

        if (isset($params['limit'], $params['start'])) {
            $start = (int) $params['start'];
            $limit = (int) $params['limit'];

            unset($params['start'], $params['limit']);    
        }

		$root = self::findRoot($params, FALSE);
        
		if ($root) {
            $columns = array(
				'a.*',
                'b.wrs_id AS status_wrs_id',
				'b.wrs_name AS status_wrs_caption',
				'b.wrs_icon AS status_wrs_icon',
				'b.wrs_color AS status_wrs_color',
				'b.wrs_data AS status_wrs_data',
				'c.wrp_id as priority_wrp_id',
				'c.wrp_name AS priority_wrp_name',
				'c.wrp_caption AS priority_wrp_caption',
				'c.wrp_weight AS priority_wrp_weight',
				'd.su_id AS creator_su_id',
				'd.su_fullname AS creator_su_fullname',
                'd.su_email AS creator_su_email',
                'd.su_avatar_file as creator_su_avatar_file'
			);

            $query = self::findTree($root)
                ->alias('a')
                ->columns($columns)
                ->join('Frontend\Worklist\Models\WorklistRefStatus', 'a.wtt_wrs_id = b.wrs_id', 'b', 'LEFT')
                ->join('Frontend\Worklist\Models\WorklistRefPriority', 'a.wtt_wrp_id = c.wrp_id', 'c', 'LEFT')
                ->join('Frontend\Common\Models\SysUser', 'a.wtt_creator = d.su_id', 'd', 'LEFT')
                ->params($params);

            if ($limit) {
                $query->limit($limit, $start);
            }

            $result = $query->execute()->filter(function($task, $fields){
                return $task->toScalar(TRUE, $fields);
            });

            return $result;
		}

	}

    public static function fetchParent($task) {
        $parent = $task->getParent();

        if ($parent) {
            $data = $parent->toScalar();
            $data->wtt_users = self::fetchUsers($parent);
            $data->parent = self::fetchParent($parent);

            return $data;
        }
        return NULL;
    }

    public static function fetchUsers($task) {
        $users = array();

        foreach($task->users as $user) {
            $data = $user->toScalar();
            
            $lead = $task->getUserProfiles(WorklistProTaskUser::params(array(
                'wptu_wtt_id' => $task->wtt_id,
                'wptu_su_id' => $user->su_id
            )))->getFirst();

            $temp = array(
                'su_id' => $data->su_id,
                'su_fullname' => $data->su_fullname,
                'su_email' => $data->su_email,
                'su_avatar_file' => $data->su_avatar_file,
                'group_sug_id' => $data->group_sug_id,
                'group_sug_name' => $data->group_sug_name,
                'wptu_leader' => $lead ? 1 : 0
            );

            $users[] = $temp;
        }

        return $users;
    }

    public function fetchFiles($task) {
        return array();
    }

    public function position() {
        return array(
            'id'    => $this->getIdValue(),
            'pid'   => $this->getParentValue() ,
            'path'  => $this->getPathValue(true),
            'left'  => $this->getLeftValue(),
            'right' => $this->getRightValue(),
            'prev'  => $this->getPreviousValue(),
            'next'  => $this->getNextValue()
        );
    }

}