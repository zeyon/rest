<?php

if ( !defined('UPLOAD_ERR_NO_TMP_DIR') ) // Introduced in PHP 5.0.3
	define('UPLOAD_ERR_NO_TMP_DIR', 6);

if ( !defined('UPLOAD_ERR_CANT_WRITE') ) // Introduced in PHP 5.1.0
	define('UPLOAD_ERR_CANT_WRITE', 7);

if ( !defined('UPLOAD_ERR_EXTENSION') ) // Introduced in PHP 5.2.0
	define('UPLOAD_ERR_EXTENSION', 8);

/**
 * Instantiate this class and return the object to indicate that a web service function does not return any data to be JSON-encoded.
 *
 * @see RESTserver
 */
class RESTvoidResult {
}

/**
 * A simple REST server to handle REST requests
 *
 * When defining a REST server, it is crucial to specify each service properties with the $action variable.
 *
 * <code>
 *  class myAPI extends RESTserver {
 * 	public $actions = array(
 *		'auth' => array('POST', 'auth', array('username', 'password')),
 *		'orders_list' => array('GET', 'orders_list', array(
 *			array('filter', 'array', array(), false),
 *			array('sort', 'string', false),
 *			array('asc', 'bool', true)
 *		)),
 *		'project_list' => array('GET', 'project_list'),
 *		'project_details' => array('GET', 'project_details', array(
 *			array('ID', 'string', false)
 *		)),
 *		'project_remove' => array('POST', 'project_remove', array(
 *			array('ID', 'string', false)
 *		))
 *	);
 *
 * 	public function do_auth($user, $password) { ... }
 * 	...
 * }
 * </code>
 *
 * The action items have the following syntax:
 *
 * TASK_NAME:string => PARAMS:array[
 *    [NAME:string, TYPE:string, DEFAULT_VALUE:mixed, REQUIRED:bool]
 *    ...
 * ]
 *
 * @author Peter-Christoph Haider (Project Leader) et al.
 * @package REST
 * @version 1.7 (2010-08-08)
 * @copyright Copyright (c) 2009-2010, Peter-Christoph Haider
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
abstract class RESTserver {

	static private $uploadErrors = array(
		UPLOAD_ERR_OK         => 'Upload OK',
		UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the configured maximum allowed file size.',
		UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the maximum allowed file size specified in the HTML form.',
		UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
		UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
		UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
		UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
		UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
	);

	/**
	 * @var array An associative array mapping actions to their definitions.
	 *     Action format:    { <name> => [ <method>, <function>, <parameters> ] }
	 *       Methods:        POST, GET, REQUEST
	 *     Parameter format: [ <name>, <type>, <default> = '', <required> = true ]
	 *       Types:          int, float, bool, array, object, string
	 */
	public $actions = array();

	/** @var string The variable name specifying the command variable (sometimes "cmd" or "do") */
	private $cmdVar = 'do';

	/** @var string The prefix for the local method definition */
	public $prefix = 'do_';

	/** @var bool Return an error message ['error': bool, 'trace': string] instead of false */
	public $showerror = true;

	/** @var bool Also adds a trace to the error message (see $showerror) */
	public $showtrace = true;

	/**
	 * Initialize a variable value.
	 *
	 * @param array $var
	 * @param string $key
	 * @param string $type Variable type
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function initParam($var, $key, $type='string', $default='', $required=true) {
		if ( $type === 'file' ) {
			if ( empty($_FILES[$key]['tmp_name']) ) {
				if ( $required )
					throw new Exception('File "'.$key.'" not found!');
				else
					return null;
			} elseif ( isset($_FILES[$key]['error']) and ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) ) {
				$errorCode = $_FILES[$key]['error'];
				throw new Exception('Bad data encountered in upload "'.$key.'" ['.( isset($uploadErrors[$errorCode]) ? $uploadErrors[$errorCode] : 'error '.$errorCode ).']. Please try again.');
			} elseif ( !is_uploaded_file($_FILES[$key]['tmp_name']) or !is_readable($_FILES[$key]['tmp_name']) ) {
				throw new Exception('Error reading uploaded file "'.$key.'"!');
			}

			return $_FILES[$key];
		}

		if ( !isset($var[$key]) or !array_key_exists($key, $var) ) {
			if ( $required )
				throw new Exception('Parameter "'.$key.'" not found!');
			else
				return $default;
		}

		$value = $var[$key];
		if ( $value === null )
			return null;

		switch ( $type ) {
			case 'int':
				if ( is_numeric($value) )
					return (int)$value;
				else
					return ( $default === null ? null : (int)$default );

			case 'float':
				if ( is_numeric($value) )
					return (float)$value;
				else
					return ( $default === null ? null : (float)$default );

			case 'array':
				return is_array($value) ? $value : array();

			case 'bool':
				return (bool)$value;

			case 'object':
				return is_object($value) ? $value : null;

			case false:
				return $value;

		}

		return (string)$value;
	}

	/**
	 * Executes an API function, possibly throwing an exception on errors.
	 *
	 * @param string $command Optional. The API command to run. Default is NULL.
	 * @param array $source Optional. An associative array mapping parameter
	 *     names to their values. Default is NULL.
	 * @return mixed
	 * @see dispatch()
	 */
	public function rawDispatch($command = null, array $source = null) {
		// Check for valid commands
		if ( $command !== null )
			;
		elseif ( isset($_REQUEST[$this->cmdVar]) )
			$command = $_REQUEST[$this->cmdVar];
		else
			throw new Exception('No command specified!');

		if ( !isset($this->actions[$command]) )
			throw new Exception('Unknown command: '.$command);

		// array(COMMAND => array(METHOD[post, get, all], FUNCTION, PARAM1, PARAM2, ...))
		$functionSpec = $this->actions[$command];
		$method       = strtoupper(array_shift($functionSpec));
		$function     = array_shift($functionSpec);

		if ( $source === null ) {
			switch( $method ) {
				case 'GET':
					$source = $_GET;
					break;
				case 'POST':
					$source = $_POST;
					break;
			case 'PUT':
			case 'DELETE':
				parse_str(file_get_contents('php://input'), $source);
				break;
			default:
				$source = $_REQUEST;
				break;
			}
		}

		// Get the function parameters
		$parameters = array();
		if ( sizeof($functionSpec) > 0 ) {
			foreach ($functionSpec[0] as $param) {
				if ( !is_array($param) ) {
					$parameters[] = $this->initParam($source, $param);
				} else {
					$type    = ( (isset($param[1]) and array_key_exists(1, $param)) ? $param[1] : 'string' );
					$default = ( (isset($param[2]) and array_key_exists(2, $param)) ? $param[2] : null );
					$required = ( isset($param[3]) ? (bool)$param[3] : true );
					$parameters[] = $this->initParam($source, $param[0], $type, $default, $required);
				}
			}
		}
		$res = call_user_func_array(array($this, $this->prefix.$function), $parameters);

		// Check if the result should be wrapped into a JSON-encoded object
		// (by "runJSON()").
		if ( $res instanceof RESTvoidResult )
			return null;

		return (is_array($res) && (isset($res['result']) || isset($res['error']))) ? $res : array('result' => $res);
	}

	/**
	 * Converts an exception to result data suitable for output as JSON.
	 *
	 * @param Exception $e
	 * @return array|null Returns NULL if {@link showerror} is FALSE, otherwise
	 *     an array with an item "error", and an item "trace" if {@link showtrace}
	 *     is set. Can be overridden to return other data.
	 * @see handleException()
	 */
	protected function exceptionToResult(Exception $e) {
		if ( !$this->showerror )
			return null;
		elseif ( $this->showtrace )
			return array('error' => $e->getMessage(), 'trace' => $this->errorTrace($e));
		else
			return array('error' => $e->getMessage());
	}

	/**
	 * Handles an exception produced by {@link dispatch()}.
	 *
	 * This method may be overridden to e.g. rethrow the exception instead of
	 * converting it to result data.
	 *
	 * @param string $command Optional. The API command to run. Default is NULL.
	 * @param array $source Optional. An associative array mapping parameter
	 *     names to their values. Default is NULL.
	 * @return mixed Returns data obtained from {@link exceptionToResult()}.
	 * @see exceptionToResult()
	 */
	protected function handleException($command = null, array $source = null, Exception $e) {
		$standaloneCall = ( ($command === null) and ($source === null) );

		if ( !$standaloneCall and
			 class_exists('PermissionDeniedException') and
			 class_exists('HTTP') and
			 (get_class($e) === 'PermissionDeniedException') and
			 empty($_REQUEST['_no_http_code']) )
			HTTP::sendStatusCode(403, 'Authentication Required');

		return $this->exceptionToResult($e);
	}

	/**
	 * Executes an API function by calling {@link rawDispatch()}.
	 *
	 * If an exception was thrown during execution, {@link handleException()}
	 * will be called.
	 *
	 * @param string $command Optional. The API command to run. Default is NULL.
	 * @param array $source Optional. An associative array mapping parameter
	 *     names to their values. Default is NULL.
	 * @return mixed
	 * @uses rawDispatch()
	 * @uses handleException()
	 */
	public function dispatch($command = null, array $source = null) {
		try {
			return $this->rawDispatch($command, $source);
		} catch (Exception $e) {
			return $this->handleException($command, $source, $e);
		}
	}

	public function errorTrace($e) {
		$strTrace = '#0: '.$e->getMessage().'; File: '.$e->getFile().'; Line: '.$e->getLine()."\n";
		$i = 1;

		foreach ($e->getTrace() as $v) {
			if ( !(isset($v['function']) && $v['function'] == 'errorHandle') ) {
				if ( isset($v['class']) )
					$strTrace .= "#$i: ".$v['class'].$v['type'].$v['function'].'(';
				elseif ( isset($v['function']) )
					$strTrace .= "#$i: ".$v['function'].'(';
				else
					$strTrace .= "#$i: ";

				if ( isset($v['args']) && isset($v['function']) ) {
					$parts = array();
					foreach($v['args'] as $arg)
						$parts[] = $this->errorArg($arg);
					$strTrace .= implode(',', $parts).') ';
				}
				if ( isset($v['file']) && isset($v['line']) )
					$strTrace .= '; File: '.$v['file'].'; Line: '.$v['line']."\n";
				$i++;
			}
		}

		return $strTrace;
	}

	/**
	 * Converts any function arguement into a string
	 *
	 * @param mixed $arg
	 * @return string
	 */
	public function errorArg($arg, $depth=true) {
		if ( is_string($arg) ) {
			return('"'.str_replace("\n", '', $arg ).'"');
		} elseif ( is_bool($arg) ) {
			return $arg ? 'true' : 'false';
		} elseif ( is_object($arg) ) {
			return 'object('.get_class($arg).')';
		} elseif ( is_resource($arg) ) {
			return 'resource('.get_resource_type($arg).')';
		} elseif ( is_array($arg) ) {
			$parts = array();
			if ( $depth )
				foreach ($arg as $k => $v)
					$parts[] = $k.' => '.$this->errorArg($v, false);
			return 'array('.implode(', ', $parts).')';
		} elseif ( $depth ) {
			return var_export($arg, true);
		}
	}

	/**
	 * Dispatches the function call and returns the result as JSON string
	 *
	 * @return void
	 */
	public function runJSON() {
		$res = $this->dispatch();
		if ( $res != null ) {
			header('Content-Type: application/json');
			echo json_encode($res);
		}
	}

	/**
	 * Only runs a function, if a valid authentication token has been sent.
	 */
	public function run() {
		if ( !isset($_REQUEST[$this->cmdVar]) )
			throw new Exception('No task specified.');
		elseif ( !in_array($_REQUEST[$this->cmdVar], $this->auth_exceptions) && !$this->auth() )
			throw new Exception('Authentication required.');

		$this->runJSON();
	}

	/**
	 * The authentication method specifies if an API task may be executed or not.
	 * This method may be different in subordinate classes
	 *
	 * @return bool
	 */
	public function auth() {
		return true;
	}

}

?>
