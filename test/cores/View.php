<?php namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT View
 *
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 */

use Phalcon\Mvc\View as PhalconView,
	Phalcon\Mvc\View\Engine\Php,
	Phalcon\Mvc\View\Engine\Volt,
	Cores\Text,
	Plugins\VoltListeners;

class View extends PhalconView {

	const CONTEXT_TEMPLATE = 'Templates';
	const CONTEXT_MODULE   = 'Views';
	const CONTEXT_LAYOUT   = 'Layouts';

	private $_bootstrap;
	private $_theme;
	private $_context;
	private $_viewpath;
	private $_layoutpath;
	private $_mainpath;
	private $_mainlayout;

	public static function factory($boot) {
		$view  = new View();
		$view->setDefaultVars();
		$view->setBootstrap($boot);
		$view->relocate();
		return $view;
	}

	public function setBootstrap($bootstrap) {
		$this->_bootstrap = $bootstrap;
	}

	public function getBootstrap() {
		return $this->_bootstrap;
	}

	public function setDefaultVars() {
		$this->setVars(array(
			'title' => 'Untitled'
		));
	}

	public function relocate($context = null) {
		
		$boot  = $this->getBootstrap();
		$theme = $boot->getTheme();

		if ( ! empty($context)) {
			$this->_context = $context;
		} else {
			$context = $this->getContext();
		}

		$layout = $this->getMainLayout();

		if ($context == 'res') {
			$this->_viewpath = $this->buildViewsDir();
		} else {
			$this->_viewpath = $boot->getModuleDir().DS.View::CONTEXT_MODULE.DS;
		}

		$this->setViewsDir($this->_viewpath);
		$this->applyTheme($theme->name, $layout);

		$this->registerEngines(array(
			'.phtml' => 'Phalcon\Mvc\View\Engine\Php',
			'.volt'  => function($view) use ($boot) {
				$volt = new Volt($view);
				$volt->setOptions(array(
					'compiledPath' => function($template) use ($boot) {

						$module     = str_replace('\\', DS, $boot->getModuleName());
						$controller = Text::camelize($boot->getDispatcher()->getControllerName());
						$path       = CACHEPATH.DS.$module.DS.$controller.DS;
						$file       = str_replace('.volt', '', basename($template)).'.php';

						if ( ! is_dir($path)) {
							mkdir($path, 0777, true);
						}

						return $path.$file;
					}
				));

				$compiler = $volt->getCompiler();
				$compiler->addExtension(new VoltListeners());
				
				return $volt;
			}
		));

		return $this;		
	}

	public function getContext() {
		$context = $this->_context;

		if (empty($context)) {
			$boot    = $this->getBootstrap();
			$context = $boot->getViewContext();
		}

		return $context;
	}

	public function getMainLayout() {
		$layout = $this->_mainlayout;
		if (empty($layout)) {
			$boot   = $this->getBootstrap();
			$layout = $boot->getMainLayout();
		}
		return $layout;
	}

	public function setMainLayout($layout) {
		$this->_mainlayout = $layout;
		$this->setMainView($this->_mainpath.$layout);
		return $this;
	}

	public function getContextPath() {
		$path = $this->getContext() == 'res' ? View::CONTEXT_TEMPLATE : View::CONTEXT_MODULE;
		$base = $this->getViewPath();
		return substr($base, 0, strpos($base, $path) + strlen($path) + 1);
	}

	public function getViewPath() {
		return $this->_viewpath;
	}
	
	public function getLayoutPath() {
		return $this->_layoutpath;
	}

	public function getMainPath() {
		return $this->_mainpath;
	}

	public function buildViewsDir() {
		$boot    = $this->getBootstrap();
		$path    = str_replace($boot->getRoot().'\\', '', $boot->getModuleName());
		$theme   = $this->getTheme();
		$respath = defined('RESPATH') ? RESPATH : BASEPATH;

		return $respath.DS.Text::masquerade('Themes').DS.$theme->name.DS.View::CONTEXT_TEMPLATE.DS.$path.DS;
	}
	
	public function applyTheme($name, $layout = 'index') {
		
		$vsdir = $this->getViewsDir();
		$vsdir = str_replace(array('/', '\\'), DS, $vsdir);
		$lsdir = '';
		$parts = explode(DS, $vsdir);
		$found = false;
		$path  = '';

		while(count($parts) > 0) {
			$dir = array_pop($parts);
			if ($dir == Text::masquerade('Themes')) {
				$lsdir = implode(DS, $parts).DS.Text::masquerade('Themes'). DS.$name.DS.View::CONTEXT_LAYOUT.DS;
				$found = true;
				break;
			}
		}

		if ($found) {
			$path = $this->findRelativePath($vsdir, $lsdir);
		} else {
			$lsdir = RESPATH.DS.Text::masquerade('Themes').DS.$name.DS.View::CONTEXT_LAYOUT.DS;
			$lsdir = str_replace(array('/', '\\'), DS, $lsdir);
			$path  = $this->findRelativePath($vsdir, $lsdir);
		}

		$this->_layoutpath = $lsdir;
		$this->_mainpath   = $path;

		$this->setLayoutsDir('./');
		$this->setMainView($path.$layout);
	}

	/**
	 * http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
	 */
	public static function findRelativePath($from, $to) {
			
		// some compatibility fixes for Windows paths
		$from    = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
		$to      = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
		$from    = str_replace('\\', '/', $from);
		$to      = str_replace('\\', '/', $to);
		
		$from    = explode('/', $from);
		$to      = explode('/', $to);
		$relPath = $to;

		foreach($from as $depth => $dir) {
		    // find first non-matching dir
		    if($dir === $to[$depth]) {
		        // ignore this directory
		        array_shift($relPath);
		    } else {
		        // get number of remaining dirs to $from
		        $remaining = count($from) - $depth;
		        if($remaining > 1) {
		            // add traversals up to first matching dir
		            $padLength = (count($relPath) + $remaining - 1) * -1;
		            $relPath = array_pad($relPath, $padLength, '..');
		            break;
		        } else {
		            $relPath[0] = './' . $relPath[0];
		        }
		    }
		}
		return implode('/', $relPath);
	}

	public function getTemplate($path = null, $params = array()) {

		$boot  = $this->getBootstrap();
		$disp  = $boot->getDispatcher();
		$root  = $boot->getRoot();
		$local = false;

		if (is_array($path)) {
			$params = $path;
			$path   = null;
		}

		if ( ! is_array($params)) {
			$params = array($params);
		}

		$parts  = explode('/', $path);
		
		list($module, $controller, $action) = array_pad($parts, -3, null);

		if (empty($module)) {
			$local  = true;
			$module = str_replace($root.'\\', '', $boot->getModuleName());
		}

		if (empty($controller)) {
			$local = true;
			$controller = $disp->getControllerName();
		}

		if (empty($action)) {
			$local  = true;
			$action = $disp->getActionName();
		}

		$module     = Text::camelize($module);
		$controller = Text::camelize($controller);
		
		if ( ! $local) {
			$app = $this->getDI()->getShared('app');
			foreach($app->getModules() as $name => $factory) {
				if ($root.'\\'.$module == $name) {
					$boot = $factory($this->getDI());
					break;
				}
			}
		}

		$view = clone $this;
		$view->setBootstrap($boot);
		$view->relocate();
		$view->start();
		$view->setVars($params);
		$view->render($controller, $action);
		$view->finish();

		return $view->getContent();
	}

	public function getTheme() {
		if ( ! $this->_theme) {
			$this->_theme = $this->getBootstrap()->getTheme();
		}
		return $this->_theme;
	}

	public function getThemeName() {
		return $this->getTheme()->name;
	}

	public function getThemeDir() {
		return Text::universalize($this->getLayoutsDir());
	}

	public function getModulePrefix() {
		return $this->_bootstrap->getModulePrefix();
	}

	public function getModuleRoot() {
		return $this->_bootstrap->getRoot();
	}

	/*public static function findRelativePath ( $frompath, $topath ) {
		$from    = explode( DS, $frompath );
		$to      = explode( DS, $topath );
		$relpath = '';
		$i       = 0;
		
		while ( isset($from[$i]) && isset($to[$i]) ) {
			if ( $from[$i] != $to[$i] ) break;
			$i++;
		}

		$j = count( $from ) - 1;
		
		while ( $i <= $j ) {
			if ( !empty($from[$j]) ) $relpath .= '..'.DS;
			$j--;
		}

		while ( isset($to[$i]) ) {
			if ( !empty($to[$i]) ) $relpath .= $to[$i].DS;
			$i++;
		}

		return substr($relpath, 0, -1);
	} */

}
