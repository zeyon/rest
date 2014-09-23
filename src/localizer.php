<?php
namespace REST;

/**
 * Loads language variables from a YAML file and caches generated files
 *
 * @author Peter-Christoph Haider (Project Leader) et al.
 * @version 1.1 (2012-04-22)
 * @package REST
 * @copyright Copyright (c) 2012, Zeyon GmbH & Co. KG
 */
class Localizer {
	// ============== Object's core attributes ==============
	/** @var Localizer The singelton instance */
	private static $instance = NULL;
	/** @var array Array containing the language variables */
	public $arrData = array();
	/** @var array Array containing accepted languages */
	public $arrAccepted = array('en');
	/** @var string Default language */
	public $strDefault = 'en';
	/** @var string Current language */
	public $strCurrent = '';
	/** @var string The default file format (json, ini or yml) */
	public $strFileFormat = 'json';
	/** @var string Regular expression used for variable replacement */
	public $regex = '/{{([A-Za-z0-9.]+)}}/';

	// ============== Object's core functions ==============

	/**
	 * Private contructor, so the class can only instatiate itself
	 *
	 * @param array $arrAccepted All accepted languages
	 * @param string $strDefault Default language code
	 */
	public function __construct($arrAccepted=array(), $strDefault='de', $strFileFormat=false) {
		if ($arrAccepted)
			$this->arrAccepted = $arrAccepted;

		$this->strDefault = $strDefault;

		if ($strFileFormat)
			$this->strFileFormat = $strFileFormat;
	}

	/**
	 * Return the current instance
	 *
	 * @param array $arrAccepted All accepted languages
	 * @param string $strDefault Default language code
	 * @return Localizer
	 */
	public static function getInstance($arrAccepted=array(), $strDefault='de') {
		if (self::$instance === NULL)
			self::$instance = new self($arrAccepted, $strDefault);
		return self::$instance;
	}

	/**
	 * Makes sure the directory ends with a slash
	 *
	 * @param string $strDirectory
	 * @return string
	 */
	private function fileFormatDir($strDirectory, $strSlash=DIRECTORY_SEPARATOR) {
		return substr($strDirectory, -1) != $strSlash ? $strDirectory.$strSlash : $strDirectory;
	}

	/**
	 * Initialized the language variable
	 *
	 * @param string $strSourcePath The source path for all localization files (.yml)
	 * @param string $strCachePath The caching path; if specified, cache files will be enabled
	 * @param bool $bolStore Switch to store language in the current session
	 * @return array The language variable array
	 */
	public function load($strSourcePath, $strCachePath=false, $bolStore=true) {
		$strSourcePath = $this->fileFormatDir($strSourcePath);

		$strLang = self::init($this->arrAccepted, $this->strDefault, $bolStore);
		$this->strCurrent = $strLang;

		if ($strCachePath) {
			$strCachePath = $this->fileFormatDir($strCachePath);
			if (file_exists($strCachePath.$strLang.'.php')) {
				include $strCachePath.$strLang.'.php';
				if (isset($LANGVAR)) {
					$this->setData($LANGVAR);
					return $LANGVAR;
				}
			}
		}


		$strFilename = $strSourcePath.$strLang.'.'.$this->strFileFormat;
		if (!file_exists($strFilename))
			throw new \Exception('Locale file not found: '.$strFilename);

		switch ($this->strFileFormat) {
			case 'yml':
				if (!class_exists('\\Spyc'))
					throw new \Exception('Spyc YAML parser not loaded!');
				$LANGVAR = \Spyc::YAMLLoad($strFilename);
				break;

			case 'ini':
				$LANGVAR = parse_ini_file($strFilename, true);
				break;

			default:
				$LANGVAR = json_decode(file_get_contents($strFilename, true));
				break;
		}

		$this->setData($LANGVAR);

		if ($strCachePath)
			$this->writeCacheFile($strCachePath, $strLang);

		return $LANGVAR;
	}

	/**
	 * Write the cache file
	 *
	 * @param string $strCachePath The caching path
	 * @param string $strLang
	 * @return bool
	 */
	public function writeCacheFile($strCachePath, $strLang) {
		if (file_exists($strCachePath) && is_dir($strCachePath)) {
			file_put_contents($strCachePath.$strLang.'.php', '<?php '."\n\n"
			.'/* Language file "'.$strLang.'; Generated '.date('Y-m-d H:i').' */'."\n\n".'$LANG = '.var_export($this->arrData, true).';'."\n".'?>');

			return true;
		}

		return false;
	}

	/**
	 * Returns the current language
	 *
	 * @return string
	 */
	public function getCurrent() {
		return $this->strCurrent;
	}

	/**
	 * Sets the data array
	 *
	 * @param array $arrData
	 */
	public function setData($arrData) {
		$this->arrData = $arrData;
	}

	/**
	 * Sets a value
	 *
	 * @param string $strKey
	 * @param string $strValue
	 */
	public function set($strKey, $strValue) {
		$this->arrData[$strKey] = $strValue;
	}

	/**
	 * Gets a variable value
	 *
	 * @param string $strKey The path for the language variable (e.g. "messages.error.single")
	 * @param bool $bolReturnPath If set the function will return the variable path, instead of FALSE
	 * @return string If the path could not be resolved, the path will be returned instead
	 */
	public function get($strKey, $bolReturnPath=true) {
		$data = $this->arrData;
		foreach (explode('.', $strKey) as $p) {
			if (isset($data[$p]))
				$data = $data[$p];
			else
				return $bolReturnPath ? $this->returnKey($strKey) : false;
		}

		// if key was not resolved completely
		if ( is_array($data) )
			return $bolReturnPath ? $this->returnKey($strKey) : false;


		return (string) $data;
	}

	/**
	 * Returns the placeholder
	 *
	 * @param $strKey
	 * @return string
	 */
	public function returnKey($strKey) {
		return  '%'.strtoupper($strKey);
	}

	/**
	 * Returns a date string without "%" for PHP compatibiltiy
	 *
	 * @param string $strKey The path for the language variable (e.g. "messages.error.single")
	 * @return string The date format string
	 */
	public function getDateFormat($strKey) {
		$format = $this->get($strKey, false);
		return $format ? preg_replace('/%([A-Za-z%])/', '$1', $format) : 'Y-m-d H:i';
	}

	/**
	 * Gets a variable value and replaces placeholders contained in it
	 *
	 * @param string $strKey The path for the language variable (e.g. "messages.error.single")
	 * @param array $arrReplace Associative array containing the placeholder variables
	 * @return string If the path could not be resolved, the path will be returned instead
	 */
	public function insert($strKey, $arrReplace) {
		$data = $this->arrData;
		foreach (explode('.', $strKey) as $p) {
			if (isset($data[$p]))
				$data = $data[$p];
			else
				return $this->returnKey($strKey).'('.json_encode($arrReplace).')';
		}

		$arrSearch = array();
		$arrValues = array();
		foreach ($arrReplace as $key => $value) {
			$arrSearch[] = '{'.$key.'}';
			$arrValues[] = $value;
		}

		return str_replace($arrSearch, $arrValues, (string) $data);
	}

	/**
	 * Insert placeholders into a string
	 *
	 * @param string $strTemplate
	 * @return $strTemplate
	 */
	public function replace($strTemplate) {
		preg_match_all($this->regex, $strTemplate, $matches);
		$arrReplace = array();
		foreach ($matches[1] as $match) {
			$arrReplace[] = $this->get($match);
		}

		return str_replace($matches[0], $arrReplace, $strTemplate);
	}

	/**
	 * Initialized the language variable and stores it in the session
	 *
	 * @param array $arrAccepted All accepted languages
	 * @param string $strDefault Default language code
	 * @param bool $bolStore Switch to store language in the current session
	 */
	public function init($arrAccepted, $strDefault, $bolStore=true) {
		$strLang = isset($_GET['lang']) ? $_GET['lang']
				: (isset($_SESSION['lang']) ? $_SESSION['lang']
				: (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : false));
		if (!$strLang || !in_array($strLang, $arrAccepted)) {
			$browser = self::askBrowser($arrAccepted, $strDefault);
			$strLang = $browser['lang'];
		}
		if ($bolStore) {
			$_SESSION['lang'] = $strLang;
			setcookie('lang', $strLang, time() + 315885600);
		}
		$this->strCurrent = $strLang;
		return $strLang;
	}

	/**
	 * Returns the accepted browser language
	 *
	 * @param array $arrAccepted
	 * @param string $strDefault
	 * @return array {'lang': PRIMARY LANGUAGE, 'other': ADDITIONAL LANGUAGES}
	 */
	public static function askBrowser($arrAccepted, $strDefault='de') {
		$res = array('lang' => $strDefault, 'other' => array());
		try {
			$lang_variable = (
				isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
				? $_SERVER['HTTP_ACCEPT_LANGUAGE']
				: null
			);

			if ( empty($lang_variable) )
				return $res;

			$accepted_languages = preg_split('/,\s*/', $lang_variable);

			$current_lang = $strDefault;
			$current_q = 0;

			$other = array();
			foreach ($accepted_languages as $accepted_language) {
				$res = preg_match('/^([a-z]{1,8}(?:-[a-z]{1,8})*)'.
								   '(?:;\s*q=(0(?:\.[0-9]{1,3})?|1(?:\.0{1,3})?))?$/i', $accepted_language, $matches);
				if (!$res)
					continue;

				$lang_code = explode('-', $matches[1]);
				if (isset($matches[2]))
					$lang_quality = (float) $matches[2];
				else
					$lang_quality = 1.0;

				while (count($lang_code)) {
					$full_code = strtolower(join ('-', $lang_code));
					if (in_array($full_code, $arrAccepted)) {
						if ($lang_quality > $current_q) {
							if ($current_lang)
								$other[] = $current_lang;
							$current_lang = $full_code;
							$current_q = $lang_quality;
							break;
						} else
							$other[] = $full_code;
					} else
						$other[] = $full_code;

					array_pop($lang_code);
				}
			}
			return array('lang' => $current_lang, 'other' => $other);;
		} catch (\Exception $e) {
			return $res;
		}
	}

}

?>
