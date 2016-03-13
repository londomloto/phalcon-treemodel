<?php
namespace Frontend\Worklistopt\Controllers;

use Cores\Controller,
	Frontend\Common\Models\SysUser as User,
	Frontend\Worklistopt\Models\WorklistTrxTask as Task,
	Frontend\Worklistopt\Models\WorklistProTaskUser as TaskUser,
	Frontend\Worklistopt\Models\WorklistTrxFile as TaskFile,
	Frontend\Worklistopt\Models\WorklistTrxUpdate as TaskUpdate;

class TaskController extends Controller {

	public function ajaxAction() {

	}

	public function testAction() {
		$task = Task::findNodeById(1191)->toScalar();
		print_r($task);
		exit();
	}

	public function findAction() {
		$params = $this->getQuery();
		return Task::fetchScalar($params);
	}

	public function findByIdAction($id) {
		return Task::findNodeById($id)->toScalar();
	}

	public function createAction() {
		$post = $this->getPost();
		$spec = json_decode($post['spec']);

		$task = new Task();

		foreach($spec as $key => $val) {
			$task->$key = $val;
		}

		$success = FALSE;

		switch($post['type']) {
			case 'append':
			break;

			case 'before':
				$first = Task::findNodeById($post['dest']);
				$success = $task->insertBefore($first);
			break;

			case 'none':
			break;
		}

		return $success ? $task->toArray() : NULL;
	}

	public function putAction($id) {
		$data = (array) $this->getRawData();
		$task = Task::findNodeById($id);

		if ($task) {
			$data['wtt_level'] = $task->depth;
			$task->save($data);

			$field = $this->request->getHeader('X_UPDATE_FIELD');

			if ($field == 'wtt_users') {
				// dismentle existing
				$task->getUserProfiles("wptu_wtt_id = {$task->wtt_id}")->delete();
				$members = json_decode($data['wtt_users']);

				if ($members && count($members) > 0) {
					$uids = array_map(function($member){ return $member->su_id; }, $members);
					$users = User::findIn($uids)->filter(function($user){
						return $user;
					});
					$task->users = $users;
					$task->save();

					// update leader
					$leaders = array_filter($members, function($member){
						return $member->wptu_leader == '1';
					});

					if ( ! empty($leaders)) {
						$profiles = TaskUser::find(TaskUser::params(array('wptu_wtt_id' => $task->wtt_id)));
						foreach($profiles as $pro) {
							$pro->wptu_leader = 0;
							if ($pro->wptu_su_id == $leaders[0]) {
								$pro->wptu_leader = 1;
							}
							$pro->save();
						}
					}
				}
			}

			return $task->toScalar();
		}
		return NULL;
	}

	public function moveAction() {
		$post = $this->getPost();

		$position = (int) $post['position'];
		$task = Task::findNodeById($post['task']);

		$result = new \stdClass();
		$result->success = Task::move($task, $position);
		
		return $result;
	}

	public function relationsAction() {
		// we have to provide several related datas
		$post = $this->getPost();
		$keys = json_decode($post['keys']);
		$user = 

		$result = array();

		foreach($keys as $key) {
			$task = Task::findFirst($key);
			$result[$key] = array(
				'wtt_users' => Task::fetchUsers($task),
				'wtt_files_count' => $task->files->count(),
				'wtt_updates_count' => $task->updates->count(),
				'wtt_updates_unread' => TaskUpdate::findUnreads(NULL, $task)->count(),
				'parent' => Task::fetchParent($task)
			);
		}

		return $result;
	}

}