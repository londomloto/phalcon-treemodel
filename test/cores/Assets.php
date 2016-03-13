<?php

namespace Cores;

if (!defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * Asset Mananger
 *
 * @package KCT Framework
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author KCT Team
 * @author xALPx
 * @author jokowow
 * @version 1.0
 * @access Public
 * @path /frameworks/phalcon_v1.3.1/Cores/Assets.php
 */

use Phalcon\Assets\Collection,
	Phalcon\Assets\Manager,
	Phalcon\Assets\Resource,
	Phalcon\Assets\Filters,
	Phalcon\Assets\Exception as AssetException,
	Cores\Text;

class Assets extends Manager {

	private $_di;

	/**
	 * Default constructor
	 *
	 * @param Phalcon\DI $di dependency injector
	 */
	public function __construct($di) {
		$this->_di = $di;
		$this->initCollection();
	}

	/**
	 * Register assets collection
	 */
	public function initCollection() {
		$this->set('cssheader', new Collection());
		$this->set('jsfooter', new Collection());
	}
	
	public function getDI() {
		return $this->_di;
	}
	
	public static function getStorage() {
		return \Phalcon\DI::getDefault()->getShared('storage');
	}

	public function collect($group, $type, $path = null) {
		$collection = $this->get("{$type}{$group}");
		if ($collection) {
			$url     = $this->getDI()->get('url', true);
			$local   = true;
			$baseuri = $url->baseUri();

			if (preg_match('/^http(s)?/', $path)) {
				$local = false;
			} else {
				if ( ! preg_match('|^'.$baseuri.'|', $path)) {
					$path = $baseuri.$path;
				}
				$path = ltrim($path, '/');
			}
			
			$collection->add(new Resource($type, $path, $local));
		}
	}

	public function addHeader($type, $path = null) {
		$this->collect('header', $type, $path);
	}

	public function addFooter($type, $path = null) {
		$this->collect('footer', $type, $path);
	}

	public function addJsFooter($path) {
		if (is_array($path)) {
			foreach($path as $item) {
				$this->addFooter('js', $item);
			}
		} else {
			$this->addFooter('js', $path);	
		}
	}

	public function addCssHeader($path) {
		if (is_array($path)) {
			foreach($path as $item) {
				$this->addHeader('css', $item);
			}
		} else {
			$this->addHeader('css', $path);
		}
	}

	public static function fetchProxy($path) {
		
		$path = trim(str_replace(array('\\', '/'), '/', $path));

		$pattern = '/([a-zA-Z0-9_\-\.\/]{0,})'.
				   '(sys|pub|app|mod|ext|svc)'.
				   '(script|config|style|image|font|vendor|html|error|upload|asset|file)\/'.
				   '([a-zA-Z0-9_\-\.\/]{0,})/';

		preg_match($pattern, $path, $matches);

		if ($matches) {
			$matches[4] = Text::camelizePath($matches[4], '/', '/');
			return array(
				'context' => $matches[2],
				'type'    => $matches[3],
				'path'    => $matches[4]
			);
		}

		return null;
	}

	public function getResourcePath($resources) {
		
		$context = $resources['context'];
		$type    = $resources['type'];
		$path    = $resources['path'];
		$cloud   = false;
		
		if ($type != 'vendor') {
			$path = explode('/', $path);
			$file = array_pop($path);
			$path = implode('/', $path).'/'.$file;
		}

		$respath = ! defined('RESPATH') ? BASEPATH : RESPATH;

		$publics = Text::masquerade('Publics');
		$vendors = Text::masquerade('Vendors');
		$errors  = Text::masquerade('Errors');
		$themes  = Text::masquerade('Themes');

		switch ($context) {
			case 'sys': $file = dirname(SYSPATH).'/'.$path; break;
			case 'svc': $file = SVCPATH.'/'.$path; break;
			case 'pub':
			case 'app':
				switch ($type) {
					case 'script': $file = $respath.'/'.$publics.'/Javascripts/'.$path; break;
					case 'config': $file = PUBLICPATH.'/Configs/'.$path; break;
					case 'style':  $file = $respath.'/'.$publics.'/Stylesheets/'.$path; break;
					case 'image':
						$path = Text::camelizePath($path, '/');
						$file = $respath.'/'.$publics.'/Images/'.$path;
						break;

					case 'font':   $file = $respath.'/'.$publics.'/Fonts/'.$path; break;
					case 'upload': $file = PUBLICPATH.'/Uploads/'.$path; break;
					case 'vendor': $file = $respath.'/'.$vendors.'/'.$path; break;
					case 'error':  $file = BASEPATH.'/'.$errors.'/'.$path; break;

					case 'asset':
						$parts  = explode('/', $path);
						$folder = array_shift($parts);
						$file   = PUBLICPATH.'/'.ucwords($folder).'/'.implode(DS, $parts);
						break;

					case 'file':
						$storage = $this->getStorage();
						if ($storage->isLocal()) {
							$path = Text::camelizePath($path, '/');
							$file = $context == 'app' ? FSPATH.'/apps/'.DOMAINBASE.'/'.$path : 
														FSPATH.'/publics/'.$path;
						} else {
							$cloud = true;
							$file  = $storage->getResourcePath($resources);
						}
						break;
				}
				break;

			case 'mod':
				
				$registry = $this->getDI()->get('registry');
				$mdir = $registry->directories->Modules.$resources['moduledir'];
				$path = Text::camelizePath($path, '/');

				switch ($type) {
					case 'script': $file = $mdir.'/Javascripts/'.$path; break;
					case 'style':  $file = $mdir.'/Stylesheets/'.$path; break;
					case 'image':  $file = $mdir.'/Images/'.$path; break;
					case 'font':   $file = $mdir.'/Fonts/'.$path; break;
					case 'html':   $file = $mdir.'/Templates/'.$path; break;
				}
				break;

			case 'ext':
				
				$view = $this->getDI()->get('view');
				$boot = $view->getBootstrap();

				$thm     = $boot->getTheme();
				$thmname = $thm->name;
				$thmpath = $respath.'/'.$themes.'/'.$thmname;
				$path    = Text::camelizePath($path, '/');

				switch ($type) {
					case 'script': $file = $thmpath.'/Javascripts/'.$path; break;
					case 'style':  $file = $thmpath.'/Stylesheets/'.$path; break;
					case 'image':  $file = $thmpath.'/Images/'.$path; break;
					case 'font':   $file = $thmpath.'/Fonts/'.$path; break;
					case 'html':   $file = $thmpath.'/Templates/'.$path; break;
					case 'asset':  $file = $thmpath.'/'.$path; break;
				}

				break;
		}

		// $file = $cloud ? str_replace(array('\\', '/'), DS, $file) : $file;
		$file = $cloud ? str_replace(array('\\', '/'), '/', $file) : $file;
		return $file;
	}
	
	public static function getRealPath($path, $type = null, $context = null) {
		$fetch = empty($type) && empty($context) ? true : false;
		
		$proxy = array(
			'context' => $context,
			'type'    => $type,
			'path'    => $path
		);

		if ($fetch) {
			$proxy = self::fetchProxy($path);
		}

		$helper = new static(\Phalcon\DI::getDefault());
		$file   = $helper->getResourcePath($proxy);
		$helper = null;

		return $file;
	}

	public static function isExists($path, $type = null, $context = null) {
		$storage = self::getStorage();
		if ($storage->isLocal()) {
			$file = self::getRealPath($path, $type, $context);
			return file_exists($file) && ! is_dir($file);
		} else {			
			return $storage->isExists($path, $type, $context);
		}
	}

	public static function toResource($path, $type = null, $context = null) {
		$storage = self::getStorage();
		if ($storage->isLocal()) {
			$file = self::getRealPath($path, $type, $context);
			if (file_exists($file) && ! is_dir($file)) {
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				return new Resource($ext, $file);
			}
		} else {
			return $storage->toResource($path, $type, $context);
		}

		return null;
	}

	public static function getUrl($path) {
		$di = \Phalcon\DI::getDefault();
		return $di->getUrl()->siteUrl($path);
		
		// $storage = self::getStorage();
		// return $storage->isLocal() ? $di->getUrl()->siteUrl($path) : $storage->getUrl($path);
	}

	public static function getSize($file, $proxy = false) {
		$storage = self::getStorage();
		if ($storage->isLocal()) {
			$file = $proxy ? self::getRealPath($file) : $file;
			return file_exists($file) ? filesize($file) : 0;
		} else {
			return $storage->getSize($file, $proxy);
		}
	}

	public static function isVideo($file, $proxy = false) {
		$file = $proxy ? self::getRealPath($file) : $file;
		$mime = self::getMimeType($file);
		return strstr($mime, 'video');
	}

	public static function isAudio($file, $proxy = false) {
		$file = $proxy ? self::getRealPath($file) : $file;
		$mime = self::getMimeType($file);
		return strstr($mime, 'audio');
	}

	public static function isImage($file, $proxy = false) {
		$file = $proxy ? self::getRealPath($file) : $file;
		$mime = self::getMimeType($file);
		return (bool)strstr($mime, 'image');
	}

	public static function createFile() {}
	public static function updateFile() {}

	public static function deleteFile($file, $proxy = false) {
		$DI = \Phalcon\DI::getDefault();
		$storage = self::getStorage();
		if ($storage->isLocal()) {
			$file = $proxy ? self::getRealPath($file) : $file;
			return @unlink($file);
		} else {
			return $storage->deleteFile($file, $proxy);
		}
	}

	public static function stream($file, $mime = null) {

		if (preg_match('/^s3:/', $file)) {
			$context = stream_context_create(array(
				's3' => array(
					'seekable' => true
				)
			));
			$stream = fopen($file, 'r', false, $context);
		} else {
			$stream = fopen($file, 'rb');	
		}

		if ( ! $stream) {	
			throw new \Exception("Could not stream file!");
		}

		$mime   = empty($mime) ? self::getMimeType($file) : $mime;
		$start  = 0;
		$size   = filesize($file);
		$end    = ($size - 1);
		$buffer = 1024*8;

		ob_get_clean();
		
		header("Content-Type: $mime");
		header("Cache-Control: max-age=2592000, public");
		header("Expires: ".gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT');
		header("Last-Modified: ".gmdate('D, d M Y H:i:s', @filemtime($file)) . ' GMT' );
		header("Accept-Ranges: 0-".$end);

		if (isset($_SERVER['HTTP_RANGE'])) {

			$_start = $start;
			$_end   = $end;

			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

			if (strpos($range, ',') !== false) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}

			if ($range == '-') {
				$_start = $size - substr($range, 1);
			} else {
				$range  = explode('-', $range);
				$_start = $range[0];
				$_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $_end;
			}
			
			$_end = ($_end > $end) ? $end : $_end;
			
			if ($_start > $_end || $_start > ($size - 1) || $_end >= $size) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}

			$start  = $_start;
			$end    = $_end;
			$length = $end - $start + 1;

			fseek($stream, $start);

			header('HTTP/1.1 206 Partial Content');
			header("Content-Length: ".$length);
			header("Content-Range: bytes $start-$end/".$size);

		} else {
			header("Content-Length: ".$size);
		}
		
		
		set_time_limit(0);
		
		$chunked = '';
		$counter = 0;

		while ( ! feof($stream)) { 
			echo fread($stream, $buffer);
			ob_flush();
			flush();
		}
		
		/*
		$i = $start;
		set_time_limit(0);

		while( ! feof($stream) && $i <= $end) {
			$bRead = $buffer;
			
			if (($i + $bRead) > $end) {
				$bRead = $end - $i + 1; 
			}

			//print($bRead);

			$chunked = fread($stream, $bRead);
			echo $chunked;

			ob_flush();
			flush();

			exit();
			$i += $bRead;
		}*/
		
		fclose($stream);
		exit();

	}	

	public function proxify($resources) {
		$file  = $this->getResourcePath($resources);
		$found = false;

		// AWS Stream allowed
		if (file_exists($file) && ! is_dir($file)) {
			$found = true;
		} else if (preg_match('/^http(s)?:\/\//', $file)) {
			$storage = $this->getStorage();
			if ( ! $storage->isLocal()) {
				$storage->proxify($resources);
				exit();
			}
		}
		
		if ($found) {

			if ($this->isVideo($file) || $this->isAudio($file)) {
				$this->stream($file);
			} else {
				
				$mime = $this->getMimeType($file);

				if ( ! $mime) $mime = 'application/octet-stream';

				if (filesize($file) == 0) {
					throw new \Phalcon\Mvc\Dispatcher\Exception('The page you requested was not found', 404);
				}
				
				$lastmod    = fileatime($file);
				$lastmodgmt = gmdate('D, d M Y H:i:s', $lastmod) . ' GMT';
				$expires    = time();
				$expiregmt  = $expires = gmdate('D, d M Y H:i:s', $expires) . ' GMT';
				$etag       = md5_file($file);

				header('Cache-Control: cache');
				header('Pragma: cache');	// old fashion
				header('Expires: ' . $expiregmt);
				header('Content-Type: ' . $mime);
				header('Content-Length: ' . filesize($file));

				$this->tryCaching($etag, $lastmodgmt, $mime);
				
				if (ob_get_level() == 0) ob_start();
				
				if (ENVIRONMENT == 'production') {
					echo $this->minify($file);
					ob_flush();
					flush();
				} else {
					/*$ext      = $this->getExtension($file);
					$resource = new Resource($ext, $file);
					$content  = $resource->getContent();*/
					// support large file from AWS S3
					if ($stream = fopen($file, 'r')) {
						while ( ! feof($stream)) {
							echo fread($stream, 2014);
							ob_flush();
							flush();
						}
						fclose($stream);
					}
				}
				ob_end_clean();
			}
		} else {
			throw new AssetException("File ".basename($file)." was not found");
		}

	}

	public static function tryCaching($etag, $lastmodified, $mime = '') {
		$etag_str = $etag;

		header("Last-Modified: $lastmodified");
		header("ETag: \"{$etag}\"");

		$if_none_match     = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : FALSE;
		$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) : FALSE;

		if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE) {
			if ( ! in_array($mime, array('image/x-png'))) {
				$etag = $etag.'-gzip';
				$if_none_match = strtolower(str_replace(array(
					'"',
					'-gzip'
				) , '', $if_none_match)) . '-gzip';
			}
		}

		if ( ! $if_modified_since && ! $if_none_match) return;
		if ($if_none_match && $if_none_match != $etag && $if_none_match != '"' . $etag . '"') return;
		if ($if_modified_since && $if_modified_since != $lastmodified) return;

		header('HTTP/1.1 304 Not Modified');
		exit();
	}

	public function thumbify($resources) {

		$file   = $this->getResourcePath($resources);
		
		$width  = (float)$resources['width'];
		$height = (float)$resources['height'];

		$scope  = $resources['context'];
		$path   = $resources['path'];
		$type   = $resources['type'];

		$found  = false;

		if (file_exists($file)) {
			$found = true;
		} else if (preg_match('/^http(s)?:\/\//', $file)) {
			$storage = $this->getStorage();
			if ( ! $storage->isLocal()) {
				$storage->thumbify($resources);
				exit();
			}
		}
		
		if ($found) {

			ini_set('memory_limit', '500M');

			$mime    = Assets::getMimeType($file);
			
			$etag      = md5_file($file);
			$lastmod   = gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT';
			$expires   = gmdate('D, d M Y H:i:s', time()) . ' GMT';
				
			Assets::tryCaching($etag, $lastmod, $mime);
			
			$size      = getimagesize($file);
			$quality   = 90;
			
			$width     = $size[0];
			$height    = $size[1];
			$maxWidth  = (float)$resources['width'];
			$maxHeight = (float)$resources['height'];
			
			if ($maxWidth >= $width && $maxHeight >= $height) {
				$maxWidth 	= $width;
				$maxHeight	= $height;
			}

			$offsetX   = 0;
			$offsetY   = 0;
			$ratio     = "{$maxWidth}:{$maxHeight}";
			
			if ( ! empty($ratio)) {

				$cropRatio = explode(':', (string) $ratio);
	            
	            if (count($cropRatio) == 2) {

	                $ratioComputed      = $width / $height;
	                $cropRatioComputed  = (float) $cropRatio[0] / (float) $cropRatio[1];

	                if ($ratioComputed < $cropRatioComputed) { 
	                    $origHeight = $height;
	                    $height     = $width / $cropRatioComputed;
	                    $offsetY    = ($origHeight - $height) / 2;
	                } else if ($ratioComputed > $cropRatioComputed) { 
	                    $origWidth  = $width;
	                    $width      = $height * $cropRatioComputed;
	                    $offsetX    = ($origWidth - $width) / 2;
	                }
	            }

	        }

			$xRatio    = $maxWidth / $width;
			$yRatio    = $maxHeight / $height;

			if ($xRatio * $height < $maxHeight) { 
				$tnHeight   = ceil($xRatio * $height);
	            $tnWidth    = $maxWidth;
	        } else {
	        	$tnWidth    = ceil($yRatio * $width);
	            $tnHeight   = $maxHeight;
	        }

			$dst = imagecreatetruecolor($tnWidth, $tnHeight);

			switch ($mime) {
	            case 'image/gif':
					$creationFunction = 'ImageCreateFromGif';
					$outputFunction   = 'ImagePng';
					$mime             = 'image/png';
					$doSharpen        = FALSE;
					$quality          = round(10 - ($quality / 10));
	            break;

	            case 'image/x-png':
	            case 'image/png':
					$creationFunction = 'ImageCreateFromPng';
					$outputFunction   = 'ImagePng';
					$doSharpen        = FALSE;
					$quality          = round(10 - ($quality / 10));
	            break;

	            default:
					$creationFunction = 'ImageCreateFromJpeg';
					$outputFunction   = 'ImageJpeg';
					$doSharpen        = TRUE;
	            break;
	        }

			$src = $creationFunction($file);

			if (in_array($mime, array('image/gif', 'image/png'))) {
				imagealphablending($dst, false);
	            imagesavealpha($dst, true);
	        }

	        ImageCopyResampled($dst, $src, 0, 0, $offsetX, $offsetY, $tnWidth, $tnHeight, $width, $height);
	        
	        ob_start();
	        $outputFunction($dst, null, $quality);
	        $data = ob_get_contents();
	        ob_end_clean();

	        ImageDestroy($src);
	        ImageDestroy($dst);

			header("Cache-Control: cache");
			header("Pragma: cache");
			header("Expires: ".$expires);
	        header("Content-type: ".$mime);
	        header("Content-Length: ".strlen($data));

	        echo $data;

		} else {
			throw new \Phalcon\Mvc\Dispatcher\Exception(
				"File {$resources['path']} doesn't exists!",
				404
			);
		}
	}

	public static function minify($file, $runtime = true) {
		
		$ext = self::getExtension($file);
		$dir = CACHEPATH.DS.'minified'.DS;
		$min = $dir.md5_file($file).'.min.'.$ext;

		if ( ! in_array($ext, array('js', 'css'))) {
			$res = new Resource($ext, $file);
			return $res->getContent();
		}

		if ( ! is_dir($dir)) {
			@mkdir($dir, 0777, true);
		}

		if ($runtime) {
			switch ($ext) {
				case 'js':
					$res = new Resource('js', $file);
					$res->setTargetPath($min)->setFilter(new Filters\Jsmin());
					break;

				case 'css':
					$res = new Resource('css', $file);
					$res->setTargetPath($min)->setFilter(new Filters\Cssmin());
					break;
			}
			return $res->getContent();
		} else {
			switch ($ext) {
				case 'js':
					$collection = md5_file($file);
					$this->collection($collection)->setTargetPath($min)->addJs($file)->addFilter(new Filters\Jsmin());
					$this->outputJs($collection);
					$file = $min;
					break;

				case 'css':
					$collection = md5_file($file);
					$this->collection($collection)->setTargetPath($min)->addCss($file)->addFilter(new Filters\Cssmin());
					$this->outputJs($collection);
					$file = $min;
					break;
			}
			return $file;
		}
	}

	public static function getExtension($file) {
		return strtolower(pathinfo($file, PATHINFO_EXTENSION));
	}

	public static function compress($files, $proxy = false, $name = 'attachment.zip') {
		
		$files = ! is_array($files) ? [$files] : $files;
		$store = self::getStorage();

		if (count($files) > 0) {

			if ($store->isLocal()) {

				$files = array_map(function($item) use($proxy) {
					if (is_string($item)) {
						$item = ['file' => $item, 'name' => basename($item)];
					}
					
					if ( ! isset($item['name'])) {
						$item['name'] = basename($item['file']);
					}

					if ($proxy) {
						$item['file'] = self::getRealPath($item['file']);
					}
					return $item;
				}, $files);

				$files = array_filter($files, function($item){
					if (file_exists($item['file'])) return $item;
				});

				if (count($files) > 0) {
					
					$temp = ini_get('upload_tmp_dir');
					$temp = $temp ? $temp : sys_get_temp_dir();

					if ($temp && is_writable($temp)) {

						if (self::isZipInstalled()) {
							
							if (ob_get_level() == 0) ob_start();

							header("Set-Cookie: downloadtoken=finish; path=/");
							header("Content-Type: application/zip");
							header("Content-disposition: attachment; filename=\"".$name."\"");

							$paths = array_map(function($item){ return $item['file']; }, $files);
							$pipe  = popen('zip -j - '.implode(' ', $paths), 'r');
							$chunk = 2014;

							while ( ! feof($pipe)) {
								echo fread($pipe, $chunk);
								ob_flush();
								flush();
							}

							pclose($pipe);
							ob_end_clean();

						} else {

							if (class_exists('ZipArchive')) {

								$file = $temp.DS.md5(uniqid(rand(), true)).'.zip';
								$zip  = new \ZipArchive();
								
								if ($zip->open($file, \ZIPARCHIVE::CREATE) !== true) {
									throw new \Exception("Failed to create {$file['name']}", 500);
								}
								
								$zip->setArchiveComment('File archived by KCT Framework');

								foreach($files as $item) {
									$zip->addFile($item['file'], $item['name']);
								}

								$zip->close();
								Assets::download($file, false, $name);

								@unlink($file);

							} else {
								throw new \Exception("ZipArchive is not installed", 500);
							}
						}

					} else {
						throw new \Exception("Directory '$temp' is neither set or writable", 500);
					}

				} else {
					throw new \Exception("No selected file(s) to compress", 500);
				}

			} else {
				$store->compress($files, $proxy, $name);
			}	
		}
	}

	public static function isZipInstalled() {
		$installed = false;
		if (strtoupper(PHP_OS) == 'LINUX' && preg_match('/ubuntu/i', php_uname())) {
			$package   = exec('which zip');
			$installed = $package ? true : false;
		}
		return $installed;
	}

	public static function download($file, $proxy = false, $name = 'download', $local = false) {
		$store = self::getStorage();

		if ($store->isLocal() OR $local) {
			
			$file  = $proxy ? Assets::getRealPath($file) : $file;
			$mime = self::getMimeType($file);
			
			if (file_exists($file)) {

				if ( ! ob_get_level()) ob_start();

				header('Set-Cookie: downloadtoken=finish; path=/');
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Cache-Control: public");
				header("Content-Description: File Transfer");
				header("Content-type: ".$mime);
				header("Content-Disposition: attachment; filename=\"".$name."\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ".filesize($file));

				if ($stream = fopen($file, 'r')) {
					while ( ! feof($stream)) {
						echo fread($stream, 2014);
						ob_flush();
						flush();
					}
					fclose($stream);
				}

				ob_end_clean();
			} else {
				throw new \Exception("File doesn't exists", 500);
			}
			
		} else {
			$store->download($file, $proxy, $name);
		}
	}

	public static function getMimeType($file) {
		
		$mimes = array(
			'hqx' => 'application/mac-binhex40',
			'cpt' => 'application/mac-compactpro',
			'csv' => array(
				'text/x-comma-separated-values',
				'text/comma-separated-values',
				'application/octet-stream',
				'application/vnd.ms-excel',
				'application/x-csv',
				'text/x-csv',
				'text/csv',
				'application/csv',
				'application/excel',
				'application/vnd.msexcel'
			) ,
			'bin' => 'application/macbinary',
			'dms' => 'application/octet-stream',
			'lha' => 'application/octet-stream',
			'lzh' => 'application/octet-stream',
			'exe' => array(
				'application/octet-stream',
				'application/x-msdownload'
			) ,
			'class' => 'application/octet-stream',
			'psd'   => 'application/x-photoshop',
			'so'    => 'application/octet-stream',
			'sea'   => 'application/octet-stream',
			'dll'   => 'application/octet-stream',
			'oda'   => 'application/oda',
			'pdf'   => array(
				'application/pdf',
				'application/x-download'
			) ,
			'ai'   => 'application/postscript',
			'eps'  => 'application/postscript',
			'ps'   => 'application/postscript',
			'smi'  => 'application/smil',
			'smil' => 'application/smil',
			'mif'  => 'application/vnd.mif',
			'xls'  => array(
				'application/excel',
				'application/vnd.ms-excel',
				'application/msexcel'
			) ,
			'ppt' => array(
				'application/powerpoint',
				'application/vnd.ms-powerpoint'
			) ,
			'wbxml' => 'application/wbxml',
			'wmlc'  => 'application/wmlc',
			'dcr'   => 'application/x-director',
			'dir'   => 'application/x-director',
			'dxr'   => 'application/x-director',
			'dvi'   => 'application/x-dvi',
			'gtar'  => 'application/x-gtar',
			'gz'    => 'application/x-gzip',
			'php'   => 'application/x-httpd-php',
			'php4'  => 'application/x-httpd-php',
			'php3'  => 'application/x-httpd-php',
			'phtml' => 'application/x-httpd-php',
			'phps'  => 'application/x-httpd-php-source',
			'js'    => 'application/x-javascript',
			'swf'   => 'application/x-shockwave-flash',
			'sit'   => 'application/x-stuffit',
			'tar'   => 'application/x-tar',
			'tgz' => array(
				'application/x-tar',
				'application/x-gzip-compressed'
			) ,
			'xhtml' => 'application/xhtml+xml',
			'xht'   => 'application/xhtml+xml',
			'zip'   => array(
				'application/x-zip',
				'application/zip',
				'application/x-zip-compressed'
			) ,
			'mid'  => 'audio/midi',
			'midi' => 'audio/midi',
			'mpga' => 'audio/mpeg',
			'mp2'  => 'audio/mpeg',
			'mp3'  => array(
				'audio/mpeg',
				'audio/mpg',
				'audio/mpeg3',
				'audio/mp3'
			) ,
			'aif'  => 'audio/x-aiff',
			'aiff' => 'audio/x-aiff',
			'aifc' => 'audio/x-aiff',
			'ram'  => 'audio/x-pn-realaudio',
			'rm'   => 'audio/x-pn-realaudio',
			'rpm'  => 'audio/x-pn-realaudio-plugin',
			'ra'   => 'audio/x-realaudio',
			'rv'   => 'video/vnd.rn-realvideo',
			'wav'  => array(
				'audio/x-wav',
				'audio/wave',
				'audio/wav'
			) ,
			'bmp' => array(
				'image/bmp',
				'image/x-windows-bmp'
			) ,
			'gif' => 'image/gif',
			'jpeg' => array(
				'image/jpeg',
				'image/pjpeg'
			) ,
			'jpg' => array(
				'image/jpeg',
				'image/pjpeg'
			) ,
			'jpe' => array(
				'image/jpeg',
				'image/pjpeg'
			) ,
			'png' => array(
				'image/png',
				'image/x-png'
			) ,
			'tiff'  => 'image/tiff',
			'tif'   => 'image/tiff',
			'css'   => 'text/css',
			'html'  => 'text/html',
			'htm'   => 'text/html',
			'shtml' => 'text/html',
			'txt'   => 'text/plain',
			'text'  => 'text/plain',
			'log'   => array(
				'text/plain',
				'text/x-log'
			) ,
			'rtx'   => 'text/richtext',
			'rtf'   => 'text/rtf',
			'xml'   => 'text/xml',
			'xsl'   => 'text/xml',
			'mpeg'  => 'video/mpeg',
			'mpg'   => 'video/mpeg',
			'mpe'   => 'video/mpeg',
			'qt'    => 'video/quicktime',
			'mov'   => 'video/quicktime',
			'mp4'	=> 'video/mp4',
			'avi'   => 'video/x-msvideo',
			'movie' => 'video/x-sgi-movie',
			'doc'   => 'application/msword',
			'docx'  => array(
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/zip'
			) ,
			'xlsx' => array(
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/zip'
			) ,
			'word' => array(
				'application/msword',
				'application/octet-stream'
			) ,
			'xl'   => 'application/excel',
			'eml'  => 'message/rfc822',
			'json' => array(
				'application/json',
				'text/json'
			) ,
			'woff' => 'application/x-font-woff',
			'eot'  => 'application/vnd.ms-fontobject',
			'ttf'  => 'font/ttf',
			'otf'  => 'font/otf'
		);
		
		$ext = strtolower(substr(strrchr($file, '.') , 1));

		if (array_key_exists($ext, $mimes)) {	
			if (is_array($mimes[$ext])) 
				return current($mimes[$ext]);
			else 
				return $mimes[$ext];
		}

		// check using finfo
		if (file_exists($file)) {
			$info = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($info, $file);
			finfo_close($info);	
		} else {
			$mime = 'application/octet-stream';
		}
		
		return $mime;
 		
	}

}
