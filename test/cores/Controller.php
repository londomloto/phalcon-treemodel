<?php

namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Controller
 *
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 */

use Phalcon\Mvc\Controller as PhalconController,
	Phalcon\Mvc\View as PhalconView,
	Cores\Dispatcher as KctDispatcher,
	Cores\Text,
	Mixins\Common;

abstract class Controller extends PhalconController {

	use Common;

	protected $_isJsonResponse = false;
	protected $_isDebug        = false;
	private static $_SiteUrl;
	private $_bootstrap;

	public function initialize() {
		
		self::$_SiteUrl = $this->url->siteUrl();
		
		/*if ($config->application->offsetExists('device')) {
			
			$devConfig = $config->application->device;
			
			if ($devConfig->autodetect == true) {
				$obj = $this->getDI()->getDevice();
				$obj->reload();

				$opt = null;
				$dvs = $devConfig->devices;

				if ($obj->isTablet() && $dvs->offsetExists('tablet')) {
					$opt = $dvs->tablet->toArray();
				} else if ($obj->isMobile() && $dvs->offsetExists('mobile')) {
					$opt = $dvs->mobile->toArray();
				} else if ($obj->isDesktop() && $dvs->offsetExists('desktop')) {
					$opt = $dvs->desktop->toArray();
				}

				if ( ! empty($opt)) {
					if (isset($opt['url']) && ! empty($opt['url'])) {
						header("location: {$opt['url']}");
						exit();
					}

					if (isset($opt['routing']) && ! empty($opt['routing'])) {
						$defaultRouting = $modules[$opt['routing']]->default;
					}
				}

			}
		}*/

		// apply main layout?
		

	}

	public function onConstruct() {

		$isAjax = false;
		$output = $this->dispatcher->getParam('_output');

		if ($this->request->isAjax() || ($output != '' && $output == 'json')) {
			$isAjax = true;
		}

		if ($isAjax) {
			$this->renderActionView();
			$this->responseJson();
		}

	}

	public function responseDebug() {
		$this->_isDebug = true;
	}

	public function responseJson() {
		$this->_isJsonResponse = true;
		$this->response->setContentType('application/json', 'UTF-8');
	}

	public function renderActionView() {
		$this->view->setRenderLevel(PhalconView::LEVEL_ACTION_VIEW);
	}
	
	//-------------------- SHARED ACTIONS --------------------------

	public function proxyAction() {
		$this->view->disable();

		$md = $this->dispatcher->getNamespaceName();
		$md = str_replace(DN, DS, $md);
		// $md = str_replace(array('/', '\\'), DS, $md);
		$md = dirname($md);
		
		$this->assets->proxify(array(
			'module'     => $this->dispatcher->getModuleName(),
			'moduledir'  => $md,
			'controller' => $this->dispatcher->getControllerName(),
			'context'    => $this->dispatcher->getParam('context'),
			'type'       => $this->dispatcher->getParam('type'),
			'path'       => $this->dispatcher->getParam('path')
		));

	}

	public function thumbAction() {
		$this->view->disable();
		$this->assets->thumbify(array(
			'module'     => $this->dispatcher->getModuleName(),
			'controller' => $this->dispatcher->getControllerName(),
			'width'      => $this->dispatcher->getParam('width'),
			'height'     => $this->dispatcher->getParam('height'),
			'context'    => $this->dispatcher->getParam('context'),
			'type'       => $this->dispatcher->getParam('type'),
			'path'       => $this->dispatcher->getParam('path')
		));
	}

	public function show401Action($message = 'Unauthorized') {
		throw new \Exception($message, 401);
	}

	public function show403Action($message = 'Forbidden Access') {
		throw new \Exception($message, 403);
	}

	public function show404Action($message = 'The page you requested was not found') {
		throw new \Exception($message, 404);
	}

	public function show500Action($message = 'Internal Server Error') {
		throw new \Exception($message, 500);
	}
	
	public function afterExecuteRoute(KctDispatcher $dispatcher) {
		
		if ($this->_isJsonResponse) {
			if ( $this->view->getRenderLevel() != PhalconView::LEVEL_ACTION_VIEW) {
				$this->renderActionView();
			}

			$data = $dispatcher->getReturnedValue();

			if (is_array($data) || is_object($data)) {
				$data = json_encode($data);
			}
			
			$this->response->setContent($data);
		}
		
		if ($this->_isDebug) {
			if ( $this->view->getRenderLevel() != PhalconView::LEVEL_ACTION_VIEW) {
				$this->renderActionView();
			}

			$data = $dispatcher->getReturnedValue();
			$this->response->setContent($data);
		}

		$this->response->send();
	}
	
	public function printOut($param) {
		echo '<pre>';
		if (is_array($param) || is_object($param))
			print_r($param);
		else
			echo $param;
		echo '</pre>';
		exit();
	}

	//-------------------- SERVICE SHORTCUT --------------------------
	
	public function getCurrentUser() {
		return $this->access->getCurrentUser();
	}

	public function forward($uri) {
		$parts  = explode('/', $uri);
		$config = array();

		if (count($parts) == 2) {
			$config = array(
				'controller' => $parts[0],
				'action'     => $parts[1]
			);
		} else {
			$config = array(
				'action' => $parts[0]
			);
		}

		return $this->dispatcher->forward($config);
	}

	public function redirect($path = '/') {
		$this->view->disable();
		$res = $this->getDI()->getShared('response');
		$res->redirect($path);
	}
	
	public function siteUrl($path) {
		return $this->url->siteUrl($path);
	}

	public function getMethod() {
		$method = $this->request->getMethod();
		if (($xmethod = $this->request->getHeader('X_METHOD'))) {
			$method = $xmethod;
		}
		return $method;
	}

	public function getRequest($name = null, $filters = null, $default = null) {
		return $this->request->get($name, $filters, $default);
	}

	public function hasQuery($name) {
		if (is_array($name)) {
			foreach($name as $val) {
				if ( ! $this->request->hasQuery($val)) {
					return false;
				}
			}
			return true;
		} else {
			return $this->request->hasQuery($name);
		}
	}
	
	public function getQuery($name = null, $filters = null, $default = null) {
		return $this->request->getQuery($name, $filters, $default);
	}

	public function hasPost($name) {
		return $this->request->hasPost($name);
	}

	public function getPost($name = null, $filters = null, $default = null) {
		return $this->request->getPost($name, $filters, $default);
	}
	
	/**	
	 * @deprecated
	 */
	public function getJsonRawBody() {
		return $this->request->getJsonRawBody();
	}
	
	public function getRawData() {
		return $this->request->getJsonRawBody();
	}

	public function getParam($param, $filters = null) {
		return $this->dispatcher->getParam($param, $filters);
	}

	public function getParams() {
		return $this->dispatcher->getParams();
	}

	public function getTemplate($path = null, $params = array()) {
		return $this->view->getTemplate($path, $params);
	}

	public function setFlash($type, $message) {
		$this->flashSession->message($type, $message);
	}

	public function getFlash($type, $delim = '<br />') {
		$message = $this->flashSession->getMessages($type, true);
		return count($message) > 0 ? implode($delim, $message) : '';
	}

}