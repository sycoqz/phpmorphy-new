<?php
require_once(dirname(__FILE__) . '/gramtab.php');
require_once(dirname(__FILE__) . '/reader.php');
require_once(dirname(__FILE__) . '/mwz.php');
require_once(dirname(__FILE__) . '/rml.php');
require_once(dirname(__FILE__) . '/../dict/model.php');

class phpMorphy_MrdManager_Exception extends Exception { }

class phpMorphy_MrdManager {
	protected
        $language,
		$encoding,
		$mrd,
		$gram_info;
    protected bool $opened = false;

    /**
     * @throws phpMorphy_MrdManager_Exception
     * @throws phpMorphy_Mrd_Exception
     * @throws phpMorphy_Exception
     */
    function open(string $filePath): void
    {
		$mwz = $this->openMwz($filePath);
		$this->encoding = $mwz->getEncoding();
		$mrd_path = $mwz->getMrdPath();
		$language = $mwz->getLanguage();

		$this->mrd = $this->openMrd($mrd_path, $this->encoding);

		$this->gram_info = $this->convertFromGramtabToDict(
			$this->openGramTab($language, $this->encoding)
		);

		$this->language = $language;
		$this->opened = true;
	}

	function isOpened(): bool
    {
		return $this->opened;
	}

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    protected function checkOpened(): void
    {
		if(!$this->isOpened()) {
			throw new phpMorphy_MrdManager_Exception(__CLASS__ . " not initialized, use open() method");
		}
	}

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    function getEncoding() {
		$this->checkOpened();
		return $this->getEncoding();
	}

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    function getLanguage() {
		$this->checkOpened();
		return $this->language;
	}

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    function getMrd() {
		$this->checkOpened();
		return $this->mrd;
	}

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    function getGramInfo() {
		$this->checkOpened();
		return $this->gram_info;
	}

    /**
     * @throws phpMorphy_Exception
     */
    protected function convertFromGramtabToDict($ancodes): ArrayIterator
    {
		$result = [];

		foreach($ancodes as $ancode) {
			$ancode_id = $ancode->getAncode();

			$result[$ancode_id] = new phpMorphy_Dict_Ancode(
				$ancode_id,
				$ancode->getPartOfSpeech(),
				$ancode->isPredictPartOfSpeech(),
				$ancode->getGrammems()
			);
		}

		return new ArrayIterator($result);
	}

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    protected function openMwz(string $wmzFile): phpMorphy_Mwz_File
    {
		return new phpMorphy_Mwz_File($wmzFile);
	}

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    protected function openMrd(string $path, $encoding): phpMorphy_Mrd_File
    {
		return new phpMorphy_Mrd_File($path, $encoding);
	}

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    protected function openGramTab($lang, $encoding): phpMorphy_GramTab_File
    {
		try {
			return $this->createGramTabFile(
				$this->getGramTabPath($lang),
				$encoding,
				$this->createGramInfoFactory($lang)
			);
		} catch(Exception $e) {
			throw new phpMorphy_MrdManager_Exception('Can`t parse gramtab file: ' . $e->getMessage());
		}
	}

	protected function getGramTabPath($lang) {
		$rml = new phpMorphy_Rml_IniFile();

		return $rml->getGramTabPath($lang);
	}

	protected function createGramInfoFactory($lang): phpMorphy_GramTab_GramInfoFactory
    {
		return new phpMorphy_GramTab_GramInfoFactory($lang);
	}

	protected function createGramTabFile($file, $encoding, phpMorphy_GramTab_GramInfoFactory $factory): phpMorphy_GramTab_File
    {
		return new phpMorphy_GramTab_File($file, $encoding, $factory);
	}
}
