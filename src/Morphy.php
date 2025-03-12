<?php

namespace cijic\phpMorphy;

use Exception;
use phpMorphy;
use phpMorphy_Exception;

class Morphy extends phpMorphy
{
    protected string $language;
    private array $dictionaries = ['ru' => 'ru_RU', 'en' => 'en_EN', 'ua' => 'uk_UA', 'de' => 'de_DE'];
    private string $dictsPath;

    /**
     * @throws phpMorphy_Exception
     * @throws Exception
     */
    public function __construct($language = 'ru')
    {
        $this->dictsPath = realpath(__DIR__ . '/../libs/phpmorphy/dicts');
        $this->language = $this->dictionaries[$language];

        if (defined('PHPMORPHY_STORAGE_FILE')) {
            $options = ['storage' => PHPMORPHY_STORAGE_FILE];
        } else {
            $options = ['storage' => 'file'];
        }

        try {
            parent::__construct($this->dictsPath, $this->language, $options);
        } catch(phpMorphy_Exception $e) {
            throw new Exception('Error occurred while creating phpMorphy instance: ' . PHP_EOL . $e);
        }
    }
}
