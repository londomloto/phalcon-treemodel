<?php

namespace Cores;

class Response extends \Phalcon\Http\Response {

	/**
	 * Fixup based on our baseuri
	 *
	 * @param  [type] $location         [description]
	 * @param  [type] $externalRedirect [description]
	 * @param  [type] $statusCode       [description]
	 *
	 * @return [type]                   [description]
	 */
	/*public function redirect($location = NULL, $externalRedirect = NULL, $statusCode = NULL) {
		$url = $this->getDI()->get('url');
		$location = $url->get($location);
		return parent::redirect($location, $externalRedirect, $statusCode);
	}*/

}