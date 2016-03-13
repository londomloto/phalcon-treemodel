<?php

namespace Cores;

class RouterGroup extends \Phalcon\Mvc\Router\Group {

	private $_baseuri;

	public function setBaseUri($uri = '/') {
		$this->_baseuri = $uri;
	}

	public function getBaseUri() {
		return $this->_baseuri;
	}

}