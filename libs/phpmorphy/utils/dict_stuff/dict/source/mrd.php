<?php

require_once dirname(__FILE__).'/../../mrd/manager.php';
require_once dirname(__FILE__).'/source.php';
require_once dirname(__FILE__).'/../../../libs/collections.php';

class phpMorphy_Dict_Source_Mrd implements phpMorphy_Dict_Source_Interface
{
    protected $manager;

    public function __construct($mwzFilePath)
    {
        $this->manager = $this->createMrdManager($mwzFilePath);
    }

    protected function createMrdManager($mwzPath)
    {
        $manager = new phpMorphy_MrdManager;
        $manager->open($mwzPath);

        return $manager;
    }

    public function getName()
    {
        return 'mrd';
    }

    // phpMorphy_Dict_Source_Interface
    public function getLanguage()
    {
        $lang = strtolower($this->manager->getLanguage());

        switch ($lang) {
            case 'russian':
                return 'ru_RU';
            case 'english':
                return 'en_EN';
            case 'german':
                return 'de_DE';
            default:
                return $this->manager->getLanguage();
        }
    }

    public function getDescription()
    {
        return 'Dialing dictionary file for '.$this->manager->getLanguage().' language';
    }

    public function getAncodes()
    {
        return $this->manager->getGramInfo();
    }

    public function getFlexias()
    {
        return $this->manager->getMrd()->flexias_section;
    }

    public function getPrefixes()
    {
        return $this->manager->getMrd()->prefixes_section;
    }

    public function getAccents()
    {
        return $this->manager->getMrd()->accents_section;
    }

    public function getLemmas()
    {
        return $this->manager->getMrd()->lemmas_section;
    }
}
