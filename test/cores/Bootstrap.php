<?php
namespace Cores;
if (!defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Bootstrap
 *
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
*/

use Phalcon\Mvc\ModuleDefinitionInterface,
	Phalcon\Events\Event,
	Cores\View,
	Cores\Dispatcher,
	Cores\Text,
	Libraries\Auth,
	Libraries\ACL,
	Plugins\DispatchListeners,
	Plugins\ViewListeners;

abstract class Bootstrap implements ModuleDefinitionInterface {
	
	private $_di;
	private $_em;
	private $_dir;
	private $_root;
	private $_name;
	private $_config;
	private $_dispatcher;
	private $_validateSession = false;
	private $_validateAccess = false;
	private $_securedSession = [];
	private $_securedAccess = [];

	public function __construct($di, $em, $dir, $root, $name) {

		$this->_di   = $di;
		$this->_em   = $em;
		$this->_dir  = $dir;
		$this->_root = $root;
		$this->_name = $name;

		$this->initialize();
	}

	public function initialize() {}
	
	public function getDI() {
		return $this->_di;
	}

	public function getDispatcher() {
		return $this->getDI()->get('dispatcher');
	}

	public function getEventsManager() {
		return $this->_em;
	}

	public function getRoot() {
		return $this->_root;
	}

	public function getModuleDir() {
		return $this->_dir;
	}

	public function getModuleName() {
		return $this->_name;
	}

	public function getModuleBase() {
		$modname = str_replace('\\', DS, $this->getModuleName());
		return basename($modname);
	}

	public function getConfig() {
		
		if ( ! $this->_config) {

			$di     = $this->getDI();
			$root   = $this->getRoot();
			$config = $di->get('config')->application->modules->$root;

			// fixup prefix key
			if ( ! $config->offsetExists('prefix')) {
				$config->offsetSet('prefix', '/');
			}

			// fixup theme key
			if ( ! $config->offsetExists('theme')) {
				$config->offsetSet('theme', array(
					'name'   => $root,
					'layout' => 'index'
				));
			}

			$this->_config = $config;
		}

		return $this->_config;
	}

	public function getModulePrefix() {
		return $this->getConfig()->prefix;
	}

	public function getRoutePrefix() {
		return $this->getConfig()->prefix;
	}

	public function hasValidationPath() {
		return $this->getConfig()->offsetExists('login');
	}

	public function getValidationPath() {
		return $this->getConfig()->login;
	}

	public function getValidationUrl() {
		return $this->getDI()->get('url', true)->siteUrl($this->getValidationPath());
	}

	public function getTheme() {
		return $this->getConfig()->theme;
	}

	/**
	 * Get view location context
	 * @deprecated instead use get Bootstrap::ViewContext()
	 */
	public function getContext() {
		return 'res';
	}
	
	public function getViewContext() {
		return 'res';
	}

	/**
	 * Get main layout, 
	 * @deprecated instead use Bootstrap::getMainLayout()
	 */
	public function getLayout() {
		return $this->getTheme()->layout;
	}

	public function getMainLayout() {
		return $this->getTheme()->layout;
	}

	public function validateAccess($state, $items) {
		$this->setValidateAccess($state);
		$this->registerAccess($items);
	}

	public function validateSession($state, $items) {
		$this->setValidateSession($state);
		$this->registerSession($items);
	}

	public function setValidateSession($state) {
		$this->_validateSession = $state;
	}

	public function getValidateSession() {
		return $this->_validateSession;
	}

	public function setValidateAccess($state) {
		$this->_validateAccess = $state;
	}

	public function getValidateAccess() {
		return $this->_validateAccess;
	}

	public function registerSession($config, $merge = false) {
		if ($merge == true) {
			$this->_securedSession = array_merge($this->_securedSession, $config);
		} else {
			$this->_securedSession = $config;
		}
	}

	public function getSecuredSession() {
		return $this->_securedSession;
	}

	public function registerAccess($config, $merge = false) {
		if ($merge == true) {
			$this->_securedAccess = array_merge($this->_securedAccess, $config);
		} else {
			$this->_securedAccess = $config;
		}
	}

	public function getSecuredAccess() {
		return $this->_securedAccess;
	}

	public function registerAutoloaders($di) {

		$loader = $di->getShared('loader');
		$dir    = $this->getModuleDir();
		$name   = $this->getModuleName();

		$loader->registerNamespaces(array(
			$name.'\\Controllers' => $dir.DS.'Controllers'.DS,
			$name.'\\Models' => $dir.DS.'Models'.DS
		), true);

		$loader->register();
	}

	public function registerServices($di) {

		$di   = $this->getDI();
		$em   = $this->getEventsManager();
		$boot = $this;

		$di->set('view', function () use ($boot, $em) {
			$view = View::factory($boot); 
			return $view;
		});

		$em->attach('dispatch', new DispatchListeners($boot));
		
		$dispatcher = new Dispatcher($boot);
		$dispatcher->setEventsManager($em);
		$di->set('dispatcher', $dispatcher);

	}

}
