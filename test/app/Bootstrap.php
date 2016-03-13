<?php

namespace Frontend\Worklistopt;

use Cores\Bootstrap as KctBootstrap;

class Bootstrap extends KctBootstrap {
	
	public function initialize() {

		$this->setValidateSession(false);
		
		$this->registerSession(array(
			'index'     => '*'
		));

	}

	/*public function getViewContext() {
		return 'app';
	}*/
	
	/*
	public function getMainLayout() {
		return 'jquery';
	}

	public function getViewContext() {
		return 'app';
	}*/

}