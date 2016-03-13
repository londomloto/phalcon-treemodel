<?php namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Loader
 *
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 */

use Phalcon\Loader as PhalconLoader,
	Cores\Text;

class Loader extends PhalconLoader {

	public function autoLoad($className) {
		// print($className."\n");
		// Vendors\Opauth\Opauth
		$isVendor = false;

		if (preg_match('#^(\\\?)(Vendors)[\\\]+(.*)#', $className, $matches)) {
			$path      = $this->_namespaces[$matches[2]];
			$className = preg_replace('#^(\\\?)(Vendors)[\\\]+(.*)#', '$3', $className);
			$filePath  = $path.str_replace(array('\\', '/'), DS, $className);
			
			foreach($this->_extensions as $ext) {
				if (file_exists($filePath.'.'.$ext)) {
					require($filePath.'.'.$ext);
					return true;
				}
			}

		} else {

			// print($className."\n");
			
			$parts     = explode('\\', $className);
			$classFile = array_pop($parts);
			$classPath = implode('\\', $parts);
			$rootPath  = false;

			if (array_key_exists($classPath, $this->_namespaces)) {
				$rootPath = $this->_namespaces[$classPath];
			} else if (array_key_exists($parts[0], $this->_namespaces)) {
				$rootPath = $this->_namespaces[$parts[0]];
				$rootPath = $rootPath.Text::masquerade(implode(DS, array_slice($parts, 1))).DS;
			}

			if (false !== $rootPath) {
					
				$filePath = Text::masquerade($classPath);
				$filePath.= DS.$classFile;
				$filePath = $rootPath.$classFile;

				foreach($this->_extensions as $ext)
				{
					if (file_exists($filePath.'.'.$ext))
					{
						require($filePath.'.'.$ext);
						return true;
					}
				}
			}

		}

		parent::autoLoad($className);

	}


}
