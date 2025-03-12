<?php

require_once dirname(__FILE__).'/../model.php';

interface phpMorphy_Dict_Source_Interface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * ISO3166 country code separated by underscore(_) from ISO639 language code
     * ru_RU, uk_UA for example
     *
     * @return string
     */
    public function getLanguage();

    /**
     * Any string
     *
     * @return string
     */
    public function getDescription();

    /**
     * @return Iterator over objects of phpMorphy_Dict_Ancode
     */
    public function getAncodes();

    /**
     * @return Iterator over objects of phpMorphy_Dict_FlexiaModel
     */
    public function getFlexias();

    /**
     * @return Iterator over objects of phpMorphy_Dict_PrefixSet
     */
    public function getPrefixes();

    /**
     * @return Iterator over objects of phpMorphy_Dict_AccentModel
     */
    public function getAccents();

    /**
     * @return Iterator over objects of phpMorphy_Dict_Lemma
     */
    public function getLemmas();
}
