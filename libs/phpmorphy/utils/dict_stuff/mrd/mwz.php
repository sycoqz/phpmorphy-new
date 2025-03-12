<?php
class phpMorphy_Mwz_Exception extends Exception { }

class phpMorphy_Mwz_File {
    protected string $mwz_path;
    protected array $values = [];

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    function __construct(string $filePath) {
        $this->mwz_path = $filePath;
        $this->parseFile($filePath);
    }

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    function getValue(string $key) {

        if(!array_key_exists($key, $this->values)) {
            throw new phpMorphy_Mrd_Exception("Key $key not exists in mwz file '$this->mwz_path'");
        }

        return $this->values[$key];
    }

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    function getMrdPath(): string
    {
        return dirname($this->mwz_path) . DIRECTORY_SEPARATOR . $this->getValue('MRD_FILE');
    }

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    function getEncoding(): bool|string
    {
        $lang = $this->getLanguage();

        if (false === ($default = $this->getEncodingForLang($lang))) {
            throw new phpMorphy_Mrd_Exception("Can`t determine encoding for '$lang' language");
        }

        return $default;
    }

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    function getLanguage(): string
    {
        return strtolower($this->getValue('LANG'));
    }

    static function getEncodingForLang(string $lang): bool|string
    {
        return match (strtolower($lang)) {
            'russian' => 'windows-1251',
            'english' => 'windows-1250',
            'german' => 'windows-1252',
            default => false,
        };
    }

    /**
     * @throws phpMorphy_Mrd_Exception
     */
    protected function parseFile($path): void
    {
        try {
            $lines = iterator_to_array($this->openFile($path));
        } catch (Exception $e) {
            throw new phpMorphy_Mrd_Exception("Can`t open $path mwz file '$path': " . $e->getMessage());
        }

        foreach(array_map('trim', $lines) as $line) {
            $pos = strcspn($line, " \t");

            if($pos !== strlen($line)) {
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));

                if(strlen($key)) {
                    $this->values[$key] = $value;
                }
            } elseif(strlen($line)) {
                $this->values[$line] = null;
            }
        }
    }

    protected function openFile($file): SplFileObject
    {
        return new SplFileObject($file);
    }
}
