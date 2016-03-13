<?php

namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/**
 *	ErrorController
 *
 * 	Digunakan untuk melakukan kontrol terhadap error
 * 	yang terjadi disistem (framework)
 *
 *
 *	@category		PHP Framework
 *	@package		Cores
 *	@author			Apri L. Pardede <apri@kct.co.id>
 *	@author			Roso Sasongko <roso@kct.co.id>
 *	@copyright		2014 - Kreasindo Cipta Teknologi
 *	@version		1.3.1
 *	@license		Public
 *	@link			http://www.kct.co.id/
 */

use Phalcon\Dispatcher as PhalconDispatcher,
	Phalcon\Mvc\Application\Exception as ApplicationException,
	Phalcon\Mvc\Dispatcher\Exception as DispatchException,
	Phalcon\Db\Exception as DBException;

class ErrorControl
{

	/**
	 * HTTP ERROR STATUS
	 */
	const HTTP_BAD_REQUEST     = 400;
	const HTTP_UNAUTHORIZED    = 401;
	const HTTP_FORBIDDEN       = 403;
	const HTTP_NOT_FOUND       = 404;

	const HTTP_SERVER_ERROR    = 500;
	const HTTP_BAD_GATEWAY     = 502;
	const HTTP_GATEWAY_TIMEOUT = 504;


	private $_di;
	private $_uri = 'apperror';

	public function __construct($di) {
		$this->_di = $di;
	}

	public function getDI() {
		return $this->_di;
	}

	public function getUri() {
		$uri = $this->getDI()->get('url')->get($this->_uri);
		$uri = preg_replace('/\/$/', '', $uri).'/';
		return $uri;
	}

	public function logError()
	{
		// [ON GOING BY xALPx] DONT DISTURB!!
		if ($this->_di->has('db'))
		{
			$db = $this->_di->get('db');
			$result = $db->query('SELECT * FROM sys_log_error');

			while($tmp = $result->fetchArray()){
				print_r($tmp);
			}

			exit;
		}
	}

	public function logException(){}

	public function handleError($code, $message, $file, $line) {
		if (ob_get_level()) ob_end_clean();
		if (0 === error_reporting())
			return false;

		throw new \ErrorException($message, 0, $code, $file, $line);
	}

	public function handleShutdown()
	{
		if (ob_get_level()) ob_end_clean();

		$error = error_get_last();
		$response = $this->getDI()->get('response');

		switch ($error['type'])
		{
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
			// case E_CORE_WARNING:
			// case E_COMPILE_WARNING:
			// case E_PARSE:
			// 
				$baseUri = $this->getUri();
				$title   = 'ERROR';
				
				$message = $error['message'];	
				$code    = $error['type'];
				$file    = $error['file'];
				$line    = $error['line'];
				$trace   = null;

				if ( ! ob_get_level()) ob_start();
				include_once BASEPATH.'/Srorrekct/ErrorPhp.php';
				$content = ob_get_contents();

				if ($response->isSent()) {
					$response
						->setStatusCode(500, null)
						->sendHeaders();
					ob_end_flush();
				} else {
					ob_end_clean();	
					$response
						->setStatusCode(500, null)
						->sendHeaders()
						->setContent($content)
						->send();
				}

			break;
		}

	}

	public function handleException($e)
	{
		// $this->logError();

		if (ob_get_level()) ob_end_clean();

		$di = $this->getDI();

		$baseUri  = $this->getUri();
		$request  = $di->get('request');
		$response = $di->get('response');
		$isAjax   = $request->isAjax();

		// GENERAL
		$title    = get_class($e);
		$message  = preg_replace('/(.*\])?/', '', $e->getMessage());
		$code     = $e->getCode();
		$file     = $e->getFile();
		$line     = $e->getLine();
		$include  = 'ErrorPhp.php';
		$trace    = $e->getTrace();
		
		switch(true)
		{

			case $e instanceof \ErrorException:
				$code  = $e->getCode();
				$title = 'Language Error';
			break;
			// PDO Exception
			case $e instanceof \PDOException:
				$code  = 505;
				$title = 'Database Error';
			break;

			// 401 UNAUTHORIZED
			case $e->getCode() == ErrorControl::HTTP_UNAUTHORIZED:
				$code  = 401;
				$title = '401 - Unauthorized';
			break;

			// 403 FORBIDDEN
			case $e->getCode() == 403:
				$code  = 403;
				$title = '403 - Forbidden';
			break;

			// 404 NOT FOUND
			case $e->getCode() == 404:
			case $e->getCode() == PhalconDispatcher::EXCEPTION_HANDLER_NOT_FOUND:
			case $e->getCode() == PhalconDispatcher::EXCEPTION_ACTION_NOT_FOUND:
			case $e instanceof \Phalcon\Mvc\View\Exception:

				$code  = 404;
				$title = '404 - Not Found';

				if (ENVIRONMENT !== 'development') {
					$message = 'The page you requested was not found';
					$include = 'Error404.php';
				}

			break;

			// 500 INTERNAL SERVER ERROR
			case $e->getCode() == 500:
				$code  = 500;
				$title = '500 - Internal Server Error';
			break;

		}
		
		// secure PHP as native language
		// $file = substr($file, 0, strrpos($file, '.php'));

		if ($isAjax) {
			// $code = $request->isDelete() ? $code : 200;
			$trace = array_map(
				function($item){ 
					$file = preg_replace('/(\/var\/www\/html)/', '..', $item['file']);
					$line = $item['line'];
					return "$file (line: {$line})";
				}, 
				array_filter(
					$trace, 
					function($item){ 
						if (isset($item['file'], $item['line'])) 
							return $item; 
					}
				)
			);

			$response
				->resetHeaders()
				->setStatusCode($code, null)
				->setContentType('application/json', 'UTF-8')
				->setContent(json_encode(array(
					'status'  => $code,
					'message' => $message,
					'file'    => preg_replace('/(\/var\/www\/html)/', '..', $file),
					'line'    => $line,
					'trace'   => $trace,
					'action'  => null
				)))
				->send();
		} else {

			if ( ! ob_get_level()) ob_start();

			include_once BASEPATH.'/Srorrekct/'.$include;
			$content = ob_get_contents();
				
			$response
				->resetHeaders()
				->setStatusCode($code, null);

			if ($response->isSent()) {
				$response->sendHeaders();
				ob_end_flush();
			} else {
				ob_end_clean();
				$response
					->setContent($content)
					->send();
			}

		}

		
	}

}
