<?php

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
	/** @var bool Return an error message ['error': bool, 'message': string] instead of false */
	public $showerror = true;
	
	public function __construct() {}
	
	/**
	 * Initialize a variable value
	 * 
	 * @param mixed $value
	 * @param string $type Variable type
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function initParam($var, $key, $type='string', $default='', $required=true) {
		if (!isset($var[$key]))
			if ($required)
				throw new Exception('Paramter "'.$key.'" not found!');
			else
				return $default;
				
		$value = $var[$key];			
		switch ($type) {
    		case 'int':
      			return is_numeric($value) ? (int) $value : (int) $default;
		    case 'float':
      			returnis_numeric($value) ? (float) $value : (float) $default;
		    case 'array':
      			return is_array($value) ? $value : array();
		    case 'bool':
		    	return (bool) $value;
			case 'object':
      			return is_object($value) ? $value : null;
  		}
  		return $value === '' ? (string) $default : (string) $value;
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
            if (sizeof($functionSpec) > 0)
				foreach ($functionSpec[0] as $param) {
					if (is_array($param))
						$parameters[] = $this -> initParam($source, $param[0], isset($param[1]) ? $param[1] : 'string', isset($param[2]) ? $param[2] : false, isset($param[3]) ? (bool) $param[3] : true);
					else
						$parameters[] = $this -> initParam($source, $param);
				}
				
            $res = call_user_func_array(array($this, $this -> prefix.$function), $parameters);
           	return (is_array($res) && (isset($res['result']) || isset($res['error']))) ? $res : array('result' => $res);
        } catch (Exception $e) {
        	return $this -> showerror ? array('error' => $e -> getMessage(), 'trace' => errorTrace($e)) :  null;
        }
    }
    
    public function runJSON() {
    	$res = $this -> dispatch();
		if ($res != null) {
			header('Content-Type: application/json');
        	echo json_encode($res);
		}
    }
}


?>