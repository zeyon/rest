<?php

/**
 * Records and validates the data from a web-form
 *
 * @author Peter-Christoph Haider (Project Leader) et al. <peter@haider.ag>
 * @package REST
 * @version 1.7 (2010-08-08)
 * @copyright Copyright (c) 2009-2010, Peter-Christoph Haider
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

class RESTrecord {
	// ============== Object's core attributes ==============
	/** @var bool Transfer status of the form */
	private $bolSuccess = false;
	/** @var string Name of the form */
	private $strFormname = 'unknown';
	/** @var array Array containing the field values */
	public $arrData = array();
	/** @var array Array containing the validator definitions */
	public $arrFilters = array();
	/** @var array Filters to apply to the the original data array */
	private $arrExclude = array('form', 'service', 'PHPSESSID', 'COOKIE_SUPPORT', 'SCREEN_NAME', 'GUEST_LANGUAGE_ID', 'LOGIN');
	/** @var bool Also check the MX record of the mail address's domain */
	private $bolCheckDNS = 0;
	/** @var array Array containing all fields which didn't pass the validation */
	private $arrErrors = array();
	/** @var string Last server response */
	private $strAnswer = false;
	/** @var mixed Evaluated server response */
	private $mxtResponse = null;
	/** @var mixed Expected response value */
	private $mxtResponseValue = null;
	/** @var bool Unserialize server response */
	private $bolResponseUnserialize = false;
	/** @var bool|string If response is an array, check a certain array value */
	private $mxtResponseIsArray = false;

	// ============== Object's core function ==============

	public function __construct($data=array()) {
		$this -> setData($data);
	}

	/**
	 * Sends the form data to a remote URL
	 *
	 * @param string $strURL
	 * @param array $arrArguements
	 * @param string $strMethod
	 * @return string Server response
	 */
	public function send($strURL, $arrArguements=array(), $strMethod='POST', $user=null, $password=null) {
		if (sizeof($this -> arrErrors) == 0) {
			$rest = new RESTclient();
			$this -> strAnswer = $rest -> request(array_merge($arrArguements, $this -> arrData), $strURL, $strMethod, $user, $password);
		}
		return $this -> strAnswer;
	}

	/**
	 * Returns the array containing all validated fields; Returns false, if validation fails
	 *
	 * @return array
	 */
	public function getData() {
		return $this -> arrData;
	}

	/**
	 * Returns all validation errors
	 *
	 * @return array
	 */
	public function getErrors() {
		return $this -> arrErrors;
	}

	/**
	 * Sets the form data
	 *
	 * @param array $data
	 * @return void
	 */
	public function setData($data=array()) {
		$this -> arrData = array();
		foreach ($data as $field => $value) {
			if (!in_array($field, $this -> arrExclude) && $value != '' && $value != 'undefined' && $value != '-')
				$this -> arrData[$field] = $value;
		}
	}

	/**
	 * Sets a single data field
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setDataField($name, $value) {
		$this -> arrData[$name] = $value;
	}

	/**
	 * Appends an additional record field
	 *
	 * @param string $name Name of the field
	 * @param string $type Type of the Filter
	 * @param string|bool Filter function
	 * @return void
	 */
	public function addFilter($name, $type='string', $validator=null) {
		$this -> arrFilters[$name] = array($type, $validator);
	}


	/* ------------------------ Validation functions ------------------------ */

	/**
	 * Filters the local form data and initializes field types
	 *
	 * @return bool
	 */
	public function filter() {
		$arrData = array();
		$arrErrors = array();

		foreach ($this -> arrFilters as $strField => $arrFilter) {
			$mxtValue = $this -> initValue($this -> arrData[$strField], $arrFilter[0]);
			$arrData[$strField] = $mxtValue;
			if (isset($arrFilter[1]) && !is_null($arrFilter[1])) {
				if (!isset($this -> arrData[$strField]) || !$this -> validateValue($mxtValue, $arrFilter[1]))
					$arrErrors[] = $strField;
			}
		}

		// Update local fields and errors
		$this -> arrErrors = $arrErrors;
		$this -> arrFields = $arrFields;

		return (sizeof($this -> arrErrors) == 0);
	}

	/**
	 * Validates the value of a field
	 *
	 * The following validation functions are available:
	 * 	- true:		Simply checks, if a value is set
	 *  - false:	Checks, if no value is set
	 *  - <N:		Checks, if the value is smaller than N (for numeric values) or if the length of the string is smaller than N
	 *  - >N:		Checks, if the value is higher than N (for numeric values) or if the length of the string is higher than N
	 *  - @:		Validate an email address
	 *  - STRING:	Checks, if the value equals the string
	 *
	 * @param string $value Name of the field
	 * @param string $function Validation function
	 * @return bool
	 */
	public function validateValue($value, $function) {
  		if ($function === true)
  			return $value ? true : false;
  		elseif ($function === false)
  			return $value ? false : true;
  		elseif ($function == '@')
  			return $this -> validateEmail($value);

  		if (!is_numeric($value))
  			$value = strlen($value);

  		if (substr($function, 0, 1) == '<' || substr($function, 0, 1) == '>') {
  			$check = (int) substr($function, 1);
  			if (substr($function, 0, 1) == '<')
  				return $value < $check;
  			elseif (substr($function, 0, 1) == '>')
  				return $value > $check;
  		} else
  			return $value == $function;
	}

	/**
	 * Validates an Email-Address
	 *
	 * Based on the work of Douglas Lovell (http://www.linuxjournal.com/article/9585)
	 *
	 * @param string $email
	 * @return bool
	 */
	public function validateEmail($email) {
		if ($atIndex = strrpos($email, "@")) {
			$domain = substr($email, $atIndex+1);
			$local = substr($email, 0, $atIndex);
			$localLen = strlen($local);
			$domainLen = strlen($domain);
			if ($localLen < 1 || $localLen > 64) {
				// local part length exceeded
				return false;
			} else if ($domainLen < 1 || $domainLen > 255) {
				// domain part length exceeded
				return false;
			} else if ($local[0] == '.' || $local[$localLen-1] == '.') {
				// local part starts or ends with '.'
				return false;
			} else if (preg_match('/\\.\\./', $local)) {
				// local part has two consecutive dots
				return false;
			} else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
				// character not valid in domain part
				return false;
			} else if (preg_match('/\\.\\./', $domain)) {
				// domain part has two consecutive dots
				return false;
			} else if(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
				// character not valid in local part unless
				// local part is quoted
				if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local)))
					return false;
			}
			// Check, if a DNS record is set for the domain (default: Off)
			if ($this -> bolCheckDNS && $isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
				return false;
		} else
			return false;
		return true;
	}

	/**
	 * Switch the checkDNS property on and off
	 *
	 * @param bool $bolOn
	 * @return void
	 */
	public function setCheckDNS($bolOn) {
		$this -> bolCheckDNS = (bool) $bolOn;
	}

	/**
	 * Initialize a variable value
	 *
	 * @param mixed $value
	 * @param string $type Variable type
	 * @return mixed
	 */
	private function initValue($value, $type='string') {
		switch ($type) {
    		case 'int':
      			return is_numeric($value) ? (int) $value : (int) $value;
		    case 'float':
      			returnis_numeric($value) ? (float) $value : (float) $value;
		    case 'array':
      			return is_array($value) ? $value : array();
		    case 'bool':
		    	return is_bool($value) ? $value : (bool) $value;
			case 'object':
      			return is_object($value) ? $value : null;
  		}
  		return (string) $value;
	}

	/* ------------------------ Logging functions ------------------------ */

	/**
	 * Encodes special XML characters
	 *
	 * @param string $strRaw
	 * @return string
	 */
	public function xmlChars($strRaw) {
		$arrRaw = array('&', '<', '>', '"', '\'', 'Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß');
		$arrEncoded = array('&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&#196;', '&#214;', '&#220;', '&#228;', '&#246;', '&#252;', '&#223;');
		return str_replace($arrRaw, $arrEncoded, $strRaw);
	}

	/**
	 * Creates an XML string of the transferred record
	 *
	 * @return string
	 */
	public function toXML() {
		$strRecord  = '<record time="'.time().'" >'."\n";
		foreach ($this -> arrFields as $field => $value)
			$strRecord .= "\t".'<param id="'.$field.'">'.$this -> xmlChars($value).'</param>'."\n";
		$strRecord .= '</record>'."\n";
		return $strRecord;
	}

	/**
	 * Writes the form content to an XML file
	 *
	 * @param string $strFile name of the log-file
	 * @return void
	 */
	public function writeRecord($strFile) {
		if ($strFile && is_writable($strFile)) {
			$fh = fopen($strFile, 'a');
			$bolStatus = fwrite($fh, $strText);
			fclose($fh);
			return $bolStatus;
		}
		return false;
	}
}


?>
