<?php
namespace ReactIntlEditor;

class ReactIntlEditor {

	private $locale = null;

	private $sourceFiles = [];
	private $localeFiles = [];

	private $sourceStrings = [];
	private $localeStrings = [];

	/***
	 * @param $name string The property to retrieve
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name) {
		switch ($name) {
			case 'sourceFileCount':
				return count($this->sourceFiles);
				break;
			case 'locales':
				return array_keys($this->localeStrings);
				break;
			case 'localeCount':
				return count($this->localeFiles);
				break;
			case 'sourceStringCount';
				return count($this->sourceStrings);
				break;
		}

		throw new \Exception('Invalid Property Specified');
	}

	/***
	 * ReactIntlEditor constructor.
	 */
	public function __construct($locale = null) {

		/**
		 * If a specific locale is being worked on, save it
		 */
		$this->locale = $locale;

		/**
		 * Find all the Source Files
		 */
		$this->sourceFiles = $this->getFileList(realpath('.') . '/intl/messages/');

		/**
		 * If no source files can be found, throw an error
		 */
		if ($this->sourceFileCount == 0) {
			throw new Exception('No source files were found in intl/messages/.  At least one source file must be included.');
		}

		/**
		 * Find all the Locale Files
		 */
		$this->localeFiles = $this->getFileList(realpath('.') . '/intl/locales/');

		/**
		 * If no locales can be found, throw an error
		 */
		if ($this->localeCount == 0) {
			throw new Exception('No locale files were found in intl/locales/.  At least one locale file must be specified.');
		}

		/**
		 * Extract All Strings from Source Files
		 */
		foreach ($this->sourceFiles as $file) {
			$found = $this->extractStringsFromSourceFile($file);
			$this->sourceStrings = array_merge($this->sourceStrings, $found);

		}

		/**
		 * Extract All Strings from Locale Files
		 */
		foreach ($this->localeFiles as $file) {
			$locale = basename($file, '.json');
			if ($this->locale === null || $this->locale === $locale)

			$found = $this->extractStringsFromLocaleFile($file);
			$this->localeStrings[$locale] = $found;
		}

	}

	/***
	 * @param $path string The path to search for json files
	 *
	 * @return array An array of filenames (full paths)
	 */
	private function getFileList($path) {
		$fileList = [];

		$Directory = new \RecursiveDirectoryIterator($path);
		$Iterator = new \RecursiveIteratorIterator($Directory);
		$Regex = new \RegexIterator($Iterator, '/^.+\.json$/i', \RecursiveRegexIterator::GET_MATCH);

		foreach($Regex as $folder) {
			foreach ($folder as $file) {
				array_push($fileList, $file);
			}
		}

		return $fileList;
	}

	/***
	 * @param $file string Path to the file to be read
	 *
	 * @return array an array containing each string array from the JSON files
	 */
	private function extractStringsFromSourceFile($file) {

		$contents = file_get_contents($file);
		$stringsFound = json_decode($contents);
		return $stringsFound;

	}

	/***
	 * @param $file string Path to the file to be read
	 *
	 * @return array an array containing each string array from the JSON files
	 */
	private function extractStringsFromLocaleFile($file) {
		$stringsFound = [];
		$contents = file_get_contents($file);
$contents = preg_replace("/[\r\n]+/", " ", $contents);
		$json = json_decode($contents);
		if ( ! $json ) return [];
		foreach ($json as $key=>$value) {
			array_push($stringsFound, array($key, $value));
		}

		return $stringsFound;

	}

	/***
	 * @param $locale
	 *
	 * @return int
	 */
	public function getLocaleStringCount($locale) {
		return count($this->localeStrings[$locale]);
	}

	/***
	 * @param $locale
	 *
	 * @return int
	 */
	public function getMatchingStringCount($locale) {
		$i = 0;
		foreach ($this->localeStrings[$locale] as $localeString) {
			foreach ($this->sourceStrings as $sourceString) {
				if ($localeString[0] == $sourceString->id) {
					$i++;
					break;
				}
			}
		}

		return $i;
	}

	/***
	 * @param $locale
	 *
	 * @return int
	 */
	public function getMissingStringCount($locale) {
		$i = 0;
		foreach ($this->sourceStrings as $sourceString) {
			$i++;
			foreach ($this->localeStrings[$locale] as $localeString) {
				if ($localeString[0] == $sourceString->id) {
					$i--;
					break;
				}
			}
		}

		return $i;
	}

	/***
	 * @param $locale
	 *
	 * @return int
	 */
	public function getMatchingStrings() {

		if ($this->locale === null) {
			throw new Exception('ReactIntlEditor was not instansiated with a locale');
		}

		$matches = [];

		foreach ($this->sourceStrings as $sourceString) {
			foreach ($this->localeStrings[$this->locale] as $localeString) {
				if ($localeString[0] == $sourceString->id) {
					$combinedString = array(
						'id' => $sourceString->id,
                        'defaultMessage'=> $sourceString->defaultMessage,
                        'description' => $sourceString->description,
                        'message' => $localeString[1]
					);
					array_push( $matches, $combinedString );
					continue;
				}
			}
		}

		return $matches;
	}

	/***
	 * @param $locale
	 *
	 * @return int
	 */
	public function getMissingStrings() {

		if ($this->locale === null) {
			throw new Exception('ReactIntlEditor was not instansiated with a locale');
		}

		$matches = [];

		foreach ($this->sourceStrings as $sourceString) {
			$found = false;
			foreach ($this->localeStrings[$this->locale] as $localeString) {
				if ($localeString[0] == $sourceString->id) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				array_push( $matches, $sourceString );
			}
		}

		return $matches;
	}

	/***
	 * @param $locale
	 *
	 * @return int
	 */
	public function getOrphanedStringCount($locale) {
		$i = 0;
		foreach ($this->localeStrings[$locale] as $localeString) {
			$i++;
			foreach ($this->sourceStrings as $sourceString) {
				if ($localeString[0] == $sourceString->id) {
					$i--;
					break;
				}
			}
		}

		return $i;
	}

	public function saveChanges() {

		$realPost = Utilities::getRealInput('POST');

		// Check for a Locale
		if (!isset($realPost['locale'])) {
			throw new Exception('Missing Locale in Form');
		} else {
			$locale = $realPost['locale'];
			unset($realPost['locale']);
		}

		// Check for a Locale
		if (!isset($realPost['type'])) {
			throw new Exception('Missing type parameter in Form');
		} else {
			$type = $realPost['type'];
			unset($realPost['type']);
		}

		$this->updateSourceStrings($realPost, $locale, $type);

		return true;
	}

	/***
	 * @param $strings array The strings passed in from the HTML form.
	 */
	private function updateSourceStrings($strings, $locale, $type) {

		foreach($strings as $key=>$value) {


			if ($type == 'matching') {
				$i = 0;
				foreach ($this->localeStrings[$locale] as $localeString) {
					if ($key == $localeString[0]) {
						$this->localeStrings[$locale][$i][1] = $value;
						continue;
					}
					$i++;
				}
			}

			if ($type == 'missing') {
				if ($value == '') continue;
				array_push($this->localeStrings[$locale], array($key, $value));
			}
		}

		$this->saveSourceStringsToFile($locale);

	}

	private function saveSourceStringsToFile($locale) {
		$output = '{';

		for ($i = 0; $i < count($this->localeStrings[$locale]); $i++) {

			if ($this->localeStrings[$locale][$i][1] == '') continue;

			$output .= '"' . $this->localeStrings[$locale][$i][0] . '": "';

			if (strpos($this->localeStrings[$locale][$i][1], "\n") !== false) {
				$output .= addcslashes(str_replace("\r\n", "\\n", $this->localeStrings[$locale][$i][1]), '"');
			} else {
				$output .= addcslashes($this->localeStrings[$locale][$i][1], '"');
			}
			$output .= ($i == count($this->localeStrings[$locale]) -1 ) ? '"' : '",';
		}

		$output .= '}';
		$output = Utilities::jsonIndent($output);

		$fname = 'intl/locales/' . $locale . '.json';
		$fhandle = fopen($fname,"w");
		fwrite($fhandle,$output);
		fclose($fhandle);
	}
}
