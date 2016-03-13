<?php 

namespace Cores;

/*!
 * KCT Config
 * 
 * @package WORKMEDIA
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 * @path /workmedia/frameworks/phalcon_v1.3.1/Serockct/Config.php
 */

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

use Phalcon\Config as PhalconConfig,
	Cores\Text;

class Config extends PhalconConfig {

	public function __construct($config = null) {
		parent::__construct($config);
	}
	
	public static function factory($path) {
		$config = self::_populate($path);
		return $config;
	}

	protected static function _populate($path) {
		$config = new Config(null);

		foreach (scandir($path) as $file) {
			$file_parts = pathinfo($file);
			if ($file == '.' || $file == '..' || $file_parts['extension'] != 'php') continue;
			$data = include_once($path.'/'.$file);
			$config->offsetSet(basename($file, '.php'), $data);
		}

		return $config;
	}
}