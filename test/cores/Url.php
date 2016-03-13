<?php namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Url
 * 
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 */

use Phalcon\Mvc\Url as PhalconUrl;

class Url extends PhalconUrl {

	private $_suffix = '';
	private $_prefix = '';

	public function setPrefix($prefix) {
		$this->_prefix = $prefix;
	}

	public function setSuffix($suffix) {
		$this->_suffix = $suffix;
	}

	public function prefix() {
		return $this->_prefix;
	}

	public function suffix() {
		return $this->_suffix;
	}

	public function extscript($file = null) {
		$router  = $this->getDI()->get('router');
		$matched = $router->getMatchedRoute();

		if (is_object($matched))
		{
			$group  = $matched->getGroup();
			$prefix = $group->getPrefix();

			return preg_replace('/\/$/', '', $prefix).'/extscript/'.$file;
		}

		return $file;
	}

	public function getRouter() {
		return $this->getDI()->get('router', true);
	}

	public function getRoute() {
		$router = $this->getRouter();
		return $router ? $router->getMatchedRoute() : null;
	}

	public function getRequest() {
		return $this->getDI()->get('request', true);
	}

	public function baseUri() {
		return $this->getBaseUri();
	}

	public function baseUrl()
	{
		$protocol = $this->getProtocol();
		$baseuri  = $this->getBaseUri();
		$host     = $this->getHost();
		
		return $protocol.$host.$baseuri;
	}

	public function siteUrl($path = '') {
		$baseuri  = $this->baseUri();
		$baseurl  = $this->baseUrl(); 

		// fixup path, remove prefix if any
		$path = ltrim($path, $baseuri);

		// baseurl always ended with trailing slash
		$path = ltrim($path, '/');

		return $baseurl.$path;
	}
	
	public function currentUrl() {	
		$baseuri = $this->baseUri();
		$baseurl = $this->baseUrl();
		$request = $this->getRequest();
		$url = $request->get('_url');
		$url = ltrim($url, $baseuri);
		return $baseurl.$url;
	}

	public function getProtocol() {
		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
	}

	public function getHost() {
		return $_SERVER['HTTP_HOST'];
	}
}