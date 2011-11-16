<?php

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

	/** @var array The array containig the REST services */
	public $actions = array();
	/** @var string The variable name specifying the command variable (sometimes "cmd" or "do") */
	private $cmdVar = 'do';
	/** @var string The prefix for the local method definition */
	public $prefix = 'do_';
	/** @var bool Return an error message ['error': bool, 'trace': string] instead of false */
	public $showerror = true;
	/** @var bool Also adds a trace to the error message (see $showerror) */
	public $showtrace = true;

	protected $initParamFunction = null;

	public function __construct() {
		$this->initParamFunction = array($this, 'initParam');
	}

	/**
	 * Initialize a variable value
	 *
	 * @param mixed $value
	 * @param string $type Variable type
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function initParam($var, $key=null, $type='string', $default=null, $required=true) {
		if (is_null($key))
			$value = $var;
		else {
			if (!isset($var[$key]) and !array_key_exists($key, $var) )
				if ($required)
					throw new Exception('Parameter "'.$key.'" not found!');
				else
					return $this->initParam($default);

			$value = $var[$key];
			if ($value === null)
				return null;
		}

		switch ( $type ) {
    		case 'int':
				return (int) $value;

		    case 'float':
      			return (float) $value;

		    case 'array':
      			return is_array($value) ? $value : array();

		    case 'bool':
		    	return (bool)$value;

			case 'object':
      			return is_object($value) ? $value : null;
  		}

  		return (string)$value;
	}

	/**
	 * Performs the dispatch
	 */
    private function dispatch() {
        try {
			// Check for valid commands
			if (isset($_REQUEST[$this -> cmdVar]))
            	$command = $_REQUEST[$this -> cmdVar];
            else
            	throw new Exception('No command specified!');

            if (!isset($this -> actions[$command]))
            	throw new Exception('Unknown command: '.$command);

			// array(COMMAND => array(METHOD[post, get, all], FUNCTION, PARAM1, PARAM2, ...))
            $functionSpec = $this -> actions[$command];
            $method = strtoupper(array_shift($functionSpec));
            $function = array_shift($functionSpec);

            switch($method) {
            	case 'GET':
            		$source = $_GET;
            		break;
            	case 'POST':
            		$source = $_POST;
            		break;
            	default:
            		$source = $_REQUEST;
            		break;
            }

            // Get the function parameters
            $parameters = array();
            if ( sizeof($functionSpec) > 0 ) {
				foreach ($functionSpec[0] as $param) {
					if ( !is_array($param) ) {
						$parameters[] = call_user_func($this->initParamFunction, $source, $param);
					} else {
						$type    = ( (isset($param[1]) and array_key_exists(1, $param)) ? $param[1] : 'string' );
						$default = ( (isset($param[2]) and array_key_exists(2, $param)) ? $param[2] : null );
						$required = ( isset($param[3]) ? (bool)$param[3] : true );
						$parameters[] = call_user_func($this->initParamFunction, $source, $param[0], $type, $default, $required);
					}
				}
			}

            $res = call_user_func_array(array($this, $this -> prefix.$function), $parameters);

			// Check if the result should be wrapped into a JSON-encoded object
			// (by "runJSON()").
			if ( $res instanceof RESTvoidResult )
				return null;

           	return (is_array($res) && (isset($res['result']) || isset($res['error']))) ? $res : array('result' => $res);
        } catch (Exception $e) {
        	return $this -> showerror ? ($this -> showtrace ? array('error' => $e -> getMessage(), 'trace' => errorTrace($e)) : array('error' => $e -> getMessage())) :  null;
        }
    }

    /**
     * Dispatches the function call and returns the result as JSON string
     *
     * @return void
     */
    public function runJSON() {
    	$res = $this -> dispatch();
		if ($res != null) {
			header('Content-Type: application/json');
        	echo json_encode($res);
		}
    }

	/**
	 * Only runs a function, if a valid authentication token has been sent.
	 */
	public function run() {
		if (!isset($_REQUEST[$this -> cmdVar]))
			throw new Exception('No task specified.');

		if (!in_array($_REQUEST[$this -> cmdVar], $this -> auth_exceptions) && !$this -> auth())
			throw new Exception('Authentication required.');

		$this -> runJSON();
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

abstract class RESTserver2 extends RESTserver {

	public function __construct() {
		$this->initParamFunction = array($this, 'initParam2');
	}

	/**
	 * Initialize a variable value.
	 *
	 * Works like {RESTserver::initParam()} but does not cast NULL values.
	 *
	 * @param mixed $value
	 * @param string $type Variable type
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function initParam2($var, $key, $type='string', $default='', $required=true) {
		if ( !isset($var[$key]) and !array_key_exists($key, $var) )
			if ($required)
				throw new Exception('Parameter "'.$key.'" not found!');
			else
				return $default;

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

  		}

  		return (string)$value;
	}

}

?>
