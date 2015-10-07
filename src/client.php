<?php
namespace REST;

// Define Exception Class
if (!class_exists('\REST\Exception')) {
	class Exception extends \Exception { }
}

/**
 * A simple REST client to perform REST request
 *
 * @author Peter-Christoph Haider (Project Leader) et al.
 * @package REST
 * @version 1.7 (2010-08-08)
 * @copyright Copyright (c) 2009-2010, Peter-Christoph Haider
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
class Client {

	/**
	 * @var string
	 */
	private $user = null;

	/**
	 * @var string
	 */
	private $password = null;

	/**
	 * @var string
	 */
	private $url = null;

	/**
	 * @var array
	 */
	private $header = array();

	/**
	 * @var array
	 */
	private $responseHeaders = array();

	private $doValidateSSLCert = true;

	/**
	 * @var array
	 */
	private $methods = array(
		'GET'    => true,
		'POST'   => true,
		'PUT'    => true,
		'DELETE' => true,
	);

	/**
	 * @var string
	 */
	private $method = 'GET';

	public $protocol_version = false; // Use the default protocol version
	public $additionHttpContextParams = false;

	public function __construct($url = null, $user = null, $password = null) {
		$this->setURL($url);
		$this->setAuthentication($user, $password);
	}

	/* --------------- Setting functions --------------- */

	public function setAdditionalHttpContextParams(array $params) {
		$this->additionHttpContextParams = $params;
	}

	public function setURL($url) {
		$this->url = $url;
		return $this;
	}

	public function setAuthentication($user, $password) {
		$this->user = $user;
		$this->password = $password;
		return $this;
	}

	public function appendHeader($strHeader) {
		$this->header[] = preg_replace('#$#', '', $strHeader);
		return $this;
	}

	public function setHeaders(array $headers) {
		$this->header = $headers;
		return $this;
	}

	/**
	 * @deprecated since 2013-01-10
	 * @see setHeaders()
	 */
	public function setHeader(array $headers) {
		$this->header = $headers;
		return $this;
	}

	public function setMethod($method) {
		$method = strtoupper($method);
		if ( empty($this->methods[$method]) )
			return false;

		$this->method = $method;
		return true;
	}

	/* --------------- Getting functions --------------- */

	public function getURL() {
		return $this->url;
	}

	public function getHeader() {
		return implode("\r\n", $this->header);
	}

	/**
	 * @return array
	 */
	public function getResponseHeaders() {
		return $this->responseHeaders;
	}

	public function disableSSLCertValidation() {
		$this->doValidateSSLCert = false;
	}

	/* --------------- Request functions --------------- */

	public function request($params = null, $url = null, $method = null, $contenttype = 'text/plain', $user = null, $password = null, $raw = false) {
		$this->responseHeaders = array();

		// Initialize parameters
		$url = parse_url( $url === null ? $this->url : $url );

		$query = isset($url['query']) ? $url['query'] : null;

		$method = strtoupper( $method === null ? $this->method : $method );
		$user = isset($url['username']) ? $url['username'] : ( $user === null ? $this->user : $user );
		$password = isset($url['password']) ? $url['password'] : ( $password === null ? $this->password : $password );

		if ( ($user === null) || ($password === null) )
			$auth = false;
		else
			$auth = base64_encode($user.':'.$password);

		if ( $contenttype ) {
			// Only set content type if is given
			$this->appendHeader('Content-Type: '.$contenttype);
		}

		// Perform the request

		if ( empty($this->methods[$method]) )
			throw new Exception('Invalid HTTP method: '.$method);

		// In all other cases perform the Request using a stream
		if ( $auth )
			$this->appendHeader('Authorization: Basic '.$auth);

		// Add this to your script if you ever encounter an
		// "417 - Expectation Failed" error message.
		//$this->appendHeader('Expect:');

		$ctxHttpParams = [
			'method'  => $method,
			'ignore_errors' => true
		];

		if ( $this->protocol_version )
			$ctxHttpParams['protocol_version'] =$this->protocol_version;

		if ( $this->additionHttpContextParams )
			$ctxHttpParams = array_merge($ctxHttpParams, $this->additionHttpContextParams);

		$strUrl = $url['scheme'].'://'.$url['host'].( isset($url['port']) ? ':'.$url['port'] : '' );
		if ( isset($url['path']) )
			$strUrl .= $url['path'];

		$contentLength = 0;
		if ( empty($params) ) {
		} elseif ( $method === 'GET' ) {
			if ( is_array($params) )
				$query = ( $query === null ? '' : '&' ).http_build_query($params, null, '&');
			else
				$query = ( $query === null ? '' : '&' ).$params;

		} elseif ( strpos($contenttype, 'application/json') === 0 ) {
			$ctxHttpParams['content'] = (
				is_string($params)
				? $params
				: json_encode($params)
			);
			$contentLength = strlen($ctxHttpParams['content']);

		} elseif ( ($params !== null) and is_scalar($params) ) {
			if ( !$raw )
				throw new Exception('Encountered raw parameter data but raw flag was not set.');

			$ctxHttpParams['content'] =	$params;
			$contentLength = strlen($params);

		} else {
			$ctxHttpParams['content'] =	http_build_query($params, null, '&');
			$contentLength = strlen($ctxHttpParams['content']);
		}

		if ( $query )
			$strUrl .= '?'.$query;

		$this->appendHeader('Content-Length: '.$contentLength);

		$ctxHttpParams['header'] = $this->getHeader();

		if ( isset($url['fragment']) )
			$strUrl .= '#'.$url['fragment'];

		$arrContentOptions = ['http' => $ctxHttpParams];
		if ( $this->doValidateSSLCert === false ) {
			$arrContentOptions['ssl'] = [
				'verify_peer'      => false,
				'verify_peer_name' => false,
			];
		}

		$ctx = stream_context_create($arrContentOptions);
		$contents = file_get_contents($strUrl, false, $ctx);

		$this->responseHeaders = (
			isset($http_response_header)
			? $http_response_header
			: array()
		);

		return $contents;
	}

	/**
	 * Convenience method wrapping a commom POST call
	 */
	public function post($params = null, $url = null, $contenttype = 'application/x-www-form-urlencoded', $user = null, $password = null, $raw = false) {
		return $this->request($params, $url, 'POST', $contenttype, $user, $password, $raw);
	}

	/**
	 * Convenience method wrapping a commom PUT call
	 */
	public function put($params = null, $url = null, $contenttype = 'application/x-www-form-urlencoded', $user = null, $password = null, $raw = false) {
		return $this->request($params, $url, 'PUT', $contenttype, $user, $password, $raw);
	}

	/**
	 * Convenience method wrapping a commom GET call
	 */
	public function get($params = null, $url = null, $contenttype = null, $user = null, $password = null, $raw = false) {
		return $this->request($params, $url, 'GET', $contenttype, $user, $password, $raw);
	}

	/**
	 * Convenience method wrapping a commom DELETE call
	 */
	public function delete($params = null, $url = null, $contenttype = 'application/x-www-form-urlencoded', $user = null, $password = null, $raw = false) {
		return $this->request($params, $url, 'DELETE', $contenttype, $user, $password, $raw);
	}

	/**
	 * Initializes and checks a server result
	 *
	 * @param array $res
	 */
	public static function initResult($res) {
		if ( !is_array($res) )
			throw new Exception('Invalid datatype. Array expected!');
		elseif ( isset($res['error']) )
			throw new Exception('Server error: '.$res['error']);
		elseif ( !isset($res['result']) )
			throw new Exception('Server returned no result: '.$res);

		return $res['result'];
	}

}

?>
