<?php

use Interfaces\DictSourceInterface;

require_once dirname(__FILE__).'/../../mrd/manager.php';
require_once dirname(__FILE__).'/source.php';
require_once dirname(__FILE__).'/../../../libs/collections.php';

class phpMorphy_Dict_Source_Mrd implements DictSourceInterface
{
    protected phpMorphy_MrdManager $manager;

    /**
     * @throws phpMorphy_MrdManager_Exception
     * @throws phpMorphy_Mrd_Exception
     * @throws phpMorphy_Exception
     */
    public function __construct($mwzFilePath)
    {
        $this->manager = $this->createMrdManager($mwzFilePath);
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     * @throws phpMorphy_Mrd_Exception
     * @throws phpMorphy_Exception
     */
    protected function createMrdManager($mwzPath): phpMorphy_MrdManager
    {
        $manager = new phpMorphy_MrdManager;
        $manager->open($mwzPath);

        return $manager;
    }

    public function getName(): string
    {
        return 'mrd';
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getLanguage(): string
    {
        $lang = strtolower($this->manager->getLanguage());

        return match ($lang) {
            'russian' => 'ru_RU',
            'english' => 'en_EN',
            'german' => 'de_DE',
            default => $this->manager->getLanguage(),
        };
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getDescription(): string
    {
        return 'Dialing dictionary file for '.$this->manager->getLanguage().' language';
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getAncodes(): Iterator
    {
        return $this->manager->getGramInfo();
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getFlexias(): Iterator
    {
        return $this->manager->getMrd()->flexias_section;
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getPrefixes(): Iterator
    {
        return $this->manager->getMrd()->prefixes_section;
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getAccents(): Iterator
    {
        return $this->manager->getMrd()->accents_section;
    }

    /**
     * @throws phpMorphy_MrdManager_Exception
     */
    public function getLemmas(): Iterator
    {
        return $this->manager->getMrd()->lemmas_section;
    }
}
