<?php

/**
 * A simple REST client to perform REST request
 *
 * @author Peter-Christoph Haider (Project Leader) et al.
 * @package REST
 * @version 1.7 (2010-08-08)
 * @copyright Copyright (c) 2009-2010, Peter-Christoph Haider
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
class RESTclient {
	private $user = null;
	private $password = null;
	private $url = null;
	private $header = array();
	private $methods = array('GET', 'POST', 'PUT', 'DELETE');
	private $method = 'GET';

	public function __construct($url=null, $user=null, $password=null) {
		$this -> setURL($url);
		$this -> setAuthentication($user, $password);
	}

	/* --------------- Setting functions --------------- */

	public function setURL($url) {
		$this -> url = $url;
	}

	public function setAuthentication($user, $password) {
		$this -> user = $user;
		$this -> password = $password;
	}

	public function appendHeader($strHeader) {
		$this -> header[] = preg_replace('#$#', '', $strHeader);
	}

	public function setHeader($arrHeader) {
		if (is_array($arrHeader))
			$this -> header = $arrHeader;
		else
			throw new Exception('Invalid variable type. Expecting array.');
	}

	public function setMethod($method) {
		if (in_array($method = strtoupper($method), $this -> methods))
			$this -> method = $method;
		else
			return false;
		return true;
	}

	/* --------------- Getting functions --------------- */

	public function getURL() {
		return $this -> url;
	}

	public function getHeader() {
		return implode("\r\n", $this -> header);
	}

	/* --------------- Request functions --------------- */

	public function request($params=null, $url=null, $method=null, $contenttype='text/plain', $user=null, $password=null) {
		// Initialize parameters
		$url = isset($url) ? $url : $this -> url;
		$url = parse_url($url);
		$query = isset($url['query']) ? $url['query'] : null;
		if (is_array($params))
			$query = (is_null($query) ? '' : '&').http_build_query($params, null, '&');
		$method = strtoupper(is_null($method) ? $this -> method : $method);
		$user = isset($url['username']) ? $url['username'] : (is_null($user) ? $this -> user : $user);
		$password = isset($url['password']) ? $url['password'] : ( is_null($password) ? $this->password : $password );
		if (is_null($user) || is_null($password))
			$auth = false;
		else
			$auth = base64_encode($user.':'.$password);

		$this -> appendHeader('Content-Type: '.$contenttype);

		// Perform the request
		if (in_array($method, $this -> methods)) {
			if ($method == 'GET') {
				// Get requests do not require a stream
				return file_get_contents($url['scheme'].'://'.($auth == '' ? '' : $auth.'@').$url['host'].( isset($url['port']) ? ':'.$url['port'] : '' ).$url['path']
										.(isset($query) ? '?'.$query : '')
										.(isset($url['fragment']) ? '#'.$url['fragment'] : ''));
			} else {
				// In all other cases perform the Request using a stream
				if ($auth)
					$this -> appendHeader("Authorization: Basic $auth");
				$this->appendHeader('Content-Length: '.strlen($query));
				$ctx = stream_context_create(array('http' => array(
					'method' => $method,
					'header'=> $this -> getHeader(),
					'content' => $query
				)));
				return file_get_contents($url['scheme'].'://'.$url['host'].( isset($url['port']) ? ':'.$url['port'] : '' ).(isset($url['path']) ? $url['path'] : '').(isset($url['fragment']) ? '#'.$url['fragment'] : ''), false, $ctx);
			}
		} else
			throw new Exception('Invalid HTTP method: '.$method);
	}

     /**
      * Convenience method wrapping a commom POST call
      */
     public function post($params=null, $url=null, $contenttype='application/x-www-form-urlencoded', $user=null, $password=null) {
		return $this -> request($params, $url, 'POST', $contenttype, $user, $password);
     }

     /**
      * Convenience method wrapping a commom PUT call
      */
     public function put($params=null, $url=null, $contenttype=null, $user=null, $password=null) {
		return $this -> request($params, $url, 'PUT', $contenttype, $user, $password);
     }

     /**
      * Convenience method wrapping a commom GET call
      */
     public function get($params=null, $url=null, $contenttype=null, $user=null, $password=null) {
		return $this -> request($params, $url, 'GET', $contenttype, $user, $password);
     }

     /**
      * Convenience method wrapping a commom delete call
      */
     public function delete($params=null, $url=null, $contenttype=null, $user=null, $password=null) {
		return $this -> request($params, $url, 'DELETE', $contenttype, $user, $password);
     }
}

?>
