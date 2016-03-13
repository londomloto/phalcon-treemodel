<?php 

namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Dispatcher
 * 
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 */

use Phalcon\Mvc\Dispatcher as PhalconDispatcher;

class Dispatcher extends PhalconDispatcher {
	
	private $_bootstrap;

	public function __construct($bootstrap) {
		$this->_bootstrap = $bootstrap;
	}

	public function getBootstrap() {
		return $this->_bootstrap;
	}

}