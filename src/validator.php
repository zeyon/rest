<?php
namespace REST;

// Additional filter definitions
define('FILTER_VALIDATE_PASSWORD',         21);
define('FILTER_VALIDATE_USERNAME',         22);
define('FILTER_VALIDATE_USERNAME_CHARS', 1022);
define('FILTER_VALIDATE_IPV4',             23);
define('FILTER_VALIDATE_IPV6',             24);
define('FILTER_VALIDATE_URLPATH',          25);
define('FILTER_VALIDATE_URLQUERY',         26);
define('FILTER_VALIDATE_MIN_LENGTH',       27);
define('FILTER_VALIDATE_MAX_LENGTH',       28);
define('FILTER_VALIDATE_LENGTH',           29);
define('FILTER_VALIDATE_DATE_RANGE',       30);
define('FILTER_VALIDATE_DATE_START_END',   31);
define('FILTER_VALIDATE_RESOURCE',         32);
define('FILTER_VALIDATE_INTZERO',          33);
define('FILTER_VALIDATE_IDENTIFIER',       34);
define('FILTER_VALIDATE_LANGCODE',         35);
define('FILTER_VALIDATE_REQUIRED',         36);

/**
 * Utility class to validate form input data
 *
 * @author Peter-Christoph Haider (Project Leader) et al.
 * @version 1.1 (2012-04-22)
 * @package REST
 * @copyright Copyright (c) 2012, Zeyon GmbH & Co. KG
 */
class Validator {
	/** @var Localizer */
	private $locale;

	/** @var array Additional filter definitions */
	private $filters = array(
		/**
		 * Filter for valid passwords:
		 *  - Must be at least 5 characters,
		 *  - Must contain at least one one lower case letter, one upper case letter, one digit
		 */
		FILTER_VALIDATE_PASSWORD => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^.*(?=.{5,})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/'),
		),
		FILTER_VALIDATE_USERNAME => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^[A-Za-z0-9._-]{5,}$/'),
		),
		FILTER_VALIDATE_USERNAME_CHARS => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^[A-Za-z0-9._-]+$/'),
		),
		FILTER_VALIDATE_IDENTIFIER => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^([a-zA-Z_$][a-zA-Z\d_$]*\.)*[a-zA-Z_$][a-zA-Z\d_$]*$/'),
		),
		FILTER_VALIDATE_RESOURCE => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^[A-Za-z0-9._-]{2,}$/'),
		),
		FILTER_VALIDATE_LANGCODE => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^[a-z]{2}_[A-Z]{2}$/'),
		),
		FILTER_VALIDATE_BOOLEAN => array(
			'filter'  => FILTER_VALIDATE_BOOLEAN,
			'flags'   => FILTER_NULL_ON_FAILURE,
		),
		FILTER_VALIDATE_IPV4 => array(
			'filter'  => FILTER_VALIDATE_IP,
			'flags'   => FILTER_FLAG_IPV4,
		),
		FILTER_VALIDATE_IPV6 => array(
			'filter'  => FILTER_VALIDATE_IP,
			'flags'   => FILTER_FLAG_IPV6,
		),
		FILTER_VALIDATE_URLPATH => array(
			'filter'  => FILTER_VALIDATE_URL,
			'flags'   => FILTER_FLAG_PATH_REQUIRED,
		),
		FILTER_VALIDATE_URLQUERY => array(
			'filter'  => FILTER_VALIDATE_URL,
			'flags'   => FILTER_FLAG_QUERY_REQUIRED,
		),
		FILTER_VALIDATE_REQUIRED => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('regexp' => '/^\S+$/'),
		),
	);
	private $callbacks = array(
		FILTER_VALIDATE_MIN_LENGTH     => array('\\REST\\Validator', 'filter_min_length'),
		FILTER_VALIDATE_MAX_LENGTH     => array('\\REST\\Validator', 'filter_max_length'),
		FILTER_VALIDATE_LENGTH         => array('\\REST\\Validator', 'filter_length'),
		FILTER_VALIDATE_DATE_RANGE     => array('\\REST\\Validator', 'filter_date_range'),
		FILTER_VALIDATE_DATE_START_END => array('\\REST\\Validator', 'filter_date_start_end'),
		FILTER_VALIDATE_INTZERO        => array('\\REST\\Validator', 'filter_int_zero'),
	);

	private $filterKeys = array(
		'PASSWORD'       => FILTER_VALIDATE_PASSWORD,
		'USERNAME'       => FILTER_VALIDATE_USERNAME,
		'USERNAME_CHARS' => FILTER_VALIDATE_USERNAME_CHARS,
		'IPV4'           => FILTER_VALIDATE_IPV4,
		'IPV6'           => FILTER_VALIDATE_IPV6,
		'URLPATH'        => FILTER_VALIDATE_URLPATH,
		'URLQUERY'       => FILTER_VALIDATE_URLQUERY,
		'MIN_LENGTH'     => FILTER_VALIDATE_MIN_LENGTH,
		'MAX_LENGTH'     => FILTER_VALIDATE_MAX_LENGTH,
		'LENGTH'         => FILTER_VALIDATE_LENGTH,
		'DATE_RANGE'     => FILTER_VALIDATE_DATE_RANGE,
		'DATE_START_END' => FILTER_VALIDATE_DATE_START_END,
		'RESOURCE'       => FILTER_VALIDATE_RESOURCE,
		'INTZERO'        => FILTER_VALIDATE_INTZERO,
		'BOOLEAN'        => FILTER_VALIDATE_BOOLEAN,
		'INT'            => FILTER_VALIDATE_INT,
		'FLOAT'          => FILTER_VALIDATE_FLOAT,
		'IDENTIFIER'     => FILTER_VALIDATE_IDENTIFIER,
		'LANGCODE'       => FILTER_VALIDATE_LANGCODE,
		'REQUIRED'       => FILTER_VALIDATE_REQUIRED,
		'IP'             => FILTER_VALIDATE_IP,
		'URL'            => FILTER_VALIDATE_URL,
		'EMAIL'          => FILTER_VALIDATE_EMAIL,
		'REGEXP'         => FILTER_VALIDATE_REGEXP,
	);

	private $typesToFilter = array(
		'integer' => array(
			'filter' => FILTER_VALIDATE_INT
		),
		'float' => array(
			'filter' => FILTER_VALIDATE_FLOAT
		),
	);

	public function __construct($locale=false) {
		$this->locale = $locale ? $locale : Localizer::getInstance();
	}

	/**
	 * Returns a Validator object
	 *
	 * @return Validator
	 */
	public static function create($locale=false) {
		return new self($locale);
	}

	static function filter_int_zero($filter) {
		return (bool) preg_match('/^[0-9]+$/', $filter['value']);
	}

	static function filter_min_length($filter) {
		return isset($filter['value']) && is_string($filter['value']) && strlen($filter['value']) >= (isset($filter['len']) ? $filter['len'] : 0);
	}

	static function filter_max_length($filter) {
		return isset($filter['value']) && is_string($filter['value']) && strlen($filter['value']) <= (isset($filter['len']) ? $filter['len'] : 0);
	}

	static function filter_length($filter) {
		if (isset($filter['value']) && is_string($filter['value'])) {
			$len = strlen($filter['value']);
			return (isset($filter['min']) && $len < $filter['min']) || (isset($filter['max']) && $len > $filter['max']) ? false : true;
		}

		return false;
	}

	static function filter_date_start_end($filter) {
		if (isset($filter['value']) && is_int($filter['value']))
			return isset($filter['end']) && $filter['value'] > $filter['end'] ? false : true;

		return false;
	}

	static function filter_date_range($filter) {
		if (isset($filter['value']) && is_int($filter['value']))
			return (isset($filter['min']) && $filter['value'] < $filter['min']) || (isset($filter['max']) && $filter['value'] > $filter['max']) ? false : true;

		return false;
	}

	/**
	 * Return array of filters created out of the types of the fields.
	 * If the field already has any filter defined nothing will happen.
	 *
	 * @param array $arrFields array of fields.
	 * @return array
	 */
	public function fieldTypesToFilter($arrFields) {
		foreach ($arrFields as $key => &$properties) {
			if (isset($properties['filter']) ||
				!isset($properties['type']) ||
				!isset($this->typesToFilter[$properties['type']]) )
				continue;

			$properties = $properties + $this->typesToFilter[$properties['type']];
		}

		return $arrFields;
	}

	/**
	 * Filters an array of values
	 * By default, this function performs the same operation as filterArray(), but this way it can be modified and replaced by
	 * subsequent classes, e.g. to output custom warnings and error messages
	 *
	 * @param array $arrData Input data
	 * @param array $arrFilters Filter definition
	 * @param bool $bolStrict Checks if all filter values exist in the data array (Default: true)
	 * @return array
	 */
	public function filter($arrData, $arrFilters, $bolStrict=true) {
		if (!$bolStrict) {
			foreach ($arrFilters as $key => $filter) {
				if (!isset($arrData[$key]))
					unset($arrFilters[$key]);
			}
		}

		// Resolve the filter keys in case the caller didn't use costants
		foreach ($arrFilters as $key => $filter) {
			if (isset($filter['filter']) && isset($this->filterKeys[strtoupper($filter['filter'])]))
				$arrFilters[$key]['filter'] = $this->filterKeys[strtoupper($filter['filter'])];
		}
		return $this->filterArray($arrData, $arrFilters);
	}

	/**
	 * Filters an array of values and returns an array with error messages
	 *
	 * @param array $arrData Input data
	 * @param array $arrFilters Filter definition
	 * @param bool $bolStrict Checks if all filter values exist in the data array (Default: true)
	 * @return array
	 */
	public function filterErrors($arrData, $arrFilters, $bolStrict=true) {
		return $this->getErrors($this->filter($arrData, $arrFilters, $bolStrict));
	}

	/**
	 * Filters an array of values
	 *
	 * @param array $arrData Input data
	 * @param array $arrFilters Filter definition
	 * @return array
	 */
	private function filterArray($arrData, $arrFilters) {
		// Normalize the filter array (['filter' => FILTER, ...])
		foreach ($arrFilters as $key => &$F) {
			if (is_int($F))
				$F = array('filter' => $F);

			elseif (!isset($F['filter'])) {
				unset($arrFilters[$key]);
				continue;

			} elseif (is_string($F['filter'])) {
				try {
					$intF = @constant($F['filter']);
				} catch (\Exception $e) {
					continue;
				}
				if ( $intF )
					$F['filter'] = $intF;
				else {
					continue;

				}
			} elseif (isset($F['required'])) {
				if (!$F['required'] && (string)$arrData[$key] == '') {
					unset($arrFilters[$key]);
					continue;
				}
			}

			$F['value'] = isset($arrData[$key]) ? $arrData[$key] : null;
			if (!isset($F['field']))
				$F['field'] = $key;
		}

		$arrCallbacks = array();
		$arrValidators = array();

		// Remove callback functions from the filter array and allocate them to the callback array
		foreach ($arrFilters as $key => $filter) {
			if (isset($this->filters[$filter['filter']])) {
				$arrValidators[$key] = $filter + $this->filters[$filter['filter']];
				$arrValidators[$key]['filter'] = $this->filters[$filter['filter']]['filter'];
			} elseif (isset($this->callbacks[$filter['filter']]))
				$arrCallbacks[$key] = $filter;
			else
				$arrValidators[$key] = $filter;
		}

		$validate = array();

		// Validate the callbacks
		foreach ($arrCallbacks as $key => $filter)
			$validate[$key] = call_user_func($this->callbacks[$filter['filter']], $filter);

		// Validate through PHP filter function
		$validate = $validate + filter_var_array($arrData, $arrValidators);

		// Check the results
		$res = array();
		foreach ($arrFilters as $key => $filter) {
			$result = isset($validate[$key]) ? $validate[$key] : null;
			if (is_null($result) || ($filter != FILTER_VALIDATE_BOOLEAN && (bool) $result == false))
				$res[$key] = $filter;
		}

		return $res;
	}

	/**
	 * Returns the error messages
	 *
	 * @param array $arrErrors
	 */
	public function getErrors($arrErrors) {
		$res = array();
		foreach ($arrErrors as $key => $error)
			$res[$key] = $this->getError($error);

		return $res;
	}

	/**
	 * Returns a single error message for a validator
	 *
	 * @param int|array $error
	 * @param array $opts
	 */
	public function getError($error, $opts=array()) {
		if (is_array($error) && isset($error['filter'])) {
			return $this->getErrorMessage($error['filter'], $error+$opts);
		} elseif (is_int($error)) {
			return $this->getErrorMessage($error, $opts);
		} else {
			return $this->getErrorMessage(0, $opts);
		}
	}

	/**
	 * Gets the error messages from the language file
	 *
	 * @param int $intCode
	 * @param array $arrOptions
	 */
	public function getErrorMessage($intCode, $arrOptions=array()) {
		if (isset($arrOptions['error']))
			return $arrOptions['error'];

		if (isset($arrOptions['message']))
			return $this->locale->insert($arrOptions['message'], array('value' => isset($arrOptions['value']) ? $arrOptions['value'] : $arrOptions['field'], 'field' => $arrOptions['field']));

		$field = isset($arrOptions['label']) ? $arrOptions['label'] : (isset($arrOptions['field']) ? $this->locale->get($arrOptions['field']) : '');

		switch ($intCode) {
			case FILTER_VALIDATE_REQUIRED:
				return $this->locale->insert('error.empty', array('value' => $field));
			case FILTER_VALIDATE_PASSWORD:
				return $this->locale->get('error.password');
			case FILTER_VALIDATE_USERNAME:
				return $this->locale->insert('error.username', array('value' => '"'.$arrOptions['value'].'"'));
			case FILTER_VALIDATE_IDENTIFIER:
				return $this->locale->insert('error.identifier', array('value' => '"'.$arrOptions['value'].'"'));
			case FILTER_VALIDATE_RESOURCE:
				return $this->locale->insert('error.resource', array('value' => '"'.$arrOptions['value'].'"'));
			case FILTER_VALIDATE_EMAIL:
				return $this->locale->insert('error.not_a_address', array('value' => '"'.$arrOptions['value'].'"', 'type' => 'E-mail'));
			case FILTER_VALIDATE_IPV4:
				return $this->locale->insert('error.not_a_address', array('value' => '"'.$arrOptions['value'].'"', 'type' => 'IPv4'));
			case FILTER_VALIDATE_IPV6:
				return $this->locale->insert('error.not_a_address', array('value' => '"'.$arrOptions['value'].'"', 'type' => 'IPv6'));
			case FILTER_VALIDATE_IP:
				return $this->locale->insert('error.not_a_address', array('value' => '"'.$arrOptions['value'].'"', 'type' => 'IP'));
			case FILTER_VALIDATE_URL:
				return $this->locale->insert('error.not_a_url', array('value' => $arrOptions['value']));
			case FILTER_VALIDATE_FLOAT:
				return $this->locale->insert('error.not_a_number', array('value' => $field));
			case FILTER_VALIDATE_INT:
				if (isset($arrOptions['min_range']) && isset($arrOptions['max_range']))
					return $this->locale->insert('error.in_between', array('value' => $field, 'min' => $arrOptions['min_range'], 'max' => $arrOptions['max_range']));
				elseif (isset($arrOptions['min_range']))
					return $this->locale->insert('error.greater_than', array('value' => $field, 'count' => $arrOptions['min_range']));
				elseif (isset($arrOptions['max_range']))
					return $this->locale->insert('error.less_than', array('value' => $field, 'count' => $arrOptions['max_range']));
				else
					return $this->locale->insert('error.not_a_int', array('value' => $field));
			case FILTER_VALIDATE_BOOLEAN:
				break;
			case FILTER_VALIDATE_MIN_LENGTH:
				return $this->locale->insert('error.too_short', array('value' => $field, 'count' => $arrOptions['len']));
			case FILTER_VALIDATE_MAX_LENGTH:
				return $this->locale->insert('error.too_long', array('value' => $field, 'count' => $arrOptions['len']));
			case FILTER_VALIDATE_LENGTH:
				return $this->locale->insert('error.wrong_range', array('value' => $field, 'min' => $arrOptions['min'], 'max' => $arrOptions['max']));
			case FILTER_VALIDATE_DATE_RANGE:
				$arrOptions['format'] = isset($arrOptions['format']) ? $arrOptions['format'] : $this->locale->getDateFormat('date.format.default');
				return $this->locale->insert('error.date_range', array('value' => $field, 'min' => date($arrOptions['format'], $arrOptions['min']), 'max' => date($arrOptions['format'], $arrOptions['max'])));
			case FILTER_VALIDATE_DATE_START_END:
				$arrOptions['format'] = isset($arrOptions['format']) ? $arrOptions['format'] : $this->locale->getDateFormat('date.format.default');
				return $this->locale->insert('error.date_start_end', array('value' => $field, 'end' => date($arrOptions['format'], $arrOptions['end'])));
			default:
				// too_long
				// too_short
				// empty (Muss ausgefüllt werden)
				// invalid
				// start_before_end
				// range: Ist keine gültige {model}
				break;
		}

		return $this->locale->insert('error.invalid', array('value' => isset($arrOptions['value']) ? $arrOptions['value'] : $field, 'field' => $field));
	}
}
