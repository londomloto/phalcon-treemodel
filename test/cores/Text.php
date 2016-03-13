<?php namespace Cores;

/*!
 * @package WORKSAURUS
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author -- Sikelopes
 * @author -- Jokowow
 * @version 1.0
 * @access Public
 * @path /worksaurus/frameworks/phalcon_v1.3.1/Cores/Text.php
 */

if (!defined('BASEPATH')) exit('No direct script access allowed!');

use Phalcon\Text as PhalconText;

class Text extends PhalconText {

	public static function masquerade($text, $separator = '\\', $suffix = 'kct') {

		if ( ! empty($text)) {
			$segments = explode($separator, $text);
			$temp = array();

			for ($i = 0; $i < count($segments); $i++) {
				$camels = preg_split('/(?=[A-Z])/', $segments[$i]);

				if ($camels) {
					$item = '';
					for ($j = count($camels) - 1; $j >= 0; $j--) {
						$part = ucwords(strtolower(strrev($camels[$j])));
						$item.= $part;
					}
				} else {
					$item = ucwords(strrev(trim(strtolower($segments[$i]))));
				}

				$temp[] = $item . $suffix;
			}

			return implode($separator, $temp);
		}
		
		return $text;
	}

	public static function humanify($text, $separator = '\\', $suffix = 'kct') {

		$segments = explode($separator, $text);
		$temp = array();

		if (!empty($text)) {
			for ($i = 0; $i < count($segments); $i++) {

				$item = $segments[$i];
				$item = preg_replace('/' . $suffix . '$/', '', trim($item));
				$camels = preg_split('/(?=[A-Z])/', $item);

				if ($camels) {
					$item = '';
					for ($j = count($camels) - 1; $j >= 0; $j--) {
						$part = ucwords(strtolower(strrev($camels[$j])));
						$item.= $part;
					}
				} else {
					$item = ucwords(strtolower(strrev($item)));
				}

				$temp[] = $item;
			}

			return implode($separator, $temp);
		}
		
		return $text;
	}

	public static function universalize($path, $slash = null) {
		if (empty($slash)) $slash = DS;
		return str_replace(array(
			'/',
			'\\'
		) , $slash, $path);
	}
	
	public static function slugify($text, $replacer = '-') {
		$text = preg_replace('~[^\\pL\d]+~u', $replacer, $text);
		$text = trim($text, '-');
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = strtolower($text);
		$text = preg_replace('~[^-\w]+~', '', $text);

		if (empty($text)) return 'n-a';
		return $text;
	}

	public static function tidify($html) {
		if (extension_loaded('tidy')) {
			$options = ['indent' => TRUE, 'show-body-only' => TRUE, 'output-html' => TRUE, 'wrap' => 0, 'clean' => TRUE, 'bare' => TRUE];

			$tidy = tidy_parse_string($html, $options, 'UTF8');
			$tidy->cleanRepair();

			return $tidy;
		} else return $html;
	}
	
	public static function camelizePath($path, $separator = null, $replacer = null, $infile = true) {
		
		if (empty($separator)) $separator = DS;
		if (empty($replacer)) $replacer = $separator;

		$parts = explode($separator, $path);
		$temp  = [];
		$file  = null;

		if ($infile) {
			$file = array_pop($parts);
		}
		
		for ($i = 0; $i < count($parts); $i++) {
			$temp[] = ucfirst($parts[$i]);
		}
		
		$path = implode($replacer, $temp);

		if ( ! empty($file)) {
			if ( ! empty($path)) {
				$path .= $replacer.$file;
			} else {
				$path .= $file;
			}
		}

		return $path;
	}

	public static function elipsize($text, $length, $position = 1, $sign = '...') {
		$text = trim(strip_tags($text));
		$position = $position > 1 ? 1 : $position;
		
		if (strlen($text) <= $length) {
			return $text;
		}

		$head = substr($text, 0, floor($length * $position));
		$tail = $position == 1 ? substr($text, 0, -($length - strlen($head))) : 
								 substr($text, -($length - strlen($head)));

		return $head.$sign.$tail;
	}

	public static function limitWord($str, $limit = 100, $endChar = '&#8230;') {
		if (trim($str) === '') {
			return $str;
		}

		preg_match('/^\s*+(?:\S++\s*+){1,'.(int) $limit.'}/', $str, $matches);

		if (strlen($str) === strlen($matches[0])) {
			$endChar = '';
		}

		return rtrim($matches[0]).$endChar;
	}

	public static function limitCharacter($str, $n = 500, $endChar = '&#8230;') {
		if (mb_strlen($str) < $n) {
			return $str;
		}

		$str = preg_replace('/ {2,}/', ' ', str_replace(array("\r", "\n", "\t", "\x0B", "\x0C"), ' ', $str));

		if (mb_strlen($str) <= $n) {
			return $str;
		}

		$out = '';
		
		foreach (explode(' ', trim($str)) as $val) {
			$out .= $val.' ';

			if (mb_strlen($out) >= $n) {
				$out = trim($out);
				return (mb_strlen($out) === mb_strlen($str)) ? $out : $out.$endChar;
			}
		}

	}

	public static function uglify() {

	}

	public static function escapeHtml($html) {
		$tool = new \Phalcon\Escaper();
		return $tool->escpapeHtml($html);
	}

}
