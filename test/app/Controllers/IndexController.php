<?php 
namespace Frontend\Worklistopt\Controllers;

use Cores\Controller;

class IndexController extends Controller {
	
	public function indexAction() {
		
		/*$root = Task::findRoot(array('wtt_sp_id' => 30), false);
		$tasks = Task::findNodes($root);
		$nodes = array();

		

		foreach($tasks as $task) {
			$nodes[] = array(
				'id'    => $task->wtt_id,
				'pid'   => $task->wtt_pid,
				'left'  => $task->wtt_left,
				'right' => $task->wtt_right,
				'text'  => $task->wtt_title,
				'depth' => $task->getDepthValue(),
				'leaf'  => $task->isLeaf()
			);
		}

		$this->view->tasks = json_encode($nodes);

		$this->assets->addCssHeader('worklistopt/modscript/libs/flattree.css');

		$this->assets->addJsFooter('worklistopt/appvendor/jsrender/jsrender.js');
		$this->assets->addJsFooter('worklistopt/appvendor/jquery/jquery.ui.js');
		$this->assets->addJsFooter('worklistopt/modscript/libs/flattree.js');*/


	}

}